<?php

namespace App\Support\Api;

use Illuminate\Http\Request;

final class AuthorizationContext
{
    public const ATTRIBUTE = 'authorization_context';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function store(Request $request, array $context): void
    {
        $request->attributes->set(
            self::ATTRIBUTE,
            array_filter(
                $context,
                static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '',
            ),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(Request $request): ?array
    {
        $context = $request->attributes->get(self::ATTRIBUTE);

        return is_array($context) && $context !== [] ? $context : null;
    }
}
