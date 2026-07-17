<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedExpense extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'category_id',
        'payment_type',
        'payment_instrument_id',
        'amount',
        'description',
        'frequency',
        'next_due_date',
        'alert_date',
        'is_active',
        'reminders_enabled',
        'type',
        'total_installments',
        'remaining_installments',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_due_date' => 'date',
            'alert_date' => 'date',
            'is_active' => 'boolean',
            'reminders_enabled' => 'boolean',
            'total_installments' => 'integer',
            'remaining_installments' => 'integer',
        ];
    }

    public function isInstallment(): bool
    {
        return $this->type === 'installment';
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Virtual morph-type accessor: returns null for 'cash' so morphTo
     * skips class resolution (avoids "Class cash not found").
     */
    public function getPaymentMorphTypeAttribute(): ?string
    {
        return $this->payment_type === 'cash' ? null : $this->payment_type;
    }

    /** @return MorphTo<Model, $this> */
    public function paymentInstrument(): MorphTo
    {
        return $this->morphTo('paymentInstrument', 'payment_morph_type', 'payment_instrument_id');
    }

    /** @return HasMany<Expense, $this> */
    public function generatedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'fixed_expense_id');
    }

    /** @return HasMany<FixedExpenseOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(FixedExpenseOccurrence::class);
    }

    /**
     * Advance to the next due date after processing an occurrence.
     * For installment expenses, decrements remaining_installments and
     * deactivates the expense when all payments are complete.
     */
    public function advanceNextDueDate(): void
    {
        if ($this->isInstallment()) {
            $remaining = $this->remaining_installments - 1;

            if ($remaining <= 0) {
                $this->remaining_installments = 0;
                $this->is_active = false;
                $this->save();

                return;
            }

            $this->remaining_installments = $remaining;
        }

        $this->next_due_date = match ($this->frequency) {
            'daily' => $this->next_due_date->addDay(),
            'weekly' => $this->next_due_date->addWeek(),
            'monthly' => $this->next_due_date->addMonth(),
            'yearly' => $this->next_due_date->addYear(),
            default => $this->next_due_date->addMonth(),
        };

        if ($this->alert_date !== null) {
            $this->alert_date = match ($this->frequency) {
                'daily' => $this->alert_date->addDay(),
                'weekly' => $this->alert_date->addWeek(),
                'monthly' => $this->alert_date->addMonth(),
                'yearly' => $this->alert_date->addYear(),
                default => $this->alert_date->addMonth(),
            };
        }

        $this->save();
    }
}
