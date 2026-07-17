<?php

namespace App\Http\Requests\FixedExpense;

use App\Rules\ValidCategoryForWorkspace;
use App\Rules\ValidPaymentMethodForWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFixedExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => [
                'sometimes',
                'uuid',
                'exists:categories,id',
                new ValidCategoryForWorkspace($this->route('workspace'), $this->user()?->id),
            ],
            'payment_type' => ['sometimes', 'string', Rule::in(['cash', 'card', 'other'])],
            'payment_instrument_id' => [
                Rule::requiredIf(fn () => in_array($this->input('payment_type'), ['card', 'other'])),
                'nullable',
                'uuid',
                $this->paymentInstrumentExistsRule(),
                new ValidPaymentMethodForWorkspace(
                    $this->route('workspace'),
                    (string) ($this->input('payment_type') ?? $this->route('fixedExpense')?->payment_type)
                ),
            ],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'frequency' => ['sometimes', 'string', 'in:daily,weekly,monthly,yearly'],
            'next_due_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'alert_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:next_due_date'],
            'reminders_enabled' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'string', Rule::in(['recurring', 'installment'])],
            'total_installments' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:999',
            ],
            'remaining_installments' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:999',
                'lte:total_installments',
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $fixedExpense = $this->route('fixedExpense');

            if ($fixedExpense !== null && $fixedExpense->occurrences()->where('status', 'paid')->exists()) {
                $validator->errors()->add(
                    'fixed_expense',
                    __('api.fixed_expenses.cannot_update_with_paid_occurrences'),
                );
            }
        }];
    }

    private function paymentInstrumentExistsRule(): \Illuminate\Validation\Rules\Exists|string
    {
        return match ($this->input('payment_type')) {
            'card' => Rule::exists('cards', 'id')->whereNull('deleted_at'),
            'other' => Rule::exists('other_payment_methods', 'id')->whereNull('deleted_at'),
            default => 'nullable',
        };
    }
}
