<?php

namespace App\Http\Resources;

use App\Models\Frequency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Frequency
 */
class ChannelStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'listener_count' => $this->memberships_count ?? $this->memberships()->count(),
            'is_busy' => $this->isPttLocked(),
            'active_transmission' => $this->isPttLocked()
                ? new TransmissionResource($this)
                : null,
        ];
    }
}
