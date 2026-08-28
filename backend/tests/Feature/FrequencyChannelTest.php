<?php

namespace Tests\Feature;

use App\Broadcasting\FrequencyChannel;
use App\Broadcasting\OperatorChannel;
use App\Events\SignalRelayed;
use App\Models\Frequency;
use App\Models\FrequencySignal;
use App\Models\User;
use App\Services\WalkieTalkieService;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FrequencyChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_presence_channel_allows_members_and_rejects_strangers(): void
    {
        Event::fake();

        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $frequency = Frequency::query()->where('number', 21)->firstOrFail();

        app(WalkieTalkieService::class)->join($member, $frequency);

        $channel = new FrequencyChannel;

        $this->assertSame($member->presencePayload(), $channel->join($member->fresh(), 21));
        $this->assertFalse($channel->join($stranger, 21));
        $this->assertFalse($channel->join($member->fresh(), 22));
    }

    public function test_presence_channel_rejects_stale_memberships(): void
    {
        Event::fake();

        $member = User::factory()->create();
        $frequency = Frequency::query()->where('number', 22)->firstOrFail();
        app(WalkieTalkieService::class)->join($member, $frequency);
        $member->membership()->update([
            'last_seen_at' => now()->subSeconds(
                (int) config('poptalk.presence_ttl_seconds') + 1
            ),
        ]);

        $this->assertFalse((new FrequencyChannel)->join($member->fresh(), 22));
    }

    public function test_operator_channel_only_allows_its_owner(): void
    {
        $operator = User::factory()->create();
        $stranger = User::factory()->create();
        $channel = new OperatorChannel;

        $this->assertTrue($channel->join($operator, $operator->uuid));
        $this->assertFalse($channel->join($stranger, $operator->uuid));
    }

    public function test_targeted_signals_use_private_operator_channels(): void
    {
        $frequency = Frequency::query()->where('number', 23)->firstOrFail();
        $sender = User::factory()->create();
        $target = User::factory()->create();
        $targetedSignal = FrequencySignal::query()->create([
            'frequency_id' => $frequency->id,
            'sender_id' => $sender->id,
            'target_id' => $target->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'private'],
        ]);
        $broadcastSignal = FrequencySignal::query()->create([
            'frequency_id' => $frequency->id,
            'sender_id' => $sender->id,
            'type' => 'offer',
            'payload' => ['sdp' => 'public'],
        ]);

        $targetedChannel = (new SignalRelayed($targetedSignal))->broadcastOn()[0];
        $broadcastChannel = (new SignalRelayed($broadcastSignal))->broadcastOn()[0];

        $this->assertInstanceOf(PrivateChannel::class, $targetedChannel);
        $this->assertSame('private-operator.'.$target->uuid, $targetedChannel->name);
        $this->assertInstanceOf(PresenceChannel::class, $broadcastChannel);

        $target->delete();

        $this->assertDatabaseMissing('frequency_signals', ['id' => $targetedSignal->id]);
        $this->assertDatabaseHas('frequency_signals', ['id' => $broadcastSignal->id]);
    }
}
