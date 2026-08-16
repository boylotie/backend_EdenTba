<?php

namespace App\Modules\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de lecture publique des contenus (US-024). Chaque rattachement
 * fourni doit exister ; une combinaison incohérente ou un identifiant invalide
 * est rejeté en 422 (A2).
 */
class ContentIndexRequest extends FormRequest
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
            'year' => ['nullable', 'integer', 'exists:years,id'],
            'month' => ['nullable', 'integer', 'exists:months,id'],
            'week' => ['nullable', 'integer', 'exists:weeks,id'],
            'activity' => ['nullable', 'integer', 'exists:special_activities,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
