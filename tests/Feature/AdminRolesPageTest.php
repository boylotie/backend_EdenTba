<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('affiche l écran des rôles pour un super administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $this->actingAs($user)
        ->get('/admin/roles')
        ->assertOk()
        ->assertSee('Rôles & permissions');
});

it('refuse l accès sans permission roles.view', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $permission = Permission::where('name', 'roles.view')->firstOrFail();
    $role->permissions()->detach($permission->id);

    $this->actingAs($user)->get('/admin/roles')->assertForbidden();
});

it('crée un rôle via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $permission = Permission::where('name', 'content.view')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::admin.roles')
        ->set('name', 'moderator')
        ->set('label', 'Modérateur')
        ->set('permissionIds', [$permission->id])
        ->call('createRole')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('roles', ['name' => 'moderator', 'label' => 'Modérateur']);
    $role = Role::where('name', 'moderator')->firstOrFail();
    expect($role->permissions()->pluck('name')->all())->toContain('content.view');
});

it('modifie un rôle via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $role = Role::create(['name' => 'moderator', 'label' => 'Modérateur']);
    $permission = Permission::where('name', 'audit.view')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::admin.roles')
        ->call('startEdit', $role->id)
        ->set('editName', 'editor')
        ->set('editLabel', 'Éditeur')
        ->set('editPermissionIds', [$permission->id])
        ->call('updateRole')
        ->assertHasNoErrors();

    $role->refresh();
    expect($role->name)->toBe('editor')
        ->and($role->label)->toBe('Éditeur')
        ->and($role->permissions()->pluck('name')->all())->toContain('audit.view');
});

it('ne peut pas supprimer le rôle super administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $superAdmin = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::admin.roles')
        ->set('deleteTarget', $superAdmin->id)
        ->call('deleteRole');

    $this->assertDatabaseHas('roles', ['id' => $superAdmin->id]);
});

it('ne peut pas supprimer un rôle encore attribué', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $role = Role::create(['name' => 'moderator']);
    User::factory()->create()->assignRole('moderator');

    Livewire::actingAs($user)
        ->test('pages::admin.roles')
        ->set('deleteTarget', $role->id)
        ->call('deleteRole');

    $this->assertDatabaseHas('roles', ['id' => $role->id]);
});

it('attribue des rôles à un utilisateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $target = User::factory()->create();
    $adminRole = Role::where('name', Role::ADMIN)->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::admin.roles')
        ->set('assignments.'.$target->id, [$adminRole->id])
        ->call('saveRoles', $target->id)
        ->assertHasNoErrors();

    expect($target->fresh()->hasRole(Role::ADMIN))->toBeTrue();
});

it('protège le dernier super administrateur', function () {
    $superAdminUser = User::where('email', 'superadmin@example.com')->firstOrFail();

    $adminRole = Role::where('name', Role::ADMIN)->firstOrFail();

    Livewire::actingAs($superAdminUser)
        ->test('pages::admin.roles')
        ->set('assignments.'.$superAdminUser->id, [$adminRole->id])
        ->call('saveRoles', $superAdminUser->id);

    expect($superAdminUser->fresh()->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

it('journalise la gestion des rôles', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    Livewire::actingAs($user)
        ->test('pages::admin.roles')
        ->set('name', 'moderator')
        ->call('createRole');

    $this->assertDatabaseHas('audit_logs', ['action' => 'roles.create']);
});
