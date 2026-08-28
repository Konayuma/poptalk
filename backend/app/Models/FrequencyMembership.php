<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'frequency_id', 'last_seen_at'])]
class FrequencyMembership extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Frequency, $this>
     */
    public function frequency(): BelongsTo
    {
        return $this->belongsTo(Frequency::class);
    }

    public function isStale(): bool
    {
        $ttl = (int) config('poptalk.presence_ttl_seconds');

        return $this->last_seen_at === null
            || $this->last_seen_at->copy()->addSeconds($ttl)->isPast();
    }
}
