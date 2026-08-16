<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function activityAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function activityToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function activityWeek(string $yearLabel = '2026-2027'): Week
{
    $year = Year::create(['label' => $yearLabel]);

    return Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
}

function activityTypeRecord(string $code = 'convention', bool $active = true): ActivityType
{
    return ActivityType::create(['code' => $code, 'label' => 'Convention', 'is_active' => $active]);
}

function specialActivity(Week $week, ?ActivityType $type = null): SpecialActivity
{
    $type ??= ActivityType::firstOrCreate(['code' => 'convention'], ['label' => 'Convention']);

    return SpecialActivity::create([
        'week_id' => $week->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention de prière',
        'mode' => 'complement',
    ]);
}

it('refuse une écriture sans authentification', function () {
    $week = activityWeek();

    $this->postJson('/api/v1/special-activities', [
        'week_id' => $week->id,
        'activity_type_id' => activityTypeRecord()->id,
        'name' => 'Convention',
        'mode' => 'complement',
    ])->assertStatus(401);
});

it('crée une activité rattachée à la semaine', function () {
    $week = activityWeek();
    $type = activityTypeRecord();

    $this->withToken(activityToken(activityAdmin()))
        ->postJson('/api/v1/special-activities', [
            'week_id' => $week->id,
            'activity_type_id' => $type->id,
            'name' => 'Convention de prière',
            'mode' => 'replace',
            'starts_on' => '2027-01-10',
            'ends_on' => '2027-01-14',
        ])
        ->assertCreated()
        ->assertJsonPath('data.special_activity.week_id', $week->id)
        ->assertJsonPath('data.special_activity.name', 'Convention de prière')
        ->assertJsonPath('data.special_activity.mode', 'replace');

    $this->assertDatabaseHas('special_activities', ['week_id' => $week->id, 'name' => 'Convention de prière']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'special_activities.create']);
});

it('rejette un mode invalide en 422', function () {
    $week = activityWeek();
    $type = activityTypeRecord();

    $this->withToken(activityToken(activityAdmin()))
        ->postJson('/api/v1/special-activities', [
            'week_id' => $week->id,
            'activity_type_id' => $type->id,
            'name' => 'Convention',
            'mode' => 'override',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['mode']]]);
});

it('rejette une fin avant le début en 422', function () {
    $week = activityWeek();
    $type = activityTypeRecord();

    $this->withToken(activityToken(activityAdmin()))
        ->postJson('/api/v1/special-activities', [
            'week_id' => $week->id,
            'activity_type_id' => $type->id,
            'name' => 'Convention',
            'mode' => 'complement',
            'starts_on' => '2027-01-14',
            'ends_on' => '2027-01-10',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['ends_on']]]);
});

it('rejette un type d activité inactif en 422', function () {
    $week = activityWeek();
    $type = activityTypeRecord('prayer', false);

    $this->withToken(activityToken(activityAdmin()))
        ->postJson('/api/v1/special-activities', [
            'week_id' => $week->id,
            'activity_type_id' => $type->id,
            'name' => 'Convention',
            'mode' => 'complement',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['activity_type_id']]]);
});

it('modifie une activité', function () {
    $week = activityWeek();
    $activity = specialActivity($week);

    $this->withToken(activityToken(activityAdmin()))
        ->putJson("/api/v1/special-activities/{$activity->id}", [
            'week_id' => $week->id,
            'activity_type_id' => $activity->activity_type_id,
            'name' => 'Camp de prière',
            'mode' => 'complement',
        ])
        ->assertOk()
        ->assertJsonPath('data.special_activity.name', 'Camp de prière');

    $this->assertDatabaseHas('audit_logs', ['action' => 'special_activities.update']);
});

it('ajoute une session à une activité', function () {
    $week = activityWeek();
    $activity = specialActivity($week);

    $this->withToken(activityToken(activityAdmin()))
        ->postJson("/api/v1/special-activities/{$activity->id}/sessions", [
            'day_of_week' => 2,
            'start_time' => '18:00',
            'duration_minutes' => 60,
            'place' => 'Salle A',
            'theme' => 'Intercession',
        ])
        ->assertCreated()
        ->assertJsonPath('data.session.day_of_week', 2)
        ->assertJsonPath('data.session.place', 'Salle A');

    $this->assertDatabaseHas('activity_sessions', ['special_activity_id' => $activity->id, 'start_time' => '18:00']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'special_activities.sessions.create']);
});

