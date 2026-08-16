<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function weekAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function weekToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('refuse sans authentification', function () {
    $year = Year::create(['label' => '2026-2027']);

    $this->getJson("/api/v1/years/{$year->id}/weeks")->assertStatus(401);
});

it('liste les semaines d une année pour un utilisateur authentifié', function () {
    $year = Year::create(['label' => '2026-2027']);
    Week::create(['year_id' => $year->id, 'label' => 'Semaine 2']);
    Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    $this->withToken(weekToken(weekAdmin()))
        ->getJson("/api/v1/years/{$year->id}/weeks")
        ->assertOk()
        ->assertJsonCount(2, 'data.weeks')
        ->assertJsonPath('data.weeks.0.label', 'Semaine 1');
});

it('crée une semaine rattachée à l année', function () {
    $year = Year::create(['label' => '2026-2027']);

    $this->withToken(weekToken(weekAdmin()))
        ->postJson("/api/v1/years/{$year->id}/weeks", ['label' => 'Semaine 1'])
        ->assertCreated()
        ->assertJsonPath('data.week.year_id', $year->id)
        ->assertJsonPath('data.week.label', 'Semaine 1');

    $this->assertDatabaseHas('weeks', ['year_id' => $year->id, 'label' => 'Semaine 1']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.weeks.create']);
});

it('rejette un doublon (année, étiquette) en 422', function () {
    $year = Year::create(['label' => '2026-2027']);
    Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    $this->withToken(weekToken(weekAdmin()))
        ->postJson("/api/v1/years/{$year->id}/weeks", ['label' => 'Semaine 1'])
        ->assertStatus(422);
});

it('modifie une semaine', function () {
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    $this->withToken(weekToken(weekAdmin()))
        ->putJson("/api/v1/years/{$year->id}/weeks/{$week->id}", ['label' => 'Semaine 2'])
        ->assertOk()
        ->assertJsonPath('data.week.label', 'Semaine 2');

    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.weeks.update']);
});

it('supprime une semaine non utilisée', function () {
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    $this->withToken(weekToken(weekAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}/weeks/{$week->id}")
        ->assertOk();

    $this->assertDatabaseMissing('weeks', ['id' => $week->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.weeks.delete']);
});

it('refuse la suppression d une année contenant des semaines', function () {
    $year = Year::create(['label' => '2026-2027']);
    Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    $this->withToken(weekToken(weekAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'year_in_use');
});

it('refuse de cibler une semaine d une autre année (404)', function () {
    $yearA = Year::create(['label' => '2025-2026']);
    $yearB = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $yearA->id, 'label' => 'Semaine 1']);

    $this->withToken(weekToken(weekAdmin()))
        ->putJson("/api/v1/years/{$yearB->id}/weeks/{$week->id}", ['label' => 'Semaine 2'])
        ->assertStatus(404);
});

it('refuse l écriture sans permission schedule.manage (403)', function () {
    $year = Year::create(['label' => '2026-2027']);
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson("/api/v1/years/{$year->id}/weeks", ['label' => 'Semaine 1'])
        ->assertForbidden();
});
