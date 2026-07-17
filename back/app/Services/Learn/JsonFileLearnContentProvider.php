<?php

namespace App\Services\Learn;

use App\Contracts\LearnContentProviderInterface;
use Illuminate\Support\Facades\File;
use RuntimeException;

class JsonFileLearnContentProvider implements LearnContentProviderInterface
{
    /** @var array<string, mixed>|null */
    private ?array $rawContent = null;

    public function catalog(string $locale): array
    {
        $content = $this->loadRawContent();
        $normalizedLocale = $this->normalizeLocale($locale);

        return [
            'version' => (int) ($content['version'] ?? 1),
            'updatedAt' => (string) ($content['updatedAt'] ?? ''),
            'locale' => $normalizedLocale,
            'hub' => $this->mapHubBlock($content, $normalizedLocale),
            'cta' => $this->resolveLocalizedBlock($content['cta'] ?? [], $normalizedLocale),
            'topics' => array_map(
                fn (array $topic): array => $this->mapTopicSummary($topic, $normalizedLocale),
                $content['topics'] ?? []
            ),
            'features' => array_map(
                fn (array $feature): array => $this->mapFeatureSummary($feature, $normalizedLocale),
                $content['features'] ?? []
            ),
        ];
    }

    public function topic(string $slug, string $locale): ?array
    {
        $content = $this->loadRawContent();
        $normalizedLocale = $this->normalizeLocale($locale);

        foreach ($content['topics'] ?? [] as $topic) {
            if (! is_array($topic) || ($topic['slug'] ?? null) !== $slug) {
                continue;
            }

            return $this->mapTopicDetail($topic, $normalizedLocale);
        }

        return null;
    }

    public function feature(string $slug, string $locale): ?array
    {
        $content = $this->loadRawContent();
        $normalizedLocale = $this->normalizeLocale($locale);

        foreach ($content['features'] ?? [] as $feature) {
            if (! is_array($feature) || ($feature['slug'] ?? null) !== $slug) {
                continue;
            }

            return $this->mapFeatureDetail($feature, $normalizedLocale);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRawContent(): array
    {
        if ($this->rawContent !== null) {
            return $this->rawContent;
        }

        $path = (string) config('learn.file.path');

        if (! File::exists($path)) {
            throw new RuntimeException("Learn content file not found at [{$path}].");
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Learn content file at [{$path}] is not valid JSON.");
        }

        $this->rawContent = $decoded;

        return $this->rawContent;
    }

    private function normalizeLocale(string $locale): string
    {
        $primaryLocale = strtolower(explode(',', $locale)[0]);
        $primaryLocale = str_replace('_', '-', $primaryLocale);
        $primaryLocale = explode('-', $primaryLocale)[0];

        return in_array($primaryLocale, ['en', 'es'], true)
            ? $primaryLocale
            : (string) config('app.locale', 'es');
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function mapHubBlock(array $content, string $locale): array
    {
        $hub = $this->resolveLocalizedBlock($content['hub'] ?? [], $locale);
        $media = $content['overviewMedia'] ?? null;

        if (is_array($media) && ($media['mp4'] ?? '') !== '') {
            $hub['overviewMedia'] = [
                'poster' => (string) ($media['poster'] ?? ''),
                'webm' => (string) ($media['webm'] ?? ''),
                'mp4' => (string) ($media['mp4'] ?? ''),
            ];
        }

        return $hub;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, string>
     */
    private function resolveLocalizedBlock(array $block, string $locale): array
    {
        $localized = $block[$locale] ?? $block['es'] ?? [];

        if (! is_array($localized)) {
            return [];
        }

        return array_map(
            static fn (mixed $value): string => is_string($value) ? $value : (string) $value,
            $localized
        );
    }

    /**
     * @param  array<string, mixed>  $topic
     * @return array<string, mixed>
     */
    private function mapTopicSummary(array $topic, string $locale): array
    {
        $localized = $this->resolveTopicContent($topic, $locale);

        return [
            'id' => (string) ($topic['id'] ?? ''),
            'slug' => (string) ($topic['slug'] ?? ''),
            'icon' => is_array($topic['icon'] ?? null) ? $topic['icon'] : [],
            'title' => $localized['title'] ?? '',
            'summary' => $localized['summary'] ?? '',
            'eyebrow' => $localized['eyebrow'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $topic
     * @return array<string, mixed>
     */
    private function mapTopicDetail(array $topic, string $locale): array
    {
        $localized = $this->resolveTopicContent($topic, $locale);

        return [
            'id' => (string) ($topic['id'] ?? ''),
            'slug' => (string) ($topic['slug'] ?? ''),
            'icon' => is_array($topic['icon'] ?? null) ? $topic['icon'] : [],
            'title' => $localized['title'] ?? '',
            'summary' => $localized['summary'] ?? '',
            'eyebrow' => $localized['eyebrow'] ?? '',
            'lead' => $localized['lead'] ?? '',
            'disclaimer' => $localized['disclaimer'] ?? null,
            'sections' => $localized['sections'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $topic
     * @return array<string, mixed>
     */
    private function resolveTopicContent(array $topic, string $locale): array
    {
        $content = $topic['content'] ?? [];

        if (! is_array($content)) {
            return [];
        }

        $localized = $content[$locale] ?? $content['es'] ?? [];

        return is_array($localized) ? $localized : [];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function mapFeatureSummary(array $feature, string $locale): array
    {
        $localized = $this->resolveTopicContent($feature, $locale);
        $screenshot = is_string($feature['screenshot'] ?? null) ? $feature['screenshot'] : '';

        return [
            'id' => (string) ($feature['id'] ?? ''),
            'slug' => (string) ($feature['slug'] ?? ''),
            'icon' => is_array($feature['icon'] ?? null) ? $feature['icon'] : [],
            'title' => $localized['title'] ?? '',
            'summary' => $localized['summary'] ?? '',
            'eyebrow' => $localized['eyebrow'] ?? '',
            'lead' => $localized['lead'] ?? '',
            'sections' => $localized['sections'] ?? [],
            'screenshot' => $screenshot,
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private function mapFeatureDetail(array $feature, string $locale): array
    {
        $localized = $this->resolveTopicContent($feature, $locale);
        $media = is_array($feature['media'] ?? null) ? $feature['media'] : [];

        return [
            'id' => (string) ($feature['id'] ?? ''),
            'slug' => (string) ($feature['slug'] ?? ''),
            'icon' => is_array($feature['icon'] ?? null) ? $feature['icon'] : [],
            'title' => $localized['title'] ?? '',
            'summary' => $localized['summary'] ?? '',
            'eyebrow' => $localized['eyebrow'] ?? '',
            'lead' => $localized['lead'] ?? '',
            'sections' => $localized['sections'] ?? [],
            'media' => [
                'poster' => (string) ($media['poster'] ?? ''),
                'webm' => (string) ($media['webm'] ?? ''),
                'mp4' => (string) ($media['mp4'] ?? ''),
            ],
        ];
    }
}
