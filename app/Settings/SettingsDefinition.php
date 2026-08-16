<?php

namespace App\Settings;

final class SettingsDefinition
{
    public const TYPE_STRING = 'string';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const GROUP_NOTIFICATIONS = 'Notifications';

    /**
     * Définition des paramètres métier configurables.
     *
     * Seuls les paramètres prévus comme paramétrables par les modules sont
     * déclarés ici. Une clé non déclarée est rejetée (pas de configuration
     * dynamique inutile).
     *
     * @return array<string, array{type: string, default: mixed, label: string, group: string, public?: bool, min?: int, max?: int, format?: string, secret?: bool}>
     */
    public static function keys(): array
    {
        return [
            'app_name' => [
                'type' => self::TYPE_STRING,
                'default' => 'Eden TBA',
                'label' => "Nom de l'application",
                'group' => 'Général',
                'public' => true,
            ],
            'rappel_actif' => [
                'type' => self::TYPE_BOOLEAN,
                'default' => true,
                'label' => 'Rappels automatiques activés',
                'group' => 'Notifications',
                'public' => true,
            ],
            'rappel_jours_avant' => [
                'type' => self::TYPE_INTEGER,
                'default' => 3,
                'label' => 'Rappel — jours avant',
                'group' => 'Notifications',
                'public' => true,
                'min' => 0,
                'max' => 365,
            ],
            'rappel_heure_programme' => [
                'type' => self::TYPE_STRING,
                'default' => '08:00',
                'label' => 'Rappel — heure fixe (programmes)',
                'group' => 'Notifications',
                'public' => true,
                'format' => 'H:i',
            ],
            'rappel_inactivite_jours' => [
                'type' => self::TYPE_INTEGER,
                'default' => 30,
                'label' => 'Rappel — délai d\'inactivité (jours)',
                'group' => 'Notifications',
                'public' => true,
                'min' => 1,
                'max' => 365,
            ],
            'ping_interval_secondes' => [
                'type' => self::TYPE_INTEGER,
                'default' => 30,
                'label' => 'Intervalle de ping (secondes)',
                'group' => 'Live',
                'min' => 5,
                'max' => 600,
            ],
            'stream_url_base' => [
                'type' => self::TYPE_STRING,
                'default' => 'https://stream.domaine.tld/live/audio',
                'label' => 'Base publique du flux live (sans jeton)',
                'group' => 'Live',
            ],
            'stream_source_url' => [
                'type' => self::TYPE_STRING,
                'default' => 'http://stream.domaine.tld:8000/live',
                'label' => 'URL de la source du serveur de diffusion (encodeur)',
                'group' => 'Live',
            ],
            'stream_source_password' => [
                'type' => self::TYPE_STRING,
                'default' => 'changeme',
                'label' => 'Mot de passe de la source du serveur de diffusion',
                'group' => 'Live',
                'secret' => true,
            ],
            'audio_max_upload_mb' => [
                'type' => self::TYPE_INTEGER,
                'default' => 500,
                'label' => 'Taille maximale d\'un fichier audio (Mo)',
                'group' => 'Contenus audio',
                'min' => 1,
                'max' => 4096,
            ],
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::keys());
    }

    /**
     * Clés des rappels (MOD-10) : préfixe `rappel_`. Ces paramètres sont
     * exposés et modifiés via `GET/PUT /api/v1/settings/reminders`.
     *
     * @return list<string>
     */
    public static function reminderKeys(): array
    {
        return array_values(array_filter(
            array_keys(self::keys()),
            static fn (string $key): bool => str_starts_with($key, 'rappel_'),
        ));
    }

    /**
     * Règles de validation d'une clé déclarée ; clé inconnue ⇒ aucune règle.
     *
     * @return list<mixed>
     */
    public static function rulesForKey(string $key): array
    {
        $definition = self::key($key);

        return $definition === null ? [] : self::rulesFor($definition);
    }

    /**
     * @return array{type: string, default: mixed, label: string, group: string, public?: bool, min?: int, max?: int, format?: string}|null
     */
    public static function key(string $key): ?array
    {
        return self::keys()[$key] ?? null;
    }

    /**
     * Clés groupées pour l'interface d'administration.
     *
     * @return array<string, array<string, array{type: string, default: mixed, label: string, group: string, public?: bool, min?: int, max?: int, format?: string}>>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (self::keys() as $key => $definition) {
            $groups[$definition['group']][$key] = $definition;
        }

        return $groups;
    }

    /**
     * Règles de validation applicables à chaque clé déclarée.
     *
     * @return array<string, list<mixed>>
     */
    public static function validationRules(): array
    {
        $rules = [];

        foreach (self::keys() as $key => $definition) {
            $rules[$key] = self::rulesFor($definition);
        }

        return $rules;
    }

    /**
     * @param  array{type: string, default: mixed, label: string, group: string, public?: bool, min?: int, max?: int, format?: string}  $definition
     * @return list<mixed>
     */
    public static function rulesFor(array $definition): array
    {
        $rules = ['required'];

        $rules[] = match ($definition['type']) {
            self::TYPE_INTEGER => 'integer',
            self::TYPE_BOOLEAN => 'boolean',
            default => 'string',
        };

        if ($definition['type'] === self::TYPE_STRING) {
            $rules[] = 'max:255';

            if (isset($definition['format'])) {
                $rules[] = 'date_format:'.$definition['format'];
            }
        }

        if (isset($definition['min'])) {
            $rules[] = 'min:'.$definition['min'];
        }

        if (isset($definition['max'])) {
            $rules[] = 'max:'.$definition['max'];
        }

        return $rules;
    }
}
