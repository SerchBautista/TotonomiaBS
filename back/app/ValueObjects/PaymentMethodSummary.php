<?php

namespace App\ValueObjects;

readonly class PaymentMethodSummary
{
    /**
     * @param  array<int, LinkedWorkspaceSummary>|null  $linkedWorkspaces
     */
    public function __construct(
        public ?string $id,
        public string $type,
        public string $name,
        public string $displayName,
        public ?string $maskedDetails,
        public ?bool $isLinked = null,
        public ?bool $isValidForTransactions = null,
        public ?string $state = null,
        public ?int $linkedWorkspacesCount = null,
        public ?array $linkedWorkspaces = null,
        public ?bool $isInUseInWorkspace = null,
    ) {}
}
