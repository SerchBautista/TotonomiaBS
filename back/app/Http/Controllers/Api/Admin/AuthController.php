<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\AuthenticatorInterface;
use App\Exceptions\DomainValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticatorInterface $authenticator,
    ) {}

    #[OA\Post(
        path: '/api/v1/auth/admin/login',
        tags: ['Auth'],
        summary: 'Login admin user and receive API token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login success'),
            new OA\Response(
                response: 422,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 422,
                            'code' => 'invalid_credentials',
                            'message' => 'Invalid credentials.',
                            'request_id' => 'req-auth-admin-login-422',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = $this->authenticator->authenticate($credentials);
        if ($user === null) {
            throw new DomainValidationException(
                'invalid_credentials',
                __('api.auth.invalid_credentials'),
            );
        }

        $token = $user->createToken('api-token')->accessToken;

        return response()->json([
            'message' => __('api.auth.login_success'),
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }
}
