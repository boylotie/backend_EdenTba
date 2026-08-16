<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Month;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMonthRequest extends FormRequest
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
        $month = $this->route('month');

        if (! $month instanceof Month) {
            abort(404);
        }

        return [
            'month_number' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('months', 'month_number')
                    ->where(fn ($query) => $query->where('year_id', $month->year_id))
                    ->ignore($month->id),
            ],
            'theme' => ['nullable', 'string', 'max:255'],
        ];
    }
}
