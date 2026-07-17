<?php

namespace App\Http\Controllers\Api;

use App\Contracts\GetLearnCatalogActionInterface;
use App\Contracts\GetLearnFeatureActionInterface;
use App\Contracts\GetLearnTopicActionInterface;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LearnController extends Controller
{
    public function __construct(
        private readonly GetLearnCatalogActionInterface $getLearnCatalogAction,
        private readonly GetLearnTopicActionInterface $getLearnTopicAction,
        private readonly GetLearnFeatureActionInterface $getLearnFeatureAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $catalog = $this->getLearnCatalogAction->execute((string) app()->getLocale());

        return response()
            ->json(['data' => $catalog])
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $topic = $this->getLearnTopicAction->execute($slug, (string) app()->getLocale());

        if ($topic === null) {
            return ApiErrorResponse::make(
                Response::HTTP_NOT_FOUND,
                'learn_topic_not_found',
            );
        }

        return response()
            ->json(['data' => $topic])
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function showFeature(Request $request, string $slug): JsonResponse
    {
        $feature = $this->getLearnFeatureAction->execute($slug, (string) app()->getLocale());

        if ($feature === null) {
            return ApiErrorResponse::make(
                Response::HTTP_NOT_FOUND,
                'learn_feature_not_found',
            );
        }

        return response()
            ->json(['data' => $feature])
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }
}
