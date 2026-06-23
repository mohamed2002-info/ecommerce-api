<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_non_admin_user_even_if_role_is_supplied(): void
    {
        // Attempt privilege escalation via mass assignment.
        $response = $this->postJson('/api/register', [
            'username' => 'eve',
            'email' => 'eve@example.com',
            'password' => 'secret123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'eve@example.com', 'role' => 'user']);
        $this->assertDatabaseMissing('users', ['email' => 'eve@example.com', 'role' => 'admin']);
    }

    public function test_register_returns_422_on_validation_failure(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', [
            'username' => 'someone',
            'email' => 'taken@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_login_issues_a_token(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'token', 'user' => ['id', 'name', 'email']]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_self(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->assertSame(1, $user->tokens()->count());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertStatus(200);

        // The token row is deleted, so it can no longer authenticate any future
        // request (verified here by DB state; Sanctum caches the resolved token
        // within a single request, so a same-process re-request is not a valid
        // check).
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }
}
