<?php

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'alert_threshold' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999.99'],
            'alert_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $budget = $this->route('budget');
            $amount = (float) ($this->input('amount') ?? $budget?->amount ?? 0);
            $threshold = $this->input('alert_threshold');

            if ($threshold !== null && (float) $threshold > $amount) {
                $v->errors()->add('alert_threshold', 'The alert amount cannot exceed the budget amount.');
            }
        });
    }
}
