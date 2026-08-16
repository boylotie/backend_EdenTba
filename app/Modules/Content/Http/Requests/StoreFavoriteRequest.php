<?php

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Models\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajout d'un favori (MOD-07-P5, US-034). Seuls les contenus publiés sont
 * éligibles ; ajouter deux fois le même contenu est idempotent.
 */
class StoreFavoriteRequest extends FormRequest
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
            'content_id' => [
                'required',
                'integer',
                Rule::exists('contents', 'id')->where('status', Content::STATUS_PUBLISHED),
            ],
        ];
    }
}
