<?php

namespace App\Actions;

use App\Contracts\GetLearnTopicActionInterface;
use App\Contracts\LearnContentProviderInterface;

class GetLearnTopicAction implements GetLearnTopicActionInterface
{
    public function __construct(
        private readonly LearnContentProviderInterface $learnContentProvider,
    ) {}

    public function execute(string $slug, string $locale): ?array
    {
        return $this->learnContentProvider->topic($slug, $locale);
    }
}
