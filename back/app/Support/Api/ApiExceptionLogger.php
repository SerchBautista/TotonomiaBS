<?php

namespace App\Support\Api;

use App\Exceptions\DomainValidationException;
use App\Support\Http\RequestId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ApiExceptionLogger
{
    private const MAX_MESSAGE_LENGTH = 500;

    private const MAX_ARRAY_ITEMS = 10;

    private const REDACTED = '[REDACTED]';

    public function handle(Throwable $throwable, Request $request, JsonResponse $response): void
    {
        $payload = $response->getData(true);
        $route = $request->route();
        $previous = $throwable->getPrevious();
        $authorizationContext = AuthorizationContext::get($request);

        Log::log(
            $this->resolveLevel((int) ($payload['status'] ?? $response->getStatusCode())),
            'API exception rendered',
            array_filter([
                'request_id' => RequestId::resolve($request),
                'status' => (int) ($payload['status'] ?? $response->getStatusCode()),
                'code' => is_string($payload['code'] ?? null) ? $payload['code'] : 'server_error',
                'exception_class' => $throwable::class,
                'exception_message' => $this->sanitizeString($throwable->getMessage()),
                'previous_exception_class' => $previous ? $previous::class : null,
                'previous_exception_message' => $previous ? $this->sanitizeString($previous->getMessage()) : null,
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $route?->getName(),
                'controller_action' => $this->resolveControllerAction($route),
                'user_id' => $request->user()?->getAuthIdentifier(),
                'ip' => $request->ip(),
                'authorization_context' => $this->sanitizeValue($authorizationContext),
                'authorization_middleware' => $this->contextValue($authorizationContext, 'middleware'),
                'authorization_reason' => $this->contextValue($authorizationContext, 'authorization_reason'),
                'workspace_id' => $this->contextValue($authorizationContext, 'workspace_id'),
                'required_permission' => $this->contextValue($authorizationContext, 'required_permission'),
                'required_role' => $this->contextValue($authorizationContext, 'required_role'),
                'resource_type' => $this->contextValue($authorizationContext, 'resource_type'),
                'resource_id' => $this->contextValue($authorizationContext, 'resource_id'),
                'route_parameters' => $this->sanitizeValue($route?->parametersWithoutNulls() ?? []),
                'validation' => $this->validationContext($throwable),
                'domain_context' => $this->domainContext($throwable),
            ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
        );
    }

    private function resolveControllerAction(?Route $route): ?string
    {
        if ($route === null) {
            return null;
        }

        $action = $route->getActionName();

        return $action !== '' ? $action : null;
    }

    private function resolveLevel(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            in_array($status, [401, 403, 409], true) => 'warning',
            default => 'notice',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validationContext(Throwable $throwable): ?array
    {
        if (! $throwable instanceof ValidationException) {
            return null;
        }

        $errors = $throwable->errors();

        return [
            'fields' => array_keys($errors),
            'error_count' => count($errors),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function domainContext(Throwable $throwable): ?array
    {
        if (! $throwable instanceof DomainValidationException) {
            return null;
        }

        $meta = $this->sanitizeValue($throwable->meta());

        if (! is_array($meta) || $meta === []) {
            return null;
        }

        return [
            'meta' => $meta,
        ];
    }

    private function contextValue(?array $context, string $key): mixed
    {
        if ($context === null || ! array_key_exists($key, $context)) {
            return null;
        }

        return $this->sanitizeValue($context[$key], $key);
    }

    private function isSensitiveKey(string $key): bool
    {
        if (in_array($key, ['authorization_reason'], true)) {
            return false;
        }

        return preg_match(
            '/(^|[_.-])(password|password_confirmation|current_password|token|access_token|refresh_token|authorization|secret|signature|cookie|api_key|client_secret)([_.-]|$)/i',
            $key,
        ) === 1;
    }

    private function sanitizeString(string $value): string
    {
        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer '.self::REDACTED, $value) ?? $value;
        $sanitized = preg_replace(
            '/((?:password|token|secret|signature|authorization|api[_-]?key|client[_-]?secret)\s*[:=]\s*)([^\s,;]+)/i',
            '$1'.self::REDACTED,
            $sanitized,
        ) ?? $sanitized;

        return Str::limit(trim($sanitized), self::MAX_MESSAGE_LENGTH);
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if ($value instanceof Model) {
            return [
                'model' => $value::class,
                'id' => $value->getKey(),
            ];
        }

        if (is_array($value)) {
            $sanitized = [];
            $count = 0;

            foreach ($value as $itemKey => $itemValue) {
                if ($count >= self::MAX_ARRAY_ITEMS) {
                    $sanitized['_truncated'] = true;
                    break;
                }

                $normalizedKey = is_string($itemKey) ? $itemKey : (string) $itemKey;
                $sanitized[$normalizedKey] = $this->sanitizeValue($itemValue, $normalizedKey);
                $count++;
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof \Stringable) {
            return $this->sanitizeString((string) $value);
        }

        return [
            'type' => $value::class,
        ];
    }
}
