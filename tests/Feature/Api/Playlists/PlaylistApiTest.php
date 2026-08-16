<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Content\Models\Content;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('audio');
    Storage::fake('content_images');
    Cache::flush();
});

function playlistAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function playlistToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function playlistRecord(array $attrs = []): Playlist
{
    return Playlist::create(array_merge([
        'title' => 'Culte de jeûne',
        'is_public' => false,
    ], $attrs));
}

function playlistContent(array $attrs = [], int $index = 0): Content
{
    Storage::disk('audio')->put("contents/playlist-{$index}.mp3", 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Enseignement playlist n°'.($index + 1),
        'file_path' => "contents/playlist-{$index}.mp3",
        'original_filename' => "playlist-{$index}.mp3",
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

it('refuse la création de playlist sans authentification', function () {
    $this->postJson('/api/v1/playlists', ['title' => 'Culte de jeûne'])
        ->assertUnauthorized();
});

it('refuse la création de playlist sans permission playlist.manage (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(playlistToken($user))
        ->postJson('/api/v1/playlists', ['title' => 'Culte de jeûne'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('crée une playlist privée par défaut', function () {
    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson('/api/v1/playlists', ['title' => 'Culte de jeûne'])
        ->assertCreated()
        ->assertJsonPath('data.playlist.title', 'Culte de jeûne')
        ->assertJsonPath('data.playlist.is_public', false)
        ->assertJsonPath('data.playlist.items_count', 0);

    $this->assertDatabaseHas('playlists', ['title' => 'Culte de jeûne', 'is_public' => false]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'playlists.create']);
});

it('crée une playlist publique avec un rattachement cohérent', function () {
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $type = ActivityType::firstOrCreate(['code' => 'convention'], ['label' => 'Convention']);
    $activity = SpecialActivity::create([
        'week_id' => $week->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention de prière',
        'mode' => 'complement',
    ]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson('/api/v1/playlists', [
            'title' => 'Culte du réveil',
            'description' => 'Playlist publique du culte',
            'is_public' => true,
            'year_id' => $year->id,
            'week_id' => $week->id,
            'special_activity_id' => $activity->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.playlist.is_public', true)
        ->assertJsonPath('data.playlist.special_activity.id', $activity->id)
        ->assertJsonPath('data.playlist.week.label', 'Semaine 1');
});

it('refuse un rattachement incohérent (activité hors semaine)', function () {
    $year = Year::create(['label' => '2026-2027']);
    $weekA = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $weekB = Week::create(['year_id' => $year->id, 'label' => 'Semaine 2']);
    $type = ActivityType::firstOrCreate(['code' => 'convention'], ['label' => 'Convention']);
    $activity = SpecialActivity::create([
        'week_id' => $weekA->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention de prière',
        'mode' => 'complement',
    ]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson('/api/v1/playlists', [
            'title' => 'Culte',
            'week_id' => $weekB->id,
            'special_activity_id' => $activity->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['special_activity_id']]]);
});

it('met à jour une playlist', function () {
    $playlist = playlistRecord();

    $this->withToken(playlistToken(playlistAdmin()))
        ->putJson("/api/v1/playlists/{$playlist->id}", ['title' => 'Culte du réveil', 'is_public' => true])
        ->assertOk()
        ->assertJsonPath('data.playlist.title', 'Culte du réveil')
        ->assertJsonPath('data.playlist.is_public', true);

    $this->assertDatabaseHas('playlists', ['id' => $playlist->id, 'title' => 'Culte du réveil', 'is_public' => true]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'playlists.update']);
});

it('supprime une playlist et ses éléments', function () {
    $playlist = playlistRecord();
    $content = playlistContent();
    $playlist->items()->create(['content_id' => $content->id, 'position' => 0]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->deleteJson("/api/v1/playlists/{$playlist->id}")
        ->assertOk();

    $this->assertDatabaseMissing('playlists', ['id' => $playlist->id]);
    $this->assertDatabaseMissing('playlist_items', ['playlist_id' => $playlist->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'playlists.delete']);
});

it('ajoute un contenu publié en fin de liste par défaut', function () {
    $playlist = playlistRecord();
    $content = playlistContent();

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson("/api/v1/playlists/{$playlist->id}/items", ['content_id' => $content->id])
        ->assertCreated()
        ->assertJsonPath('data.playlist_item.content_id', $content->id)
        ->assertJsonPath('data.playlist_item.position', 0);

    $this->assertDatabaseHas('playlist_items', ['playlist_id' => $playlist->id, 'content_id' => $content->id, 'position' => 0]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'playlists.items.add']);
});

it('ajoute un contenu à une position explicite libre', function () {
    $playlist = playlistRecord();
    $content = playlistContent();

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson("/api/v1/playlists/{$playlist->id}/items", ['content_id' => $content->id, 'position' => 5])
        ->assertCreated()
        ->assertJsonPath('data.playlist_item.position', 5);
});

it('refuse un contenu non publié (422)', function () {
    $playlist = playlistRecord();
    $draft = playlistContent(['status' => Content::STATUS_DRAFT]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson("/api/v1/playlists/{$playlist->id}/items", ['content_id' => $draft->id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'playlist_item_rejected');

    $this->assertDatabaseMissing('playlist_items', ['playlist_id' => $playlist->id, 'content_id' => $draft->id]);
});

it('refuse un contenu déjà présent (422)', function () {
    $playlist = playlistRecord();
    $content = playlistContent();
    $playlist->items()->create(['content_id' => $content->id, 'position' => 0]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson("/api/v1/playlists/{$playlist->id}/items", ['content_id' => $content->id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'playlist_item_rejected');
});

it('refuse une position déjà occupée (422)', function () {
    $playlist = playlistRecord();
    $first = playlistContent([], 1);
    $second = playlistContent([], 2);
    $playlist->items()->create(['content_id' => $first->id, 'position' => 0]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson("/api/v1/playlists/{$playlist->id}/items", ['content_id' => $second->id, 'position' => 0])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'playlist_item_rejected');
});

it('refuse un contenu inexistant (422)', function () {
    $playlist = playlistRecord();

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson("/api/v1/playlists/{$playlist->id}/items", ['content_id' => 999999])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['content_id']]]);
});

it('réordonne les contenus d une playlist', function () {
    $playlist = playlistRecord();
    $a = playlistContent([], 1);
    $b = playlistContent([], 2);
    $c = playlistContent([], 3);
    $playlist->items()->create(['content_id' => $a->id, 'position' => 0]);
    $playlist->items()->create(['content_id' => $b->id, 'position' => 1]);
    $playlist->items()->create(['content_id' => $c->id, 'position' => 2]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->putJson("/api/v1/playlists/{$playlist->id}/items", ['items' => [$c->id, $a->id, $b->id]])
        ->assertOk()
        ->assertJsonPath('data.items.0.content_id', $c->id)
        ->assertJsonPath('data.items.1.content_id', $a->id)
        ->assertJsonPath('data.items.2.content_id', $b->id);

    $this->assertDatabaseHas('audit_logs', ['action' => 'playlists.items.reorder']);
    $this->assertDatabaseHas('playlist_items', ['playlist_id' => $playlist->id, 'content_id' => $c->id, 'position' => 0]);
});

it('refuse un réordonnancement incohérent (422)', function () {
    $playlist = playlistRecord();
    $a = playlistContent([], 1);
    $b = playlistContent([], 2);
    $playlist->items()->create(['content_id' => $a->id, 'position' => 0]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->putJson("/api/v1/playlists/{$playlist->id}/items", ['items' => [$a->id, $b->id]])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'playlist_items_mismatch');
});

it('retire un contenu d une playlist', function () {
    $playlist = playlistRecord();
    $content = playlistContent();
    $item = $playlist->items()->create(['content_id' => $content->id, 'position' => 0]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->deleteJson("/api/v1/playlists/{$playlist->id}/items/{$item->id}")
        ->assertOk();

    $this->assertDatabaseMissing('playlist_items', ['id' => $item->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'playlists.items.remove']);
});

it('refuse de manipuler un élément d une autre playlist (binding scopé)', function () {
    $playlistA = playlistRecord(['title' => 'Playlist A']);
    $playlistB = playlistRecord(['title' => 'Playlist B']);
    $content = playlistContent();
    $item = $playlistA->items()->create(['content_id' => $content->id, 'position' => 0]);

    $this->withToken(playlistToken(playlistAdmin()))
        ->deleteJson("/api/v1/playlists/{$playlistB->id}/items/{$item->id}")
        ->assertNotFound();
});

it('refuse la suppression d une activité référencée par une playlist', function () {
    $year = Year::create(['label' => '2026-2027']);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $type = ActivityType::firstOrCreate(['code' => 'convention'], ['label' => 'Convention']);
    $activity = SpecialActivity::create([
        'week_id' => $week->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention de prière',
        'mode' => 'complement',
    ]);
    playlistRecord(['special_activity_id' => $activity->id]);

    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    $this->withToken(playlistToken($user))
        ->deleteJson("/api/v1/special-activities/{$activity->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'activity_in_use');
});

it('invalide le cache public après une écriture', function () {
    $this->getJson('/api/v1/playlists')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 0);

    $this->withToken(playlistToken(playlistAdmin()))
        ->postJson('/api/v1/playlists', ['title' => 'Culte de jeûne', 'is_public' => true])
        ->assertCreated();

    $this->getJson('/api/v1/playlists')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
});
