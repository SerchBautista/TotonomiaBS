<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class OtherPaymentMethod extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'description',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function setAsDefault(string $workspaceId): void
    {
        DB::transaction(function () use ($workspaceId) {
            $newValue = ! $this->is_default;
            static::where('workspace_id', $workspaceId)->update(['is_default' => false]);
            if ($newValue) {
                $this->update(['is_default' => true]);
            } else {
                $this->refresh();
            }
        });
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

    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'other_payment_method_workspace')
            ->withPivot(['is_shared', 'is_active']);
    }

    /** @return MorphMany<Expense, $this> */
    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'paymentInstrument', 'payment_type', 'payment_instrument_id');
    }

    /** @return MorphMany<FixedExpense, $this> */
    public function fixedExpenses(): MorphMany
    {
        return $this->morphMany(FixedExpense::class, 'paymentInstrument', 'payment_type', 'payment_instrument_id');
    }
}
