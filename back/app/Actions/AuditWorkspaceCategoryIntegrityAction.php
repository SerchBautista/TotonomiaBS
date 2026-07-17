<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

class AuditWorkspaceCategoryIntegrityAction
{
    /**
     * @return array{
     *   findings: array<int, array<string, string|null>>,
     *   totals: array<string, int>,
     *   by_source: array<string, int>,
     *   by_workspace_type: array<string, int>
     * }
     */
    public function execute(?string $workspaceId = null, ?int $limit = null): array
    {
        $findings = [];
        $remaining = $limit;

        foreach ($this->sourceConfigs() as $config) {
            if ($remaining !== null && $remaining <= 0) {
                break;
            }

            $query = $this->buildSourceQuery(
                table: $config['table'],
                categoryColumn: $config['category_column'],
                expectedUserColumn: $config['expected_user_column'],
                sourceLabel: $config['source_label'],
                hasDeletedAt: $config['has_deleted_at'],
                workspaceId: $workspaceId,
            );

            if ($remaining !== null) {
                $query->limit($remaining);
            }

            $rows = $query->get();

            foreach ($rows as $row) {
                $workspaceType = $row->workspace_type === 'personal' ? 'personal' : 'shared';
                $sharedOwnershipMatches = $workspaceType === 'shared'
                    && $row->category_owner_id !== null
                    && $row->workspace_owner_id !== null
                    && $row->category_owner_id === $row->workspace_owner_id;

                $findings[] = [
                    'source' => $row->source,
                    'record_id' => $row->record_id,
                    'workspace_id' => $row->workspace_id,
                    'workspace_type' => $workspaceType,
                    'category_id' => $row->category_id,
                    'category_owner_id' => $row->category_owner_id,
                    'workspace_owner_id' => $row->workspace_owner_id,
                    'expected_user_id' => $row->expected_user_id,
                    'issue_type' => $workspaceType === 'personal'
                        ? 'personal_category_owner_mismatch'
                        : ($sharedOwnershipMatches ? 'shared_category_not_linked' : 'shared_category_owner_mismatch'),
                    'fix_strategy' => $workspaceType === 'personal'
                        ? 'requires_manual_review'
                        : ($sharedOwnershipMatches ? 'attach_category_to_workspace' : 'requires_manual_review'),
                ];
            }

            if ($remaining !== null) {
                $remaining -= $rows->count();
            }
        }

        $bySource = [];
        $byWorkspaceType = ['personal' => 0, 'shared' => 0];
        $autofixable = 0;
        $manual = 0;

        foreach ($findings as $finding) {
            $source = $finding['source'] ?? 'unknown';
            $workspaceType = $finding['workspace_type'] ?? 'shared';

            $bySource[$source] = ($bySource[$source] ?? 0) + 1;
            $byWorkspaceType[$workspaceType] = ($byWorkspaceType[$workspaceType] ?? 0) + 1;

            if (($finding['fix_strategy'] ?? null) === 'attach_category_to_workspace') {
                $autofixable++;
            } else {
                $manual++;
            }
        }

        return [
            'findings' => $findings,
            'totals' => [
                'inconsistencies' => count($findings),
                'autofixable' => $autofixable,
                'requires_manual_review' => $manual,
            ],
            'by_source' => $bySource,
            'by_workspace_type' => $byWorkspaceType,
        ];
    }

    /**
     * @return array<int, array{table: string, category_column: string, expected_user_column: string, source_label: string, has_deleted_at: bool}>
     */
    private function sourceConfigs(): array
    {
        return [
            [
                'table' => 'expenses',
                'category_column' => 'category_id',
                'expected_user_column' => 'expenses.user_id',
                'source_label' => 'expenses.category_id',
                'has_deleted_at' => true,
            ],
            [
                'table' => 'budgets',
                'category_column' => 'category_id',
                'expected_user_column' => 'w.owner_id',
                'source_label' => 'budgets.category_id',
                'has_deleted_at' => true,
            ],
            [
                'table' => 'fixed_expenses',
                'category_column' => 'category_id',
                'expected_user_column' => 'fixed_expenses.user_id',
                'source_label' => 'fixed_expenses.category_id',
                'has_deleted_at' => true,
            ],
            [
                'table' => 'budget_adjustments',
                'category_column' => 'from_category_id',
                'expected_user_column' => 'budget_adjustments.user_id',
                'source_label' => 'budget_adjustments.from_category_id',
                'has_deleted_at' => false,
            ],
            [
                'table' => 'budget_adjustments',
                'category_column' => 'to_category_id',
                'expected_user_column' => 'budget_adjustments.user_id',
                'source_label' => 'budget_adjustments.to_category_id',
                'has_deleted_at' => false,
            ],
        ];
    }

    private function buildSourceQuery(
        string $table,
        string $categoryColumn,
        string $expectedUserColumn,
        string $sourceLabel,
        bool $hasDeletedAt,
        ?string $workspaceId,
    ) {
        $query = DB::table($table)
            ->join('workspaces as w', 'w.id', '=', $table.'.workspace_id')
            ->join('categories as c', 'c.id', '=', $table.'.'.$categoryColumn)
            ->leftJoin('category_workspace as cw', function ($join) use ($table, $categoryColumn): void {
                $join->on('cw.category_id', '=', $table.'.'.$categoryColumn)
                    ->on('cw.workspace_id', '=', $table.'.workspace_id');
            })
            ->whereNotNull($table.'.'.$categoryColumn)
            ->where(function ($query) use ($expectedUserColumn): void {
                $query->where(function ($personal) use ($expectedUserColumn): void {
                    $personal->where('w.type', 'personal')
                        ->whereColumn('c.user_id', '<>', $expectedUserColumn);
                })->orWhere(function ($shared): void {
                    $shared->where(function ($workspaceType): void {
                        $workspaceType->where('w.type', '<>', 'personal')
                            ->orWhereNull('w.type');
                    })->whereNull('cw.category_id');
                });
            })
            ->select([
                $table.'.id as record_id',
                $table.'.workspace_id',
                $table.'.'.$categoryColumn.' as category_id',
                'w.type as workspace_type',
                'w.owner_id as workspace_owner_id',
                'c.user_id as category_owner_id',
                DB::raw($expectedUserColumn.' as expected_user_id'),
                DB::raw("'{$sourceLabel}' as source"),
            ]);

        if ($workspaceId !== null && $workspaceId !== '') {
            $query->where($table.'.workspace_id', $workspaceId);
        }

        if ($hasDeletedAt) {
            $query->whereNull($table.'.deleted_at');
        }

        return $query->orderBy($table.'.id');
    }
}
