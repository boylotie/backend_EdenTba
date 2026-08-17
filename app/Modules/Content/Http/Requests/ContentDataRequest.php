<?php

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Storage\AudioStorage;
use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Base commune aux créations/mises à jour de contenus (US-023) : métadonnées,
 * rattachement organisationnel optionnel et cohérence du rattachement (A2).
 */
abstract class ContentDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles du fichier audio, communes à la création (requis) et la mise à
     * jour (optionnel) : extensions autorisées et taille max du paramètre
     * système audio_max_upload_mb.
     *
     * @return array<int, string>
     */
    public function fileRules(): array
    {
        $maxKb = (int) app(SettingsService::class)->get('audio_max_upload_mb') * 1024;

        return ['file', 'extensions:'.implode(',', AudioStorage::allowedExtensions()), "max:{$maxKb}"];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'speaker' => ['nullable', 'string', 'max:255'],
            'speaker_id' => ['nullable', 'integer', 'exists:speakers,id'],
            'year_id' => ['nullable', 'integer', 'exists:years,id'],
            'month_id' => ['nullable', 'integer', 'exists:months,id'],
            'week_id' => ['nullable', 'integer', 'exists:weeks,id'],
            'special_activity_id' => ['nullable', 'integer', 'exists:special_activities,id'],
            'day_of_week' => ['nullable', 'integer', 'between:1,7'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'approval_comment' => ['nullable', 'string', 'max:5000'],
            'approved_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Cohérence du rattachement (A2) : chaque entité fournie doit appartenir à
     * la même année que les autres ; une activité doit correspondre à la
     * semaine fournie.
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
