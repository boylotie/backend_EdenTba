<?php

namespace App\Modules\SpecialActivities\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecialActivityRequest extends FormRequest
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
            'week_id' => ['required', 'integer', 'exists:weeks,id'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id', Rule::exists('activity_types', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'string', 'in:replace,complement'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
