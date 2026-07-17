<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_verify_email_with_valid_signed_link(): void
    {
        $this->seed();

        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $response = $this->getJson($path);

        $response->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_with_wrong_hash_returns_403(): void
    {
        $this->seed();

        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => 'wrong-hash']
        );

        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $response = $this->getJson($path);

        $response
            ->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'email_verification_invalid')
            ->assertJsonPath('message', 'Invalid or expired verification link.');
    }

    public function test_verification_with_invalid_signature_returns_403(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->getJson("/api/v1/auth/email/verify/{$user->id}/fakehash?expires=1&signature=bad");

        $response
            ->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'email_verification_invalid')
            ->assertJsonPath('message', 'Invalid or expired verification link.');
    }

    public function test_already_verified_email_returns_200(): void
    {
        $this->seed();

        $user = User::factory()->create(); // email_verified_at is set by default
        $user->assignRole('user');

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $response = $this->getJson($path);

        $response->assertOk();
    }

    public function test_resend_verification_returns_200(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/email/resend', [
            'email' => $user->email,
        ]);

        $response->assertOk();
    }

    public function test_resend_for_already_verified_user_returns_200(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/email/resend', [
            'email' => $user->email,
        ]);

        $response->assertOk();
    }

    public function test_resend_for_nonexistent_email_returns_200(): void
    {
        $response = $this->postJson('/api/v1/auth/email/resend', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk();
    }
}
