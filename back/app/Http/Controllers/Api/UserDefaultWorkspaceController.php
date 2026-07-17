<?php

namespace App\Http\Controllers\Api;

use App\Actions\SetDefaultWorkspaceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\SetDefaultWorkspaceRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class UserDefaultWorkspaceController extends Controller
{
    public function __invoke(
        SetDefaultWorkspaceRequest $request,
        SetDefaultWorkspaceAction $action,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user = $action->execute($user, $request->validated('workspace_id'));

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
