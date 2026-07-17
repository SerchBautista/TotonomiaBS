<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateUserData extends Command
{
    protected $signature = 'userdata:generate {user_id : The UUID of the user to generate data for}';

    protected $description = 'Generate realistic test data (categories, payment methods, expenses) for an existing user.';

    /** @var array<int, array{name: string, icon: string, color: string}> */
    private const CATEGORIES = [
        ['name' => 'Comida y Bebidas', 'icon' => 'utensils', 'color' => '#FF6B6B'],
        ['name' => 'Transporte', 'icon' => 'car', 'color' => '#4ECDC4'],
        ['name' => 'Hogar', 'icon' => 'home', 'color' => '#45B7D1'],
        ['name' => 'Salud', 'icon' => 'heart', 'color' => '#96CEB4'],
        ['name' => 'Entretenimiento', 'icon' => 'film', 'color' => '#FFEAA7'],
    ];

    /** @var array<int, array{name: string, card_type: string, brand: string, last_4_digits: string}> */
    private const CARDS = [
        ['name' => 'Visa Personal', 'card_type' => 'credit', 'brand' => 'visa', 'last_4_digits' => '4242'],
        ['name' => 'Mastercard Débito', 'card_type' => 'debit', 'brand' => 'mastercard', 'last_4_digits' => '5555'],
        ['name' => 'Amex Gold', 'card_type' => 'credit', 'brand' => 'amex', 'last_4_digits' => '3782'],
    ];

    /** @var array<int, array{name: string, description: string}> */
    private const OTHER_PAYMENT_METHODS = [
        ['name' => 'Efectivo', 'description' => 'Pagos en efectivo'],
        ['name' => 'Transferencia', 'description' => 'Transferencia bancaria SPEI'],
    ];

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

    public function handle(): int
    {
        /** @var string $userId */
        $userId = $this->argument('user_id');

        $user = User::find($userId);

        if ($user === null) {
            $this->error("User with ID [{$userId}] not found.");

            return self::FAILURE;
        }

        $this->info("Generating test data for user: {$user->name} ({$user->email})");

        $stats = DB::transaction(function () use ($user): array {
            $workspace = $this->resolveWorkspace($user);
            $this->info("Workspace: {$workspace->name} ({$workspace->id})");

            $categories = $this->createCategories($user, $workspace);
            $this->info('Created '.count($categories).' categories.');

            [$cards, $otherMethods] = $this->createPaymentMethods($workspace);
            $this->info('Created '.count($cards).' cards and '.count($otherMethods).' other payment methods.');

            $fixedExpenses = $this->createFixedExpenses($user, $workspace, $categories, $cards, $otherMethods);
            $this->info('Created '.count($fixedExpenses).' fixed expenses.');

            $expenseCount = $this->createVariableExpenses($user, $workspace, $categories, $cards, $otherMethods);
            $this->info("Created {$expenseCount} variable expenses across the last 6 months.");

            return [
                'workspace' => $workspace,
                'categories' => count($categories),
                'cards' => count($cards),
                'other_payment_methods' => count($otherMethods),
                'fixed_expenses' => count($fixedExpenses),
                'variable_expenses' => $expenseCount,
            ];
        });

        $this->displaySummary($stats);

        return self::SUCCESS;
    }

    private function resolveWorkspace(User $user): Workspace
    {
        if ($user->default_workspace_id !== null) {
            /** @var Workspace */
            return Workspace::findOrFail($user->default_workspace_id);
        }

        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => "Workspace de {$user->name}",
            'type' => 'personal',
            'currency_code' => 'MXN',
        ]);

        $user->update(['default_workspace_id' => $workspace->id]);

        $workspace->members()->syncWithoutDetaching([
            $user->id => [
                'role' => 'owner',
                'can_add_fixed_expenses' => true,
                'can_add_categories' => true,
            ],
        ]);

        return $workspace;
    }

    /**
     * @return list<Category>
     */
    private function createCategories(User $user, Workspace $workspace): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $data) {
            $category = $user->categories()->create([
                'name' => $data['name'],
                'icon' => $data['icon'],
                'color' => $data['color'],
                'is_default' => false,
            ]);

            $category->workspaces()->attach($workspace->id, [
                'is_shared' => true,
                'is_active' => true,
            ]);

            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * @return array{0: list<Card>, 1: list<OtherPaymentMethod>}
     */
    private function createPaymentMethods(Workspace $workspace): array
    {
        $cards = [];

        foreach (self::CARDS as $index => $data) {
            $cards[] = Card::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'card_type' => $data['card_type'],
                'brand' => $data['brand'],
                'last_4_digits' => $data['last_4_digits'],
                'is_default' => $index === 0,
            ]);
        }

        $otherMethods = [];

        foreach (self::OTHER_PAYMENT_METHODS as $index => $data) {
            $otherMethods[] = OtherPaymentMethod::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'is_default' => $index === 0,
            ]);
        }

        return [$cards, $otherMethods];
    }

    /**
     * @param  list<Category>  $categories
     * @param  list<Card>  $cards
     * @param  list<OtherPaymentMethod>  $otherMethods
     * @return list<FixedExpense>
     */
    private function createFixedExpenses(
        User $user,
        Workspace $workspace,
        array $categories,
        array $cards,
        array $otherMethods,
    ): array {
        $entertainmentCategory = $this->findByName($categories, 'Entretenimiento');
        $homeCategory = $this->findByName($categories, 'Hogar');
        $primaryCard = $cards[0];
        $transferMethod = $otherMethods[1]; // "Transferencia"
        $nextMonth = now()->addMonth()->startOfMonth();

        $definitions = [
            [
                'description' => 'Netflix',
                'amount' => 299.00,
                'category' => $entertainmentCategory,
                'payment_type' => 'card',
                'payment_instrument_id' => $primaryCard->id,
            ],
            [
                'description' => 'Spotify',
                'amount' => 149.00,
                'category' => $entertainmentCategory,
                'payment_type' => 'card',
                'payment_instrument_id' => $primaryCard->id,
            ],
            [
                'description' => 'Internet',
                'amount' => 599.00,
                'category' => $homeCategory,
                'payment_type' => 'other',
                'payment_instrument_id' => $transferMethod->id,
            ],
        ];

        $fixedExpenses = [];

        foreach ($definitions as $def) {
            $fixedExpenses[] = FixedExpense::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'category_id' => $def['category']->id,
                'payment_type' => $def['payment_type'],
                'payment_instrument_id' => $def['payment_instrument_id'],
                'amount' => $def['amount'],
                'description' => $def['description'],
                'frequency' => 'monthly',
                'next_due_date' => $nextMonth,
                'is_active' => true,
                'reminders_enabled' => true,
                'type' => 'recurring',
            ]);
        }

        return $fixedExpenses;
    }

    /**
     * @param  list<Category>  $categories
     * @param  list<Card>  $cards
     * @param  list<OtherPaymentMethod>  $otherMethods
     */
    private function createVariableExpenses(
        User $user,
        Workspace $workspace,
        array $categories,
        array $cards,
        array $otherMethods,
    ): int {
        $count = 0;
        $now = now();

        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $monthDate = $now->copy()->subMonthsNoOverflow($monthOffset);
            $expensesThisMonth = random_int(10, 15);

            for ($i = 0; $i < $expensesThisMonth; $i++) {
                /** @var Category $category */
                $category = $this->pickRandom($categories);
                ['type' => $paymentType, 'id' => $instrumentId] = $this->pickRandomPayment($cards, $otherMethods);

                Expense::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'paid_by_user_id' => null,
                    'category_id' => $category->id,
                    'payment_type' => $paymentType,
                    'payment_instrument_id' => $instrumentId,
                    'fixed_expense_id' => null,
                    'amount' => $this->generateRealisticAmount(),
                    'date' => $this->randomDateInMonth($monthDate),
                    'description' => $this->pickRandomDescription($category->name),
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pickRandom(array $mixed): mixed
    {
        return $mixed[array_rand($mixed)];
    }

    /**
     * @param  list<Category>  $categories
     */
    private function findByName(array $categories, string $name): Category
    {
        foreach ($categories as $category) {
            if ($category->name === $name) {
                return $category;
            }
        }

        throw new \InvalidArgumentException("Category '{$name}' not found.");
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

        return $this->pickRandom($options);
    }

    private function pickRandomDescription(string $categoryName): string
    {
        $descriptions = self::DESCRIPTIONS[$categoryName] ?? ['Gasto general'];

        return $this->pickRandom($descriptions);
    }

    /**
     * Generate a realistic expense amount in MXN.
     * Distribution: 70% between $50-$800, 20% between $800-$2000, 10% between $2000-$5000.
     */
    private function generateRealisticAmount(): float
    {
        $rand = mt_rand(1, 100);

        return match (true) {
            $rand <= 70 => round(mt_rand(5000, 80000) / 100, 2),       // $50.00 – $800.00
            $rand <= 90 => round(mt_rand(80000, 200000) / 100, 2),     // $800.00 – $2,000.00
            default => round(mt_rand(200000, 500000) / 100, 2),        // $2,000.00 – $5,000.00
        };
    }

    private function randomDateInMonth(Carbon $month): Carbon
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $today = now()->startOfDay();

        // Don't generate future dates
        if ($end->isFuture()) {
            $end = $today->copy();
        }

        if ($start->greaterThan($end)) {
            return $start;
        }

        $daysDiff = $start->diffInDays($end);
        $randomDays = random_int(0, max(0, (int) $daysDiff));

        return $start->copy()->addDays($randomDays);
    }

    /**
     * @param  array{workspace: Workspace, categories: int, cards: int, other_payment_methods: int, fixed_expenses: int, variable_expenses: int}  $stats
     */
    private function displaySummary(array $stats): void
    {
        $this->newLine();
        $this->info('✅ Data generation complete!');

        $this->table(
            ['Resource', 'Count'],
            [
                ['Workspace', $stats['workspace']->name],
                ['Categories', (string) $stats['categories']],
                ['Cards', (string) $stats['cards']],
                ['Other Payment Methods', (string) $stats['other_payment_methods']],
                ['Fixed Expenses', (string) $stats['fixed_expenses']],
                ['Variable Expenses', (string) $stats['variable_expenses']],
                ['Total Records', (string) ($stats['categories'] + $stats['cards'] + $stats['other_payment_methods'] + $stats['fixed_expenses'] + $stats['variable_expenses'])],
            ]
        );
    }
}
