<?php

namespace App\Modules\Speakers\Http\Requests;

use App\Modules\Speakers\Models\Speaker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpeakerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('speakers', 'name')],
            'title' => ['required', 'string', Rule::in(Speaker::titleKeys())],
            'bio' => ['nullable', 'string', 'max:5000'],
            'photo_path' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
