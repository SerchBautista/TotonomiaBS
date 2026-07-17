<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * Whitelist of mass-assignable attributes.
     *
     * Sensitive fields (`user_id`, `is_default`) are intentionally excluded so
     * they cannot be set via mass assignment from user input. `user_id` must
     * always be set by trusted server-side code (e.g. `$user->categories()->create(...)`)
     * and `is_default` must only be set via the dedicated `setAsDefault()`
     * business method on this model.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'icon',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Toggle the `is_default` flag for this category.
     *
     * When called, the previous default (if any) is unmarked and the new
     * state is set to the opposite of the current value (true → false,
     * false → true). The previous `$workspaceId` parameter was misleading
     * because the actual scope is `$this->user_id`, so it has been removed.
     *
     * Implementation note: uses `forceFill()` + `save()` so the value can be
     * written even though `is_default` is intentionally NOT in `$fillable`
     * (mass-assignment protection — see `$fillable` docblock).
     */
    public function setAsDefault(): void
    {
        DB::transaction(function () {
            $newValue = ! $this->is_default;
            static::where('user_id', $this->user_id)->update(['is_default' => false]);
            if ($newValue) {
                $this->forceFill(['is_default' => true])->save();
            } else {
                $this->refresh();
            }
        });
    }

    protected static function booted(): void
    {
        static::deleting(function (Category $category) {
            $category->workspaces()->detach();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'category_workspace')
            ->withPivot(['is_shared', 'is_active']);
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

    /**
     * Scope for categories enabled in a specific workspace.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeEnabledIn($query, string $workspaceId)
    {
        return $query->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId));
    }
}
