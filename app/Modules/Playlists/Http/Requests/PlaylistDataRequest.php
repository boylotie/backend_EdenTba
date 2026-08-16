<?php

namespace App\Modules\Playlists\Http\Requests;

use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Base commune aux créations/mises à jour de playlists (US-036) : titre,
 * visibilité publique et rattachement organisationnel optionnel, avec
 * cohérence du rattachement (même année, activité rattachée à la semaine).
 */
abstract class PlaylistDataRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_public' => ['nullable', 'boolean'],
            'special_activity_id' => ['nullable', 'integer', 'exists:special_activities,id'],
            'year_id' => ['nullable', 'integer', 'exists:years,id'],
            'month_id' => ['nullable', 'integer', 'exists:months,id'],
            'week_id' => ['nullable', 'integer', 'exists:weeks,id'],
        ];
    }

    /**
     * Cohérence du rattachement : chaque entité fournie doit appartenir à la
     * même année que les autres ; une activité doit correspondre à la semaine
     * fournie.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $data = $validator->validated();

                $year = isset($data['year_id']) ? Year::query()->whereKey((int) $data['year_id'])->first() : null;
                $month = isset($data['month_id']) ? Month::query()->whereKey((int) $data['month_id'])->first() : null;
                $week = isset($data['week_id']) ? Week::query()->whereKey((int) $data['week_id'])->first() : null;
                $activity = isset($data['special_activity_id']) ? SpecialActivity::query()->whereKey((int) $data['special_activity_id'])->first() : null;

                $effectiveYearId = $year->id
                    ?? $week->year_id
                    ?? $month->year_id
                    ?? $activity?->week?->year_id;

                if ($month !== null && $month->year_id !== $effectiveYearId) {
                    $validator->errors()->add('month_id', "Le mois n'appartient pas à l'année indiquée.");
                }

                if ($week !== null && $week->year_id !== $effectiveYearId) {
                    $validator->errors()->add('week_id', "La semaine n'appartient pas à l'année indiquée.");
                }

                if ($activity !== null && $week !== null && $activity->week_id !== $week->id) {
                    $validator->errors()->add('special_activity_id', "L'activité n'est pas rattachée à la semaine indiquée.");
                }

                if ($activity !== null && $effectiveYearId !== null && $activity->week?->year_id !== $effectiveYearId) {
                    $validator->errors()->add('special_activity_id', "L'activité n'appartient pas à l'année indiquée.");
                }
            },
        ];
    }
}
