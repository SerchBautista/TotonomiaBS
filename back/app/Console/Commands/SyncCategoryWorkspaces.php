<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SyncCategoryWorkspaces extends Command
{
    protected $signature = 'categories:sync-workspaces
                            {--user= : Sync only for a specific user ID}
                            {--dry-run : Show what would be synced without making changes}
                            {--apply : Apply changes (by default the command is dry-run)}';

    protected $description = 'Link existing categories to the workspaces owned by their creator.';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $dryRun = ! $apply || $this->option('dry-run');
        $userId = $this->option('user');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        } else {
            $this->info('Applying changes...');
        }

        $query = User::with(['categories.workspaces', 'ownedWorkspaces'])
            ->whereHas('categories')
            ->whereHas('ownedWorkspaces');

        if ($userId) {
            $query->where('id', $userId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users with categories and owned workspaces found.');

            return self::SUCCESS;
        }

        $totalLinked = 0;
        $totalSkipped = 0;

        foreach ($users as $user) {
            $ownedWorkspaceIds = $user->ownedWorkspaces->pluck('id');

            foreach ($user->categories as $category) {
                $existingWorkspaceIds = $category->workspaces->pluck('id');
                $missing = $ownedWorkspaceIds->diff($existingWorkspaceIds);

                if ($missing->isEmpty()) {
                    $totalSkipped += $ownedWorkspaceIds->count();

                    continue;
                }

                $this->line(sprintf(
                    '  User [%s] — category "%s" → linking to %d workspace(s): %s',
                    $user->email,
                    $category->name,
                    $missing->count(),
                    $missing->implode(', ')
                ));

                if (! $dryRun) {
                    $category->workspaces()->syncWithoutDetaching($missing->all());
                }

                $totalLinked += $missing->count();
                $totalSkipped += $existingWorkspaceIds->intersect($ownedWorkspaceIds)->count();
            }
        }

        $this->newLine();
        $this->table(
            ['Linked', 'Already existed (skipped)'],
            [[$totalLinked, $totalSkipped]]
        );

        if ($dryRun) {
            $this->warn('DRY RUN — run with --apply to apply changes.');
        } else {
            $this->info('Sync complete.');
        }

        return self::SUCCESS;
    }
}
