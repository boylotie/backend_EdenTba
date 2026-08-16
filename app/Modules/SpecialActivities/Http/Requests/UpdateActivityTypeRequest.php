<?php

namespace App\Modules\SpecialActivities\Http\Requests;

use App\Modules\SpecialActivities\Models\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityTypeRequest extends FormRequest
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
        $type = $this->route('activity_type');

        if (! $type instanceof ActivityType) {
            abort(404);
        }

        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/', Rule::unique('activity_types', 'code')->ignore($type->id)],
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
