<?php

namespace Tests\Unit\Actions;

use App\Actions\ToggleTwoFactorAction;
use App\Exceptions\DomainValidationException;
use App\Models\TwoFactorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToggleTwoFactorActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_enable_2fa_with_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('MyPassword1')]);

        $action = new ToggleTwoFactorAction;
        $result = $action->execute($user, true, 'MyPassword1');

        $this->assertTrue($result->two_factor_enabled);
    }

    public function test_disable_2fa_with_correct_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('MyPassword1'),
            'two_factor_enabled' => true,
        ]);

        $action = new ToggleTwoFactorAction;
        $result = $action->execute($user, false, 'MyPassword1');

        $this->assertFalse($result->two_factor_enabled);
    }

    public function test_wrong_password_throws_invalid_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('MyPassword1')]);

        $this->expectException(DomainValidationException::class);

        $action = new ToggleTwoFactorAction;

        try {
            $action->execute($user, true, 'WrongPassword1');
        } catch (DomainValidationException $e) {
            $this->assertEquals('invalid_password', $e->errorCode());
            $this->assertArrayHasKey('password', $e->fieldErrors());

            throw $e;
        }
    }

    public function test_disabling_2fa_removes_active_sessions(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('MyPassword1'),
            'two_factor_enabled' => true,
        ]);

        TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertDatabaseCount('two_factor_sessions', 1);

        $action = new ToggleTwoFactorAction;
        $action->execute($user, false, 'MyPassword1');

        $this->assertDatabaseCount('two_factor_sessions', 0);
    }

    public function test_enabling_2fa_does_not_remove_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('MyPassword1')]);

        TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $action = new ToggleTwoFactorAction;
        $action->execute($user, true, 'MyPassword1');

        $this->assertDatabaseCount('two_factor_sessions', 1);
    }
}
