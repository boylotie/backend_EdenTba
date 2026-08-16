<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Streaming\Models\LiveSession;
use App\Settings\SettingsService;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('content_images');
});

function livePageAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function livePageSession(array $attrs = []): LiveSession
{
    return LiveSession::create(array_merge([
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Culte du dimanche',
        'started_at' => now(),
        'created_by' => livePageAdmin()->id,
    ], $attrs));
}

it('affiche l écran du direct pour un administrateur', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/admin/live')
        ->assertOk()
        ->assertSee('Direct (live)')
        ->assertSee('Lancer un direct');
});

it('refuse l accès sans permission streaming.start', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $role->permissions()->detach(Permission::where('name', 'streaming.start')->firstOrFail()->id);

    $this->actingAs($user)->get('/admin/live')->assertForbidden();
});

it('affiche l état en direct quand une session est en cours', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    livePageSession();

    $this->actingAs($user)
        ->get('/admin/live')
        ->assertOk()
        ->assertSee('EN DIRECT')
        ->assertSee('Culte du dimanche')
        ->assertSee('Arrêter le direct');
});

it('affiche la capture micro navigateur quand un direct est en cours', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    livePageSession();

    $this->actingAs($user)
        ->get('/admin/live')
        ->assertOk()
        ->assertSee('Capturer le micro (diffusion navigateur)')
        ->assertSee('Démarrer le micro');
});

it('masque la capture micro navigateur hors direct', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/admin/live')
        ->assertOk()
        ->assertDontSee('Démarrer le micro');
});

it('affiche les accès du serveur de diffusion pour l encodeur', function () {
    app(SettingsService::class)->replace(array_merge(app(SettingsService::class)->all(), [
        'stream_source_url' => 'http://stream.domaine.tld:8000/live',
        'stream_source_password' => 'mot-de-passe-source',
        'stream_url_base' => 'https://stream.domaine.tld/live/audio',
    ]));

    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    $this->actingAs($user)
        ->get('/admin/live')
        ->assertOk()
        ->assertSee('Serveur de diffusion (encodeur)')
        ->assertSee('http://stream.domaine.tld:8000/live')
        ->assertSee('https://stream.domaine.tld/live/audio');
});

it('masque puis révèle le mot de passe de la source', function () {
    app(SettingsService::class)->replace(array_merge(app(SettingsService::class)->all(), [
        'stream_source_password' => 'mot-de-passe-source',
    ]));

    Livewire::actingAs(livePageAdmin())
        ->test('pages::admin.live')
        ->assertSee('Mot de passe de la source')
        ->assertSet('showPassword', false)
        ->assertSeeHtml('type="password"')
        ->call('togglePassword')
        ->assertSet('showPassword', true)
        ->assertSeeHtml('type="text"');
});

it('lance un direct via le formulaire', function () {
    Livewire::actingAs(livePageAdmin())
        ->test('pages::admin.live')
        ->set('title', 'Culte du dimanche')
        ->set('description', 'Retransmission en direct.')
        ->call('startLive')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('live_sessions', [
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Culte du dimanche',
        'description' => 'Retransmission en direct.',
    ]);
});

it('arrête un direct en cours via le formulaire', function () {
    $session = livePageSession();

    Livewire::actingAs(livePageAdmin())
        ->test('pages::admin.live')
        ->call('stopLive');

    $this->assertDatabaseHas('live_sessions', [
        'id' => $session->id,
        'state' => LiveSession::STATE_OFF,
    ]);

    expect($session->fresh()->stopped_at)->not->toBeNull();
});

it('refuse de lancer un direct quand un live est déjà en cours', function () {
    livePageSession();

    Livewire::actingAs(livePageAdmin())
        ->test('pages::admin.live')
        ->set('title', 'Autre direct')
        ->call('startLive')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('live_sessions', 1);
    $this->assertDatabaseHas('live_sessions', ['title' => 'Culte du dimanche', 'state' => LiveSession::STATE_LIVE]);
});
