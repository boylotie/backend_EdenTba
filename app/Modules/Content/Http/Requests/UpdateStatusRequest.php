<?php

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Models\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transition de statut d'un contenu (US-025). Le statut cible doit être un
 * statut connu ; la matrice des transitions est appliquée dans le service.
 * Programmer une publication (US-026) exige une date future `scheduled_at`.
 */
class UpdateStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(Content::statuses())],
            'scheduled_at' => ['required_if:status,'.Content::STATUS_SCHEDULED, 'nullable', 'date', 'after:now'],
        ];
    }
}
