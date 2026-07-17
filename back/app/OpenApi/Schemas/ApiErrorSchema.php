<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiError',
    type: 'object',
    required: ['status', 'code', 'message'],
    properties: [
        new OA\Property(property: 'status', type: 'integer', example: 403),
        new OA\Property(property: 'code', type: 'string', example: 'forbidden'),
        new OA\Property(property: 'message', type: 'string', example: 'You do not have permission to access this resource.'),
        new OA\Property(
            property: 'request_id',
            type: 'string',
            nullable: true,
            description: 'Echoes the sanitized X-Request-Id header when provided, or a generated identifier for API requests.',
            example: 'req-budget-adjustment-insufficient-funds-422'
        ),
        new OA\Property(
            property: 'meta',
            ref: '#/components/schemas/ApiErrorMeta',
            nullable: true,
            description: 'Optional domain-specific metadata returned by some business errors.'
        ),
        new OA\Property(
            property: 'fieldErrors',
            ref: '#/components/schemas/ApiFieldErrors',
            nullable: true,
            description: 'Present on validation failures and keyed by request field path.'
        ),
    ]
)]
class ApiErrorSchema {}
