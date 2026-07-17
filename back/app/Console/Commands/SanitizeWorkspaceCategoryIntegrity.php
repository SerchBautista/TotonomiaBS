<?php

namespace App\Console\Commands;

use App\Actions\AuditWorkspaceCategoryIntegrityAction;
use App\Actions\SanitizeWorkspaceCategoryIntegrityAction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SanitizeWorkspaceCategoryIntegrity extends Command
{
    protected $signature = 'categories:sanitize-workspace-integrity
                            {--apply : Apply safe fixes. Without this flag, runs in dry-run mode}
                            {--workspace= : Filter by workspace ID}
                            {--limit= : Maximum inconsistencies to inspect}';

    protected $description = 'Controlled sanitization for category inconsistencies by workspace.';

    public function handle(
        AuditWorkspaceCategoryIntegrityAction $auditAction,
        SanitizeWorkspaceCategoryIntegrityAction $sanitizeAction,
    ): int {
        $workspaceId = $this->option('workspace');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $apply = (bool) $this->option('apply');

        if ($limit !== null && $limit <= 0) {
            $this->error('The --limit option must be greater than zero.');

            return self::INVALID;
        }

        if (! $apply) {
            $this->warn('DRY RUN — no changes will be made. Use --apply to persist safe fixes.');
        }

        $auditResult = $auditAction->execute($workspaceId ?: null, $limit);
        $findings = collect($auditResult['findings']);

        if ($findings->isEmpty()) {
            $this->info('No inconsistencies found. Nothing to sanitize.');

            return self::SUCCESS;
        }

        $sanitization = $sanitizeAction->execute($findings, $apply);

        $this->newLine();
        $this->table(
            ['Scanned', 'Changed', 'Unchanged', 'Requires manual review'],
            [[
                $sanitization['scanned'],
                $sanitization['changed'],
                $sanitization['unchanged'],
                $sanitization['requires_manual_review'],
            ]]
        );

        $this->renderFixPreview($sanitization['touched_pairs'], $apply);
        $this->renderManualReviewItems($findings);

        if (! $apply) {
            $this->warn('DRY RUN complete — run with --apply to execute safe fixes.');
        } elseif ($sanitization['requires_manual_review'] > 0) {
            $this->warn('Applied safe fixes only. Some records require manual review.');
        } else {
            $this->info('Sanitization complete.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{workspace_id: string, category_id: string}>  $pairs
     */
    private function renderFixPreview(array $pairs, bool $apply): void
    {
        if ($pairs === []) {
            return;
        }

        $this->newLine();
        $this->line($apply ? 'Safe fixes processed:' : 'Safe fixes planned (dry-run):');
        $this->table(
            ['Workspace ID', 'Category ID'],
            collect($pairs)->map(fn (array $pair): array => [$pair['workspace_id'], $pair['category_id']])->all()
        );
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $findings
     */
    private function renderManualReviewItems(Collection $findings): void
    {
        $manual = $findings
            ->filter(fn (array $finding): bool => ($finding['fix_strategy'] ?? null) === 'requires_manual_review')
            ->values();

        if ($manual->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn('Requires manual review:');
        $this->table(
            ['Source', 'Record ID', 'Workspace', 'Type', 'Category', 'Issue'],
            $manual->map(fn (array $finding): array => [
                $finding['source'],
                $finding['record_id'],
                $finding['workspace_id'],
                $finding['workspace_type'],
                $finding['category_id'],
                $finding['issue_type'],
            ])->all()
        );
    }
}
