<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function rbacPermissionAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function rbacPermissionToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('liste les permissions pour un super administrateur', function () {
    $this->withToken(rbacPermissionToken(rbacPermissionAdmin()))
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonCount(24, 'data.permissions');
});

it('refuse la liste des permissions sans permission', function () {
    $this->withToken(rbacPermissionToken(User::factory()->create()))
        ->getJson('/api/v1/permissions')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('refuse la liste des permissions sans authentification', function () {
    $this->getJson('/api/v1/permissions')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('crée une permission', function () {
    $this->withToken(rbacPermissionToken(rbacPermissionAdmin()))
        ->postJson('/api/v1/permissions', ['name' => 'analytics.view'])
        ->assertCreated()
        ->assertJsonPath('data.permission.name', 'analytics.view');

    $this->assertDatabaseHas('permissions', ['name' => 'analytics.view']);
});

it('refuse une permission en doublon', function () {
    $this->withToken(rbacPermissionToken(rbacPermissionAdmin()))
        ->postJson('/api/v1/permissions', ['name' => 'roles.view'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('met à jour une permission', function () {
    $permission = Permission::where('name', 'settings.manage')->firstOrFail();

    $this->withToken(rbacPermissionToken(rbacPermissionAdmin()))
        ->putJson('/api/v1/permissions/'.$permission->id, ['name' => 'settings.configure'])
        ->assertOk()
        ->assertJsonPath('data.permission.name', 'settings.configure');

    $this->assertDatabaseHas('permissions', ['name' => 'settings.configure']);
});

it('supprime une permission non utilisée', function () {
    $permission = Permission::create(['name' => 'analytics.view']);

    $this->withToken(rbacPermissionToken(rbacPermissionAdmin()))
        ->deleteJson('/api/v1/permissions/'.$permission->id)
        ->assertOk();

    $this->assertDatabaseMissing('permissions', ['name' => 'analytics.view']);
});

it('refuse la suppression dune permission utilisée', function () {
    $permission = Permission::where('name', 'roles.view')->firstOrFail();

    $this->withToken(rbacPermissionToken(rbacPermissionAdmin()))
        ->deleteJson('/api/v1/permissions/'.$permission->id)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'permission_in_use');

    $this->assertDatabaseHas('permissions', ['name' => 'roles.view']);
});

it('journalise la création dune permission', function () {
    $this->withToken(rbacPermissionToken(rbacPermissionAdmin()))
        ->postJson('/api/v1/permissions', ['name' => 'analytics.view'])
        ->assertCreated();

    $permission = Permission::where('name', 'analytics.view')->firstOrFail();

    $this->assertDatabaseHas('audit_logs', ['action' => 'permissions.create', 'entity_id' => (string) $permission->id]);
});
