<?php

namespace App\Actions;

use App\Contracts\GetLearnFeatureActionInterface;
use App\Contracts\LearnContentProviderInterface;

class GetLearnFeatureAction implements GetLearnFeatureActionInterface
{
    public function __construct(
        private readonly LearnContentProviderInterface $learnContentProvider,
    ) {}

    public function execute(string $slug, string $locale): ?array
    {
        return $this->learnContentProvider->feature($slug, $locale);
    }
}
