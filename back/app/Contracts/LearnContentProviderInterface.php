<?php

namespace App\Contracts;

interface LearnContentProviderInterface
{
    /**
     * @return array{
     *     version: int,
     *     updatedAt: string,
     *     locale: string,
     *     hub: array<string, string>,
     *     cta: array<string, string>,
     *     topics: array<int, array<string, mixed>>,
     *     features: array<int, array<string, mixed>>
     * }
     */
    public function catalog(string $locale): array;

    /**
     * @return array<string, mixed>|null
     */
    public function topic(string $slug, string $locale): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function feature(string $slug, string $locale): ?array;
}
