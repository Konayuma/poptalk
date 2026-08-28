<?php

namespace App\Events;

use App\Models\Frequency;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PttStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public int $frequencyNumber;

    public ?string $transmissionId;

    /** @var array{id: string, callsign: string} */
    public array $operatorData;

    public function __construct(Frequency $frequency, User $operator)
    {
        $this->frequencyNumber = $frequency->number;
        $this->transmissionId = $frequency->ptt_uuid;
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
        return 'ptt.started';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'frequency' => $this->frequencyNumber,
            'transmission_id' => $this->transmissionId,
            'operator' => $this->operatorData,
            'timeout_seconds' => (int) config('poptalk.ptt_timeout_seconds'),
        ];
    }
}
