<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertPushDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'string', 'max:255'],
            'fcm_token' => ['required', 'string', 'max:1024'],
            'platform' => ['required', 'string', 'in:ios,android,web'],
            'notification_permission' => ['sometimes', 'string', 'in:granted,denied,not_determined'],
        ];
    }
}
