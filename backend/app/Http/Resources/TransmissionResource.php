<?php

namespace App\Http\Resources;

use App\Models\Frequency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Frequency
 */
class TransmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ptt_uuid,
            'session_id' => $this->talkingOperator?->uuid,
            'callsign' => $this->talkingOperator?->callsign,
            'channel' => $this->number,
            'started_at' => $this->ptt_started_at?->toIso8601String(),
            'last_seen_at' => $this->ptt_last_seen_at?->toIso8601String(),
            'ended_at' => null,
        ];
    }
}
