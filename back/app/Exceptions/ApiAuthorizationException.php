<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

final class ApiAuthorizationException extends AuthorizationException
{
    public function __construct(
        private readonly string $errorCode,
        ?string $message = null,
    ) {
        parent::__construct($message, 0);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
