<?php

namespace App\Http\Controllers\Api;

use App\Actions\NotifySharedWorkspaceMembersOfExpenseAction;
use App\Contracts\PayOccurrenceActionInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Occurrence\PayOccurrenceRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\OccurrenceResource;
use App\Models\FixedExpenseOccurrence;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OccurrenceController extends Controller
{
    public function __construct(
        private readonly PayOccurrenceActionInterface $payOccurrenceAction,
        private readonly NotifySharedWorkspaceMembersOfExpenseAction $notifyMembers,
    ) {}

    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('view', $workspace);

        $occurrences = FixedExpenseOccurrence::whereHas(
            'fixedExpense',
            fn ($q) => $q->where('workspace_id', $workspace->id)
        )
            ->whereIn('status', ['pending', 'overdue'])
            ->with(['fixedExpense.category', 'fixedExpense.paymentInstrument'])
            ->orderBy('due_date')
            ->get();

        return OccurrenceResource::collection($occurrences);
    }

    public function pay(PayOccurrenceRequest $request, FixedExpenseOccurrence $occurrence): JsonResponse
    {
        $this->authorize('pay', $occurrence);

        $expense = $this->payOccurrenceAction->execute(
            $request->user(),
            $occurrence,
            $request->validated()
        );

        $this->notifyMembers->execute($expense);

        return (new ExpenseResource($expense))
            ->response()
            ->setStatusCode(201);
    }
}
