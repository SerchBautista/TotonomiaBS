<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OnboardingSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    private function verifyEmail(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $this->getJson($path);
    }

    public function test_email_verification_creates_general_workspace_and_category(): void
    {
        $this->seed();
        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        $this->verifyEmail($user);

        $this->assertDatabaseHas('workspaces', [
            'owner_id' => $user->id,
            'name' => 'General',
        ]);

        $workspace = Workspace::where('owner_id', $user->id)->where('name', 'General')->first();
        $this->assertNotNull($workspace);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'General',
        ]);
    }

    public function test_email_verification_sets_default_workspace_id(): void
    {
        $this->seed();
        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        $this->verifyEmail($user);

        $workspace = Workspace::where('owner_id', $user->id)->where('name', 'General')->first();
        $this->assertEquals($workspace->id, $user->fresh()->default_workspace_id);
    }

    public function test_created_workspace_has_user_as_owner_member(): void
    {
        $this->seed();
        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        $this->verifyEmail($user);

        $workspace = Workspace::where('owner_id', $user->id)->where('name', 'General')->first();
        $this->assertNotNull($workspace);

        // The owner must be inserted into the workspace_user pivot with role
        // 'owner' so that policies (Budget, Expense, etc.) recognize them.
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_re_verification_does_not_create_duplicate_workspace(): void
    {
        $this->seed();
        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        $this->verifyEmail($user);

        $countBefore = Workspace::where('owner_id', $user->id)->count();

        // Dispatch the Verified event again manually to simulate re-verification
        event(new Verified($user));

        $countAfter = Workspace::where('owner_id', $user->id)->count();
        $this->assertEquals($countBefore, $countAfter);
    }
}
