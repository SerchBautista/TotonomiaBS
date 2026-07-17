<?php

namespace App\Support\Api;

use App\Support\Http\RequestId;
use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiErrorResponse
{
    /**
     * @param  array<string, array<int, string>>  $fieldErrors
     */
    public static function make(
        int $status,
        string $code,
        ?string $message = null,
        array $fieldErrors = [],
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'status' => $status,
            'code' => $code,
            'message' => self::resolveMessage($status, $code, $message),
        ];

        $requestId = RequestId::current();

        if ($requestId !== null) {
            $payload['request_id'] = $requestId;
        }

        if ($fieldErrors !== []) {
            $payload['fieldErrors'] = self::normalizeFieldErrors($fieldErrors);
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status);
    }

    public static function badRequest(?string $message = null, string $code = 'bad_request', array $meta = []): JsonResponse
    {
        return self::make(Response::HTTP_BAD_REQUEST, $code, $message, [], $meta);
    }

    public static function unauthenticated(?string $message = null, string $code = 'unauthenticated', array $meta = []): JsonResponse
    {
        return self::make(Response::HTTP_UNAUTHORIZED, $code, $message, [], $meta);
    }

    public static function forbidden(?string $message = null, string $code = 'forbidden', array $meta = []): JsonResponse
    {
        return self::make(Response::HTTP_FORBIDDEN, $code, $message, [], $meta);
    }

    public static function notFound(?string $message = null, string $code = 'not_found', array $meta = []): JsonResponse
    {
        return self::make(Response::HTTP_NOT_FOUND, $code, $message, [], $meta);
    }

    public static function conflict(?string $message = null, string $code = 'conflict', array $meta = []): JsonResponse
    {
        return self::make(Response::HTTP_CONFLICT, $code, $message, [], $meta);
    }

    public static function tooManyRequests(?string $message = null, string $code = 'too_many_requests', array $meta = []): JsonResponse
    {
        return self::make(Response::HTTP_TOO_MANY_REQUESTS, $code, $message, [], $meta);
    }

    public static function serverError(?string $message = null, string $code = 'server_error', array $meta = []): JsonResponse
    {
        return self::make(Response::HTTP_INTERNAL_SERVER_ERROR, $code, $message, [], $meta);
    }

    /**
     * @param  array<string, array<int, string>>  $fieldErrors
     */
    public static function unprocessableEntity(
        ?string $message = null,
        string $code = 'unprocessable_entity',
        array $fieldErrors = [],
        array $meta = [],
    ): JsonResponse {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, $code, $message, $fieldErrors, $meta);
    }

    /**
     * @param  array<string, array<int, string>>  $fieldErrors
     */
    public static function validation(
        array $fieldErrors,
        ?string $message = null,
        string $code = 'validation_error',
        array $meta = [],
    ): JsonResponse {
        return self::make(Response::HTTP_UNPROCESSABLE_ENTITY, $code, $message, $fieldErrors, $meta);
    }

    /**
     * @param  array<string, array<int, string>>  $fieldErrors
     * @return array<string, array<int, string>>
     */
    private static function normalizeFieldErrors(array $fieldErrors): array
    {
        $normalized = [];

        foreach ($fieldErrors as $field => $messages) {
            $normalized[(string) $field] = array_values(array_map(
                static fn (mixed $message): string => (string) $message,
                $messages,
            ));
        }

        return $normalized;
    }

    private static function resolveMessage(int $status, string $code, ?string $message): string
    {
        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        $translationKey = self::translationKey($status, $code);

        $container = Container::getInstance();
        $translated = $container && $container->bound('translator')
            ? __($translationKey)
            : $translationKey;

        if ($translated !== $translationKey) {
            return $translated;
        }

        return 'Request failed.';
    }

    private static function translationKey(int $status, string $code): string
    {
        $codeTranslationKey = match ($code) {
            'validation_error' => 'api.errors.validation_error',
            'email_not_verified' => 'api.auth.email_not_verified',
            'email_verification_invalid' => 'api.auth.email_verification_invalid',
            'subscription_already_active' => 'api.errors.subscription_already_active',
            'learn_topic_not_found' => 'api.learn.topic_not_found',
            default => null,
        };

        if ($codeTranslationKey !== null) {
            return $codeTranslationKey;
        }

        return match ($status) {
            Response::HTTP_UNAUTHORIZED => 'api.errors.unauthenticated',
            Response::HTTP_BAD_REQUEST => 'api.errors.bad_request',
            Response::HTTP_FORBIDDEN => 'api.errors.forbidden',
            Response::HTTP_NOT_FOUND => 'api.errors.not_found',
            Response::HTTP_CONFLICT => 'api.errors.conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'api.errors.unprocessable_entity',
            Response::HTTP_TOO_MANY_REQUESTS => 'api.errors.too_many_requests',
            default => 'api.errors.server_error',
        };
    }
}
