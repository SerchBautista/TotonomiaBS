<?php

namespace App\Actions;

use App\Contracts\GetLearnCatalogActionInterface;
use App\Contracts\LearnContentProviderInterface;

class GetLearnCatalogAction implements GetLearnCatalogActionInterface
{
    public function __construct(
        private readonly LearnContentProviderInterface $learnContentProvider,
    ) {}

    public function execute(string $locale): array
    {
        return $this->learnContentProvider->catalog($locale);
    }
}
