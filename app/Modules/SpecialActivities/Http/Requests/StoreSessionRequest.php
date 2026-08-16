<?php

namespace App\Modules\SpecialActivities\Http\Requests;

use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Modules\SpecialActivities\Support\SessionSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSessionRequest extends FormRequest
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
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'place' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $activity = $this->route('activity');

                if (! $activity instanceof SpecialActivity) {
                    return;
                }

                if ($validator->errors()->hasAny(['day_of_week', 'start_time', 'duration_minutes'])) {
                    return;
                }

                if (SessionSchedule::hasOverlap(
                    $activity,
                    null,
                    (int) $this->input('day_of_week'),
                    (string) $this->input('start_time'),
                    (int) $this->input('duration_minutes'),
                )) {
                    $validator->errors()->add('start_time', 'Cette session chevauche une autre session de la même activité.');
                }
            },
        ];
    }
}
