<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\SpecialActivities\Models\ActivityType;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('affiche l écran des types pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/admin/activity-types')
        ->assertOk()
        ->assertSee('Types d\'activités');
});

it('refuse l accès sans permission special_activity.manage', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $permission = Permission::where('name', 'special_activity.manage')->firstOrFail();
    $role->permissions()->detach($permission->id);

    $this->actingAs($user)->get('/admin/activity-types')->assertForbidden();
});

it('crée un type via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    Livewire::actingAs($user)
        ->test('pages::admin.activity-types')
        ->set('code', 'seminar')
        ->set('label', 'Séminaire')
        ->call('createType')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('activity_types', ['code' => 'seminar', 'label' => 'Séminaire']);
});

it('désactive un type via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $type = ActivityType::create(['code' => 'prayer', 'label' => 'Prière']);

    Livewire::actingAs($user)
        ->test('pages::admin.activity-types')
        ->call('toggleActive', $type->id);

    $this->assertDatabaseHas('activity_types', ['id' => $type->id, 'is_active' => false]);
});

it('modifie un type via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $type = ActivityType::create(['code' => 'prayer', 'label' => 'Prière']);

    Livewire::actingAs($user)
        ->test('pages::admin.activity-types')
        ->call('startEdit', $type->id)
        ->set('editCode', 'seminar')
        ->set('editLabel', 'Séminaire')
        ->call('updateType')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('activity_types', ['id' => $type->id, 'code' => 'seminar', 'label' => 'Séminaire']);
});
