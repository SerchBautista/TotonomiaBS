<?php

namespace App\Http\Controllers\Api;

use App\Actions\NotifySharedWorkspaceMembersOfExpenseAction;
use App\Contracts\CheckBudgetThresholdActionInterface;
use App\Contracts\RegisterExpenseActionInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\IndexExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function index(IndexExpenseRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Expense::class, $workspace]);

        $validated = $request->validated();

        $expenses = $workspace->expenses()
            ->with('category', 'paymentInstrument', 'user', 'paidBy')
            ->when(isset($validated['from']), fn ($q) => $q->whereDate('date', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($q) => $q->whereDate('date', '<=', $validated['to']))
            ->when(isset($validated['category_id']), fn ($q) => $q->where('category_id', $validated['category_id']))
            ->when(isset($validated['payment_type']), fn ($q) => $q->where('payment_type', $validated['payment_type']))
            ->when(isset($validated['search']), function ($q) use ($validated) {
                $likeOperator = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where('description', $likeOperator, '%'.$validated['search'].'%');
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(30);

        return ExpenseResource::collection($expenses);
    }

    public function store(
        StoreExpenseRequest $request,
        Workspace $workspace,
        RegisterExpenseActionInterface $action,
        CheckBudgetThresholdActionInterface $budgetCheck,
        NotifySharedWorkspaceMembersOfExpenseAction $notifyMembers,
    ): JsonResponse {
        $this->authorize('create', [Expense::class, $workspace]);

        $validated = $request->validated();
        $expense = $action->execute($request->user(), $workspace, $validated);

        $notifyMembers->execute($expense);

        $warnings = $budgetCheck->execute(
            $workspace,
            $validated['category_id'],
            $validated['date'],
        );

        $expense->load('category', 'paymentInstrument', 'user', 'paidBy');
        $payload = ExpenseResource::make($expense)->resolve($request);

        if (! empty($warnings)) {
            $payload['budget_warnings'] = $warnings;
        }

        return response()->json(['data' => $payload], 201);
    }

    public function show(Workspace $workspace, Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);

        $expense->load('category', 'paymentInstrument', 'user', 'paidBy');

        return new ExpenseResource($expense);
    }

    public function update(UpdateExpenseRequest $request, Workspace $workspace, Expense $expense): ExpenseResource
    {
        $this->authorize('update', $expense);

        $expense->update($request->validated());

        return new ExpenseResource($expense->fresh(['category', 'paymentInstrument', 'user', 'paidBy']));
    }

    public function destroy(Workspace $workspace, Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->json(null, 204);
    }

    public function total(IndexExpenseRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [Expense::class, $workspace]);

        $validated = $request->validated();

        $total = $workspace->expenses()
            ->when(isset($validated['from']), fn ($q) => $q->whereDate('date', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($q) => $q->whereDate('date', '<=', $validated['to']))
            ->when(isset($validated['category_id']), fn ($q) => $q->where('category_id', $validated['category_id']))
            ->when(isset($validated['payment_type']), fn ($q) => $q->where('payment_type', $validated['payment_type']))
            ->when(isset($validated['search']), function ($q) use ($validated) {
                $likeOperator = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where('description', $likeOperator, '%'.$validated['search'].'%');
            })
            ->sum('amount');

        return response()->json(['data' => ['total' => (string) ($total ?? '0')]]);
    }
}
