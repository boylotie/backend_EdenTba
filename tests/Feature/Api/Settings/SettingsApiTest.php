<?php

use App\Models\Role;
use App\Models\User;
use App\Settings\SettingsService;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function settingsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function settingsToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function settingsPayload(): array
{
    return [
        'app_name' => 'Eden Radio',
        'rappel_actif' => false,
        'rappel_jours_avant' => 7,
        'rappel_heure_programme' => '09:30',
        'rappel_inactivite_jours' => 15,
        'ping_interval_secondes' => 45,
        'audio_max_upload_mb' => 600,
        'stream_url_base' => 'https://stream.domaine.tld/live/audio',
        'stream_source_url' => 'http://stream.domaine.tld:8000/live',
        'stream_source_password' => 'mon-pass-source',
    ];
}

it('refuse sans authentification', function () {
    $this->getJson('/api/v1/settings')->assertStatus(401);
});

it('liste les paramètres effectifs avec permission', function () {
    $this->withToken(settingsToken(settingsAdmin()))
        ->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonFragment(['app_name' => 'Eden TBA'])
        ->assertJsonFragment(['rappel_jours_avant' => 3]);
});

it('modifie les paramètres avec permission', function () {
    $this->withToken(settingsToken(settingsAdmin()))
        ->putJson('/api/v1/settings', ['settings' => settingsPayload()])
        ->assertOk()
        ->assertJsonFragment(['app_name' => 'Eden Radio'])
        ->assertJsonFragment(['rappel_jours_avant' => 7])
        ->assertJsonFragment(['audio_max_upload_mb' => 600]);

    $this->assertDatabaseHas('settings', ['key' => 'app_name']);

    expect(app(SettingsService::class)->get('app_name'))->toBe('Eden Radio')
        ->and(app(SettingsService::class)->get('rappel_actif'))->toBeFalse();
});

it('journalise la modification', function () {
    $admin = settingsAdmin();

    $this->withToken(settingsToken($admin))
        ->putJson('/api/v1/settings', ['settings' => settingsPayload()])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'settings.update',
        'actor_id' => $admin->id,
    ]);
});

it('rejette une valeur invalide (422)', function () {
    $this->withToken(settingsToken(settingsAdmin()))
        ->putJson('/api/v1/settings', ['settings' => array_merge(settingsPayload(), [
            'rappel_jours_avant' => 'invalide',
        ])])
        ->assertStatus(422);
});

it('rejette une clé inconnue (422)', function () {
    $this->withToken(settingsToken(settingsAdmin()))
        ->putJson('/api/v1/settings', ['settings' => array_merge(settingsPayload(), [
            'clé_inconnue' => 1,
        ])])
        ->assertStatus(422);
});

it('refuse l accès sans permission (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->getJson('/api/v1/settings')
        ->assertForbidden();

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->putJson('/api/v1/settings', ['settings' => settingsPayload()])
        ->assertForbidden();
});
