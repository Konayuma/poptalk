<?php

namespace App\Events;

use App\Models\FrequencySignal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class SignalRelayed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public int $signalId;

    public int $frequencyNumber;

    public string $type;

    /** @var array<string, mixed> */
    public array $payload;

    /** @var array{id: string, callsign: string} */
    public array $senderData;

    public ?string $targetUuid;

    public ?string $createdAt;

    public function __construct(FrequencySignal $signal)
    {
        $signal->loadMissing(['sender', 'target', 'frequency']);

        $this->signalId = $signal->id;
        $this->frequencyNumber = $signal->frequency->number;
        $this->type = $signal->type;
        $this->payload = $signal->payload;
        $this->senderData = $signal->sender->presencePayload();
        $this->targetUuid = $signal->target?->uuid;
        $this->createdAt = $signal->created_at?->toIso8601String();
    }

    /**
     * @return array<int, PresenceChannel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if ($this->targetUuid !== null) {
            return [new PrivateChannel('operator.'.$this->targetUuid)];
        }

        return [
            new PresenceChannel('frequency.'.$this->frequencyNumber),
        ];
    }

    public function broadcastAs(): string
    {
        return 'signal.relayed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->signalId,
            'frequency' => $this->frequencyNumber,
            'type' => $this->type,
            'payload' => $this->payload,
            'sender' => $this->senderData,
            'target_id' => $this->targetUuid,
            'created_at' => $this->createdAt,
        ];
    }
}
