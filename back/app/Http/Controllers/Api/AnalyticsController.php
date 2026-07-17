<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AnalyticsHeatmapActionInterface;
use App\Contracts\AnalyticsMemberSplitActionInterface;
use App\Contracts\AnalyticsProjectionActionInterface;
use App\Contracts\AnalyticsSummaryActionInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsHeatmapRequest;
use App\Http\Requests\Analytics\AnalyticsSummaryRequest;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsSummaryActionInterface $summary,
        private readonly AnalyticsHeatmapActionInterface $heatmap,
        private readonly AnalyticsProjectionActionInterface $projection,
        private readonly AnalyticsMemberSplitActionInterface $memberSplit,
    ) {}

    public function summary(AnalyticsSummaryRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $validated = $request->validated();
        $from = $validated['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? Carbon::now()->toDateString();

        return response()->json([
            'data' => $this->summary->summary($workspace, $from, $to),
        ]);
    }

    public function heatmap(AnalyticsHeatmapRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $validated = $request->validated();
        $year = (int) ($validated['year'] ?? Carbon::now()->year);
        $month = (int) ($validated['month'] ?? Carbon::now()->month);

        return response()->json([
            'data' => $this->heatmap->heatmap($workspace, $year, $month),
        ]);
    }

    public function projection(Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json([
            'data' => $this->projection->projection($workspace),
        ]);
    }

    public function memberSplit(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $year = (int) ($request->query('year') ?? Carbon::now()->year);
        $month = (int) ($request->query('month') ?? Carbon::now()->month);

        return response()->json([
            'data' => $this->memberSplit->memberSplit($workspace, $year, $month),
        ]);
    }
}
