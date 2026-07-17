<?php

namespace App\Http\Requests\Occurrence;

use App\Rules\ValidPaymentMethodForWorkspace;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayOccurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'payment_type' => ['required', 'string', Rule::in(['cash', 'card', 'other'])],
            'payment_instrument_id' => [
                Rule::requiredIf(fn () => in_array($this->input('payment_type'), ['card', 'other'])),
                'nullable',
                'uuid',
                new ValidPaymentMethodForWorkspace(
                    $this->route('occurrence')?->fixedExpense?->workspace,
                    (string) $this->input('payment_type')
                ),
            ],
            'paid_at' => ['required', 'date', function (string $attribute, mixed $value, \Closure $fail) {
                $timezone = $this->user()?->timezone ?? 'UTC';
                $today = Carbon::now($timezone)->toDateString();

                if ($value > $today) {
                    $fail(__('api.validation.date_not_in_future', [
                        'attribute' => trans('validation.attributes.paid_at'),
                    ]));
                }
            }],
            'paid_by_user_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('workspace_user', 'user_id')
                    ->where('workspace_id', $this->route('occurrence')->fixedExpense->workspace_id),
            ],
        ];
    }
}
