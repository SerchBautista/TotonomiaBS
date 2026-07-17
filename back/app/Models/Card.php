<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Card extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'card_type',
        'brand',
        'last_4_digits',
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

    public function getLast4DigitsAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function setLast4DigitsAttribute(?string $value): void
    {
        $this->attributes['last_4_digits'] = $value !== null ? Crypt::encryptString($value) : null;
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
        return $this->belongsToMany(Workspace::class, 'card_workspace')
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
