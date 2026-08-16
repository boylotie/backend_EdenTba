<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function rbacUserRoleAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function rbacUserRoleToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('attribue un rôle à un utilisateur', function () {
    $admin = rbacUserRoleAdmin();
    $target = User::factory()->create();

    $this->withToken(rbacUserRoleToken($admin))
        ->putJson('/api/v1/users/'.$target->id.'/roles', ['roles' => [Role::ADMIN]])
        ->assertOk()
        ->assertJsonCount(1, 'data.user.roles');

    $this->assertDatabaseHas('role_user', [
        'user_id' => $target->id,
        'role_id' => Role::where('name', Role::ADMIN)->firstOrFail()->id,
    ]);
});

it('remplace les rôles existants', function () {
    $admin = rbacUserRoleAdmin();
    $target = User::factory()->create();
    $target->assignRole(Role::ADMIN);

    $this->withToken(rbacUserRoleToken($admin))
        ->putJson('/api/v1/users/'.$target->id.'/roles', ['roles' => [Role::USER]])
        ->assertOk();

    expect($target->refresh()->hasRole(Role::ADMIN))->toBeFalse();
    expect($target->hasRole(Role::USER))->toBeTrue();
});

it('retire tous les rôles à un utilisateur', function () {
    $admin = rbacUserRoleAdmin();
    $target = User::factory()->create();
    $target->assignRole(Role::ADMIN);

    $this->withToken(rbacUserRoleToken($admin))
        ->putJson('/api/v1/users/'.$target->id.'/roles', ['roles' => []])
        ->assertOk();

    expect($target->refresh()->roles()->count())->toBe(0);
});

it('refuse dattribuer un rôle inconnu', function () {
    $admin = rbacUserRoleAdmin();

    $this->withToken(rbacUserRoleToken($admin))
        ->putJson('/api/v1/users/'.User::factory()->create()->id.'/roles', ['roles' => ['inexistant']])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('refuse lattribution de rôles sans permission', function () {
    $target = User::factory()->create();

    $this->withToken(rbacUserRoleToken($target))
        ->putJson('/api/v1/users/'.$target->id.'/roles', ['roles' => [Role::ADMIN]])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('refuse lattribution de rôles sans authentification', function () {
    $this->putJson('/api/v1/users/1/roles', ['roles' => [Role::ADMIN]])
        ->assertUnauthorized();
});

it('refuse de retirer le dernier super administrateur', function () {
    // Le seed crée un super administrateur permanent (superadmin@example.com) :
    // c'est le dernier, retirer son rôle doit être refusé.
    $seeded = User::where('email', 'superadmin@example.com')->firstOrFail();

    $this->withToken(rbacUserRoleToken($seeded))
        ->putJson('/api/v1/users/'.$seeded->id.'/roles', ['roles' => [Role::USER]])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'last_super_admin');

    expect($seeded->refresh()->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

it('autorise le retrait du super administrateur sil en reste un autre', function () {
    $admin = rbacUserRoleAdmin();
    $other = User::factory()->create();
    $other->assignRole(Role::SUPER_ADMIN);

    $this->withToken(rbacUserRoleToken($admin))
        ->putJson('/api/v1/users/'.$admin->id.'/roles', ['roles' => [Role::USER]])
        ->assertOk();

    expect($admin->refresh()->hasRole(Role::SUPER_ADMIN))->toBeFalse();
    expect($other->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

it('journalise lattribution de rôles', function () {
    $admin = rbacUserRoleAdmin();
    $target = User::factory()->create();

    $this->withToken(rbacUserRoleToken($admin))
        ->putJson('/api/v1/users/'.$target->id.'/roles', ['roles' => [Role::USER]])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', ['action' => 'users.roles.update', 'actor_id' => $admin->id, 'entity_id' => (string) $target->id]);
});
