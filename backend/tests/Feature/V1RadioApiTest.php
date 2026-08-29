<?php

namespace Tests\Feature;

use App\Models\Frequency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1RadioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_health_is_available_without_authentication(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.service', 'poptalk')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure(['data' => ['service', 'status', 'server_time']])
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_opening_a_radio_session_requires_authentication(): void
    {
        $this->postJson('/api/v1/sessions', [
            'callsign' => 'ROOKIE-7',
            'channel' => 7,
        ])->assertUnauthorized();
    }

    public function test_authenticated_session_contract_matches_the_frontend(): void
    {
        $user = $this->createSession('ROOKIE-7', 7);

        $this->getJson('/api/v1/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.callsign', 'ROOKIE-7')
            ->assertJsonPath('data.channel', 7)
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'callsign',
                    'channel',
                    'last_seen_at',
                    'connected_at',
                ],
            ]);
    }

    public function test_session_identity_and_channel_can_be_updated(): void
    {
        $this->createSession('ALPHA-1', 1);

        $this->patchJson('/api/v1/sessions/current', [
            'callsign' => 'bravo-2',
            'channel' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.callsign', 'BRAVO-2')
            ->assertJsonPath('data.channel', 2);

        $this->getJson('/api/v1/channels/2')
            ->assertOk()
            ->assertJsonPath('data.number', 2)
            ->assertJsonPath('data.listener_count', 1)
            ->assertJsonPath('data.is_busy', false)
            ->assertJsonPath('data.active_transmission', null);

        $this->assertDatabaseMissing('frequency_memberships', [
            'frequency_id' => Frequency::query()->where('number', 1)->value('id'),
        ]);
    }

    public function test_transmissions_can_be_claimed_renewed_and_released(): void
    {
        $this->createSession('TALKER-1', 8);

        $started = $this->postJson('/api/v1/channels/8/transmissions')
            ->assertOk()
            ->assertJsonPath('data.callsign', 'TALKER-1')
            ->assertJsonPath('data.channel', 8)
            ->assertJsonPath('data.ended_at', null)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'session_id',
                    'callsign',
                    'channel',
                    'started_at',
                    'last_seen_at',
                    'ended_at',
                ],
            ]);

        $transmissionId = $started->json('data.id');
        $firstHeartbeat = $started->json('data.last_seen_at');

        Carbon::setTestNow(now()->addSeconds(20));

        $this->patchJson('/api/v1/transmissions/'.$transmissionId)
            ->assertOk()
            ->assertJsonPath('data.id', $transmissionId)
            ->assertJsonMissingExact(['last_seen_at' => $firstHeartbeat]);

        $this->deleteJson('/api/v1/transmissions/'.$transmissionId)
            ->assertNoContent();

        $this->getJson('/api/v1/channels/8')
            ->assertOk()
            ->assertJsonPath('data.is_busy', false)
            ->assertJsonPath('data.active_transmission', null);

        Carbon::setTestNow();
    }

    public function test_busy_transmissions_return_the_structured_frontend_error(): void
    {
        $talker = $this->createSession('TALKER-2', 9);
        $listener = User::factory()->create(['callsign' => 'LISTENER-2', 'name' => 'LISTENER-2']);

        $this->postJson('/api/v1/channels/9/transmissions')->assertOk();

        Auth::forgetGuards();
        Sanctum::actingAs($listener);
        $this->postJson('/api/v1/sessions', [
            'callsign' => 'LISTENER-2',
            'channel' => 9,
        ])->assertCreated();

        $this->postJson('/api/v1/channels/9/transmissions')
            ->assertConflict()
            ->assertJsonPath('code', 'channel_busy')
            ->assertJsonPath('errors.channel.0', 'Wait for the current caller to release PTT.');

        $this->assertTrue($talker->is($talker->fresh()));
    }

    public function test_ending_a_session_releases_presence_but_keeps_the_account(): void
    {
        $this->createSession('SIGN-OFF', 11);

        $this->deleteJson('/api/v1/sessions/current')->assertNoContent();

        $this->getJson('/api/v1/sessions/current')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'radio_session_expired');

        $this->assertDatabaseCount('frequency_memberships', 0);
        $this->assertDatabaseHas('users', ['callsign' => 'SIGN-OFF']);
    }

    public function test_stale_sessions_are_rejected_without_deleting_the_account(): void
    {
        $this->createSession('STALE-1', 12);

        Carbon::setTestNow(
            now()->addSeconds((int) config('poptalk.presence_ttl_seconds') + 1)
        );

        $this->getJson('/api/v1/sessions/current')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'radio_session_expired');

        $this->assertDatabaseHas('users', ['callsign' => 'STALE-1']);
        $this->assertDatabaseCount('frequency_memberships', 0);

        Carbon::setTestNow();
    }

    private function createSession(string $callsign, int $channel): User
    {
        $normalized = strtoupper($callsign);
        $user = User::factory()->create([
            'callsign' => $normalized,
            'name' => $normalized,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sessions', [
            'callsign' => $callsign,
            'channel' => $channel,
        ])
            ->assertCreated()
            ->assertJsonPath('data.callsign', $normalized)
            ->assertJsonPath('data.channel', $channel);

        return $user;
    }
}
