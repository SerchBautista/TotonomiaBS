<?php

namespace App\Console\Commands;

use App\Actions\CalculateEffectiveBudgetAction;
use App\Actions\GetValidWorkspaceCategoriesAction;
use App\Models\Budget;
use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyExpenses extends Command
{
    protected $signature = 'expenses:generate-monthly
                            {user_id : The UUID of the user to generate expenses for}
                            {--count=50 : Number of expenses to generate for the current month}
                            {--workspace= : Workspace UUID (defaults to user default workspace)}';

    protected $description = 'Generate random expenses for the current month using existing user/workspace data.';

    /** @var array<string, list<string>> */
    private const DESCRIPTIONS = [
        'Comida y Bebidas' => [
            'Supermercado', 'Restaurante', 'Café', 'Uber Eats', 'Oxxo',
            'Farmacia (snacks)', 'Panadería', 'Tacos', 'Sushi', 'Pizza',
        ],
        'Transporte' => [
            'Uber', 'Gasolina', 'Estacionamiento', 'Metro', 'DiDi',
            'Peaje', 'Verificación', 'Lavado de auto',
        ],
        'Hogar' => [
            'Luz CFE', 'Agua', 'Limpieza', 'Reparación', 'Gas',
            'Internet', 'Artículos de limpieza', 'Decoración',
        ],
        'Salud' => [
            'Farmacia', 'Consulta médica', 'Gimnasio', 'Dentista',
            'Análisis clínicos', 'Vitaminas',
        ],
        'Entretenimiento' => [
            'Cine', 'Concierto', 'Videojuegos', 'Libros', 'Netflix',
            'Spotify', 'Museo', 'Bowling',
        ],
    ];

    public function __construct(
        private readonly GetValidWorkspaceCategoriesAction $getValidWorkspaceCategoriesAction,
        private readonly CalculateEffectiveBudgetAction $calculateEffectiveBudgetAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string $userId */
        $userId = $this->argument('user_id');
        $count = max(1, (int) $this->option('count'));

        $user = User::find($userId);

        if ($user === null) {
            $this->error("User with ID [{$userId}] not found.");

            return self::FAILURE;
        }

        $workspace = $this->resolveWorkspace($user);

        if ($workspace === null) {
            $this->error('No workspace resolved. Set the user default workspace or pass --workspace=.');

            return self::FAILURE;
        }

        $categories = $this->getValidWorkspaceCategoriesAction->execute($workspace)->get();

        if ($categories->isEmpty()) {
            $this->error("No enabled categories found for workspace [{$workspace->id}].");

            return self::FAILURE;
        }

        /** @var list<Card> $cards */
        $cards = $workspace->cards()->get()->all();

        /** @var list<OtherPaymentMethod> $otherMethods */
        $otherMethods = $workspace->otherPaymentMethods()->get()->all();

        if ($cards === [] && $otherMethods === []) {
            $this->error("No payment methods found in workspace [{$workspace->id}] (no cards or other methods).");

            return self::FAILURE;
        }

        $month = now()->copy()->startOfMonth();
        $monthLabel = $month->format('Y-m');

        $this->info("Generating {$count} expenses for {$user->name} in {$monthLabel}...");

        $result = DB::transaction(function () use ($user, $workspace, $categories, $cards, $otherMethods, $count, $month): array {
            return $this->generateExpenses($user, $workspace, $categories, $cards, $otherMethods, $count, $month);
        });

        $this->displaySummary($user, $workspace, $monthLabel, $result);

        return self::SUCCESS;
    }

    private function resolveWorkspace(User $user): ?Workspace
    {
        $workspaceId = $this->option('workspace') ?: $user->default_workspace_id;

        if ($workspaceId === null) {
            return null;
        }

        return Workspace::find($workspaceId);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @param  list<Card>  $cards
     * @param  list<OtherPaymentMethod>  $otherMethods
     * @return array{created: int, totals_by_category: array<string, float>}
     */
    private function generateExpenses(
        User $user,
        Workspace $workspace,
        $categories,
        array $cards,
        array $otherMethods,
        int $count,
        Carbon $month,
    ): array {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $today = now()->startOfDay();

        $existingSpentByCategory = $this->existingSpentByCategory($workspace, $monthStart, $monthEnd);

        $hasCategoryBudgets = $categories->contains(
            fn (Category $category): bool => Budget::currentFor($workspace, $category->id, $month) !== null
        );

        /** @var array<string, array{category: Category, remaining: float|null, weight: float}> $categoryState */
        $categoryState = [];

        foreach ($categories as $category) {
            if ($hasCategoryBudgets) {
                $effective = $this->calculateEffectiveBudgetAction->execute($workspace, $category->id, $month);
                $spent = (float) ($existingSpentByCategory[$category->id] ?? 0);
                $remaining = max(0, $effective['effective_budget'] - $spent);
                $hasBudget = Budget::currentFor($workspace, $category->id, $month) !== null;
                $weight = $hasBudget ? $remaining : 1.0;
            } else {
                $remaining = null;
                $weight = 1.0;
            }

            $categoryState[$category->id] = [
                'category' => $category,
                'remaining' => $remaining,
                'weight' => $weight,
            ];
        }

        $generatedByCategory = [];
        $created = 0;
        $attempts = 0;
        $maxAttempts = $count * 10;

        while ($created < $count && $attempts < $maxAttempts) {
            $attempts++;
            $categoryId = $this->pickWeightedCategoryId($categoryState);

            if ($categoryId === null) {
                break;
            }

            $state = &$categoryState[$categoryId];
            $category = $state['category'];

            $amount = $this->resolveAmount($state['remaining'], $generatedByCategory[$categoryId] ?? 0.0);

            if ($amount <= 0) {
                $state['weight'] = 0;

                continue;
            }

            ['type' => $paymentType, 'id' => $instrumentId] = $this->pickRandomPayment($cards, $otherMethods);

            Expense::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'paid_by_user_id' => null,
                'category_id' => $category->id,
                'payment_type' => $paymentType,
                'payment_instrument_id' => $instrumentId,
                'fixed_expense_id' => null,
                'amount' => $amount,
                'date' => $this->randomDateInCurrentMonth($monthStart, $today),
                'description' => $this->pickDescription($category->name),
            ]);

            $generatedByCategory[$categoryId] = ($generatedByCategory[$categoryId] ?? 0.0) + $amount;
            $created++;

            if ($state['remaining'] !== null) {
                $state['remaining'] = max(0, $state['remaining'] - $amount);
                $state['weight'] = $state['remaining'] > 0 ? $state['remaining'] : 0;
            }
        }

        $totalsByCategory = [];

        foreach ($categories as $category) {
            $totalsByCategory[$category->name] = (float) ($generatedByCategory[$category->id] ?? 0);
        }

        return [
            'created' => $created,
            'totals_by_category' => $totalsByCategory,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function existingSpentByCategory(Workspace $workspace, Carbon $monthStart, Carbon $monthEnd): array
    {
        return $workspace->expenses()
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * @param  array<string, array{category: Category, remaining: float|null, weight: float}>  $categoryState
     */
    private function pickWeightedCategoryId(array $categoryState): ?string
    {
        $eligible = array_filter(
            $categoryState,
            fn (array $state): bool => $state['weight'] > 0
        );

        if ($eligible === []) {
            return null;
        }

        $totalWeight = array_sum(array_column($eligible, 'weight'));
        $pick = (mt_rand() / mt_getrandmax()) * $totalWeight;
        $running = 0.0;

        foreach ($eligible as $categoryId => $state) {
            $running += $state['weight'];

            if ($pick <= $running) {
                return $categoryId;
            }
        }

        return array_key_last($eligible);
    }

    private function resolveAmount(?float $remaining, float $generatedInRun): float
    {
        $amount = $this->generateRealisticAmount();

        if ($remaining === null) {
            return $amount;
        }

        $available = max(0, $remaining - $generatedInRun);

        if ($available <= 0) {
            return 0;
        }

        return min($amount, $available);
    }

    /**
     * @param  list<Card>  $cards
     * @param  list<OtherPaymentMethod>  $otherMethods
     * @return array{type: string, id: string|null}
     */
    private function pickRandomPayment(array $cards, array $otherMethods): array
    {
        $options = [];

        foreach ($cards as $card) {
            $options[] = ['type' => 'card', 'id' => $card->id];
        }

        foreach ($otherMethods as $method) {
            $options[] = ['type' => 'other', 'id' => $method->id];
        }

        $options[] = ['type' => 'cash', 'id' => null];

        return $options[array_rand($options)];
    }

    private function pickDescription(string $categoryName): string
    {
        $descriptions = self::DESCRIPTIONS[$categoryName] ?? null;

        if ($descriptions !== null) {
            return $descriptions[array_rand($descriptions)];
        }

        return "Gasto en {$categoryName}";
    }

    private function generateRealisticAmount(): float
    {
        $rand = mt_rand(1, 100);

        return match (true) {
            $rand <= 70 => round(mt_rand(5000, 80000) / 100, 2),
            $rand <= 90 => round(mt_rand(80000, 200000) / 100, 2),
            default => round(mt_rand(200000, 500000) / 100, 2),
        };
    }

    private function randomDateInCurrentMonth(Carbon $monthStart, Carbon $today): Carbon
    {
        $end = $monthStart->copy()->endOfMonth();

        if ($end->greaterThan($today)) {
            $end = $today->copy();
        }

        if ($monthStart->greaterThan($end)) {
            return $monthStart->copy();
        }

        $daysDiff = (int) $monthStart->diffInDays($end);

        return $monthStart->copy()->addDays(random_int(0, max(0, $daysDiff)));
    }

    /**
     * @param  array{created: int, totals_by_category: array<string, float>}  $result
     */
    private function displaySummary(User $user, Workspace $workspace, string $monthLabel, array $result): void
    {
        $this->newLine();
        $this->info('Monthly expense generation complete.');

        $this->table(
            ['Field', 'Value'],
            [
                ['User', "{$user->name} ({$user->email})"],
                ['Workspace', "{$workspace->name} ({$workspace->id})"],
                ['Month', $monthLabel],
                ['Expenses created', (string) $result['created']],
            ]
        );

        $categoryRows = [];

        foreach ($result['totals_by_category'] as $name => $total) {
            if ($total > 0) {
                $categoryRows[] = [$name, number_format($total, 2, '.', '')];
            }
        }

        if ($categoryRows !== []) {
            $this->newLine();
            $this->info('Totals by category (generated this run):');
            $this->table(['Category', 'Total (MXN)'], $categoryRows);
        }
    }
}
