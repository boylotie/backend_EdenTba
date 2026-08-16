<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Services\NotificationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour des préférences de notification (MOD-09-P4, US-041).
 *
 * Sémantique de remplacement complet : chaque type connu est requis et
 * booléen ; tout type inconnu est rejeté (422).
 */
class UpdateNotificationPreferencesRequest extends FormRequest
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
            'preferences' => [
                'present',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    foreach (array_keys((array) $value) as $key) {
                        if (! NotificationService::isKnownType((string) $key)) {
                            $fail("Le type de notification [{$key}] est inconnu.");
                        }
                    }
                },
            ],
        ];

        foreach (NotificationService::types() as $type) {
            $rules['preferences.'.$type] = ['required', 'boolean'];
        }

        return $rules;
    }
}
