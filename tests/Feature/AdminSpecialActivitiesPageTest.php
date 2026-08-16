<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Database\Seeders\RbacSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

it('affiche l écran des activités pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/admin/special-activities')
        ->assertOk()
        ->assertSee('Activités spéciales');
});

it('refuse l accès sans permission special_activity.manage', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $permission = Permission::where('name', 'special_activity.manage')->firstOrFail();
    $role->permissions()->detach($permission->id);

    $this->actingAs($user)->get('/admin/special-activities')->assertForbidden();
});

it('crée une activité via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $type = ActivityType::create(['code' => 'convention', 'label' => 'Convention']);

    Livewire::actingAs($user)
        ->test('pages::admin.special-activities')
        ->set('weekId', $week->id)
        ->set('activityTypeId', $type->id)
        ->set('name', 'Convention de prière')
        ->call('createActivity')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('special_activities', ['week_id' => $week->id, 'name' => 'Convention de prière']);
});

it('affiche l écran des sessions d une activité', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $type = ActivityType::create(['code' => 'convention', 'label' => 'Convention']);
    $activity = SpecialActivity::create(['week_id' => $week->id, 'activity_type_id' => $type->id, 'name' => 'Convention de prière']);

    $this->actingAs($user)
        ->get("/admin/special-activities/{$activity->id}")
        ->assertOk()
        ->assertSee('Convention de prière');
});

it('ajoute une session via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $type = ActivityType::create(['code' => 'convention', 'label' => 'Convention']);
    $activity = SpecialActivity::create(['week_id' => $week->id, 'activity_type_id' => $type->id, 'name' => 'Convention de prière']);

    Livewire::actingAs($user)
        ->test('pages::admin.special-activity-sessions', ['activity' => $activity])
        ->set('dayOfWeek', 2)
        ->set('startTime', '18:00')
        ->call('addSession')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('activity_sessions', ['special_activity_id' => $activity->id, 'start_time' => '18:00']);
});

it('modifie une activité via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $otherWeek = Week::create(['year_id' => $year->id, 'label' => 'Semaine 2']);
    $type = ActivityType::create(['code' => 'convention', 'label' => 'Convention']);
    $otherType = ActivityType::create(['code' => 'seminar', 'label' => 'Séminaire']);
    $activity = SpecialActivity::create(['week_id' => $week->id, 'activity_type_id' => $type->id, 'name' => 'Convention de prière']);

    Livewire::actingAs($user)
        ->test('pages::admin.special-activities')
        ->call('startEdit', $activity->id)
        ->set('editWeekId', $otherWeek->id)
        ->set('editActivityTypeId', $otherType->id)
        ->set('editName', 'Séminaire de rentrée')
        ->set('editMode', 'replace')
        ->call('updateActivity')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('special_activities', ['id' => $activity->id, 'week_id' => $otherWeek->id, 'activity_type_id' => $otherType->id, 'name' => 'Séminaire de rentrée', 'mode' => 'replace']);
});

it('modifie une session via le formulaire', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $type = ActivityType::create(['code' => 'convention', 'label' => 'Convention']);
    $activity = SpecialActivity::create(['week_id' => $week->id, 'activity_type_id' => $type->id, 'name' => 'Convention de prière']);
    $session = $activity->sessions()->create(['day_of_week' => 2, 'start_time' => '18:00', 'duration_minutes' => 60]);

    Livewire::actingAs($user)
        ->test('pages::admin.special-activity-sessions', ['activity' => $activity])
        ->call('startEdit', $session->id)
        ->set('editDayOfWeek', 4)
        ->set('editStartTime', '19:30')
        ->set('editDurationMinutes', 90)
        ->set('editPlace', 'Salle 2')
        ->call('updateSession')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('activity_sessions', ['id' => $session->id, 'day_of_week' => 4, 'start_time' => '19:30', 'duration_minutes' => 90, 'place' => 'Salle 2']);
});
