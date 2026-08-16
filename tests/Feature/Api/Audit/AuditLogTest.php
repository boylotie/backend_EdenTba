<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Shared\Audit\AuditLogger;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function auditAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function auditToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('capture les événements dauthentification', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_id' => $user->id]);
});

it('liste le journal pour un super administrateur', function () {
    AuditLog::create(['action' => 'auth.login']);

    $this->withToken(auditToken(auditAdmin()))
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonPath('error', null)
        ->assertJsonStructure(['data', 'meta' => ['pagination'], 'error'])
        ->assertJsonCount(1, 'data');
});

it('refuse la consultation du journal sans authentification', function () {
    $this->getJson('/api/v1/audit-logs')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('refuse la consultation du journal sans permission', function () {
    $this->withToken(auditToken(User::factory()->create()))
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('filtre le journal par action', function () {
    AuditLog::create(['action' => 'auth.login']);
    AuditLog::create(['action' => 'roles.create']);

    $this->withToken(auditToken(auditAdmin()))
        ->getJson('/api/v1/audit-logs?action=auth.login')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'auth.login');
});

it('filtre le journal par utilisateur', function () {
    $actor = User::factory()->create();
    AuditLog::create(['action' => 'auth.login', 'actor_id' => $actor->id]);
    AuditLog::create(['action' => 'auth.login']);

    $this->withToken(auditToken(auditAdmin()))
        ->getJson('/api/v1/audit-logs?user_id='.$actor->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.actor_id', $actor->id);
});

it('filtre le journal par période', function () {
    AuditLog::forceCreate(['action' => 'auth.login', 'created_at' => now()->subDays(10)]);
    $recent = AuditLog::forceCreate(['action' => 'auth.login', 'created_at' => now()]);

    $this->withToken(auditToken(auditAdmin()))
        ->getJson('/api/v1/audit-logs?from='.now()->subDays(2)->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recent->id);
});

it('pagina le journal', function () {
    AuditLog::create(['action' => 'auth.login']);
    AuditLog::create(['action' => 'roles.create']);

    $this->withToken(auditToken(auditAdmin()))
        ->getJson('/api/v1/audit-logs?per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 1)
        ->assertJsonCount(1, 'data');
});

it('ne journalise pas le mot de passe', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'mauvais-mdp',
    ])->assertStatus(401);

    $entry = AuditLog::where('action', 'auth.login.failed')->firstOrFail();

    expect($entry->context)->toBe(['email' => $user->email]);
});

it('bascule vers le canal de secours quand lécriture échoue', function () {
    $auditChannel = Mockery::mock();
    $auditChannel->shouldReceive('error')->once();

    Log::shouldReceive('channel')->once()->with('audit')->andReturn($auditChannel);

    Event::listen('eloquent.creating: '.AuditLog::class, function (): never {
        throw new RuntimeException('boom');
    });

    try {
        AuditLogger::log('auth.login');
    } finally {
        Event::forget('eloquent.creating: '.AuditLog::class);
    }
});
