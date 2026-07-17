<?php

namespace App\Contracts;

interface GetLearnFeatureActionInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug, string $locale): ?array;
}
