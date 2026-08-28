<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'talking_user_id',
    'ptt_uuid',
    'ptt_started_at',
    'ptt_last_seen_at',
])]
class Frequency extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'ptt_started_at' => 'datetime',
            'ptt_last_seen_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function label(): string
    {
        return sprintf('%02d', $this->number);
    }

    /**
     * @return HasMany<FrequencyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(FrequencyMembership::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function talkingOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'talking_user_id');
    }

    /**
     * @return HasMany<FrequencySignal, $this>
     */
    public function signals(): HasMany
    {
        return $this->hasMany(FrequencySignal::class);
    }

    public function isPttHeldBy(User $user): bool
    {
        return $this->talking_user_id === $user->id;
    }

    public function isPttLocked(): bool
    {
        return $this->talking_user_id !== null;
    }

    public function pttHasExpired(): bool
    {
        if ($this->talking_user_id === null || $this->ptt_last_seen_at === null) {
            return false;
        }

        $timeout = (int) config('poptalk.ptt_timeout_seconds');

        return $this->ptt_last_seen_at->copy()->addSeconds($timeout)->isPast();
    }
}
