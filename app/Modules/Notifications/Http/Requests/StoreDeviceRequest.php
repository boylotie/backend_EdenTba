<?php

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement d'un token d'appareil (US-039).
 */
class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ];
    }
}
