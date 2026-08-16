<?php

use App\Models\Role;
use App\Models\User;
use App\Support\AdminNavigation;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function adminShellAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

it('redirige un utilisateur sans rôle admin hors du tableau de bord', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertRedirect(route('home'));
});

it('refuse l accès au tableau de bord sans authentification', function () {
    $this->get('/dashboard')
        ->assertRedirect(route('login'));
});

it('permet à un super administrateur d accéder au tableau de bord', function () {
    $this->actingAs(adminShellAdmin())
        ->get('/dashboard')
        ->assertOk();
});

it('affiche les modules selon les permissions', function () {
    $this->actingAs(adminShellAdmin())
        ->get('/dashboard')
        ->assertSee('Rôles & permissions')
        ->assertSee('Journal d\'audit')
        ->assertSee('Paramètres système');
});

it('masque les entrées sans permission', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSee('Rôles & permissions')
        ->assertDontSee('Paramètres système');
});

it('refuse l accès à un module sans permission', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/admin/settings')
        ->assertForbidden();
});

it('permet l accès aux modules avec permission', function () {
    $admin = adminShellAdmin();

    $this->actingAs($admin)->get('/admin/roles')->assertOk();
    $this->actingAs($admin)->get('/admin/audit-logs')->assertOk();
    $this->actingAs($admin)->get('/admin/settings')->assertOk();
});

it('filtre les groupes de navigation par permissions', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $labels = array_column(
        array_merge(...array_column(AdminNavigation::forUser($user), 'items')),
        'label',
    );

    expect($labels)->toContain('Rôles & permissions')->not->toContain('Paramètres système');
});
