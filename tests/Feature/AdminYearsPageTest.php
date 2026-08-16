<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\YearService;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('affiche l écran des années pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/admin/years')
        ->assertOk()
        ->assertSee('Années & thèmes');
});

it('refuse l accès sans permission schedule.manage', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $scheduleManage = Permission::where('name', 'schedule.manage')->firstOrFail();
    $role->permissions()->detach($scheduleManage->id);

    $this->actingAs($user)->get('/admin/years')->assertForbidden();
});

it('crée une année via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    Livewire::actingAs($user)
        ->test('pages::admin.years')
        ->set('label', '2026-2027')
        ->set('theme', 'Foi & espérance')
        ->set('makeCurrent', true)
        ->call('createYear')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('years', ['label' => '2026-2027', 'theme' => 'Foi & espérance', 'is_current' => true]);

    $year = Year::where('label', '2026-2027')->firstOrFail();
    expect($year->months()->count())->toBe(12)
        ->and($year->months()->pluck('month_number')->sort()->values()->all())->toBe(range(1, 12));
});

it('désigne une année courante via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    Year::create(['label' => '2025-2026', 'is_current' => true]);
    $second = Year::create(['label' => '2026-2027']);

    Livewire::actingAs($user)
        ->test('pages::admin.years')
        ->call('setCurrent', $second->id)
        ->assertHasNoErrors();

    expect(Year::find($second->id)->is_current)->toBeTrue()
        ->and(Year::where('label', '2025-2026')->first()->is_current)->toBeFalse();
});

it('supprime une année via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $year = Year::create(['label' => '2020-2021']);

    Livewire::actingAs($user)
        ->test('pages::admin.years')
        ->set('deleteTarget', $year->id)
        ->call('deleteYear');

    $this->assertDatabaseMissing('years', ['id' => $year->id]);
});

it('supprime une année et ses mois vides via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $year = app(YearService::class)->create(['label' => '2020-2021']);
    expect($year->months()->count())->toBe(12);

    Livewire::actingAs($user)
        ->test('pages::admin.years')
        ->set('deleteTarget', $year->id)
        ->call('deleteYear');

    $this->assertDatabaseMissing('years', ['id' => $year->id]);
    $this->assertDatabaseMissing('months', ['year_id' => $year->id]);
});

it('modifie une année via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $year = Year::create(['label' => '2025-2026']);

    Livewire::actingAs($user)
        ->test('pages::admin.years')
        ->call('startEdit', $year->id)
        ->set('editLabel', '2026-2027')
        ->set('editTheme', 'Foi & espérance')
        ->set('editMakeCurrent', true)
        ->call('updateYear')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('years', ['id' => $year->id, 'label' => '2026-2027', 'theme' => 'Foi & espérance', 'is_current' => true]);
});

it('journalise la création via le service', function () {
    app(YearService::class)->create(['label' => '2026-2027']);

    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.years.create']);
});
