<?php

namespace App\Actions;

use App\Contracts\AnalyticsHeatmapActionInterface;
use App\Contracts\AnalyticsMemberSplitActionInterface;
use App\Contracts\AnalyticsProjectionActionInterface;
use App\Contracts\AnalyticsSummaryActionInterface;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsAction implements AnalyticsHeatmapActionInterface, AnalyticsMemberSplitActionInterface, AnalyticsProjectionActionInterface, AnalyticsSummaryActionInterface
{
    /**
     * Get spending summary for a workspace within a date range.
     *
     * @return array{
     *   total: string,
     *   period: array{from: string, to: string},
     *   by_category: list<array{id: string, name: string, icon: string|null, color: string|null, total: string, count: int}>,
     *   by_payment_method: list<array{id: string, name: string, type: string, total: string, count: int}>,
     * }
     */
    public function summary(Workspace $workspace, string $from, string $to): array
    {
        $base = $workspace->expenses()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to);

        $total = (string) ($base->sum('amount') ?: '0.00');

        $byCategory = (clone $base)
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                'categories.icon',
                'categories.color',
                DB::raw('SUM(expenses.amount) as total'),
                DB::raw('COUNT(expenses.id) as count')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'icon' => $row->icon,
                'color' => $row->color,
                'total' => number_format((float) $row->total, 2, '.', ''),
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $byPaymentMethod = (clone $base)
            ->leftJoin('cards', function ($join) {
                $join->on('expenses.payment_instrument_id', '=', 'cards.id')
                    ->where('expenses.payment_type', '=', 'card');
            })
            ->leftJoin('other_payment_methods', function ($join) {
                $join->on('expenses.payment_instrument_id', '=', 'other_payment_methods.id')
                    ->where('expenses.payment_type', '=', 'other');
            })
            ->select(
                'expenses.payment_type',
                'expenses.payment_instrument_id',
                DB::raw("COALESCE(cards.name, other_payment_methods.name, 'Efectivo') as instrument_name"),
                DB::raw('SUM(expenses.amount) as total'),
                DB::raw('COUNT(expenses.id) as count')
            )
            ->groupBy('expenses.payment_type', 'expenses.payment_instrument_id', 'instrument_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->payment_instrument_id,
                'name' => $row->instrument_name,
                'type' => $row->payment_type,
                'total' => number_format((float) $row->total, 2, '.', ''),
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        return [
            'total' => number_format((float) $total, 2, '.', ''),
            'period' => ['from' => $from, 'to' => $to],
            'by_category' => $byCategory,
            'by_payment_method' => $byPaymentMethod,
        ];
    }

    /**
     * Get daily spending totals for a given month (calendar heatmap).
     *
     * @return list<array{date: string, total: string, count: int}>
     */
    public function heatmap(Workspace $workspace, int $year, int $month): array
    {
        $from = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth()->endOfDay();

        return $workspace->expenses()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->select(
                DB::raw('DATE(date) as day'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->day,
                'total' => number_format((float) $row->total, 2, '.', ''),
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * Get projected monthly spending based on the current month's average daily spend.
     *
     * @return array{
     *   current_month_total: string,
     *   days_elapsed: int,
     *   days_in_month: int,
     *   daily_average: string,
     *   projected_total: string,
     * }
     */
    public function projection(Workspace $workspace): array
    {
        $now = Carbon::now();
        $from = $now->copy()->startOfMonth()->toDateString();
        $to = $now->toDateString();
        $daysInMonth = (int) $now->daysInMonth;
        $daysElapsed = (int) $now->day;

        $currentTotal = (float) $workspace->expenses()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->sum('amount');

        $dailyAverage = $daysElapsed > 0 ? $currentTotal / $daysElapsed : 0.0;
        $projectedTotal = $dailyAverage * $daysInMonth;

        return [
            'current_month_total' => number_format($currentTotal, 2, '.', ''),
            'days_elapsed' => $daysElapsed,
            'days_in_month' => $daysInMonth,
            'daily_average' => number_format($dailyAverage, 2, '.', ''),
            'projected_total' => number_format($projectedTotal, 2, '.', ''),
        ];
    }

    /**
     * Get expense split between workspace members for a given month.
     */
    public function memberSplit(Workspace $workspace, int $year, int $month): array
    {
        $from = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $to = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        // Load owner + members
        $workspace->load(['owner', 'members']);

        $memberMap = collect();

        if ($workspace->owner) {
            $memberMap->put($workspace->owner->id, [
                'id' => $workspace->owner->id,
                'name' => $workspace->owner->name,
                'paid' => 0.0,
            ]);
        }

        foreach ($workspace->members as $member) {
            if (! $memberMap->has($member->id)) {
                $memberMap->put($member->id, [
                    'id' => $member->id,
                    'name' => $member->name,
                    'paid' => 0.0,
                ]);
            }
        }

        $memberCount = $memberMap->count();

        // Sum expenses per payer (paid_by_user_id when set, otherwise user_id)
        $rows = $workspace->expenses()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->select(
                DB::raw('COALESCE(paid_by_user_id, user_id) as payer_id'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('payer_id')
            ->get();

        foreach ($rows as $row) {
            if ($memberMap->has($row->payer_id)) {
                $entry = $memberMap->get($row->payer_id);
                $entry['paid'] = (float) $row->total;
                $memberMap->put($row->payer_id, $entry);
            }
        }

        $grandTotal = $memberMap->sum('paid');
        $fairShare = $memberCount > 0 ? $grandTotal / $memberCount : 0.0;

        // Calculate balance (positive = owed to them, negative = they owe others)
        $members = $memberMap->values()->map(function ($m) use ($fairShare) {
            $balance = $m['paid'] - $fairShare;

            return [
                'id' => $m['id'],
                'name' => $m['name'],
                'paid' => number_format($m['paid'], 2, '.', ''),
                'balance' => number_format($balance, 2, '.', ''),
            ];
        })->all();

        // Compute settlements using greedy algorithm
        $creditors = $memberMap->values()
            ->map(fn ($m) => ['id' => $m['id'], 'name' => $m['name'], 'balance' => round($m['paid'] - $fairShare, 2)])
            ->filter(fn ($m) => $m['balance'] > 0.005)
            ->sortByDesc('balance')
            ->values()
            ->toArray();

        $debtors = $memberMap->values()
            ->map(fn ($m) => ['id' => $m['id'], 'name' => $m['name'], 'balance' => round($m['paid'] - $fairShare, 2)])
            ->filter(fn ($m) => $m['balance'] < -0.005)
            ->sortBy('balance')
            ->values()
            ->toArray();

        $settlements = [];
        $ci = 0;
        $di = 0;

        while ($ci < count($creditors) && $di < count($debtors)) {
            $credit = $creditors[$ci]['balance'];
            $debt = abs($debtors[$di]['balance']);
            $transfer = min($credit, $debt);

            $settlements[] = [
                'from_id' => $debtors[$di]['id'],
                'from_name' => $debtors[$di]['name'],
                'to_id' => $creditors[$ci]['id'],
                'to_name' => $creditors[$ci]['name'],
                'amount' => number_format($transfer, 2, '.', ''),
            ];

            $creditors[$ci]['balance'] -= $transfer;
            $debtors[$di]['balance'] += $transfer;

            if ($creditors[$ci]['balance'] < 0.005) {
                $ci++;
            }
            if (abs($debtors[$di]['balance']) < 0.005) {
                $di++;
            }
        }

        return [
            'month' => Carbon::createFromDate($year, $month, 1)->format('Y-m'),
            'total' => number_format($grandTotal, 2, '.', ''),
            'member_count' => $memberCount,
            'fair_share' => number_format($fairShare, 2, '.', ''),
            'members' => $members,
            'settlements' => $settlements,
        ];
    }
}
