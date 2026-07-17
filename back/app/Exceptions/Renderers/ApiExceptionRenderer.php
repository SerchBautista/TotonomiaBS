<?php

namespace App\Exceptions\Renderers;

use App\Exceptions\ApiAuthorizationException;
use App\Exceptions\DomainConflictException;
use App\Exceptions\DomainNotFoundException;
use App\Exceptions\DomainRateLimitException;
use App\Exceptions\DomainValidationException;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    private const EMAIL_NOT_VERIFIED_EXCEPTION_MESSAGE = 'Your email address is not verified.';

    private const EMAIL_VERIFICATION_INVALID_EXCEPTION_MESSAGE = 'Invalid or expired verification link.';

    /** @var array<string, string> */
    private const SPECIAL_FORBIDDEN_CODES = [
        'email_not_verified' => 'api.auth.email_not_verified',
        'email_verification_invalid' => 'api.auth.email_verification_invalid',
    ];

    public function render(Throwable $throwable, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $throwable instanceof AuthenticationException => ApiErrorResponse::unauthenticated(),
            $throwable instanceof AuthorizationException => $this->renderAuthorizationException($throwable),
            $throwable instanceof DomainNotFoundException => ApiErrorResponse::notFound(
                $throwable->getMessage(),
                $throwable->errorCode(),
            ),
            $throwable instanceof DomainValidationException => ApiErrorResponse::unprocessableEntity(
                $throwable->getMessage(),
                $throwable->errorCode(),
                fieldErrors: $throwable->fieldErrors(),
                meta: $throwable->meta(),
            ),
            $throwable instanceof DomainConflictException => ApiErrorResponse::conflict(
                $throwable->getMessage(),
                $throwable->errorCode(),
            ),
            $throwable instanceof DomainRateLimitException => ApiErrorResponse::tooManyRequests(
                $throwable->getMessage(),
                $throwable->errorCode(),
                meta: $throwable->meta(),
            ),
            $throwable instanceof ModelNotFoundException,
            $throwable instanceof NotFoundHttpException => ApiErrorResponse::notFound(),
            $throwable instanceof ValidationException => ApiErrorResponse::validation($throwable->errors()),
            $throwable instanceof HttpExceptionInterface => $this->renderHttpException($throwable),
            default => ApiErrorResponse::serverError(),
        };
    }

    private function renderHttpException(HttpExceptionInterface $throwable): ?JsonResponse
    {
        return match ($throwable->getStatusCode()) {
            401 => ApiErrorResponse::unauthenticated(),
            403 => $this->renderForbiddenHttpException($throwable),
            404 => ApiErrorResponse::notFound(),
            429 => ApiErrorResponse::tooManyRequests(),
            422 => ApiErrorResponse::unprocessableEntity(),
            500 => ApiErrorResponse::serverError(),
            default => ApiErrorResponse::make($throwable->getStatusCode(), 'http_error'),
        };
    }

    private function renderAuthorizationException(AuthorizationException $throwable): JsonResponse
    {
        $code = $this->resolveForbiddenCode($throwable);
        $message = $this->resolveForbiddenMessage($code, $throwable->getMessage());

        return ApiErrorResponse::forbidden($message, $code);
    }

    private function renderForbiddenHttpException(HttpExceptionInterface $throwable): JsonResponse
    {
        if ($this->matchesForbiddenMessage($throwable->getMessage(), 'email_not_verified')) {
            return ApiErrorResponse::forbidden(code: 'email_not_verified');
        }

        if ($this->matchesForbiddenMessage($throwable->getMessage(), 'email_verification_invalid')) {
            return ApiErrorResponse::forbidden(code: 'email_verification_invalid');
        }

        return ApiErrorResponse::forbidden();
    }

    private function resolveForbiddenCode(AuthorizationException $throwable): string
    {
        if ($throwable instanceof ApiAuthorizationException) {
            return $throwable->errorCode();
        }

        return 'forbidden';
    }

    private function resolveForbiddenMessage(string $code, string $message): ?string
    {
        if ($code === 'forbidden') {
            return null;
        }

        if (trim($message) !== '') {
            return $message;
        }

        $translationKey = self::SPECIAL_FORBIDDEN_CODES[$code] ?? null;

        return $translationKey !== null ? __($translationKey) : null;
    }

    private function matchesForbiddenMessage(string $message, string $code): bool
    {
        $knownMessages = match ($code) {
            'email_not_verified' => [
                __('api.auth.email_not_verified'),
                self::EMAIL_NOT_VERIFIED_EXCEPTION_MESSAGE,
            ],
            'email_verification_invalid' => [
                __('api.auth.email_verification_invalid'),
                self::EMAIL_VERIFICATION_INVALID_EXCEPTION_MESSAGE,
            ],
            default => [],
        };

        return in_array($message, $knownMessages, true);
    }
}
