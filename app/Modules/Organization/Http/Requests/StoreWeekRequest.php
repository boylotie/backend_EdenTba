<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Year;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeekRequest extends FormRequest
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
        $year = $this->route('year');

        if (! $year instanceof Year) {
            abort(404);
        }

        return [
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique('weeks', 'label')->where(fn ($query) => $query->where('year_id', $year->id)),
            ],
        ];
    }
}
