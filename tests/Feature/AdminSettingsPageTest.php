<?php

use App\Models\Role;
use App\Models\User;
use App\Settings\SettingsService;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('affiche l écran des paramètres pour un super administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $this->actingAs($user)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('Paramètres système');
});

it('refuse l accès sans permission settings.manage', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)->get('/admin/settings')->assertForbidden();
});

it('enregistre les paramètres via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    Livewire::actingAs($user)
        ->test('pages::admin.settings')
        ->set('values.app_name', 'Eden Radio')
        ->set('values.rappel_actif', false)
        ->set('values.rappel_jours_avant', 7)
        ->set('values.ping_interval_secondes', 45)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsService::class)->get('app_name'))->toBe('Eden Radio')
        ->and(app(SettingsService::class)->get('rappel_actif'))->toBeFalse();

    $this->assertDatabaseHas('audit_logs', ['action' => 'settings.update']);
});

it('refuse une valeur invalide via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    Livewire::actingAs($user)
        ->test('pages::admin.settings')
        ->set('values.rappel_jours_avant', 'abc')
        ->call('save')
        ->assertHasErrors('values.rappel_jours_avant');
});
