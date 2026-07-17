<?php

namespace App\Exceptions;

use RuntimeException;

final class DomainValidationException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $fieldErrors
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly array $meta = [],
        private readonly array $fieldErrors = [],
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

    /**
     * @return array<string, array<int, string>>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
