<?php

namespace App\Http\Requests\BudgetAdjustment;

use App\Contracts\CalculateEffectiveBudgetActionInterface;
use App\Contracts\SuggestCategoriesForAdjustmentActionInterface;
use App\Exceptions\DomainValidationException;
use App\Models\BudgetAdjustment;
use App\Rules\ValidCategoryForWorkspace;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        if (! $workspace) {
            return false;
        }

        return $this->user()?->can('create', [BudgetAdjustment::class, $workspace]) ?? false;
    }

    public function rules(): array
    {
        return [
            'from_category_id' => [
                'required',
                'uuid',
                Rule::exists('categories', 'id'),
                new ValidCategoryForWorkspace($this->route('workspace'), $this->user()?->id),
            ],
            'to_category_id' => [
                'required',
                'uuid',
                Rule::exists('categories', 'id'),
                new ValidCategoryForWorkspace($this->route('workspace'), $this->user()?->id),
                'different:from_category_id',
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'month' => ['required', 'date_format:Y-m'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Skip business-logic validation if the basic rules already failed
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $workspace = $this->route('workspace');
            $fromCategoryId = $this->input('from_category_id');
            $toCategoryId = $this->input('to_category_id');

            if (! $workspace) {
                return;
            }

            $month = Carbon::createFromFormat('Y-m', $this->input('month'))->startOfMonth();
            $amount = (float) $this->input('amount');

            $effectiveBudgetAction = app(CalculateEffectiveBudgetActionInterface::class);
            $effective = $effectiveBudgetAction->execute($workspace, $fromCategoryId, $month);

            $spent = (float) $workspace->expenses()
                ->whereDate('date', '>=', $month->copy()->startOfMonth()->toDateString())
                ->whereDate('date', '<=', $month->copy()->endOfMonth()->toDateString())
                ->where('category_id', $fromCategoryId)
                ->sum('amount');

            $available = max(0, $effective['effective_budget'] - $spent);

            if ($amount > $available) {
                $suggestAction = app(SuggestCategoriesForAdjustmentActionInterface::class);
                $suggestions = $suggestAction->execute($workspace, $toCategoryId, $month);

                throw new DomainValidationException(
                    'budget_adjustment_insufficient_funds',
                    'Insufficient funds in the selected category.',
                    [
                        'suggested_categories' => $suggestions,
                    ],
                );
            }
        });
    }
}
