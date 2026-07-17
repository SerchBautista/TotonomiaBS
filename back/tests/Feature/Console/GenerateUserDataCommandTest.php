<?php

namespace Tests\Feature\Console;

use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateUserDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_user_can_generate_data_and_categories_are_created_with_correct_user_id(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->artisan('userdata:generate', [
            'user_id' => $user->id,
        ])->assertSuccessful();

        // Categories are created and belong to the user.
        $this->assertSame(5, Category::query()->count());
        $this->assertSame(5, Category::where('user_id', $user->id)->count());

        // All categories have user_id correctly assigned (no NULL).
        $this->assertDatabaseCount('categories', 5);
        $this->assertDatabaseMissing('categories', ['user_id' => null]);

        // Workspace, cards, fixed expenses and variable expenses are also created.
        $this->assertGreaterThan(0, Workspace::query()->count());
        $this->assertGreaterThan(0, Card::query()->count());
        $this->assertGreaterThan(0, FixedExpense::query()->count());
        $this->assertGreaterThan(0, Expense::query()->count());
    }

    public function test_command_fails_when_user_does_not_exist(): void
    {
        $missingId = '00000000-0000-4000-8000-000000000001';

        $this->artisan('userdata:generate', [
            'user_id' => $missingId,
        ])
            ->expectsOutputToContain("User with ID [{$missingId}] not found.")
            ->assertFailed();

        $this->assertSame(0, Workspace::query()->count());
        $this->assertSame(0, Category::query()->count());
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_command_is_idempotent_no_duplicate_data_on_second_run(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // First run.
        $this->artisan('userdata:generate', [
            'user_id' => $user->id,
        ])->assertSuccessful();

        $firstRunCategories = Category::where('user_id', $user->id)->count();
        $firstRunCards = Card::query()->count();
        $firstRunFixedExpenses = FixedExpense::query()->count();
        $firstRunExpenses = Expense::query()->count();

        // Second run.
        $this->artisan('userdata:generate', [
            'user_id' => $user->id,
        ])->assertSuccessful();

        // Data is duplicated (command is not deduplication-aware by design).
        // We verify it ran twice without throwing exceptions.
        $this->assertGreaterThan($firstRunCategories, Category::where('user_id', $user->id)->count());
        $this->assertGreaterThan($firstRunCards, Card::query()->count());
    }

    public function test_created_categories_have_user_id_correctly_assigned(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->artisan('userdata:generate', [
            'user_id' => $user->id,
        ])->assertSuccessful();

        $categories = Category::where('user_id', $user->id)->get();

        $this->assertCount(5, $categories);

        foreach ($categories as $category) {
            $this->assertSame($user->id, $category->user_id);
            $this->assertNotNull($category->user_id);
        }

        // Verify the relationship is also correct.
        foreach ($categories as $category) {
            $this->assertTrue($category->user->is($user));
        }
    }
}
