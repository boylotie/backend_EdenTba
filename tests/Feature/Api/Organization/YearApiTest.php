<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Models\Year;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function orgAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function orgToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('refuse sans authentification', function () {
    $this->getJson('/api/v1/years')->assertStatus(401);
});

it('liste les années pour un utilisateur authentifié', function () {
    Year::create(['label' => '2026-2027', 'theme' => 'Foi']);

    $this->withToken(orgToken(orgAdmin()))
        ->getJson('/api/v1/years')
        ->assertOk()
        ->assertJsonCount(1, 'data.years');
});

it('crée une année avec son thème', function () {
    $response = $this->withToken(orgToken(orgAdmin()))
        ->postJson('/api/v1/years', ['label' => '2026-2027', 'theme' => 'Foi & espérance'])
        ->assertCreated()
        ->assertJsonPath('data.year.label', '2026-2027')
        ->assertJsonPath('data.year.theme', 'Foi & espérance');

    $this->assertDatabaseHas('years', ['label' => '2026-2027', 'theme' => 'Foi & espérance']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.years.create']);

    $year = Year::where('label', '2026-2027')->firstOrFail();
    expect($year->months()->count())->toBe(12)
        ->and($year->months()->pluck('month_number')->sort()->values()->all())->toBe(range(1, 12));
});

it('crée automatiquement les 12 mois de l année', function () {
    $year = Year::create(['label' => '2025-2026']);

    $this->withToken(orgToken(orgAdmin()))
        ->postJson('/api/v1/years', ['label' => '2026-2027'])
        ->assertCreated();

    $newYear = Year::where('label', '2026-2027')->firstOrFail();
    expect($newYear->months()->count())->toBe(12)
        ->and($newYear->months()->pluck('month_number')->sort()->values()->all())->toBe(range(1, 12));

    expect($year->months()->count())->toBe(0);
});

it('supprime une année non utilisée', function () {
    $year = Year::create(['label' => '2020-2021']);

    $this->withToken(orgToken(orgAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}")
        ->assertOk();

    $this->assertDatabaseMissing('years', ['id' => $year->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'organization.years.delete']);
});

it('rejette un libellé en double (422)', function () {
    Year::create(['label' => '2026-2027']);

    $this->withToken(orgToken(orgAdmin()))
        ->postJson('/api/v1/years', ['label' => '2026-2027'])
        ->assertStatus(422);
});

it('ne laisse qu une seule année courante à la fois', function () {
    $first = Year::create(['label' => '2025-2026', 'is_current' => true]);
    $second = Year::create(['label' => '2026-2027']);

    $this->withToken(orgToken(orgAdmin()))
        ->putJson("/api/v1/years/{$second->id}", ['label' => '2026-2027', 'is_current' => true])
        ->assertOk()
        ->assertJsonPath('data.year.is_current', true);

    expect($first->fresh()->is_current)->toBeFalse()
        ->and($second->fresh()->is_current)->toBeTrue();
});

it('ne décurse pas l année courante lors d une simple mise à jour de libellé', function () {
    $year = Year::create(['label' => '2026-2027', 'is_current' => true]);

    $this->withToken(orgToken(orgAdmin()))
        ->putJson("/api/v1/years/{$year->id}", ['label' => '2026-2027', 'theme' => 'Foi'])
        ->assertOk();

    expect($year->fresh()->is_current)->toBeTrue();
});

it('retourne l année courante', function () {
    Year::create(['label' => '2025-2026']);
    Year::create(['label' => '2026-2027', 'is_current' => true]);

    $this->withToken(orgToken(orgAdmin()))
        ->getJson('/api/v1/years/current')
        ->assertOk()
        ->assertJsonPath('data.year.label', '2026-2027');
});

it('retourne 404 si aucune année courante', function () {
    Year::create(['label' => '2025-2026']);

    $this->withToken(orgToken(orgAdmin()))
        ->getJson('/api/v1/years/current')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'year_not_found');
});

it('refuse l écriture sans permission schedule.manage (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson('/api/v1/years', ['label' => '2026-2027'])
        ->assertForbidden();
});

it('autorise la lecture pour un utilisateur authentifié sans rôle admin', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    Year::create(['label' => '2026-2027']);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->getJson('/api/v1/years')
        ->assertOk();
});
