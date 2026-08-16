<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Week;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWeekRequest extends FormRequest
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
        $week = $this->route('week');

        if (! $week instanceof Week) {
            abort(404);
        }

        return [
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique('weeks', 'label')
                    ->where(fn ($query) => $query->where('year_id', $week->year_id))
                    ->ignore($week->id),
            ],
        ];
    }
}
