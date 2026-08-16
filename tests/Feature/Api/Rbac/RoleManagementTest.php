<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function rbacRoleAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function rbacRoleToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('liste les rôles pour un super administrateur', function () {
    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('error', null)
        ->assertJsonCount(3, 'data.roles');
});

it('permet à un administrateur en lecture seule de lister les rôles', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->withToken(rbacRoleToken($user))
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonCount(3, 'data.roles');
});

it('refuse la liste des rôles sans permission', function () {
    $this->withToken(rbacRoleToken(User::factory()->create()))
        ->getJson('/api/v1/roles')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('refuse la liste des rôles sans authentification', function () {
    $this->getJson('/api/v1/roles')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('crée un rôle avec ses permissions', function () {
    $view = Permission::where('name', 'roles.view')->firstOrFail();

    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->postJson('/api/v1/roles', [
            'name' => 'moderator',
            'label' => 'Modérateur',
            'permissions' => [$view->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.role.name', 'moderator')
        ->assertJsonCount(1, 'data.role.permissions');

    $role = Role::where('name', 'moderator')->firstOrFail();

    $this->assertDatabaseHas('roles', ['name' => 'moderator']);
    $this->assertDatabaseHas('role_permission', ['role_id' => $role->id, 'permission_id' => $view->id]);
});

it('attribue une permission déjà attribuée de façon idempotente', function () {
    $view = Permission::where('name', 'roles.view')->firstOrFail();

    $role = Role::create(['name' => 'moderator']);
    $role->permissions()->sync([$view->id, $view->id]);

    expect($role->permissions()->count())->toBe(1);

    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->putJson('/api/v1/roles/'.$role->id, [
            'name' => 'moderator',
            'permissions' => [$view->id, $view->id],
        ])
        ->assertOk();

    expect($role->refresh()->permissions()->count())->toBe(1);
});

it('refuse la création dun rôle par un administrateur sans permission de création', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->withToken(rbacRoleToken($user))
        ->postJson('/api/v1/roles', ['name' => 'moderator'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');

    $this->assertDatabaseMissing('roles', ['name' => 'moderator']);
});

it('refuse la création dun rôle en doublon', function () {
    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->postJson('/api/v1/roles', ['name' => 'user'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('met à jour un rôle', function () {
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $permission = Permission::where('name', 'users.manage')->firstOrFail();

    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->putJson('/api/v1/roles/'.$role->id, [
            'name' => 'administrateur',
            'permissions' => [$permission->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.role.name', 'administrateur')
        ->assertJsonCount(1, 'data.role.permissions');

    $this->assertDatabaseHas('roles', ['name' => 'administrateur']);
});

it('supprime un rôle non utilisé', function () {
    $role = Role::create(['name' => 'moderator']);

    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->deleteJson('/api/v1/roles/'.$role->id)
        ->assertOk();

    $this->assertDatabaseMissing('roles', ['name' => 'moderator']);
});

it('refuse la suppression du rôle super administrateur', function () {
    $role = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();

    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->deleteJson('/api/v1/roles/'.$role->id)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'role_locked');

    $this->assertDatabaseHas('roles', ['name' => Role::SUPER_ADMIN]);
});

it('refuse la suppression dun rôle attribué à un utilisateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $role = Role::where('name', Role::ADMIN)->firstOrFail();

    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->deleteJson('/api/v1/roles/'.$role->id)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'role_in_use');

    $this->assertDatabaseHas('roles', ['name' => Role::ADMIN]);
});

it('journalise la création dun rôle', function () {
    $this->withToken(rbacRoleToken(rbacRoleAdmin()))
        ->postJson('/api/v1/roles', ['name' => 'moderator'])
        ->assertCreated();

    $role = Role::where('name', 'moderator')->firstOrFail();

    $this->assertDatabaseHas('audit_logs', ['action' => 'roles.create', 'entity_id' => (string) $role->id]);
});
