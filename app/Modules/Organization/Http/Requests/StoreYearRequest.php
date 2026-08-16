<?php

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreYearRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:255', Rule::unique('years', 'label')],
            'theme' => ['nullable', 'string', 'max:255'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
