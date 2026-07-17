<?php

namespace App\Contracts;

interface GetLearnTopicActionInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug, string $locale): ?array;
}
