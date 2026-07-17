<?php

namespace App\Http\Requests\Budget;

use App\Models\Budget;
use App\Rules\ValidCategoryForWorkspace;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'id'),
                new ValidCategoryForWorkspace($this->route('workspace'), $this->user()?->id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'alert_threshold' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999.99'],
            'alert_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $workspace = $this->route('workspace');
            $categoryId = $this->input('category_id');

            if (! $workspace) {
                return;
            }

            $effectiveFrom = Carbon::now()->startOfMonth()->toDateString();

            $exists = Budget::withoutTrashed()
                ->where('workspace_id', $workspace->id)
                ->where('category_id', $categoryId)
                ->whereDate('effective_from', $effectiveFrom)
                ->exists();

            if ($exists) {
                $v->errors()->add('amount', 'A budget for this scope and month already exists.');
            }

            $amount = (float) $this->input('amount', 0);
            $threshold = $this->input('alert_threshold');

            if ($threshold !== null && (float) $threshold > $amount) {
                $v->errors()->add('alert_threshold', 'The alert amount cannot exceed the budget amount.');
            }
        });
    }
}
