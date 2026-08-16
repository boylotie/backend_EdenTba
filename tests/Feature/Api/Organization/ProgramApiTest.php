<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function programAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function programToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function programWeek(string $yearLabel = '2026-2027'): Week
{
    $year = Year::create(['label' => $yearLabel]);

    return Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
}

it('refuse sans authentification', function () {
    $week = programWeek();

    $this->getJson("/api/v1/weeks/{$week->id}/programs")->assertStatus(401);
});

it('liste les programmes d une semaine triés par jour puis heure', function () {
    $week = programWeek();
    Program::create(['week_id' => $week->id, 'day_of_week' => 7, 'start_time' => '09:00', 'duration_minutes' => 120, 'type' => 'Culte']);
    Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    $this->withToken(programToken(programAdmin()))
        ->getJson("/api/v1/weeks/{$week->id}/programs")
        ->assertOk()
        ->assertJsonCount(2, 'data.programs')
        ->assertJsonPath('data.programs.0.type', 'Enseignement')
        ->assertJsonPath('data.programs.1.type', 'Culte');
});

it('crée un programme rattaché à la semaine', function () {
    $week = programWeek();

    $this->withToken(programToken(programAdmin()))
        ->postJson("/api/v1/weeks/{$week->id}/programs", [
            'day_of_week' => 4,
            'start_time' => '17:00',
            'duration_minutes' => 90,
            'type' => 'Prière',
        ])
        ->assertCreated()
        ->assertJsonPath('data.program.week_id', $week->id)
        ->assertJsonPath('data.program.type', 'Prière');

    $this->assertDatabaseHas('programs', ['week_id' => $week->id, 'day_of_week' => 4, 'type' => 'Prière']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.programs.create']);
});

it('rejette une heure de début invalide en 422', function () {
    $week = programWeek();

    $this->withToken(programToken(programAdmin()))
        ->postJson("/api/v1/weeks/{$week->id}/programs", [
            'day_of_week' => 4,
            'start_time' => '25:99',
            'duration_minutes' => 90,
            'type' => 'Prière',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['start_time']]]);
});

it('rejette un jour hors plage en 422', function () {
    $week = programWeek();

    $this->withToken(programToken(programAdmin()))
        ->postJson("/api/v1/weeks/{$week->id}/programs", [
            'day_of_week' => 8,
            'start_time' => '09:00',
            'duration_minutes' => 90,
            'type' => 'Culte',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['day_of_week']]]);
});

it('rejette un chevauchement d horaires en 422', function () {
    $week = programWeek();
    Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    $this->withToken(programToken(programAdmin()))
        ->postJson("/api/v1/weeks/{$week->id}/programs", [
            'day_of_week' => 2,
            'start_time' => '18:30',
            'duration_minutes' => 30,
            'type' => 'Chorale',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['start_time']]]);
});

it('autorise deux créneaux adjacents', function () {
    $week = programWeek();
    Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    $this->withToken(programToken(programAdmin()))
        ->postJson("/api/v1/weeks/{$week->id}/programs", [
            'day_of_week' => 2,
            'start_time' => '19:00',
            'duration_minutes' => 30,
            'type' => 'Chorale',
        ])
        ->assertCreated();
});

it('modifie un programme', function () {
    $week = programWeek();
    $program = Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    $this->withToken(programToken(programAdmin()))
        ->putJson("/api/v1/weeks/{$week->id}/programs/{$program->id}", [
            'day_of_week' => 3,
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'type' => 'Bible',
        ])
        ->assertOk()
        ->assertJsonPath('data.program.day_of_week', 3)
        ->assertJsonPath('data.program.type', 'Bible');

    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.programs.update']);
});

it('supprime un programme non référencé', function () {
    $week = programWeek();
    $program = Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    $this->withToken(programToken(programAdmin()))
        ->deleteJson("/api/v1/weeks/{$week->id}/programs/{$program->id}")
        ->assertOk();

    $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.programs.delete']);
});

it('refuse la suppression d une semaine contenant des programmes', function () {
    $week = programWeek();
    Program::create(['week_id' => $week->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    $this->withToken(programToken(programAdmin()))
        ->deleteJson("/api/v1/years/{$week->year_id}/weeks/{$week->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'week_in_use');
});

it('refuse de cibler un programme d une autre semaine (404)', function () {
    $weekA = programWeek('2025-2026');
    $weekB = programWeek('2026-2027');
    $program = Program::create(['week_id' => $weekA->id, 'day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60, 'type' => 'Enseignement']);

    $this->withToken(programToken(programAdmin()))
        ->putJson("/api/v1/weeks/{$weekB->id}/programs/{$program->id}", [
            'day_of_week' => 3,
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'type' => 'Bible',
        ])
        ->assertStatus(404);
});

it('refuse l écriture sans permission schedule.manage (403)', function () {
    $week = programWeek();
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson("/api/v1/weeks/{$week->id}/programs", [
            'day_of_week' => 2,
            'start_time' => '18:00',
            'duration_minutes' => 60,
            'type' => 'Enseignement',
        ])
        ->assertForbidden();
});
