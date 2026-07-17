<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserPreferencesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfilePreferencesRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: '/user/profile',
        tags: ['Profile'],
        summary: 'Regular profile endpoint',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Profile loaded'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'message' => __('api.profile.loaded'),
            'data' => [
                'user' => new UserResource($request->user()),
            ],
        ]);
    }

    #[OA\Put(
        path: '/user/profile',
        tags: ['Profile'],
        summary: 'Update user preferences',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Preferences updated'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function update(UpdateProfilePreferencesRequest $request, UpdateUserPreferencesAction $action): JsonResponse
    {
        $user = $action->execute(
            $request->user(),
            $request->only(['theme', 'locale', 'timezone'])
        );

        return response()->json([
            'message' => __('api.profile.updated'),
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
