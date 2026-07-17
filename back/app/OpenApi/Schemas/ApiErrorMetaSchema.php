<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiErrorMeta',
    type: 'object',
    additionalProperties: true,
    properties: [
        new OA\Property(
            property: 'suggested_categories',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/SuggestedCategoryMetaItem')
        ),
    ],
    example: [
        'suggested_categories' => [
            [
                'category_id' => '72b6f5ad-03a5-4d32-9779-03a2eb7396c1',
                'category_name' => 'Savings',
                'available' => '120.00',
            ],
        ],
    ]
)]
class ApiErrorMetaSchema {}
