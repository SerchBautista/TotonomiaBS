<?php

namespace Tests\Unit\Actions;

use App\Actions\RegisterUserAction;
use App\Events\UserRegistered;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_user_with_given_data(): void
    {
        $this->seed();
        Notification::fake();
        Event::fake([UserRegistered::class]);

        $action = app(RegisterUserAction::class);
        $user = $action->execute([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'StrongPass123',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertNull($user->email_verified_at);
    }

    public function test_assigns_user_role(): void
    {
        $this->seed();
        Notification::fake();
        Event::fake([UserRegistered::class]);

        $action = app(RegisterUserAction::class);
        $user = $action->execute([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'StrongPass123',
        ]);

        $this->assertTrue($user->hasRole('user'));
    }

    public function test_dispatches_user_registered_event(): void
    {
        $this->seed();
        Notification::fake();
        Event::fake([UserRegistered::class]);

        $action = app(RegisterUserAction::class);
        $action->execute([
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'password' => 'StrongPass123',
        ]);

        Event::assertDispatched(UserRegistered::class);
    }
}
