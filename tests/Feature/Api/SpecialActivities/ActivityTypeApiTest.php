<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\SpecialActivities\Models\ActivityType;
use Database\Seeders\ActivityTypeSeeder;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function typeAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function typeToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('seed les types documentés', function () {
    $this->seed(ActivityTypeSeeder::class);

    expect(ActivityType::count())->toBe(6)
        ->and(ActivityType::where('code', 'convention')->exists())->toBeTrue()
        ->and(ActivityType::where('code', 'retreat')->exists())->toBeTrue();
});

it('refuse sans authentification', function () {
    $this->getJson('/api/v1/activity-types')->assertStatus(401);
});

it('liste les types pour un utilisateur authentifié', function () {
    ActivityType::create(['code' => 'prayer', 'label' => 'Prière']);
    ActivityType::create(['code' => 'seminar', 'label' => 'Séminaire']);

    $this->withToken(typeToken(typeAdmin()))
        ->getJson('/api/v1/activity-types')
        ->assertOk()
        ->assertJsonCount(2, 'data.activity_types');
});

it('crée un type d activité', function () {
    $this->withToken(typeToken(typeAdmin()))
        ->postJson('/api/v1/activity-types', [
            'code' => 'seminar',
            'label' => 'Séminaire',
        ])
        ->assertCreated()
        ->assertJsonPath('data.activity_type.code', 'seminar')
        ->assertJsonPath('data.activity_type.is_active', true);

    $this->assertDatabaseHas('activity_types', ['code' => 'seminar', 'label' => 'Séminaire']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'activity_types.create']);
});

it('rejette un code en double en 422', function () {
    ActivityType::create(['code' => 'prayer', 'label' => 'Prière']);

    $this->withToken(typeToken(typeAdmin()))
        ->postJson('/api/v1/activity-types', ['code' => 'prayer', 'label' => 'Prière'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['code']]]);
});

it('rejette un code invalide en 422', function () {
    $this->withToken(typeToken(typeAdmin()))
        ->postJson('/api/v1/activity-types', ['code' => 'Prière!', 'label' => 'Prière'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['code']]]);
});

it('modifie un type (dont désactivation)', function () {
    $type = ActivityType::create(['code' => 'prayer', 'label' => 'Prière']);

    $this->withToken(typeToken(typeAdmin()))
        ->putJson("/api/v1/activity-types/{$type->id}", [
            'code' => 'prayer',
            'label' => 'Prière & intercession',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.activity_type.label', 'Prière & intercession')
        ->assertJsonPath('data.activity_type.is_active', false);

    $this->assertDatabaseHas('audit_logs', ['action' => 'activity_types.update']);
});

it('supprime un type non utilisé', function () {
    $type = ActivityType::create(['code' => 'other', 'label' => 'Autre']);

    $this->withToken(typeToken(typeAdmin()))
        ->deleteJson("/api/v1/activity-types/{$type->id}")
        ->assertOk();

    $this->assertDatabaseMissing('activity_types', ['id' => $type->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'activity_types.delete']);
});

it('refuse l écriture sans permission special_activity.manage (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson('/api/v1/activity-types', ['code' => 'other', 'label' => 'Autre'])
        ->assertForbidden();
});
