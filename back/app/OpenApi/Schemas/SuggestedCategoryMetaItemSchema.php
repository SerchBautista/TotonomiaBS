<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SuggestedCategoryMetaItem',
    type: 'object',
    required: ['category_id', 'category_name', 'available'],
    properties: [
        new OA\Property(property: 'category_id', type: 'string', format: 'uuid', example: '72b6f5ad-03a5-4d32-9779-03a2eb7396c1'),
        new OA\Property(property: 'category_name', type: 'string', example: 'Savings'),
        new OA\Property(property: 'available', type: 'string', example: '120.00'),
    ]
)]
class SuggestedCategoryMetaItemSchema {}
