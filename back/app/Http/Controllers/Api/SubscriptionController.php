<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetSubscriptionStatusAction;
use App\Actions\InitiateCheckoutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\CheckoutSessionResource;
use App\Http\Resources\SubscriptionStatusResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SubscriptionController extends Controller
{
    #[OA\Post(
        path: '/api/v1/subscriptions/checkout',
        tags: ['Subscriptions'],
        summary: 'Create a checkout session for premium subscription',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Checkout session created',
                content: new OA\JsonContent(
                    type: 'object',
                    required: ['url', 'is_dummy'],
                    properties: [
                        new OA\Property(property: 'url', type: 'string', example: 'https://checkout.stripe.com/c/pay/cs_test_123'),
                        new OA\Property(property: 'is_dummy', type: 'boolean', example: false),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 401,
                            'code' => 'unauthenticated',
                            'message' => 'Authentication is required to access this resource.',
                            'request_id' => 'req-subscription-checkout-401',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Email not verified',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 403,
                            'code' => 'email_not_verified',
                            'message' => 'Your email address is not verified. Please check your inbox.',
                            'request_id' => 'req-subscription-checkout-403',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Subscription conflict',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 409,
                            'code' => 'subscription_already_active',
                            'message' => 'The user already has an active subscription.',
                            'request_id' => 'req-subscription-checkout-conflict-409',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function checkout(CheckoutRequest $request, InitiateCheckoutAction $action): CheckoutSessionResource
    {
        $session = $action->execute($request->user());

        return new CheckoutSessionResource($session);
    }

    #[OA\Get(
        path: '/api/v1/user/subscription',
        tags: ['Subscriptions'],
        summary: 'Get current user subscription status and recent payments',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Subscription status loaded',
                content: new OA\JsonContent(
                    type: 'object',
                    required: ['plan', 'subscription_ends_at', 'payments'],
                    properties: [
                        new OA\Property(property: 'plan', type: 'string', enum: ['free', 'premium'], example: 'free'),
                        new OA\Property(property: 'subscription_ends_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(
                            property: 'payments',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                required: ['date', 'amount', 'currency', 'status', 'gateway', 'invoice_url'],
                                properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-06-01'),
                                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 9.99),
                                    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                                    new OA\Property(property: 'status', type: 'string', example: 'paid'),
                                    new OA\Property(property: 'gateway', type: 'string', example: 'stripe'),
                                    new OA\Property(property: 'invoice_url', type: 'string', nullable: true),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 401,
                            'code' => 'unauthenticated',
                            'message' => 'Authentication is required to access this resource.',
                            'request_id' => 'req-subscription-status-401',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function show(Request $request, GetSubscriptionStatusAction $action): SubscriptionStatusResource
    {
        return new SubscriptionStatusResource($action->execute($request->user()));
    }
}
