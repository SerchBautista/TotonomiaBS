<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'type',
        'currency_code',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot(['role', 'can_add_fixed_expenses', 'can_add_categories'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Category, $this> */
    public function enabledCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_workspace')
            ->withPivot(['is_shared', 'is_active']);
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** @return HasMany<OtherPaymentMethod, $this> */
    public function otherPaymentMethods(): HasMany
    {
        return $this->hasMany(OtherPaymentMethod::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<FixedExpense, $this> */
    public function fixedExpenses(): HasMany
    {
        return $this->hasMany(FixedExpense::class);
    }

    /** @return HasMany<Budget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Check if the owner of this workspace has the premium plan.
     */
    public function ownerHasPremium(): bool
    {
        $owner = $this->relationLoaded('owner') ? $this->owner : $this->owner()->first();

        return $owner?->hasRole('premium') ?? false;
    }

    /**
     * Check if a user is a member of this workspace.
     */
    public function hasMember(string $userId): bool
    {
        return $this->members()->where('users.id', $userId)->exists();
    }

    /**
     * Get the role of a user in this workspace.
     */
    public function memberRole(string $userId): ?string
    {
        $member = $this->members()->where('users.id', $userId)->first();

        return $member?->pivot->role;
    }
}
