<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_with_registered_email_returns_200(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    public function test_forgot_password_with_unregistered_email_returns_200(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk();
        Notification::assertNothingSent();
    }

    public function test_forgot_password_with_invalid_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();
    }

    public function test_forgot_password_without_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', []);

        $response->assertUnprocessable();
    }
}
