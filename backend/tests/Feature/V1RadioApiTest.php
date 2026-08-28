<?php

namespace Tests\Feature;

use App\Models\Frequency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class V1RadioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_health_and_session_contract_matches_the_frontend(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.service', 'poptalk')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure(['data' => ['service', 'status', 'server_time']]);

        $response = $this->postJson('/api/v1/sessions', [
            'callsign' => 'rookie-7',
            'channel' => 7,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.callsign', 'ROOKIE-7')
            ->assertJsonPath('data.channel', 7)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'callsign',
                    'channel',
                    'last_seen_at',
                    'connected_at',
                ],
                'meta' => [
                    'session_token',
                    'heartbeat_interval_seconds',
                    'presence_ttl_seconds',
                    'server_time',
                ],
            ]);

        $token = $response->json('meta.session_token');

        $this->withToken($token)
            ->getJson('/api/v1/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.callsign', 'ROOKIE-7')
            ->assertJsonPath('data.channel', 7);
    }

    public function test_session_identity_and_channel_can_be_updated(): void
    {
        $token = $this->createSession('ALPHA-1', 1);

        $this->withToken($token)
            ->patchJson('/api/v1/sessions/current', [
                'callsign' => 'bravo-2',
                'channel' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.callsign', 'BRAVO-2')
            ->assertJsonPath('data.channel', 2);

        $this->withToken($token)
            ->getJson('/api/v1/channels/2')
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
        $token = $this->createSession('TALKER-1', 8);

        $started = $this->withToken($token)
            ->postJson('/api/v1/channels/8/transmissions')
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

        $this->withToken($token)
            ->patchJson('/api/v1/transmissions/'.$transmissionId)
            ->assertOk()
            ->assertJsonPath('data.id', $transmissionId)
            ->assertJsonPath('data.callsign', 'TALKER-1')
            ->assertJsonMissingExact(['last_seen_at' => $firstHeartbeat]);

        $this->withToken($token)
            ->deleteJson('/api/v1/transmissions/'.$transmissionId)
            ->assertNoContent();

        $this->withToken($token)
            ->getJson('/api/v1/channels/8')
            ->assertOk()
            ->assertJsonPath('data.is_busy', false)
            ->assertJsonPath('data.active_transmission', null);

        Carbon::setTestNow();
    }

    public function test_busy_transmissions_return_the_structured_frontend_error(): void
    {
        $talkerToken = $this->createSession('TALKER-2', 9);
        $listenerToken = $this->createSession('LISTENER-2', 9);

        $this->withToken($talkerToken)
            ->postJson('/api/v1/channels/9/transmissions')
            ->assertOk();

        Auth::forgetGuards();

        $this->withToken($listenerToken)
            ->postJson('/api/v1/channels/9/transmissions')
            ->assertConflict()
            ->assertJsonPath('code', 'channel_busy')
            ->assertJsonPath('errors.channel.0', 'Wait for the current caller to release PTT.');
    }

    public function test_ending_a_session_releases_presence_and_revokes_its_token(): void
    {
        $token = $this->createSession('SIGN-OFF', 11);

        $this->withToken($token)
            ->deleteJson('/api/v1/sessions/current')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/sessions/current')
            ->assertUnauthorized();

        $this->assertDatabaseCount('frequency_memberships', 0);
        $this->assertDatabaseMissing('users', ['callsign' => 'SIGN-OFF']);
    }

    public function test_stale_sessions_are_rejected_and_removed(): void
    {
        $token = $this->createSession('STALE-1', 12);

        Carbon::setTestNow(
            now()->addSeconds((int) config('poptalk.presence_ttl_seconds') + 1)
        );

        $this->withToken($token)
            ->getJson('/api/v1/sessions/current')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_session_token');

        $this->assertDatabaseMissing('users', ['callsign' => 'STALE-1']);

        Carbon::setTestNow();
    }

    private function createSession(string $callsign, int $channel): string
    {
        return $this->postJson('/api/v1/sessions', [
            'callsign' => $callsign,
            'channel' => $channel,
        ])->assertCreated()->json('meta.session_token');
    }
}
