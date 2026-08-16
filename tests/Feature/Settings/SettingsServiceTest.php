<?php

use App\Settings\SettingsService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('retourne la valeur par défaut pour un paramètre inexistant', function () {
    expect(app(SettingsService::class)->get('rappel_jours_avant'))->toBe(3);
});

it('retourne null pour une clé non déclarée', function () {
    expect(app(SettingsService::class)->get('inconnu'))->toBeNull();
});

it('remplace et type les valeurs', function () {
    app(SettingsService::class)->replace([
        'app_name' => 'Eden Radio',
        'rappel_actif' => false,
        'rappel_jours_avant' => 7,
        'ping_interval_secondes' => 45,
    ]);

    expect(app(SettingsService::class)->get('app_name'))->toBe('Eden Radio')
        ->and(app(SettingsService::class)->get('rappel_actif'))->toBeFalse()
        ->and(app(SettingsService::class)->get('rappel_jours_avant'))->toBe(7)
        ->and(app(SettingsService::class)->get('ping_interval_secondes'))->toBe(45);
});

it('invalide le cache après une modification', function () {
    app(SettingsService::class)->all();

    Cache::spy();

    app(SettingsService::class)->replace([
        'app_name' => 'Eden Radio',
        'rappel_actif' => true,
        'rappel_jours_avant' => 7,
        'ping_interval_secondes' => 45,
    ]);

    Cache::shouldHaveReceived('forget')->with('settings.all')->once();
});

it('expose uniquement les paramètres publics', function () {
    $public = app(SettingsService::class)->allPublic();

    expect(array_keys($public))->toContain('app_name')
        ->and($public)->not->toHaveKey('ping_interval_secondes');
});
