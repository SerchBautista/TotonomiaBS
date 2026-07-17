<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateTwoFactorCodeAction;
use App\Actions\RevokePushDeviceAction;
use App\Contracts\AuthenticatorInterface;
use App\Exceptions\ApiAuthorizationException;
use App\Exceptions\DomainRateLimitException;
use App\Exceptions\DomainValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticatorInterface $authenticator,
        private readonly RevokePushDeviceAction $revokePushDeviceAction,
        private readonly GenerateTwoFactorCodeAction $generateTwoFactorCodeAction,
    ) {}

    #[OA\Post(
        path: '/api/v1/auth/user/login',
        tags: ['Auth'],
        summary: 'Login user or admin and receive API token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(
                        property: 'email',
                        type: 'string',
                        format: 'email',
                        example: 'user@example.com'
                    ),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login success',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(property: 'token', type: 'string', example: '1|laravel_passport_access_token'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                                new OA\Property(property: 'role', type: 'string', example: 'user', description: "'user' or 'admin' (admin users can also authenticate via this endpoint)"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation or domain validation error',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 422,
                            'code' => 'invalid_credentials',
                            'message' => 'Invalid credentials.',
                            'request_id' => 'req-auth-login-422',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden login state (for example, unverified email)',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 403,
                            'code' => 'email_not_verified',
                            'message' => 'Your email address is not verified. Please check your inbox.',
                            'request_id' => 'req-auth-login-403',
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

        if (! $user->hasVerifiedEmail()) {
            throw new ApiAuthorizationException(
                'email_not_verified',
                __('api.auth.email_not_verified'),
            );
        }

        // If 2FA is enabled, generate code and return without emitting token
        if ($user->two_factor_enabled) {
            // H1 fix: check for existing locked session before generating a new one
            $lockedSession = $user->twoFactorSessions()
                ->whereNotNull('locked_until')
                ->where('locked_until', '>', now())
                ->first();

            if ($lockedSession) {
                $retryAfter = (int) now()->diffInSeconds($lockedSession->locked_until, false);

                throw new DomainRateLimitException(
                    'two_factor_locked',
                    __('api.auth.two_factor_locked'),
                    ['retry_after' => $retryAfter],
                );
            }

            $session = $this->generateTwoFactorCodeAction->execute($user);

            return response()->json([
                'two_factor_required' => true,
                'session_token' => $session->token,
                'message' => __('api.auth.two_factor_required'),
            ]);
        }

        $token = $user->createToken('api-token')->accessToken;

        return response()->json([
            'message' => __('api.auth.login_success'),
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/auth/me',
        tags: ['Auth'],
        summary: 'Get authenticated user',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Profile loaded'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                                new OA\Property(property: 'role', type: 'string', example: 'user'),
                                new OA\Property(property: 'plan', type: 'string', example: 'free'),
                                new OA\Property(property: 'default_workspace_id', type: 'integer', nullable: true, example: null),
                                new OA\Property(property: 'theme', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'locale', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'timezone', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'two_factor_enabled', type: 'boolean', example: false),
                                new OA\Property(
                                    property: 'permissions',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    example: ['profile.view', 'profile.update', 'two-factor.update', 'files.upload'],
                                    description: 'List of permission names granted to the user. Empty array if no permissions.'
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'message' => __('api.auth.profile_loaded'),
            'user' => new UserResource($request->user()),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        tags: ['Auth'],
        summary: 'Logout current token and optionally revoke a push device',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'installation_id',
                        description: 'Optional push device installation ID to revoke on logout',
                        type: 'string',
                        example: 'abc123-installation-id'
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Logged out')]
    )]
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Revoke the push device if installation_id is provided
        $installationId = $request->input('installation_id');
        if ($installationId !== null) {
            $device = PushDevice::where('user_id', $user->id)
                ->where('installation_id', $installationId)
                ->first();

            if ($device) {
                $this->revokePushDeviceAction->execute($device);
            }
        }

        $user->token()?->revoke();

        return response()->json([
            'message' => __('api.auth.logout_success'),
        ]);
    }
}
