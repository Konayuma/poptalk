<?php

namespace App\Http\Resources;

use App\Models\Frequency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Frequency
 */
class FrequencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $operators = $this->whenLoaded('memberships', function () {
            return OperatorResource::collection(
                $this->memberships
                    ->map(fn ($membership) => $membership->operator)
                    ->filter()
                    ->values()
            );
        });

        return [
            'number' => $this->number,
            'label' => $this->label(),
            'occupancy' => $this->memberships_count ?? $this->memberships->count(),
            'talking' => $this->talkingOperator
                ? new OperatorResource($this->talkingOperator)
                : null,
            'operators' => $this->when($this->relationLoaded('memberships'), $operators),
        ];
    }
}
