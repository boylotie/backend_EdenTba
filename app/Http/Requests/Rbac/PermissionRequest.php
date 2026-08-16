<?php

namespace App\Http\Requests\Rbac;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($this->route('permission'))],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
