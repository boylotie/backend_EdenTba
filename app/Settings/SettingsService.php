<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Ensemble des paramètres effectifs (enregistrés ou valeurs par défaut),
     * mis en cache. Cache invalidé à chaque modification.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $saved = [];

            foreach (Setting::query()->get() as $setting) {
                $saved[$setting->key] = $setting->value;
            }

            $merged = [];

            foreach (SettingsDefinition::keys() as $key => $definition) {
                $merged[$key] = array_key_exists($key, $saved) ? $saved[$key] : $definition['default'];
            }

            return $merged;
        });
    }

    /**
     * Valeur d'un paramètre ; valeur par défaut si absent (scénario A1).
     */
    public function get(string $key): mixed
    {
        $definition = SettingsDefinition::key($key);

        if ($definition === null) {
            return null;
        }

        $values = $this->all();

        return array_key_exists($key, $values)
            ? $values[$key]
            : $definition['default'];
    }

    /**
     * Remplacement complet des paramètres déclarés (sémantique PUT).
     *
     * @param  array<string, mixed>  $values
     */
    public function replace(array $values): void
    {
        foreach (SettingsDefinition::keys() as $key => $definition) {
            $typed = $this->cast($values[$key] ?? $definition['default'], $definition['type']);

            Setting::updateOrCreate(['key' => $key], ['value' => $typed]);
        }

        $this->flush();
    }

    /**
     * Paramètres marqués comme publics (consommables côté mobile/modules).
     *
     * @return array<string, mixed>
     */
    public function allPublic(): array
    {
        $public = [];

        foreach (SettingsDefinition::keys() as $key => $definition) {
            if ($definition['public'] ?? false) {
                $public[$key] = $this->get($key);
            }
        }

        return $public;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            SettingsDefinition::TYPE_INTEGER => (int) $value,
            SettingsDefinition::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }
}
