<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedExpenseOccurrence extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'fixed_expense_id',
        'due_date',
        'suggested_amount',
        'actual_amount',
        'payment_type',
        'payment_instrument_id',
        'paid_at',
        'status',
        'expense_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'suggested_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FixedExpense, $this> */
    public function fixedExpense(): BelongsTo
    {
        return $this->belongsTo(FixedExpense::class);
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
