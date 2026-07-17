<?php

namespace Tests\Feature;

use Tests\TestCase;

class LearnContentTest extends TestCase
{
    public function test_learn_catalog_returns_localized_topics_in_spanish(): void
    {
        $response = $this->getJson('/api/v1/learn?lang=es');

        $response->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('data.locale', 'es')
            ->assertJsonPath('data.hub.title', 'Mejora tus finanzas personales')
            ->assertJsonPath('data.hub.overviewTitle', 'Totonomía en acción')
            ->assertJsonPath('data.hub.overviewMedia.mp4', '/media/walkthroughs/overview.mp4')
            ->assertJsonPath('data.hub.featuresLead', 'Explora cada área con capturas reales de la app. Cambia de pestaña sin salir de esta página.')
            ->assertJsonPath('data.features.0.screenshot', '/media/walkthroughs/screens/dashboard.png')
            ->assertJsonCount(3, 'data.features.0.sections')
            ->assertJsonPath('data.topics.0.slug', 'expense-tracking')
            ->assertJsonStructure([
                'data' => [
                    'version',
                    'updatedAt',
                    'locale',
                    'hub' => [
                        'eyebrow',
                        'title',
                        'lead',
                        'topicsTitle',
                        'featuresTitle',
                        'featuresLead',
                        'overviewTitle',
                        'overviewLead',
                        'overviewMedia' => ['poster', 'webm', 'mp4'],
                    ],
                    'cta' => ['title', 'subtitle', 'primary', 'secondary'],
                    'topics' => [
                        [
                            'id',
                            'slug',
                            'icon' => ['web', 'mobile'],
                            'title',
                            'summary',
                            'eyebrow',
                        ],
                    ],
                    'features' => [
                        [
                            'id',
                            'slug',
                            'icon' => ['web', 'mobile'],
                            'title',
                            'summary',
                            'eyebrow',
                            'lead',
                            'screenshot',
                            'sections' => [
                                ['title', 'body'],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_learn_catalog_honors_accept_language_header(): void
    {
        $response = $this->getJson('/api/v1/learn', [
            'Accept-Language' => 'en',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.hub.title', 'Improve your personal finances');
    }

    public function test_learn_topic_returns_localized_detail(): void
    {
        $response = $this->getJson('/api/v1/learn/debt?lang=es');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'debt')
            ->assertJsonPath('data.title', '¿Vives con deuda?')
            ->assertJsonCount(5, 'data.sections');
    }

    public function test_learn_topic_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/v1/learn/unknown-topic?lang=es');

        $response->assertNotFound()
            ->assertJsonPath('code', 'learn_topic_not_found');
    }

    public function test_learn_feature_returns_localized_detail_with_media(): void
    {
        $response = $this->getJson('/api/v1/learn/features/dashboard?lang=es');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'dashboard')
            ->assertJsonPath('data.title', 'Panel financiero')
            ->assertJsonPath('data.media.mp4', '/media/walkthroughs/dashboard.mp4')
            ->assertJsonCount(3, 'data.sections');
    }

    public function test_learn_feature_returns_expenses_with_media(): void
    {
        $response = $this->getJson('/api/v1/learn/features/expenses?lang=es');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'expenses')
            ->assertJsonPath('data.title', 'Registro de gastos')
            ->assertJsonPath('data.media.mp4', '/media/walkthroughs/expenses.mp4')
            ->assertJsonCount(3, 'data.sections');
    }

    public function test_learn_feature_returns_budgets_with_media(): void
    {
        $response = $this->getJson('/api/v1/learn/features/budgets?lang=es');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'budgets')
            ->assertJsonPath('data.title', 'Presupuestos')
            ->assertJsonPath('data.media.mp4', '/media/walkthroughs/budgets.mp4')
            ->assertJsonCount(3, 'data.sections');
    }

    public function test_learn_feature_returns_fixed_expenses_with_media(): void
    {
        $response = $this->getJson('/api/v1/learn/features/fixed-expenses?lang=es');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'fixed-expenses')
            ->assertJsonPath('data.title', 'Gastos fijos')
            ->assertJsonPath('data.media.mp4', '/media/walkthroughs/fixed-expenses.mp4')
            ->assertJsonCount(3, 'data.sections');
    }

    public function test_learn_feature_returns_workspaces_with_media(): void
    {
        $response = $this->getJson('/api/v1/learn/features/workspaces?lang=es');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'workspaces')
            ->assertJsonPath('data.title', 'Espacios de trabajo')
            ->assertJsonPath('data.media.mp4', '/media/walkthroughs/workspaces.mp4')
            ->assertJsonCount(3, 'data.sections');
    }

    public function test_learn_feature_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/v1/learn/features/unknown-feature?lang=es');

        $response->assertNotFound()
            ->assertJsonPath('code', 'learn_feature_not_found');
    }
}
