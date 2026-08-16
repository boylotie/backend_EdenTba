<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Streaming\Events\LiveStarted;
use App\Modules\Streaming\Events\LiveStopped;
use App\Modules\Streaming\Models\LiveSession;
use Database\Seeders\RbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('content_images');
    config(['streaming.signing_key' => 'test-signing-key']);
});

function liveAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function liveToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('retourne absent quand aucune session nest déclarée', function () {
    $this->getJson('/api/v1/live/status')
        ->assertOk()
        ->assertJsonPath('data.state', 'absent')
        ->assertJsonPath('data.metadata', null)
        ->assertJsonPath('data.ping_interval_seconds', 30)
        ->assertJsonMissingPath('data.stream_url');
});

it('ne fournit pas d URL signée à un utilisateur authentifié quand absent', function () {
    $this->withToken(liveToken(liveAdmin()))
        ->getJson('/api/v1/live/status')
        ->assertOk()
        ->assertJsonPath('data.state', 'absent')
        ->assertJsonMissingPath('data.stream_url');
});

it('refuse le démarrage sans authentification', function () {
    $this->postJson('/api/v1/live/start')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('refuse le démarrage sans permission streaming.start (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(liveToken($user))
        ->postJson('/api/v1/live/start', ['title' => 'Direct culte'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('démarre un direct : création, audit et événement', function () {
    Event::fake([LiveStarted::class]);

    $admin = liveAdmin();

    $this->withToken(liveToken($admin))
        ->postJson('/api/v1/live/start', [
            'title' => 'Culte du dimanche',
            'description' => 'Retransmission en direct.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.live_session.state', LiveSession::STATE_LIVE)
        ->assertJsonPath('data.live_session.title', 'Culte du dimanche');

    $this->assertDatabaseHas('live_sessions', [
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Culte du dimanche',
        'created_by' => $admin->id,
    ]);

    $session = LiveSession::latest('id')->firstOrFail();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'streaming.start',
        'entity_id' => (string) $session->id,
        'actor_id' => $admin->id,
    ]);

    Event::assertDispatched(LiveStarted::class, function (LiveStarted $event) use ($session): bool {
        return $event->session->id === $session->id;
    });
});

it('refuse un double démarrage (409, A1)', function () {
    LiveSession::create([
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Direct en cours',
        'started_at' => now(),
        'created_by' => liveAdmin()->id,
    ]);

    $this->withToken(liveToken(liveAdmin()))
        ->postJson('/api/v1/live/start', ['title' => 'Autre direct'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'live_already_started');
});

it('stocke un visuel optionnel au démarrage', function () {
    $admin = liveAdmin();

    $this->withToken(liveToken($admin))
        ->postJson('/api/v1/live/start', [
            'title' => 'Direct avec visuel',
            'image' => UploadedFile::fake()->image('live.png'),
        ])
        ->assertCreated();

    $session = LiveSession::latest('id')->firstOrFail();

    expect($session->image_path)->not->toBeNull()
        ->and(Storage::disk('content_images')->exists($session->image_path))->toBeTrue();
});

it('expose l état live avec URL signée uniquement pour un utilisateur authentifié', function () {
    LiveSession::create([
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Direct du jour',
        'started_at' => now(),
        'created_by' => liveAdmin()->id,
    ]);

    $this->getJson('/api/v1/live/status')
        ->assertOk()
        ->assertJsonPath('data.state', 'live')
        ->assertJsonPath('data.metadata.title', 'Direct du jour')
        ->assertJsonMissingPath('data.stream_url');

    auth()->forgetGuards();

    $this->withToken(liveToken(liveAdmin()))
        ->getJson('/api/v1/live/status')
        ->assertOk()
        ->assertJsonPath('data.state', 'live')
        ->assertJsonPath('data.stream_url_expires_at', now()->addSeconds(600)->getTimestamp())
        ->assertJsonPath('data.stream_url', fn (string $url): bool => str_starts_with($url, 'https://stream.domaine.tld/live/audio?expires=')
            && str_contains($url, '&token='));
});

it('refuse l arrêt sans authentification', function () {
    $this->postJson('/api/v1/live/stop')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('refuse l arrêt sans permission streaming.stop (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(liveToken($user))
        ->postJson('/api/v1/live/stop')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('arrête un direct : état off, audit et événement (A2)', function () {
    Event::fake([LiveStopped::class]);

    $admin = liveAdmin();
    $session = LiveSession::create([
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Direct à arrêter',
        'started_at' => now(),
        'created_by' => $admin->id,
    ]);

    $this->withToken(liveToken($admin))
        ->postJson('/api/v1/live/stop')
        ->assertOk()
        ->assertJsonPath('data.live_session.state', LiveSession::STATE_OFF);

    $session->refresh();

    expect($session->state)->toBe(LiveSession::STATE_OFF)
        ->and($session->stopped_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'streaming.stop',
        'entity_id' => (string) $session->id,
    ]);

    Event::assertDispatched(LiveStopped::class, function (LiveStopped $event) use ($session): bool {
        return $event->session->id === $session->id;
    });
});

it('refuse un arrêt sans live (409)', function () {
    $this->withToken(liveToken(liveAdmin()))
        ->postJson('/api/v1/live/stop')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'live_not_started');
});

it('expose l état off après arrêt sans URL signée', function () {
    LiveSession::create([
        'state' => LiveSession::STATE_OFF,
        'title' => 'Direct terminé',
        'started_at' => now()->subHour(),
        'stopped_at' => now(),
        'created_by' => liveAdmin()->id,
    ]);

    $this->withToken(liveToken(liveAdmin()))
        ->getJson('/api/v1/live/status')
        ->assertOk()
        ->assertJsonPath('data.state', 'off')
        ->assertJsonMissingPath('data.stream_url');
});

it('sert le visuel du direct en cours', function () {
    Storage::disk('content_images')->put('live/cover.png', 'pngdata');

    LiveSession::create([
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Direct avec visuel',
        'image_path' => 'live/cover.png',
        'started_at' => now(),
        'created_by' => liveAdmin()->id,
    ]);

    $this->get('/api/v1/live/image')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('retourne 404 sur le visuel quand aucune session', function () {
    $this->get('/api/v1/live/image')->assertNotFound();
});

it('rejette un visuel invalide au démarrage (422)', function () {
    $this->withToken(liveToken(liveAdmin()))
        ->postJson('/api/v1/live/start', [
            'image' => UploadedFile::fake()->create('document.txt', 10),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['image']]]);
});
