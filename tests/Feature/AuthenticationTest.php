<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_a_user_and_returns_a_token_without_sensitive_attributes(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'organization_name' => 'Acme Distribution',
            'name' => 'Rafaela Souza',
            'email' => 'rafaela@example.test',
            'password' => 'a-secure-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.name', 'Rafaela Souza')
            ->assertJsonPath('data.user.email', 'rafaela@example.test')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $this->assertDatabaseHas('users', ['email' => 'rafaela@example.test']);
        $this->assertDatabaseHas('organizations', ['name' => 'Acme Distribution']);
        $registeredUser = User::query()->where('email', 'rafaela@example.test')->firstOrFail();
        $this->assertSame('Acme Distribution', $registeredUser->organization->name);
        $this->assertSame('admin', $registeredUser->role);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_rejects_duplicate_email_and_invalid_registration_data(): void
    {
        User::factory()->create(['email' => 'rafaela@example.test']);

        $this->postJson('/api/auth/register', [
            'organization_name' => 'Another Organization',
            'name' => 'Another user',
            'email' => 'rafaela@example.test',
            'password' => 'a-secure-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/register', [
            'organization_name' => 'Short Password Organization',
            'name' => 'Short Password',
            'email' => 'short@example.test',
            'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_logs_in_with_valid_credentials_and_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'rafaela@example.test',
            'password' => 'a-secure-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'rafaela@example.test',
            'password' => 'a-secure-password',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'rafaela@example.test')
            ->assertJsonStructure(['data' => ['user', 'token']])
            ->assertJsonMissingPath('data.user.password');

        $this->postJson('/api/auth/login', [
            'email' => 'rafaela@example.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_throttles_login_after_five_attempts_per_email_and_ip(): void
    {
        $credentials = [
            'email' => 'limited@example.test',
            'password' => 'wrong-password',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', $credentials)->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', $credentials)->assertTooManyRequests();
    }

    public function test_throttles_registration_after_ten_attempts_per_ip(): void
    {
        $registration = [
            'organization_name' => 'Limited Organization',
            'name' => 'Limited User',
            'email' => 'limited-registration@example.test',
            'password' => 'a-secure-password',
        ];

        $this->postJson('/api/auth/register', $registration)->assertCreated();

        for ($attempt = 1; $attempt < 10; $attempt++) {
            $this->postJson('/api/auth/register', $registration)->assertUnprocessable();
        }

        $this->postJson('/api/auth/register', $registration)->assertTooManyRequests();
    }

    public function test_authenticated_user_can_access_me_and_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();
        $firstToken = $user->createToken('first')->plainTextToken;
        $secondToken = $user->createToken('second')->plainTextToken;

        $this->withToken($firstToken)->getJson('/api/auth/me')->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');

        $this->withToken($firstToken)->postJson('/api/auth/logout')->assertOk();

        Auth::forgetGuards();
        $this->withToken($firstToken)->getJson('/api/auth/me')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($secondToken)->getJson('/api/auth/me')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_anonymous_requests_cannot_access_business_endpoints(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $this->getJson('/api/suppliers')->assertUnauthorized();
        $this->getJson('/api/departments')->assertUnauthorized();
        $this->getJson('/api/reports/consumption?from=2026-01-01&to=2026-01-31')->assertUnauthorized();
        $this->postJson('/api/products/1/movements', [])->assertUnauthorized();
        $this->putJson('/api/products/1/suppliers/1', [])->assertUnauthorized();
    }

    public function test_every_api_business_route_uses_sanctum_authentication(): void
    {
        $publicRoutes = ['api/auth/register', 'api/auth/login'];
        $businessRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/'))
            ->reject(fn ($route): bool => in_array($route->uri(), $publicRoutes, true));

        $this->assertNotEmpty($businessRoutes);

        foreach ($businessRoutes as $route) {
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), $route->uri());
        }
    }
}
