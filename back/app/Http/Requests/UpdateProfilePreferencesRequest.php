<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'theme' => ['required', 'in:dark,light'],
            'locale' => ['required', 'in:es,en'],
            'timezone' => ['required', 'timezone'],
        ];
    }
}
