<?php

namespace App\Events;

use App\Models\Frequency;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class OperatorJoinedFrequency implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public int $frequencyNumber;

    /** @var array{id: string, callsign: string} */
    public array $operatorData;

    public function __construct(Frequency $frequency, User $operator)
    {
        $this->frequencyNumber = $frequency->number;
        $this->operatorData = $operator->presencePayload();
    }

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('frequency.'.$this->frequencyNumber),
        ];
    }

    public function broadcastAs(): string
    {
        return 'operator.joined';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'frequency' => $this->frequencyNumber,
            'operator' => $this->operatorData,
        ];
    }
}
