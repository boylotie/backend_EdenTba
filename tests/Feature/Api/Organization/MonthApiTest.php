<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\YearService;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function monthAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function monthToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('refuse sans authentification', function () {
    $year = Year::create(['label' => '2026-2027']);

    $this->getJson("/api/v1/years/{$year->id}/months")->assertStatus(401);
});

it('liste les mois d une année pour un utilisateur authentifié', function () {
    $year = Year::create(['label' => '2026-2027']);
    Month::create(['year_id' => $year->id, 'month_number' => 3]);
    Month::create(['year_id' => $year->id, 'month_number' => 1]);

    $this->withToken(monthToken(monthAdmin()))
        ->getJson("/api/v1/years/{$year->id}/months")
        ->assertOk()
        ->assertJsonCount(2, 'data.months')
        ->assertJsonPath('data.months.0.month_number', 1);
});

it('crée un mois rattaché à l année avec son thème', function () {
    $year = Year::create(['label' => '2026-2027']);

    $this->withToken(monthToken(monthAdmin()))
        ->postJson("/api/v1/years/{$year->id}/months", ['month_number' => 5, 'theme' => 'Espérance'])
        ->assertCreated()
        ->assertJsonPath('data.month.year_id', $year->id)
        ->assertJsonPath('data.month.month_number', 5)
        ->assertJsonPath('data.month.theme', 'Espérance');

    $this->assertDatabaseHas('months', ['year_id' => $year->id, 'month_number' => 5, 'theme' => 'Espérance']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.months.create']);
});

it('rejette un doublon (année, mois) en 422', function () {
    $year = Year::create(['label' => '2026-2027']);
    Month::create(['year_id' => $year->id, 'month_number' => 5]);

    $this->withToken(monthToken(monthAdmin()))
        ->postJson("/api/v1/years/{$year->id}/months", ['month_number' => 5])
        ->assertStatus(422);
});

it('modifie un mois', function () {
    $year = Year::create(['label' => '2026-2027']);
    $month = Month::create(['year_id' => $year->id, 'month_number' => 5]);

    $this->withToken(monthToken(monthAdmin()))
        ->putJson("/api/v1/years/{$year->id}/months/{$month->id}", ['month_number' => 6, 'theme' => 'Renouveau'])
        ->assertOk()
        ->assertJsonPath('data.month.month_number', 6)
        ->assertJsonPath('data.month.theme', 'Renouveau');

    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.months.update']);
});

it('supprime un mois non utilisé', function () {
    $year = Year::create(['label' => '2026-2027']);
    $month = Month::create(['year_id' => $year->id, 'month_number' => 5]);

    $this->withToken(monthToken(monthAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}/months/{$month->id}")
        ->assertOk();

    $this->assertDatabaseMissing('months', ['id' => $month->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.months.delete']);
});

it('supprime une année dont les mois vides sont supprimés en cascade', function () {
    $year = app(YearService::class)->create(['label' => '2020-2021']);
    expect($year->months()->count())->toBe(12);

    $this->withToken(monthToken(monthAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}")
        ->assertOk();

    $this->assertDatabaseMissing('years', ['id' => $year->id]);
    $this->assertDatabaseMissing('months', ['year_id' => $year->id]);
});

it('refuse la suppression d une année utilisée par des semaines', function () {
    $year = Year::create(['label' => '2026-2027']);
    Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    $this->withToken(monthToken(monthAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'year_in_use');
});

it('refuse de cibler un mois d une autre année (404)', function () {
    $yearA = Year::create(['label' => '2025-2026']);
    $yearB = Year::create(['label' => '2026-2027']);
    $month = Month::create(['year_id' => $yearA->id, 'month_number' => 5]);

    $this->withToken(monthToken(monthAdmin()))
        ->putJson("/api/v1/years/{$yearB->id}/months/{$month->id}", ['month_number' => 5])
        ->assertStatus(404);
});

it('refuse l écriture sans permission schedule.manage (403)', function () {
    $year = Year::create(['label' => '2026-2027']);
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson("/api/v1/years/{$year->id}/months", ['month_number' => 5])
        ->assertForbidden();
});
