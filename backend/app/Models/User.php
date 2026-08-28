<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'uuid', 'callsign', 'last_seen_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<FrequencyMembership, $this>
     */
    public function membership(): HasOne
    {
        return $this->hasOne(FrequencyMembership::class);
    }

    /**
     * @return HasMany<FrequencySignal, $this>
     */
    public function sentSignals(): HasMany
    {
        return $this->hasMany(FrequencySignal::class, 'sender_id');
    }

    public function isOnFrequency(Frequency $frequency): bool
    {
        return $this->membership?->frequency_id === $frequency->id;
    }

    public function isStale(): bool
    {
        if ($this->last_seen_at === null) {
            return true;
        }

        return $this->last_seen_at
            ->copy()
            ->addSeconds((int) config('poptalk.presence_ttl_seconds'))
            ->isPast();
    }

    /**
     * @return array{id: string, callsign: string}
     */
    public function presencePayload(): array
    {
        return [
            'id' => $this->uuid,
            'callsign' => $this->callsign,
        ];
    }
}
