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

it('affiche l écran des mois pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $year = app(YearService::class)->create(['label' => '2026-2027']);

    $this->actingAs($user)
        ->get("/admin/years/{$year->id}/months")
        ->assertOk()
        ->assertSee('Mois & thèmes')
        ->assertSee('2026-2027')
        ->assertSee('Janvier')
        ->assertSee('Décembre');
});

it('affiche les 12 mois de l année dans l ordre janvier-décembre', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = app(YearService::class)->create(['label' => '2026-2027']);

    $months = $year->months()->orderBy('month_number')->get();

    expect($months)->toHaveCount(12)
        ->and($months->pluck('month_number')->all())->toBe(range(1, 12));
});

it('refuse l accès sans permission schedule.manage', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $year = Year::create(['label' => '2026-2027']);

    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $scheduleManage = Permission::where('name', 'schedule.manage')->firstOrFail();
    $role->permissions()->detach($scheduleManage->id);

    $this->actingAs($user)->get("/admin/years/{$year->id}/months")->assertForbidden();
});

it('attribue un thème à un mois via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = app(YearService::class)->create(['label' => '2026-2027']);
    $month = $year->months()->where('month_number', 4)->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::admin.months', ['year' => $year])
        ->call('startEdit', $month->id)
        ->set('editTheme', 'Renouveau')
        ->call('updateMonth')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('months', ['id' => $month->id, 'month_number' => 4, 'theme' => 'Renouveau']);
});

it('efface le thème d un mois en laissant le champ vide', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = app(YearService::class)->create(['label' => '2026-2027']);
    $month = $year->months()->where('month_number', 4)->firstOrFail();
    $month->update(['theme' => 'Renouveau']);

    Livewire::actingAs($user)
        ->test('pages::admin.months', ['year' => $year])
        ->call('startEdit', $month->id)
        ->set('editTheme', '')
        ->call('updateMonth')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('months', ['id' => $month->id, 'theme' => null]);
});
