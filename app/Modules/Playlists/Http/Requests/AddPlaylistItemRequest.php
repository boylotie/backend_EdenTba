<?php

namespace App\Modules\Playlists\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajout d'un contenu à une playlist (US-036). Les règles métier (contenu
 * publié, doublon, position occupée) sont appliquées dans le service.
 */
class AddPlaylistItemRequest extends FormRequest
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
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
