<?php

namespace App\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SanitizeWorkspaceCategoryIntegrityAction
{
    /**
     * @return array{
     *   scanned: int,
     *   changed: int,
     *   unchanged: int,
     *   requires_manual_review: int,
     *   dry_run: bool,
     *   touched_pairs: array<int, array{workspace_id: string, category_id: string}>
     * }
     */
    public function execute(Collection $findings, bool $apply = false): array
    {
        $pairs = $findings
            ->filter(fn (array $finding): bool => ($finding['fix_strategy'] ?? null) === 'attach_category_to_workspace')
            ->map(fn (array $finding): array => [
                'workspace_id' => (string) $finding['workspace_id'],
                'category_id' => (string) $finding['category_id'],
            ])
            ->unique(fn (array $pair): string => $pair['workspace_id'].'|'.$pair['category_id'])
            ->values();

        $manualCount = $findings
            ->filter(fn (array $finding): bool => ($finding['fix_strategy'] ?? null) !== 'attach_category_to_workspace')
            ->count();

        $changed = 0;

        foreach ($pairs as $pair) {
            if (! $apply) {
                continue;
            }

            $inserted = DB::table('category_workspace')->insertOrIgnore([
                'workspace_id' => $pair['workspace_id'],
                'category_id' => $pair['category_id'],
            ]);

            if ($inserted > 0) {
                $changed += (int) $inserted;
            }
        }

        $plannedChanges = $pairs->count();

        return [
            'scanned' => $findings->count(),
            'changed' => $apply ? $changed : 0,
            'unchanged' => $apply ? max(0, $plannedChanges - $changed) : 0,
            'requires_manual_review' => $manualCount,
            'dry_run' => ! $apply,
            'touched_pairs' => $pairs->all(),
        ];
    }
}
