<?php

namespace App\ValueObjects;

readonly class BulkOperationResult
{
    /**
     * @param  array<int, string>  $processedIds
     * @param  array<int, string>  $blockedIds
     */
    public function __construct(
        public string $operation,
        public int $total,
        public int $processed,
        public int $blocked,
        public array $processedIds,
        public array $blockedIds,
    ) {}
}
