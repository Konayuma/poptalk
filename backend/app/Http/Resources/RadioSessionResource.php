<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class RadioSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $this->relationLoaded('membership')
            ? $this->membership
            : $this->membership()->with('frequency')->first();

        return [
            'id' => $this->uuid,
            'callsign' => $this->callsign,
            'channel' => $membership?->frequency?->number,
            'last_seen_at' => ($membership?->last_seen_at ?? $this->last_seen_at)?->toIso8601String(),
            'connected_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
