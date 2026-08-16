<?php

namespace App\Modules\Content\Http\Requests;

class StoreContentRequest extends ContentDataRequest
{
    /**
     * Le fichier audio est requis à la création (US-023).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'file' => array_merge(['required'], $this->fileRules()),
        ]);
    }
}
