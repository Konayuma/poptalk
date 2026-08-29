<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_register_and_receives_an_authenticated_session(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Rookie Seven',
            'email' => 'rookie@example.com',
            'callsign' => 'rookie-7',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'rookie@example.com')
            ->assertJsonPath('data.callsign', 'ROOKIE-7')
            ->assertJsonPath('data.name', 'Rookie Seven')
            ->assertJsonMissingPath('data.password')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'rookie@example.com',
            'callsign' => 'ROOKIE-7',
        ]);
    }

    public function test_duplicate_emails_and_callsigns_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
            'callsign' => 'ALPHA-1',
        ]);

        $this->postJson('/api/auth/register', [
            'name' => 'Alpha',
            'email' => 'taken@example.com',
            'callsign' => 'BRAVO-2',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/register', [
            'name' => 'Alpha',
            'email' => 'alpha@example.com',
            'callsign' => 'alpha-1',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertUnprocessable()->assertJsonValidationErrors('callsign');
    }

    public function test_invalid_callsigns_and_weak_passwords_are_rejected(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Tiny',
            'email' => 'tiny@example.com',
            'callsign' => 'x',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertUnprocessable()->assertJsonValidationErrors('callsign');

        $this->postJson('/api/auth/register', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'callsign' => 'WEAK-1',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_a_user_can_log_in_and_the_session_is_regenerated(): void
    {
        $user = User::factory()->create([
            'email' => 'pilot@example.com',
            'password' => 'Password1',
        ]);

        $this->withSession(['_token' => 'old-csrf-token']);
        $previousSessionId = $this->app['session']->getId();

        $this->postJson('/api/auth/login', [
            'email' => 'pilot@example.com',
            'password' => 'Password1',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'pilot@example.com');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSessionId, $this->app['session']->getId());
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'pilot@example.com',
            'password' => 'Password1',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'pilot@example.com',
            'password' => 'WrongPass1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create([
            'email' => 'pilot@example.com',
            'password' => 'Password1',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'WrongPass1',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPass1',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
    }

    public function test_logout_invalidates_the_session_and_blocks_radio_use(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Sign Off',
            'email' => 'signoff@example.com',
            'callsign' => 'SIGN-OFF',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertCreated();

        $this->postJson('/api/v1/sessions', ['channel' => 4])->assertCreated();
        $this->postJson('/api/auth/logout')->assertNoContent();

        $this->assertGuest();
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->getJson('/api/v1/sessions/current')->assertUnauthorized();
        $this->assertDatabaseCount('frequency_memberships', 0);
        $this->assertDatabaseHas('users', ['email' => 'signoff@example.com']);
    }

    public function test_the_current_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/auth/user')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['callsign' => 'MIKE-9']));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.callsign', 'MIKE-9');
    }
}
