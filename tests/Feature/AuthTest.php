<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_user_and_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Asha',
            'email' => 'Asha@Example.com',
            'password' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'asha@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']])
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_register_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'TAKEN@example.com',
            'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        User::factory()->create(['email' => 'asha@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/login', ['email' => 'ASHA@example.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'asha@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/login', ['email' => 'asha@example.com', 'password' => 'wrong-pass1'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid email or password.']);
    }

    public function test_non_string_email_is_a_validation_error_not_a_crash(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['x'], 'password' => 'secret123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_is_rate_limited(): void
    {
        $credentials = ['email' => 'asha@example.com', 'password' => 'wrong-pass1'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $credentials)->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', $credentials)->assertTooManyRequests();
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/notes')->assertUnauthorized();
        // No Accept header: must still be a JSON 401, not a redirect to a login page.
        $this->get('/api/notes')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_me_and_logout_revokes_the_token(): void
    {
        User::factory()->create(['email' => 'asha@example.com', 'password' => 'secret123']);
        $token = $this->postJson('/api/auth/login', ['email' => 'asha@example.com', 'password' => 'secret123'])
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', 'asha@example.com');
        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
