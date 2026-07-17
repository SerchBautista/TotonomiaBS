<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiFieldErrors',
    type: 'object',
    additionalProperties: new OA\AdditionalProperties(
        type: 'array',
        items: new OA\Items(type: 'string')
    ),
    example: [
        'email' => ['The email field is required.'],
        'users.0.email' => ['The users.0.email must be a valid email address.'],
    ]
)]
class ApiFieldErrorsSchema {}
