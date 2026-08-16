<?php

namespace App\Http\Requests\Settings;

use App\Settings\SettingsDefinition;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour de la configuration des rappels (MOD-10-P1, US-042).
 *
 * Sémantique de remplacement complet des seules clés de rappel (`rappel_*`) :
 * toutes requises et typées ; toute clé hors rappel (ou inconnue) est rejetée
 * (422). Les autres paramètres système ne sont pas touchés.
 */
class ReminderSettingsUpdateRequest extends FormRequest
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
        $rules = [
            'reminders' => [
                'present',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    foreach (array_keys((array) $value) as $key) {
                        if (! in_array((string) $key, SettingsDefinition::reminderKeys(), true)) {
                            $fail("La clé de paramètre [{$key}] est inconnue.");
                        }
                    }
                },
            ],
        ];

        foreach (SettingsDefinition::reminderKeys() as $key) {
            $rules['reminders.'.$key] = SettingsDefinition::rulesForKey($key);
        }

        return $rules;
    }
}
