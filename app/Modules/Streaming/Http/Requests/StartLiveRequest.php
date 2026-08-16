<?php

namespace App\Modules\Streaming\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Démarrage d'une session de direct (MOD-11-P2, US-046). Métadonnées
 * optionnelles ; le flux lui-même n'est jamais envoyé via l'API.
 */
class StartLiveRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
