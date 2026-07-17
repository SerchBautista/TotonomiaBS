<?php

namespace App\Support\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestId
{
    public const ATTRIBUTE = 'request_id';

    public const HEADER = 'X-Request-Id';

    private const MAX_LENGTH = 128;

    public static function resolve(Request $request): string
    {
        $current = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($current) && $current !== '') {
            return $current;
        }

        $requestId = self::sanitize($request->headers->get(self::HEADER))
            ?? (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $requestId);

        return $requestId;
    }

    public static function current(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return self::sanitize(app(Request::class)->attributes->get(self::ATTRIBUTE));
    }

    private static function sanitize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        return preg_match('/\A[a-zA-Z0-9._-]+\z/', $value) === 1
            ? $value
            : null;
    }
}