it('rejette un chevauchement de sessions en 422', function () {
    $week = activityWeek();
    $activity = specialActivity($week);
    $activity->sessions()->create(['day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60]);

    $this->withToken(activityToken(activityAdmin()))
        ->postJson("/api/v1/special-activities/{$activity->id}/sessions", [
            'day_of_week' => 2,
            'start_time' => '18:30',
            'duration_minutes' => 30,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['start_time']]]);
});

it('autorise deux sessions adjacentes', function () {
    $week = activityWeek();
    $activity = specialActivity($week);
    $activity->sessions()->create(['day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60]);

    $this->withToken(activityToken(activityAdmin()))
        ->postJson("/api/v1/special-activities/{$activity->id}/sessions", [
            'day_of_week' => 2,
            'start_time' => '19:00',
            'duration_minutes' => 30,
        ])
        ->assertCreated();
});

it('modifie une session', function () {
    $week = activityWeek();
    $activity = specialActivity($week);
    $session = $activity->sessions()->create(['day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60]);

    $this->withToken(activityToken(activityAdmin()))
        ->putJson("/api/v1/special-activities/{$activity->id}/sessions/{$session->id}", [
            'day_of_week' => 3,
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'theme' => 'Veillée',
        ])
        ->assertOk()
        ->assertJsonPath('data.session.day_of_week', 3)
        ->assertJsonPath('data.session.theme', 'Veillée');

    $this->assertDatabaseHas('audit_logs', ['action' => 'special_activities.sessions.update']);
});

it('supprime une session', function () {
    $week = activityWeek();
    $activity = specialActivity($week);
    $session = $activity->sessions()->create(['day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60]);

    $this->withToken(activityToken(activityAdmin()))
        ->deleteJson("/api/v1/special-activities/{$activity->id}/sessions/{$session->id}")
        ->assertOk();

    $this->assertDatabaseMissing('activity_sessions', ['id' => $session->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'special_activities.sessions.delete']);
});

it('refuse de cibler une session d une autre activité (404)', function () {
    $weekA = activityWeek('2025-2026');
    $weekB = activityWeek('2026-2027');
    $activityA = specialActivity($weekA);
    $activityB = specialActivity($weekB);
    $session = $activityA->sessions()->create(['day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60]);

    $this->withToken(activityToken(activityAdmin()))
        ->putJson("/api/v1/special-activities/{$activityB->id}/sessions/{$session->id}", [
            'day_of_week' => 3,
            'start_time' => '19:00',
            'duration_minutes' => 60,
        ])
        ->assertStatus(404);
});

it('supprime une activité non référencée', function () {
    $week = activityWeek();
    $activity = specialActivity($week);

    $this->withToken(activityToken(activityAdmin()))
        ->deleteJson("/api/v1/special-activities/{$activity->id}")
        ->assertOk();

    $this->assertDatabaseMissing('special_activities', ['id' => $activity->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'special_activities.delete']);
});

it('refuse la suppression d une semaine contenant une activité', function () {
    $week = activityWeek();
    specialActivity($week);

    $this->withToken(activityToken(activityAdmin()))
        ->deleteJson("/api/v1/years/{$week->year_id}/weeks/{$week->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'week_in_use');
});

it('refuse l écriture sans permission special_activity.manage (403)', function () {
    $week = activityWeek();
    $type = activityTypeRecord();
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson('/api/v1/special-activities', [
            'week_id' => $week->id,
            'activity_type_id' => $type->id,
            'name' => 'Convention',
            'mode' => 'complement',
        ])
        ->assertForbidden();
});

it('expose la lecture publique sans authentification', function () {
    $week = activityWeek();
    $activity = specialActivity($week);
    $activity->sessions()->create(['day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60]);

    $this->getJson('/api/v1/special-activities')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Convention de prière');
});

it('expose le détail public d une activité avec sessions ordonnées', function () {
    $week = activityWeek();
    $activity = specialActivity($week);
    $activity->sessions()->create(['day_of_week' => 7, 'start_time' => '09:00', 'duration_minutes' => 120]);
    $activity->sessions()->create(['day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60]);

    $this->getJson("/api/v1/special-activities/{$activity->id}")
        ->assertOk()
        ->assertJsonPath('data.special_activity.name', 'Convention de prière')
        ->assertJsonPath('data.special_activity.activity_type.code', 'convention')
        ->assertJsonCount(2, 'data.special_activity.sessions')
        ->assertJsonPath('data.special_activity.sessions.0.day_of_week', 2)
        ->assertJsonPath('data.special_activity.sessions.1.day_of_week', 7);
});
