<?php

namespace App\Http\Controllers\Api;

use App\Contracts\RegisterUserActionInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegisterUserActionInterface $registerUserAction,
    ) {}

    public function __invoke(RegisterUserRequest $request): JsonResponse
    {
        $this->registerUserAction->execute($request->validated());

        return response()->json([
            'message' => __('api.auth.register_success'),
        ], 201);
    }
}
