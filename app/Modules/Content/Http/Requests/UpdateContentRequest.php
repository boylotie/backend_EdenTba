<?php

namespace App\Modules\Content\Http\Requests;

class UpdateContentRequest extends ContentDataRequest
{
    /**
     * Le fichier audio est optionnel à la mise à jour ; s'il est fourni, il
     * remplace le fichier existant.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'file' => array_merge(['nullable'], $this->fileRules()),
        ]);
    }
}
