<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ReminderSettingsUpdateRequest;
use App\Models\Setting;
use App\Settings\SettingsDefinition;
use App\Settings\SettingsService;
use App\Shared\Api\ApiResponse;
use App\Shared\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * Configuration des rappels (MOD-10-P1, US-042) : expose et met à jour les
 * seuls paramètres `rappel_*` (heures, jours, délais — jamais codés en dur).
 *
 * Permission `settings.manage` (même modèle que les autres paramètres). La
 * mise à jour fusionne les valeurs validées dans l'existant : les paramètres
 * hors rappel sont préservés.
 */
class ReminderSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return ApiResponse::success([
            'reminders' => $this->reminderValues(),
        ]);
    }

    public function update(ReminderSettingsUpdateRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $values = $request->validated('reminders');

        $service = app(SettingsService::class);
        $service->replace(array_merge($service->all(), $values));

        AuditLogger::log('settings.reminders.update', ['keys' => array_keys($values)]);

        return ApiResponse::success([
            'reminders' => $this->reminderValues(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reminderValues(): array
    {
        return Arr::only(app(SettingsService::class)->all(), SettingsDefinition::reminderKeys());
    }
}
