<?php

namespace App\Http\Resources;

use App\ValueObjects\BulkOperationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BulkOperationResult */
class BulkOperationResultResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        BulkOperationResult $resource,
        private readonly string $processedIdsKey,
        private readonly string $blockedIdsKey,
    ) {
        parent::__construct($resource);
    }

    public static function forCategorySharing(BulkOperationResult $result): self
    {
        return new self($result, 'processed_category_ids', 'blocked_category_ids');
    }

    public static function forPaymentMethodLinks(BulkOperationResult $result): self
    {
        return new self($result, 'processed_method_ids', 'blocked_method_ids');
    }

    public function toArray(Request $request): array
    {
        return [
            'operation' => $this->operation,
            'total' => $this->total,
            'processed' => $this->processed,
            'blocked' => $this->blocked,
            $this->processedIdsKey => $this->processedIds,
            $this->blockedIdsKey => $this->blockedIds,
        ];
    }
}
