<?php

namespace App\Contracts;

interface GetLearnCatalogActionInterface
{
    /**
     * @return array<string, mixed>
     */
    public function execute(string $locale): array;
}
