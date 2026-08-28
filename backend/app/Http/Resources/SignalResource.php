<?php

namespace App\Http\Resources;

use App\Models\FrequencySignal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FrequencySignal
 */
class SignalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'payload' => $this->payload,
            'sender' => new OperatorResource($this->whenLoaded('sender')),
            'target_id' => $this->target?->uuid,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
