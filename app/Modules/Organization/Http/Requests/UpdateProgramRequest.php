<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Support\ProgramSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProgramRequest extends FormRequest
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
        $program = $this->route('program');

        if (! $program instanceof Program) {
            abort(404);
        }

        return [
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'type' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $program = $this->route('program');

                if (! $program instanceof Program) {
                    return;
                }

                if ($validator->errors()->hasAny(['day_of_week', 'start_time', 'duration_minutes'])) {
                    return;
                }

                if (ProgramSchedule::hasOverlap(
                    $program->week,
                    $program->id,
                    (int) $this->input('day_of_week'),
                    (string) $this->input('start_time'),
                    (int) $this->input('duration_minutes'),
                )) {
                    $validator->errors()->add('start_time', 'Ce programme chevauche un autre programme de la même journée.');
                }
            },
        ];
    }
}
