<?php

namespace App\Console\Commands;

use App\Actions\DedupeUserRolesAction;
use Illuminate\Console\Command;

class DedupeUserRoles extends Command
{
    protected $signature = 'users:dedupe-roles
                            {--user= : Process only this user ID}
                            {--apply : Persist changes. Without this flag, runs in dry-run mode}';

    protected $description = 'Removes the redundant `user` role from accounts that already have `admin` (fix for misclassified admin login).';

    public function handle(DedupeUserRolesAction $action): int
    {
        $userId = $this->option('user');
        $apply = (bool) $this->option('apply');

        if (! $apply) {
            $this->warn('DRY RUN — no changes will be made. Use --apply to persist.');
        }

        $report = $action->execute($userId, $apply);

        if ($report === []) {
            $this->info('No users with both `admin` and `user` roles found.');

            return self::SUCCESS;
        }

        $rows = array_map(
            fn (array $entry): array => [
                'user_id' => $entry['user_id'],
                'email' => $entry['email'],
                'action' => $apply ? ($entry['removed'] ? 'removed' : 'skipped') : 'would_remove',
            ],
            $report,
        );

        $this->table(['user_id', 'email', 'action'], $rows);

        return self::SUCCESS;
    }
}
