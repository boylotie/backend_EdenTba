<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('affiche le journal d audit pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    AuditLog::create([
        'actor_id' => $user->id,
        'action' => 'settings.update',
        'context' => [],
    ]);

    $this->actingAs($user)
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertSee('Journal d\'audit')
        ->assertSee('settings.update');
});

it('refuse l accès au rôle admin sans permission audit.view', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)->get('/admin/audit-logs')->assertForbidden();
});

it('filtre les entrées par action', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    AuditLog::create(['actor_id' => $user->id, 'action' => 'settings.update', 'context' => []]);
    AuditLog::create(['actor_id' => $user->id, 'action' => 'roles.create', 'context' => []]);

    Livewire::actingAs($user)
        ->test('pages::admin.audit-logs')
        ->set('actionFilter', 'roles.create')
        ->assertSee('<code class="text-sm">roles.create</code>', false)
        ->assertDontSeeHtml('<code class="text-sm">settings.update</code>');
});

it('affiche l état vide', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $this->actingAs($user)
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertSee('Aucune entrée d\'audit.');
});
