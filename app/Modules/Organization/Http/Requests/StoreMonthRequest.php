<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Year;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonthRequest extends FormRequest
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
            'month_number' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('months', 'month_number')->where(fn ($query) => $query->where('year_id', $year->id)),
            ],
            'theme' => ['nullable', 'string', 'max:255'],
        ];
    }
}
