<?php

namespace App\Modules\Playlists\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Réordonnancement complet d'une playlist (US-036) : la liste des identifiants
 * de contenus dans l'ordre voulu. Le service vérifie que l'ensemble correspond
 * exactement aux contenus présents.
 */
class ReorderPlaylistItemsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'distinct'],
            'items.*' => ['required', 'integer', 'exists:contents,id'],
        ];
    }
}
