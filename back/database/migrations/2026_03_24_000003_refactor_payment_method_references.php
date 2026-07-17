<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns to expenses (default 'cash' so existing rows satisfy NOT NULL)
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('payment_type')->default('cash')->after('category_id'); // cash, card, other
            $table->uuid('payment_instrument_id')->nullable()->after('payment_type');
        });

        // 2. Add new columns to fixed_expenses
        Schema::table('fixed_expenses', function (Blueprint $table) {
            $table->string('payment_type')->default('cash')->after('category_id'); // cash, card, other
            $table->uuid('payment_instrument_id')->nullable()->after('payment_type');
        });

        // 3. Migrate existing data
        $this->migrateExistingData();

        // 4. Drop old FK and column from expenses
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
            $table->index(['payment_type', 'payment_instrument_id']);
        });

        // 5. Drop old FK and column from fixed_expenses
        Schema::table('fixed_expenses', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
            $table->index(['payment_type', 'payment_instrument_id']);
        });
    }

    private function migrateExistingData(): void
    {
        // Map old payment_methods to new cards (deduplicating)
        $cardMap = []; // old payment_method_id => new card_id

        $paymentMethods = DB::table('payment_methods')->whereNull('deleted_at')->get();

        foreach ($paymentMethods as $pm) {
            if (in_array($pm->type, ['credit_card', 'debit_card'])) {
                $cardType = $pm->type === 'credit_card' ? 'credit' : 'debit';
                $cardId = Str::uuid()->toString();

                DB::table('cards')->insert([
                    'id' => $cardId,
                    'workspace_id' => $pm->workspace_id,
                    'name' => $pm->name,
                    'card_type' => $cardType,
                    'brand' => null,
                    'last_4_digits' => null, // old encrypted values can't be safely migrated
                    'created_at' => $pm->created_at,
                    'updated_at' => $pm->updated_at,
                ]);

                $cardMap[$pm->id] = $cardId;
            }
        }

        // Migrate expenses
        $expenses = DB::table('expenses')->whereNotNull('payment_method_id')->get();

        foreach ($expenses as $expense) {
            $pm = $paymentMethods->firstWhere('id', $expense->payment_method_id);

            if ($pm === null) {
                // Orphaned reference — default to cash
                DB::table('expenses')->where('id', $expense->id)->update([
                    'payment_type' => 'cash',
                    'payment_instrument_id' => null,
                ]);

                continue;
            }

            if ($pm->type === 'cash') {
                DB::table('expenses')->where('id', $expense->id)->update([
                    'payment_type' => 'cash',
                    'payment_instrument_id' => null,
                ]);
            } else {
                DB::table('expenses')->where('id', $expense->id)->update([
                    'payment_type' => 'card',
                    'payment_instrument_id' => $cardMap[$pm->id] ?? null,
                ]);
            }
        }

        // Migrate fixed_expenses
        $fixedExpenses = DB::table('fixed_expenses')->whereNotNull('payment_method_id')->get();

        foreach ($fixedExpenses as $fe) {
            $pm = $paymentMethods->firstWhere('id', $fe->payment_method_id);

            if ($pm === null) {
                DB::table('fixed_expenses')->where('id', $fe->id)->update([
                    'payment_type' => 'cash',
                    'payment_instrument_id' => null,
                ]);

                continue;
            }

            if ($pm->type === 'cash') {
                DB::table('fixed_expenses')->where('id', $fe->id)->update([
                    'payment_type' => 'cash',
                    'payment_instrument_id' => null,
                ]);
            } else {
                DB::table('fixed_expenses')->where('id', $fe->id)->update([
                    'payment_type' => 'card',
                    'payment_instrument_id' => $cardMap[$pm->id] ?? null,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Re-add payment_method_id to expenses
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['payment_type', 'payment_instrument_id']);
            $table->uuid('payment_method_id')->nullable()->after('category_id');
        });

        // Re-add payment_method_id to fixed_expenses
        Schema::table('fixed_expenses', function (Blueprint $table) {
            $table->dropIndex(['payment_type', 'payment_instrument_id']);
            $table->uuid('payment_method_id')->nullable()->after('category_id');
        });

        // Reverse data migration: map cards back to payment_methods
        $cards = DB::table('cards')->get();

        foreach ($cards as $card) {
            $cardType = $card->card_type === 'credit' ? 'credit_card' : 'debit_card';
            $pmId = Str::uuid()->toString();

            DB::table('payment_methods')->insert([
                'id' => $pmId,
                'workspace_id' => $card->workspace_id,
                'name' => $card->name,
                'type' => $cardType,
                'last_4_digits' => $card->last_4_digits,
                'created_at' => $card->created_at,
                'updated_at' => $card->updated_at,
            ]);

            // Update expenses referencing this card
            DB::table('expenses')
                ->where('payment_type', 'card')
                ->where('payment_instrument_id', $card->id)
                ->update(['payment_method_id' => $pmId]);

            DB::table('fixed_expenses')
                ->where('payment_type', 'card')
                ->where('payment_instrument_id', $card->id)
                ->update(['payment_method_id' => $pmId]);
        }

        // Cash expenses: create a "cash" payment method per workspace
        $workspaceIds = DB::table('expenses')
            ->where('payment_type', 'cash')
            ->distinct()
            ->pluck('workspace_id');

        foreach ($workspaceIds as $wsId) {
            $pmId = Str::uuid()->toString();

            DB::table('payment_methods')->insert([
                'id' => $pmId,
                'workspace_id' => $wsId,
                'name' => 'Efectivo',
                'type' => 'cash',
                'last_4_digits' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('expenses')
                ->where('payment_type', 'cash')
                ->where('workspace_id', $wsId)
                ->update(['payment_method_id' => $pmId]);

            DB::table('fixed_expenses')
                ->where('payment_type', 'cash')
                ->where('workspace_id', $wsId)
                ->update(['payment_method_id' => $pmId]);
        }

        // Add FK back
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->dropColumn(['payment_type', 'payment_instrument_id']);
        });

        Schema::table('fixed_expenses', function (Blueprint $table) {
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->dropColumn(['payment_type', 'payment_instrument_id']);
        });
    }
};
