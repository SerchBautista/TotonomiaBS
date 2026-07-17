<?php

namespace App\Http\Requests\Expense;

use App\Rules\ValidCategoryForWorkspace;
use App\Rules\ValidPaymentMethodForWorkspace;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
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
                'sometimes',
                'nullable',
                'uuid',
                $this->paymentInstrumentExistsRule(),
                new ValidPaymentMethodForWorkspace(
                    $this->route('workspace'),
                    (string) ($this->input('payment_type') ?? $this->route('expense')?->payment_type)
                ),
            ],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'date' => ['sometimes', 'date', function (string $attribute, mixed $value, \Closure $fail) {
                $timezone = $this->user()?->timezone ?? 'UTC';
                $today = Carbon::now($timezone)->toDateString();

                if ($value > $today) {
                    $fail(__('api.validation.date_not_in_future', [
                        'attribute' => trans('validation.attributes.date'),
                    ]));
                }
            }],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'paid_by_user_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('workspace_user', 'user_id')
                    ->where('workspace_id', $this->route('workspace')?->id),
            ],
        ];
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
