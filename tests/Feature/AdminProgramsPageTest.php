<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function programsPageYearAndWeek(): array
{
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    return [$year, $week];
}

it('affiche l écran des programmes pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    [$year, $week] = programsPageYearAndWeek();

    $this->actingAs($user)
        ->get("/admin/years/{$year->id}/weeks/{$week->id}/programs")
        ->assertOk()
        ->assertSee('Programmes')
        ->assertSee('Semaine 1');
});

it('refuse l accès sans permission schedule.manage', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    [$year, $week] = programsPageYearAndWeek();

    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $scheduleManage = Permission::where('name', 'schedule.manage')->firstOrFail();
    $role->permissions()->detach($scheduleManage->id);

    $this->actingAs($user)->get("/admin/years/{$year->id}/weeks/{$week->id}/programs")->assertForbidden();
});

it('crée un programme via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    [$year, $week] = programsPageYearAndWeek();

    Livewire::actingAs($user)
        ->test('pages::admin.programs', ['year' => $year, 'week' => $week])
        ->set('dayOfWeek', 4)
        ->set('startTime', '17:00')
        ->set('durationMinutes', 90)
        ->set('type', 'Prière')
        ->call('createProgram')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('programs', ['week_id' => $week->id, 'day_of_week' => 4, 'type' => 'Prière']);
});

it('rejette un chevauchement via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    [$year, $week] = programsPageYearAndWeek();
    Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    Livewire::actingAs($user)
        ->test('pages::admin.programs', ['year' => $year, 'week' => $week])
        ->set('dayOfWeek', 2)
        ->set('startTime', '18:30')
        ->set('durationMinutes', 30)
        ->set('type', 'Chorale')
        ->call('createProgram')
        ->assertHasErrors('startTime');

    $this->assertDatabaseCount('programs', 1);
});

it('supprime un programme via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    [$year, $week] = programsPageYearAndWeek();
    $program = Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    Livewire::actingAs($user)
        ->test('pages::admin.programs', ['year' => $year, 'week' => $week])
        ->set('deleteTarget', $program->id)
        ->call('deleteProgram');

    $this->assertDatabaseMissing('programs', ['id' => $program->id]);
});

it('modifie un programme via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    [$year, $week] = programsPageYearAndWeek();
    $program = Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    Livewire::actingAs($user)
        ->test('pages::admin.programs', ['year' => $year, 'week' => $week])
        ->call('startEdit', $program->id)
        ->set('editDayOfWeek', 4)
        ->set('editStartTime', '17:00')
        ->set('editDurationMinutes', 90)
        ->set('editType', 'Prière')
        ->call('updateProgram')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('programs', ['id' => $program->id, 'day_of_week' => 4, 'start_time' => '17:00', 'type' => 'Prière']);
});

it('rejette un chevauchement lors de la modification', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    [$year, $week] = programsPageYearAndWeek();
    Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);
    $other = Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '19:00', 'duration_minutes' => 60, 'type' => 'Chorale']);

    Livewire::actingAs($user)
        ->test('pages::admin.programs', ['year' => $year, 'week' => $week])
        ->call('startEdit', $other->id)
        ->set('editStartTime', '18:30')
        ->set('editDurationMinutes', 30)
        ->call('updateProgram')
        ->assertHasErrors('editStartTime');

    $this->assertDatabaseHas('programs', ['id' => $other->id, 'start_time' => '19:00']);
});
