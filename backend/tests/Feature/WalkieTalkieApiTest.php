<?php

namespace Tests\Feature;

use App\Events\OperatorJoinedFrequency;
use App\Events\OperatorLeftFrequency;
use App\Events\PttStarted;
use App\Events\PttStopped;
use App\Events\SignalRelayed;
use App\Models\Frequency;
use App\Models\FrequencyMembership;
use App\Models\FrequencySignal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalkieTalkieApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_can_register_with_a_callsign(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Pop Seven',
            'email' => 'pop7@example.com',
            'callsign' => 'pop-7',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.callsign', 'POP-7')
            ->assertJsonPath('data.email', 'pop7@example.com');

        $this->assertDatabaseHas('users', [
            'callsign' => 'POP-7',
            'email' => 'pop7@example.com',
        ]);
        $this->assertAuthenticated();
    }

    public function test_duplicate_callsigns_are_rejected(): void
    {
        User::factory()->create(['callsign' => 'ALPHA-1', 'name' => 'ALPHA-1']);

        $this->postJson('/api/auth/register', [
            'name' => 'Alpha',
            'email' => 'alpha@example.com',
            'callsign' => 'alpha-1',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('callsign');
    }

    public function test_invalid_callsigns_are_rejected(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Tiny',
            'email' => 'tiny@example.com',
            'callsign' => 'x',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('callsign');

        $this->postJson('/api/auth/register', [
            'name' => 'Bad Sign',
            'email' => 'bad@example.com',
            'callsign' => 'no_underscores!',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('callsign');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/frequencies')->assertUnauthorized();
        $this->postJson('/api/frequencies/1/join')->assertUnauthorized();
        $this->postJson('/api/frequencies/1/ptt/start')->assertUnauthorized();
        $this->postJson('/api/v1/sessions', ['channel' => 1])->assertUnauthorized();
    }

    public function test_it_lists_all_ninety_nine_frequencies(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/frequencies');

        $response->assertOk();
        $this->assertCount(99, $response->json('data'));
        $this->assertSame(1, $response->json('data.0.number'));
        $this->assertSame('01', $response->json('data.0.label'));
        $this->assertSame(99, $response->json('data.98.number'));
        $this->assertSame('99', $response->json('data.98.label'));
    }

    public function test_unknown_frequencies_return_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/frequencies/0')->assertNotFound();
        $this->getJson('/api/frequencies/100')->assertNotFound();
        $this->getJson('/api/frequencies/abc')->assertNotFound();
    }

    public function test_operators_can_join_and_leave_a_frequency(): void
    {
        Event::fake([OperatorJoinedFrequency::class, OperatorLeftFrequency::class]);

        $operator = User::factory()->create(['callsign' => 'BRAVO-2']);
        Sanctum::actingAs($operator);

        $this->postJson('/api/frequencies/7/join')
            ->assertOk()
            ->assertJsonPath('data.number', 7)
            ->assertJsonPath('data.label', '07')
            ->assertJsonPath('data.occupancy', 1)
            ->assertJsonPath('data.operators.0.callsign', 'BRAVO-2');

        Event::assertDispatched(OperatorJoinedFrequency::class);

        $this->getJson('/api/frequencies/07')
            ->assertOk()
            ->assertJsonPath('data.occupancy', 1);

        $this->postJson('/api/frequencies/7/leave')
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        Event::assertDispatched(OperatorLeftFrequency::class);

        $this->getJson('/api/frequencies/7')
            ->assertOk()
            ->assertJsonPath('data.occupancy', 0);
    }

    public function test_joining_another_frequency_leaves_the_previous_one(): void
    {
        $operator = User::factory()->create();
        Sanctum::actingAs($operator);

        $this->postJson('/api/frequencies/1/join')->assertOk();
        $this->postJson('/api/frequencies/2/join')->assertOk();

        $this->assertSame(2, $operator->fresh()->membership?->frequency?->number);
        $this->assertSame(0, Frequency::query()->where('number', 1)->withCount('memberships')->value('memberships_count'));
        $this->assertSame(1, Frequency::query()->where('number', 2)->withCount('memberships')->value('memberships_count'));
    }

    public function test_only_one_operator_can_hold_ptt_on_a_frequency(): void
    {
        Event::fake([PttStarted::class, PttStopped::class]);

        $talker = User::factory()->create(['callsign' => 'TALK-1']);
        $listener = User::factory()->create(['callsign' => 'LISTEN-1']);

        Sanctum::actingAs($talker);
        $this->postJson('/api/frequencies/3/join')->assertOk();
        $this->postJson('/api/frequencies/3/ptt/start')
            ->assertOk()
            ->assertJsonPath('data.talking.callsign', 'TALK-1');

        Event::assertDispatched(PttStarted::class);

        Sanctum::actingAs($listener);
        $this->postJson('/api/frequencies/3/join')->assertOk();
        $this->postJson('/api/frequencies/3/ptt/start')
            ->assertConflict()
            ->assertJsonPath('message', 'Frequency is held by TALK-1.');

        $this->postJson('/api/frequencies/3/ptt/stop')
            ->assertForbidden();

        Sanctum::actingAs($talker);
        $this->postJson('/api/frequencies/3/ptt/stop')
            ->assertOk()
            ->assertJsonPath('data.talking', null);

        Event::assertDispatched(PttStopped::class);
    }

    public function test_ptt_requires_membership(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/frequencies/4/ptt/start')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'radio_session_expired');
    }

    public function test_expired_ptt_locks_are_released(): void
    {
        $talker = User::factory()->create(['callsign' => 'OLD-1']);
        $challenger = User::factory()->create(['callsign' => 'NEW-1']);

        Sanctum::actingAs($talker);
        $this->postJson('/api/frequencies/5/join')->assertOk();
        $this->postJson('/api/frequencies/5/ptt/start')->assertOk();

        Carbon::setTestNow(now()->addSeconds((int) config('poptalk.ptt_timeout_seconds') + 1));

        Sanctum::actingAs($challenger);
        $this->postJson('/api/frequencies/5/join')->assertOk();
        $this->postJson('/api/frequencies/5/ptt/start')
            ->assertOk()
            ->assertJsonPath('data.talking.callsign', 'NEW-1');

        Carbon::setTestNow();
    }

    public function test_webrtc_signals_are_relayed_to_other_members(): void
    {
        Event::fake([SignalRelayed::class]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $outsider = User::factory()->create();

        Sanctum::actingAs($sender);
        $this->postJson('/api/frequencies/8/join')->assertOk();

        Sanctum::actingAs($receiver);
        $this->postJson('/api/frequencies/8/join')->assertOk();

        Sanctum::actingAs($outsider);
        $this->postJson('/api/frequencies/9/join')->assertOk();

        Sanctum::actingAs($sender);
        $created = $this->postJson('/api/frequencies/8/signals', [
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
            'target_id' => $receiver->uuid,
        ])->assertCreated();

        $signalId = $created->json('data.id');
        Event::assertDispatched(SignalRelayed::class);

        Sanctum::actingAs($receiver);
        $this->getJson('/api/frequencies/8/signals')
            ->assertOk()
            ->assertJsonPath('data.0.id', $signalId)
            ->assertJsonPath('data.0.type', 'offer')
            ->assertJsonPath('data.0.payload.sdp', 'v=0');

        $this->getJson('/api/frequencies/8/signals?after='.$signalId)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($sender);
        $this->getJson('/api/frequencies/8/signals')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($outsider);
        $this->getJson('/api/frequencies/9/signals')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_signals_cannot_target_operators_on_another_frequency(): void
    {
        $sender = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($sender);
        $this->postJson('/api/frequencies/10/join')->assertOk();

        Sanctum::actingAs($target);
        $this->postJson('/api/frequencies/11/join')->assertOk();

        Sanctum::actingAs($sender);
        $this->postJson('/api/frequencies/10/signals', [
            'type' => 'offer',
            'payload' => ['sdp' => 'v=0'],
            'target_id' => $target->uuid,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Target operator is not on this frequency.');
    }

    public function test_heartbeat_keeps_presence_alive_and_stale_members_are_pruned(): void
    {
        $operator = User::factory()->create();
        Sanctum::actingAs($operator);

        $this->postJson('/api/frequencies/12/join')->assertOk();
        $this->postJson('/api/me/heartbeat')
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.frequency.number', 12);

        FrequencyMembership::query()->update([
            'last_seen_at' => now()->subSeconds((int) config('poptalk.presence_ttl_seconds') + 5),
        ]);

        $this->artisan('poptalk:prune')->assertSuccessful();

        $this->assertDatabaseCount('frequency_memberships', 0);
    }

    public function test_me_returns_the_current_operator_and_frequency(): void
    {
        $operator = User::factory()->create(['callsign' => 'MIKE-9']);
        Sanctum::actingAs($operator);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.operator.callsign', 'MIKE-9')
            ->assertJsonPath('data.frequency', null);

        $this->postJson('/api/frequencies/15/join')->assertOk();

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.frequency.number', 15);
    }

    public function test_leaving_releases_a_held_ptt_lock(): void
    {
        $talker = User::factory()->create(['callsign' => 'GONE-1']);
        Sanctum::actingAs($talker);

        $this->postJson('/api/frequencies/16/join')->assertOk();
        $this->postJson('/api/frequencies/16/ptt/start')->assertOk();
        $this->postJson('/api/frequencies/16/leave')->assertOk();

        $this->assertNull(Frequency::query()->where('number', 16)->value('talking_user_id'));
    }

    public function test_old_signals_are_pruned(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        Sanctum::actingAs($sender);
        $this->postJson('/api/frequencies/18/join')->assertOk();
        Sanctum::actingAs($receiver);
        $this->postJson('/api/frequencies/18/join')->assertOk();

        Sanctum::actingAs($sender);
        $this->postJson('/api/frequencies/18/signals', [
            'type' => 'hangup',
            'payload' => ['reason' => 'over'],
        ])->assertCreated();

        FrequencySignal::query()->update([
            'created_at' => now()->subSeconds((int) config('poptalk.signal_ttl_seconds') + 5),
        ]);

        $this->artisan('poptalk:prune')->assertSuccessful();

        $this->assertDatabaseCount('frequency_signals', 0);
    }
}
