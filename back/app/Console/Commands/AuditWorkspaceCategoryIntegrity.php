<?php

namespace App\Console\Commands;

use App\Actions\AuditWorkspaceCategoryIntegrityAction;
use Illuminate\Console\Command;

class AuditWorkspaceCategoryIntegrity extends Command
{
    protected $signature = 'categories:audit-workspace-integrity
                            {--workspace= : Filter by workspace ID}
                            {--limit= : Maximum inconsistencies to inspect}';

    protected $description = 'Read-only audit for category inconsistencies by workspace rules.';

    public function handle(AuditWorkspaceCategoryIntegrityAction $action): int
    {
        $workspaceId = $this->option('workspace');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($limit !== null && $limit <= 0) {
            $this->error('The --limit option must be greater than zero.');

            return self::INVALID;
        }

        $this->info('Running category integrity audit (read-only)...');

        $result = $action->execute($workspaceId ?: null, $limit);

        $this->newLine();
        $this->table(
            ['Total', 'Autofixable', 'Requires manual review'],
            [[
                $result['totals']['inconsistencies'],
                $result['totals']['autofixable'],
                $result['totals']['requires_manual_review'],
            ]]
        );

        $this->newLine();
        $this->line('By workspace type:');
        $this->table(
            ['Personal', 'Shared'],
            [[
                $result['by_workspace_type']['personal'] ?? 0,
                $result['by_workspace_type']['shared'] ?? 0,
            ]]
        );

        if ($result['totals']['inconsistencies'] === 0) {
            $this->info('No inconsistencies found.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Detailed inconsistencies:');
        $this->table(
            ['Source', 'Record ID', 'Workspace', 'Type', 'Category', 'Issue', 'Fix'],
            collect($result['findings'])->map(fn (array $finding): array => [
                $finding['source'],
                $finding['record_id'],
                $finding['workspace_id'],
                $finding['workspace_type'],
                $finding['category_id'],
                $finding['issue_type'],
                $finding['fix_strategy'],
            ])->all()
        );

        return self::SUCCESS;
    }
}
