<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Analytics\Models\ListeningEvent;
use App\Modules\Content\Models\Content;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('audio');
});

function statisticsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function statisticsRoleAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);

    return $user;
}

function statisticsToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function statisticsContent(array $attrs = [], int $index = 0): Content
{
    Storage::disk('audio')->put("contents/stats-{$index}.mp3", 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Statistiques n°'.($index + 1),
        'file_path' => "contents/stats-{$index}.mp3",
        'original_filename' => "stats-{$index}.mp3",
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

function statisticsEvent(int $contentId, string $type, ?int $daysAgo = null): void
{
    ListeningEvent::create([
        'content_id' => $contentId,
        'event_type' => $type,
        'occurred_at' => $daysAgo === null ? now() : now()->subDays($daysAgo),
    ]);
}

it('refuse la consultation sans authentification', function () {
    $this->getJson('/api/v1/admin/statistics')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('refuse la consultation sans permission statistics.view (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(statisticsToken($user))
        ->getJson('/api/v1/admin/statistics')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('retourne un état vide explicite (A1)', function () {
    $this->withToken(statisticsToken(statisticsAdmin()))
        ->getJson('/api/v1/admin/statistics?period=7d')
        ->assertOk()
        ->assertJsonPath('data.statistics.empty', true)
        ->assertJsonPath('data.statistics.totals.plays', 0)
        ->assertJsonPath('data.statistics.totals.completions', 0)
        ->assertJsonPath('data.statistics.totals.contents', 0)
        ->assertJsonCount(0, 'data.statistics.by_content')
        ->assertJsonCount(7, 'data.statistics.by_period')
        ->assertJsonPath('data.statistics.by_period.0.plays', 0);
});

it('agrège les écoutes par contenu et par période (US-048)', function () {
    $first = statisticsContent([], 0);
    $second = statisticsContent([], 1);

    statisticsEvent($first->id, ListeningEvent::EVENT_PLAY);
    statisticsEvent($first->id, ListeningEvent::EVENT_PLAY);
    statisticsEvent($first->id, ListeningEvent::EVENT_COMPLETED);
    statisticsEvent($second->id, ListeningEvent::EVENT_PLAY);
    statisticsEvent($second->id, ListeningEvent::EVENT_COMPLETED, 2);

    $this->withToken(statisticsToken(statisticsAdmin()))
        ->getJson('/api/v1/admin/statistics?period=7d')
        ->assertOk()
        ->assertJsonPath('data.statistics.empty', false)
        ->assertJsonPath('data.statistics.totals.plays', 3)
        ->assertJsonPath('data.statistics.totals.completions', 2)
        ->assertJsonPath('data.statistics.totals.contents', 2)
        ->assertJsonPath('data.statistics.by_content.0.content_id', $first->id)
        ->assertJsonPath('data.statistics.by_content.0.plays', 2)
        ->assertJsonPath('data.statistics.by_content.0.completions', 1)
        ->assertJsonPath('data.statistics.by_content.0.title', 'Statistiques n°1')
        ->assertJsonPath('data.statistics.by_content.1.content_id', $second->id);
});

it('remplit les jours sans écoute avec des zéros', function () {
    statisticsEvent(statisticsContent([], 0)->id, ListeningEvent::EVENT_PLAY, 2);

    $response = $this->withToken(statisticsToken(statisticsAdmin()))
        ->getJson('/api/v1/admin/statistics?period=7d')
        ->assertOk()
        ->assertJsonCount(7, 'data.statistics.by_period')
        ->assertJsonPath('data.statistics.by_period.4.plays', 1);

    $dates = collect($response->json('data.statistics.by_period'));

    expect($dates->get(0)['plays'])->toBe(0)
        ->and($dates->get(1)['plays'])->toBe(0)
        ->and($dates->get(5)['plays'])->toBe(0);
});

it('filtre les événements hors période', function () {
    statisticsEvent(statisticsContent([], 0)->id, ListeningEvent::EVENT_PLAY, 10);

    $this->withToken(statisticsToken(statisticsAdmin()))
        ->getJson('/api/v1/admin/statistics?period=7d')
        ->assertOk()
        ->assertJsonPath('data.statistics.empty', true)
        ->assertJsonPath('data.statistics.totals.plays', 0);

    $this->withToken(statisticsToken(statisticsAdmin()))
        ->getJson('/api/v1/admin/statistics?period=30d')
        ->assertOk()
        ->assertJsonPath('data.statistics.empty', false)
        ->assertJsonPath('data.statistics.totals.plays', 1);
});

it('respecte la limite de contenus retournés', function () {
    statisticsEvent(statisticsContent([], 0)->id, ListeningEvent::EVENT_PLAY);
    statisticsEvent(statisticsContent([], 1)->id, ListeningEvent::EVENT_PLAY);
    statisticsEvent(statisticsContent([], 2)->id, ListeningEvent::EVENT_PLAY);

    $this->withToken(statisticsToken(statisticsAdmin()))
        ->getJson('/api/v1/admin/statistics?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data.statistics.by_content');
});

it('autorise un administrateur avec la permission statistics.view', function () {
    $this->withToken(statisticsToken(statisticsRoleAdmin()))
        ->getJson('/api/v1/admin/statistics')
        ->assertOk()
        ->assertJsonPath('data.statistics.empty', true);
});

it('journalise la consultation des statistiques', function () {
    $admin = statisticsAdmin();

    $this->withToken(statisticsToken($admin))
        ->getJson('/api/v1/admin/statistics?period=7d')
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'statistics.view',
        'actor_id' => $admin->id,
    ]);
});
