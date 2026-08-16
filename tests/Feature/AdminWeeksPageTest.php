<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('affiche l écran des semaines pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $year = Year::create(['label' => '2026-2027']);

    $this->actingAs($user)
        ->get("/admin/years/{$year->id}/weeks")
        ->assertOk()
        ->assertSee('Semaines')
        ->assertSee('2026-2027');
});

it('refuse l accès sans permission schedule.manage', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $year = Year::create(['label' => '2026-2027']);

    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $scheduleManage = Permission::where('name', 'schedule.manage')->firstOrFail();
    $role->permissions()->detach($scheduleManage->id);

    $this->actingAs($user)->get("/admin/years/{$year->id}/weeks")->assertForbidden();
});

it('crée une semaine via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = Year::create(['label' => '2026-2027']);

    Livewire::actingAs($user)
        ->test('pages::admin.weeks', ['year' => $year])
        ->set('label', 'Semaine 1')
        ->call('createWeek')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('weeks', ['year_id' => $year->id, 'label' => 'Semaine 1']);
});

it('rejette un doublon via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = Year::create(['label' => '2026-2027']);
    Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    Livewire::actingAs($user)
        ->test('pages::admin.weeks', ['year' => $year])
        ->set('label', 'Semaine 1')
        ->call('createWeek')
        ->assertHasErrors('label');
});

it('supprime une semaine via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    Livewire::actingAs($user)
        ->test('pages::admin.weeks', ['year' => $year])
        ->set('deleteTarget', $week->id)
        ->call('deleteWeek');

    $this->assertDatabaseMissing('weeks', ['id' => $week->id]);
});

it('modifie une semaine via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    Livewire::actingAs($user)
        ->test('pages::admin.weeks', ['year' => $year])
        ->call('startEdit', $week->id)
        ->set('editLabel', 'Semaine 2')
        ->call('updateWeek')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('weeks', ['id' => $week->id, 'label' => 'Semaine 2']);
});
