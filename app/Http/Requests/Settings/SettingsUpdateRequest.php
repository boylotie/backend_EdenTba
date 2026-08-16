<?php

namespace App\Http\Requests\Settings;

use App\Settings\SettingsDefinition;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sémantique de remplacement complet : toutes les clés déclarées sont
     * requises et typées ; toute clé inconnue est rejetée (422).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'settings' => [
                'present',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    foreach (array_keys((array) $value) as $key) {
                        if (! SettingsDefinition::has((string) $key)) {
                            $fail("La clé de paramètre [{$key}] est inconnue.");
                        }
                    }
                },
            ],
        ];

        foreach (SettingsDefinition::validationRules() as $key => $keyRules) {
            $rules['settings.'.$key] = $keyRules;
        }

        return $rules;
    }
}
