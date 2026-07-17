<?php

namespace App\Http\Controllers\Api;

use App\Contracts\BudgetStatusActionInterface;
use App\Contracts\DestroyBudgetActionInterface;
use App\Contracts\StoreBudgetActionInterface;
use App\Contracts\UpdateBudgetActionInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\StoreBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BudgetController extends Controller
{
    public function __construct(
        private readonly StoreBudgetActionInterface $storeBudget,
        private readonly UpdateBudgetActionInterface $updateBudget,
        private readonly DestroyBudgetActionInterface $destroyBudget,
        private readonly BudgetStatusActionInterface $budgetStatus,
    ) {}

    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Budget::class, $workspace]);

        $budgets = $workspace->budgets()
            ->with('category')
            ->orderByDesc('effective_from')
            ->paginate(30);

        return BudgetResource::collection($budgets);
    }

    public function store(StoreBudgetRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('create', [Budget::class, $workspace]);

        $budget = $this->storeBudget->execute($workspace, $request->validated());

        return (new BudgetResource($budget))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace, Budget $budget): BudgetResource
    {
        $this->authorize('view', $budget);

        $budget->load('category');

        return new BudgetResource($budget);
    }

    public function update(UpdateBudgetRequest $request, Workspace $workspace, Budget $budget): BudgetResource
    {
        $this->authorize('update', $budget);

        return new BudgetResource($this->updateBudget->execute($budget, $request->validated()));
    }

    public function destroy(Workspace $workspace, Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $this->destroyBudget->execute($budget);

        return response()->json(null, 204);
    }

    public function status(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [Budget::class, $workspace]);

        $month = $request->filled('month')
            ? Carbon::parse($request->input('month'))
            : Carbon::now();

        return response()->json([
            'data' => $this->budgetStatus->execute($workspace, $month),
        ]);
    }
}
