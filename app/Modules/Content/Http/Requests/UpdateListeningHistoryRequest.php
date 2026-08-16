<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Synchronisation de la position d'écoute (MOD-07-P5, US-035).
 */
class UpdateListeningHistoryRequest extends FormRequest
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
            'position_seconds' => ['required', 'integer', 'min:0'],
            'completed' => ['sometimes', 'boolean'],
        ];
    }
}
