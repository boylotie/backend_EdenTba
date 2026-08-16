<?php

use App\Models\Role;
use App\Models\User;
use App\Settings\SettingsService;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function reminderAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function reminderToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function reminderPayload(): array
{
    return [
        'rappel_actif' => false,
        'rappel_jours_avant' => 7,
        'rappel_heure_programme' => '09:30',
        'rappel_inactivite_jours' => 15,
    ];
}

it('refuse la consultation sans authentification', function () {
    $this->getJson('/api/v1/settings/reminders')->assertStatus(401);
});

it('liste les paramètres de rappel par défaut (A2)', function () {
    $this->withToken(reminderToken(reminderAdmin()))
        ->getJson('/api/v1/settings/reminders')
        ->assertOk()
        ->assertJsonCount(4, 'data.reminders')
        ->assertJsonPath('data.reminders.rappel_actif', true)
        ->assertJsonPath('data.reminders.rappel_jours_avant', 3)
        ->assertJsonPath('data.reminders.rappel_heure_programme', '08:00')
        ->assertJsonPath('data.reminders.rappel_inactivite_jours', 30)
        ->assertJsonMissingPath('data.reminders.app_name');
});

it('modifie la configuration des rappels avec permission', function () {
    $this->withToken(reminderToken(reminderAdmin()))
        ->putJson('/api/v1/settings/reminders', ['reminders' => reminderPayload()])
        ->assertOk()
        ->assertJsonPath('data.reminders.rappel_actif', false)
        ->assertJsonPath('data.reminders.rappel_jours_avant', 7);

    expect(app(SettingsService::class)->get('rappel_actif'))->toBeFalse()
        ->and(app(SettingsService::class)->get('rappel_jours_avant'))->toBe(7);
});

it('préserve les paramètres hors rappel', function () {
    app(SettingsService::class)->replace([
        'app_name' => 'Eden Radio',
        'rappel_actif' => true,
        'rappel_jours_avant' => 3,
        'ping_interval_secondes' => 45,
    ]);

    $this->withToken(reminderToken(reminderAdmin()))
        ->putJson('/api/v1/settings/reminders', ['reminders' => reminderPayload()])
        ->assertOk();

    expect(app(SettingsService::class)->get('app_name'))->toBe('Eden Radio')
        ->and(app(SettingsService::class)->get('ping_interval_secondes'))->toBe(45);
});

it('journalise la modification', function () {
    $admin = reminderAdmin();

    $this->withToken(reminderToken($admin))
        ->putJson('/api/v1/settings/reminders', ['reminders' => reminderPayload()])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'settings.reminders.update',
        'actor_id' => $admin->id,
    ]);
});

it('rejette une valeur invalide (422, A1)', function () {
    $this->withToken(reminderToken(reminderAdmin()))
        ->putJson('/api/v1/settings/reminders', ['reminders' => [
            'rappel_actif' => true,
            'rappel_jours_avant' => 'invalide',
        ]])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['reminders.rappel_jours_avant']]]);
});

it('rejette une clé hors rappel (422)', function () {
    $this->withToken(reminderToken(reminderAdmin()))
        ->putJson('/api/v1/settings/reminders', ['reminders' => array_merge(reminderPayload(), [
            'app_name' => 'Eden Radio',
        ])])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['reminders']]]);
});

it('exige chaque clé de rappel (422)', function () {
    $this->withToken(reminderToken(reminderAdmin()))
        ->putJson('/api/v1/settings/reminders', ['reminders' => ['rappel_actif' => true]])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['reminders.rappel_jours_avant']]]);
});

it('refuse l accès sans permission (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->getJson('/api/v1/settings/reminders')
        ->assertForbidden();

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->putJson('/api/v1/settings/reminders', ['reminders' => reminderPayload()])
        ->assertForbidden();
});
