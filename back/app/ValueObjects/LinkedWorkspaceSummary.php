<?php

namespace App\ValueObjects;

readonly class LinkedWorkspaceSummary
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
