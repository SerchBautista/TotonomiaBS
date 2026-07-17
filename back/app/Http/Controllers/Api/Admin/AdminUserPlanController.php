<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\AssignUserPlanActionInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserPlanController extends Controller
{
    public function __construct(
        private readonly AssignUserPlanActionInterface $assignUserPlanAction,
    ) {}

    public function __invoke(StorePlanRequest $request, User $user): JsonResponse
    {
        $this->assignUserPlanAction->execute($user, $request->validated('plan'));

        return (new UserResource($user->fresh()))
            ->response()
            ->setStatusCode(200);
    }
}
