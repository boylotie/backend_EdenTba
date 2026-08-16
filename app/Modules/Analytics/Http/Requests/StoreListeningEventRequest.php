<?php

namespace App\Modules\Analytics\Http\Requests;

use App\Modules\Analytics\Models\ListeningEvent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement d'un événement d'écoute anonymisé (MOD-12-P1, US-048).
 */
class StoreListeningEventRequest extends FormRequest
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
            'content_id' => ['required', 'integer', 'exists:contents,id'],
            'event_type' => ['required', 'string', 'in:'.implode(',', ListeningEvent::eventTypes())],
            'position_seconds' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
