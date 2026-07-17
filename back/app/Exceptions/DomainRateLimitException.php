<?php

namespace App\Exceptions;

use RuntimeException;

final class DomainRateLimitException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly array $meta = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
