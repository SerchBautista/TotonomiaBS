<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'workspace_id',
        'user_id',
        'paid_by_user_id',
        'category_id',
        'payment_type',
        'payment_instrument_id',
        'fixed_expense_id',
        'amount',
        'date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    /** @return BelongsTo<FixedExpense, $this> */
    public function fixedExpense(): BelongsTo
    {
        return $this->belongsTo(FixedExpense::class);
    }
}
