<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'category_id',
        'amount',
        'effective_from',
        'alert_threshold',
        'alert_enabled',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date:Y-m-d',
            'alert_threshold' => 'decimal:2',
            'alert_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope to budgets effective on or before the given month.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEffectiveFor(Builder $query, Carbon $month): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $month->copy()->startOfMonth()->toDateString())
            ->orderByDesc('effective_from');
    }

    /**
     * Get the budget currently effective for a given workspace, scope, and month.
     */
    public static function currentFor(Workspace $workspace, ?string $categoryId, Carbon $month): ?self
    {
        return static::query()
            ->where('workspace_id', $workspace->id)
            ->where('category_id', $categoryId)
            ->effectiveFor($month)
            ->first();
    }
}
