<?php

namespace App\Services;

use App\Events\OperatorJoinedFrequency;
use App\Events\OperatorLeftFrequency;
use App\Events\PttStarted;
use App\Events\PttStopped;
use App\Events\SignalRelayed;
use App\Exceptions\FrequencyBusyException;
use App\Exceptions\NotOnFrequencyException;
use App\Exceptions\PttNotHeldException;
use App\Exceptions\TargetNotOnFrequencyException;
use App\Exceptions\TransmissionNotFoundException;
use App\Models\Frequency;
use App\Models\FrequencyMembership;
use App\Models\FrequencySignal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalkieTalkieService
{
    public function createAccount(
        string $email,
        string $password,
        string $callsign,
        string $name,
    ): User {
        $callsign = $this->normalizeCallsign($callsign);

        try {
            return User::query()->create([
                'name' => $name,
                'callsign' => $callsign,
                'email' => $email,
                'password' => $password,
                'last_seen_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if (User::query()->where('callsign', $callsign)->exists()) {
                throw ValidationException::withMessages([
                    'callsign' => ['This callsign is already in use.'],
                ]);
            }

            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered.'],
                ]);
            }

            throw $exception;
        }
    }

    public function heartbeat(User $operator): ?Frequency
    {
        $frequency = DB::transaction(function () use ($operator): ?Frequency {
            $lockedOperator = User::query()->lockForUpdate()->findOrFail($operator->id);
            $now = now();

            $lockedOperator->forceFill(['last_seen_at' => $now])->save();

            $membership = FrequencyMembership::query()
                ->where('user_id', $lockedOperator->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                return null;
            }

            $membership->forceFill(['last_seen_at' => $now])->save();

            return $membership->frequency;
        });

        $this->pruneStale();

        return $frequency === null ? null : $this->freshFrequency($frequency);
    }

    /**
     * @return Collection<int, Frequency>
     */
    public function listFrequencies(): Collection
    {
        $this->pruneStale();

        return Frequency::query()
            ->with(['talkingOperator'])
            ->withCount('memberships')
            ->orderBy('number')
            ->get();
    }

    public function show(Frequency $frequency): Frequency
    {
        $this->pruneStale();

        return $this->freshFrequency($frequency);
    }

    public function join(User $operator, Frequency $frequency): Frequency
    {
        $this->pruneStale();

        $result = DB::transaction(function () use ($operator, $frequency): array {
            $lockedOperator = User::query()->lockForUpdate()->findOrFail($operator->id);
            $membership = FrequencyMembership::query()
                ->where('user_id', $lockedOperator->id)
                ->first();

            $frequencyIds = collect([$frequency->id, $membership?->frequency_id])
                ->filter()
                ->unique()
                ->sort()
                ->values();

            $frequencies = Frequency::query()
                ->whereKey($frequencyIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $targetFrequency = $frequencies->get($frequency->id);
            $previousFrequency = $membership === null
                ? null
                : $frequencies->get($membership->frequency_id);
            $now = now();

            $membership = FrequencyMembership::query()
                ->where('user_id', $lockedOperator->id)
                ->lockForUpdate()
                ->first();

            if ($membership !== null && $membership->frequency_id === $frequency->id) {
                $membership->forceFill(['last_seen_at' => $now])->save();
                $lockedOperator->forceFill(['last_seen_at' => $now])->save();

                return [
                    'changed' => false,
                    'operator' => $lockedOperator,
                    'previous' => null,
                    'released_ptt' => false,
                ];
            }

            $releasedPtt = $previousFrequency?->isPttHeldBy($lockedOperator) ?? false;

            if ($releasedPtt) {
                $this->clearPtt($previousFrequency);
            }

            if ($membership === null) {
                FrequencyMembership::query()->create([
                    'user_id' => $lockedOperator->id,
                    'frequency_id' => $frequency->id,
                    'last_seen_at' => $now,
                ]);
            } else {
                $membership->forceFill([
                    'frequency_id' => $frequency->id,
                    'last_seen_at' => $now,
                ])->save();
            }

            $lockedOperator->forceFill(['last_seen_at' => $now])->save();

            return [
                'changed' => true,
                'operator' => $lockedOperator,
                'previous' => $previousFrequency,
                'released_ptt' => $releasedPtt,
                'target' => $targetFrequency,
            ];
        });

        if ($result['changed']) {
            if ($result['released_ptt']) {
                PttStopped::dispatch($result['previous'], $result['operator'], 'left');
            }

            if ($result['previous'] !== null) {
                OperatorLeftFrequency::dispatch($result['previous'], $result['operator']);
            }

            OperatorJoinedFrequency::dispatch($result['target'], $result['operator']);
        }

        return $this->freshFrequency($frequency);
    }

    public function leave(User $operator, Frequency $frequency, bool $prune = true): void
    {
        if ($prune) {
            $this->pruneStale();
        }

        $result = DB::transaction(function () use ($operator, $frequency): ?array {
            $lockedOperator = User::query()->lockForUpdate()->find($operator->id);

            if ($lockedOperator === null) {
                return null;
            }

            $lockedFrequency = Frequency::query()
                ->whereKey($frequency->id)
                ->lockForUpdate()
                ->firstOrFail();
            $membership = FrequencyMembership::query()
                ->where('user_id', $lockedOperator->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null || $membership->frequency_id !== $lockedFrequency->id) {
                return null;
            }

            $releasedPtt = $lockedFrequency->isPttHeldBy($lockedOperator);

            if ($releasedPtt) {
                $this->clearPtt($lockedFrequency);
            }

            $membership->delete();

            return [
                'operator' => $lockedOperator,
                'frequency' => $lockedFrequency,
                'released_ptt' => $releasedPtt,
            ];
        });

        if ($result === null) {
            return;
        }

        if ($result['released_ptt']) {
            PttStopped::dispatch($result['frequency'], $result['operator'], 'left');
        }

        OperatorLeftFrequency::dispatch($result['frequency'], $result['operator']);
    }

    public function startPtt(User $operator, Frequency $frequency): Frequency
    {
        $this->pruneStale();

        $result = DB::transaction(function () use ($operator, $frequency): array {
            $lockedOperator = User::query()->lockForUpdate()->findOrFail($operator->id);
            $lockedFrequency = Frequency::query()
                ->whereKey($frequency->id)
                ->lockForUpdate()
                ->firstOrFail();
            $membership = FrequencyMembership::query()
                ->where('user_id', $lockedOperator->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null || $membership->frequency_id !== $lockedFrequency->id) {
                throw NotOnFrequencyException::make();
            }

            $expiredTalker = null;

            if ($lockedFrequency->pttHasExpired()) {
                $expiredTalker = $lockedFrequency->talkingOperator;
                $this->clearPtt($lockedFrequency);
                $lockedFrequency->refresh();
            }

            if ($lockedFrequency->isPttHeldBy($lockedOperator)) {
                $lockedFrequency->forceFill(['ptt_last_seen_at' => now()])->save();

                return [
                    'frequency' => $lockedFrequency,
                    'operator' => $lockedOperator,
                    'expired_talker' => $expiredTalker,
                    'started' => false,
                ];
            }

            if ($lockedFrequency->isPttLocked()) {
                $lockedFrequency->loadMissing('talkingOperator');

                throw FrequencyBusyException::alreadyTalking(
                    $lockedFrequency->talkingOperator?->callsign ?? 'another operator'
                );
            }

            $now = now();
            $claimed = Frequency::query()
                ->whereKey($lockedFrequency->id)
                ->whereNull('talking_user_id')
                ->update([
                    'talking_user_id' => $lockedOperator->id,
                    'ptt_uuid' => (string) Str::uuid(),
                    'ptt_started_at' => $now,
                    'ptt_last_seen_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($claimed !== 1) {
                $lockedFrequency->refresh()->loadMissing('talkingOperator');

                throw FrequencyBusyException::alreadyTalking(
                    $lockedFrequency->talkingOperator?->callsign ?? 'another operator'
                );
            }

            $lockedFrequency->refresh();

            return [
                'frequency' => $lockedFrequency,
                'operator' => $lockedOperator,
                'expired_talker' => $expiredTalker,
                'started' => true,
            ];
        });

        if ($result['expired_talker'] !== null) {
            PttStopped::dispatch($result['frequency'], $result['expired_talker'], 'timeout');
        }

        if ($result['started']) {
            PttStarted::dispatch($result['frequency'], $result['operator']);
        }

        return $this->freshFrequency($result['frequency']);
    }

    public function stopPtt(User $operator, Frequency $frequency): Frequency
    {
        $this->pruneStale();

        $result = DB::transaction(function () use ($operator, $frequency): array {
            $lockedOperator = User::query()->lockForUpdate()->findOrFail($operator->id);
            $lockedFrequency = Frequency::query()
                ->whereKey($frequency->id)
                ->lockForUpdate()
                ->firstOrFail();
            $membership = FrequencyMembership::query()
                ->where('user_id', $lockedOperator->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null || $membership->frequency_id !== $lockedFrequency->id) {
                throw NotOnFrequencyException::make();
            }

            if ($lockedFrequency->pttHasExpired()) {
                $expiredTalker = $lockedFrequency->talkingOperator;
                $this->clearPtt($lockedFrequency);

                return [
                    'frequency' => $lockedFrequency,
                    'stopped_operator' => $expiredTalker,
                    'reason' => 'timeout',
                ];
            }

            if (! $lockedFrequency->isPttLocked()) {
                return [
                    'frequency' => $lockedFrequency,
                    'stopped_operator' => null,
                    'reason' => null,
                ];
            }

            if (! $lockedFrequency->isPttHeldBy($lockedOperator)) {
                throw PttNotHeldException::make();
            }

            $this->clearPtt($lockedFrequency);

            return [
                'frequency' => $lockedFrequency,
                'stopped_operator' => $lockedOperator,
                'reason' => 'released',
            ];
        });

        if ($result['stopped_operator'] !== null) {
            PttStopped::dispatch(
                $result['frequency'],
                $result['stopped_operator'],
                $result['reason'],
            );
        }

        return $this->freshFrequency($result['frequency']);
    }

    public function updateSession(
        User $operator,
        ?string $callsign = null,
        ?Frequency $frequency = null,
    ): User {
        if ($callsign !== null && $callsign !== $operator->callsign) {
            $callsign = $this->normalizeCallsign($callsign);

            try {
                $operator->forceFill([
                    'name' => $callsign,
                    'callsign' => $callsign,
                ])->save();
            } catch (UniqueConstraintViolationException $exception) {
                if (User::query()
                    ->where('callsign', $callsign)
                    ->where('id', '!=', $operator->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'callsign' => ['This callsign is already in use.'],
                    ]);
                }

                throw $exception;
            }
        }

        if ($frequency !== null) {
            $this->join($operator, $frequency);
        } else {
            $this->heartbeat($operator);
        }

        return $operator->fresh(['membership.frequency']) ?? $operator;
    }

    public function disconnect(User $operator): void
    {
        $membership = $operator->membership()->with('frequency')->first();

        if ($membership?->frequency !== null) {
            $this->leave($operator, $membership->frequency);
        }

        $operator->unsetRelation('membership');
        $operator->tokens()->delete();
    }

    public function heartbeatTransmission(User $operator, string $transmissionId): Frequency
    {
        $this->pruneStale();

        $frequency = DB::transaction(function () use ($operator, $transmissionId): Frequency {
            $lockedOperator = User::query()->lockForUpdate()->findOrFail($operator->id);
            $lockedFrequency = Frequency::query()
                ->where('ptt_uuid', $transmissionId)
                ->lockForUpdate()
                ->first();

            if ($lockedFrequency === null || ! $lockedFrequency->isPttHeldBy($lockedOperator)) {
                throw TransmissionNotFoundException::make();
            }

            $membership = FrequencyMembership::query()
                ->where('user_id', $lockedOperator->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null || $membership->frequency_id !== $lockedFrequency->id) {
                throw TransmissionNotFoundException::make();
            }

            $lockedFrequency->forceFill(['ptt_last_seen_at' => now()])->save();

            return $lockedFrequency;
        });

        return $this->freshFrequency($frequency);
    }

    public function stopTransmission(User $operator, string $transmissionId): void
    {
        $this->pruneStale();

        $frequency = DB::transaction(function () use ($operator, $transmissionId): Frequency {
            $lockedOperator = User::query()->lockForUpdate()->findOrFail($operator->id);
            $lockedFrequency = Frequency::query()
                ->where('ptt_uuid', $transmissionId)
                ->lockForUpdate()
                ->first();

            if ($lockedFrequency === null || ! $lockedFrequency->isPttHeldBy($lockedOperator)) {
                throw TransmissionNotFoundException::make();
            }

            $this->clearPtt($lockedFrequency);

            return $lockedFrequency;
        });

        PttStopped::dispatch($frequency, $operator, 'released');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function relaySignal(
        User $operator,
        Frequency $frequency,
        string $type,
        array $payload,
        ?User $target = null,
    ): FrequencySignal {
        $this->pruneStale();
        $this->assertOnFrequency($operator, $frequency);

        if ($target !== null) {
            $target->unsetRelation('membership');
            $target->load('membership');

            if (! $target->isOnFrequency($frequency)) {
                throw TargetNotOnFrequencyException::make();
            }
        }

        $signal = FrequencySignal::query()->create([
            'frequency_id' => $frequency->id,
            'sender_id' => $operator->id,
            'target_id' => $target?->id,
            'type' => $type,
            'payload' => $payload,
        ]);

        $signal->setRelation('frequency', $frequency);
        $signal->setRelation('sender', $operator);
        $signal->setRelation('target', $target);

        SignalRelayed::dispatch($signal);

        return $signal;
    }

    /**
     * @return Collection<int, FrequencySignal>
     */
    public function signalsSince(User $operator, Frequency $frequency, int $afterId = 0): Collection
    {
        $this->pruneStale();
        $this->assertOnFrequency($operator, $frequency);

        return FrequencySignal::query()
            ->with(['sender', 'target'])
            ->where('frequency_id', $frequency->id)
            ->where('id', '>', $afterId)
            ->where('sender_id', '!=', $operator->id)
            ->where(function ($query) use ($operator): void {
                $query->whereNull('target_id')
                    ->orWhere('target_id', $operator->id);
            })
            ->orderBy('id')
            ->get();
    }

    public function pruneStale(): void
    {
        $presenceCutoff = now()->subSeconds((int) config('poptalk.presence_ttl_seconds'));
        $signalCutoff = now()->subSeconds((int) config('poptalk.signal_ttl_seconds'));
        $pttCutoff = now()->subSeconds((int) config('poptalk.ptt_timeout_seconds'));

        Frequency::query()
            ->whereNotNull('talking_user_id')
            ->whereNotNull('ptt_uuid')
            ->whereNotNull('ptt_last_seen_at')
            ->where('ptt_last_seen_at', '<=', $pttCutoff)
            ->get()
            ->each(function (Frequency $frequency) use ($pttCutoff): void {
                $result = DB::transaction(function () use ($frequency, $pttCutoff): ?array {
                    $talker = User::query()
                        ->whereKey($frequency->talking_user_id)
                        ->lockForUpdate()
                        ->first();
                    $lockedFrequency = Frequency::query()
                        ->whereKey($frequency->id)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedFrequency === null
                        || $lockedFrequency->ptt_uuid !== $frequency->ptt_uuid
                        || $lockedFrequency->ptt_last_seen_at === null
                        || $lockedFrequency->ptt_last_seen_at->isAfter($pttCutoff)) {
                        return null;
                    }

                    $this->clearPtt($lockedFrequency);

                    return [
                        'frequency' => $lockedFrequency,
                        'talker' => $talker,
                    ];
                });

                if ($result !== null && $result['talker'] !== null) {
                    PttStopped::dispatch($result['frequency'], $result['talker'], 'timeout');
                }
            });

        FrequencyMembership::query()
            ->where('last_seen_at', '<=', $presenceCutoff)
            ->get()
            ->each(function (FrequencyMembership $membership) use ($presenceCutoff): void {
                $result = DB::transaction(function () use ($membership, $presenceCutoff): ?array {
                    $operator = User::query()
                        ->whereKey($membership->user_id)
                        ->lockForUpdate()
                        ->first();

                    if ($operator === null) {
                        return null;
                    }

                    $frequency = Frequency::query()
                        ->whereKey($membership->frequency_id)
                        ->lockForUpdate()
                        ->first();
                    $lockedMembership = FrequencyMembership::query()
                        ->whereKey($membership->id)
                        ->lockForUpdate()
                        ->first();

                    if ($frequency === null
                        || $lockedMembership === null
                        || $lockedMembership->last_seen_at->isAfter($presenceCutoff)) {
                        return null;
                    }

                    $releasedPtt = $frequency->isPttHeldBy($operator);

                    if ($releasedPtt) {
                        $this->clearPtt($frequency);
                    }

                    $lockedMembership->delete();

                    return [
                        'operator' => $operator,
                        'frequency' => $frequency,
                        'released_ptt' => $releasedPtt,
                    ];
                });

                if ($result === null) {
                    return;
                }

                if ($result['released_ptt']) {
                    PttStopped::dispatch($result['frequency'], $result['operator'], 'timeout');
                }

                OperatorLeftFrequency::dispatch($result['frequency'], $result['operator']);
            });

        FrequencySignal::query()
            ->where('created_at', '<=', $signalCutoff)
            ->delete();
    }

    public function normalizeCallsign(string $callsign): string
    {
        return strtoupper(trim($callsign));
    }

    public function findOperatorByUuid(string $uuid): ?User
    {
        return User::query()->where('uuid', $uuid)->first();
    }

    private function assertOnFrequency(User $operator, Frequency $frequency): void
    {
        $operator->unsetRelation('membership');
        $operator->load('membership');

        if (! $operator->isOnFrequency($frequency)) {
            throw NotOnFrequencyException::make();
        }
    }

    private function clearPtt(Frequency $frequency): void
    {
        $frequency->forceFill([
            'talking_user_id' => null,
            'ptt_uuid' => null,
            'ptt_started_at' => null,
            'ptt_last_seen_at' => null,
        ])->save();
    }

    private function freshFrequency(Frequency $frequency): Frequency
    {
        return $frequency->fresh(['talkingOperator', 'memberships.operator'])
            ?? $frequency->load(['talkingOperator', 'memberships.operator']);
    }
}
