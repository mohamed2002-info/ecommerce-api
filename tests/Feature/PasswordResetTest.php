<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_requires_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('original-password'),
        ]);

        // Account-takeover attempt: reset without a valid token must fail.
        $this->postJson('/api/reset-password', [
            'email' => 'victim@example.com',
            'token' => 'totally-made-up-token',
            'password' => 'attacker-set-password',
        ])->assertStatus(422);

        $user->refresh();
        $this->assertTrue(Hash::check('original-password', $user->password));
    }

    public function test_forgot_password_does_not_leak_account_existence(): void
    {
        $known = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);
        $known->assertStatus(200)->assertJsonStructure(['message']);
        // No token is issued for a non-existent account.
        $this->assertNull($known->json('reset_token'));
    }

    public function test_full_reset_flow_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $forgot = $this->postJson('/api/forgot-password', ['email' => 'owner@example.com']);
        $forgot->assertStatus(200);
        $token = $forgot->json('reset_token');
        $this->assertNotEmpty($token);

        $this->postJson('/api/reset-password', [
            'email' => 'owner@example.com',
            'token' => $token,
            'password' => 'brand-new-password',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $user->password));

        // Token is single-use.
        $this->postJson('/api/reset-password', [
            'email' => 'owner@example.com',
            'token' => $token,
            'password' => 'another-password',
        ])->assertStatus(422);
    }
}
