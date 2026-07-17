<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResendTwoFactorCodeAction;
use App\Actions\ToggleTwoFactorAction;
use App\Actions\VerifyTwoFactorCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendTwoFactorRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Http\Requests\ToggleTwoFactorRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly VerifyTwoFactorCodeAction $verifyAction,
        private readonly ResendTwoFactorCodeAction $resendAction,
        private readonly ToggleTwoFactorAction $toggleAction,
    ) {}

    #[OA\Post(
        path: '/api/v1/auth/user/verify-2fa',
        tags: ['Auth'],
        summary: 'Verify 2FA OTP code and receive access token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['session_token', 'code'],
                properties: [
                    new OA\Property(property: 'session_token', type: 'string', example: 'uuid-string'),
                    new OA\Property(property: 'code', type: 'string', example: '123456'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Verification success — token + user returned',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(property: 'user', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid code, expired session, or invalid session token',
            ),
            new OA\Response(
                response: 429,
                description: 'Session locked due to too many attempts',
            ),
        ]
    )]
    public function verify(VerifyTwoFactorRequest $request): JsonResponse
    {
        $user = $this->verifyAction->execute(
            $request->input('session_token'),
            $request->input('code'),
        );

        $token = $user->createToken('api-token')->accessToken;

        return response()->json([
            'message' => __('api.auth.login_success'),
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/user/resend-2fa',
        tags: ['Auth'],
        summary: 'Resend 2FA OTP code (60s cooldown)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['session_token'],
                properties: [
                    new OA\Property(property: 'session_token', type: 'string', example: 'uuid-string'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Code resent successfully'),
            new OA\Response(response: 422, description: 'Invalid or expired session'),
            new OA\Response(response: 429, description: 'Cooldown not elapsed yet'),
        ]
    )]
    public function resend(ResendTwoFactorRequest $request): JsonResponse
    {
        $newSession = $this->resendAction->execute($request->input('session_token'));

        return response()->json([
            'message' => __('api.auth.two_factor_code_sent'),
            'session_token' => $newSession->token,
        ]);
    }

    #[OA\Put(
        path: '/api/v1/user/two-factor',
        tags: ['User'],
        summary: 'Toggle two-factor authentication on or off',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['enabled', 'password'],
                properties: [
                    new OA\Property(property: 'enabled', type: 'boolean', example: true),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '2FA toggled successfully'),
            new OA\Response(response: 422, description: 'Invalid password'),
            new OA\Response(response: 403, description: 'Forbidden — missing permission'),
        ]
    )]
    public function toggle(ToggleTwoFactorRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user = $this->toggleAction->execute(
            $user,
            (bool) $request->input('enabled'),
            $request->input('password'),
        );

        $message = $user->two_factor_enabled
            ? __('api.auth.two_factor_enabled')
            : __('api.auth.two_factor_disabled');

        return response()->json([
            'message' => $message,
            'data' => [
                'two_factor_enabled' => $user->two_factor_enabled,
            ],
        ]);
    }
}
