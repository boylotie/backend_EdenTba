<?php

use App\Modules\Content\Models\Content;
use App\Modules\Organization\Models\Year;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('audio');
    Storage::fake('content_images');
    Cache::flush();
});

function publicPlaylist(array $attrs = []): Playlist
{
    return Playlist::create(array_merge([
        'title' => 'Culte du réveil',
        'description' => 'Playlist publique',
        'is_public' => true,
    ], $attrs));
}

it('liste uniquement les playlists publiques', function () {
    publicPlaylist();
    publicPlaylist(['title' => 'Privée', 'is_public' => false]);

    $this->getJson('/api/v1/playlists')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Culte du réveil')
        ->assertJsonPath('data.0.items_count', 0)
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertHeader('Cache-Control', 'max-age=300, public');
});

it('pagine la liste publique des playlists', function () {
    for ($i = 0; $i < 12; $i++) {
        publicPlaylist(['title' => 'Playlist n°'.($i + 1)]);
    }

    $this->getJson('/api/v1/playlists')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 12)
        ->assertJsonPath('meta.pagination.last_page', 2)
        ->assertJsonCount(10, 'data');

    $this->getJson('/api/v1/playlists?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('affiche le détail d une playlist publique avec les contenus publiés', function () {
    $published = publicContentRecord();
    $draft = publicContentRecord(['status' => Content::STATUS_DRAFT], 1);
    $playlist = publicPlaylist();
    $playlist->items()->create(['content_id' => $published->id, 'position' => 0]);
    $playlist->items()->create(['content_id' => $draft->id, 'position' => 1]);

    $this->getJson("/api/v1/playlists/{$playlist->id}")
        ->assertOk()
        ->assertJsonPath('data.playlist.id', $playlist->id)
        ->assertJsonPath('data.playlist.items.0.content.id', $published->id)
        ->assertJsonPath('data.playlist.items.0.content.title', $published->title)
        ->assertJsonPath('data.playlist.items.0.content.image_url', null)
        ->assertJsonCount(1, 'data.playlist.items')
        ->assertHeader('Cache-Control', 'max-age=300, public');
});

it('répond 404 pour une playlist non publique', function () {
    $private = publicPlaylist(['is_public' => false]);

    $this->getJson("/api/v1/playlists/{$private->id}")->assertNotFound();
});

it('répond 404 pour une playlist inexistante', function () {
    $this->getJson('/api/v1/playlists/999999')->assertNotFound();
});

it('réordonne les contenus du détail par position', function () {
    $first = publicContentRecord([], 1);
    $second = publicContentRecord([], 2);
    $playlist = publicPlaylist();
    $playlist->items()->create(['content_id' => $first->id, 'position' => 1]);
    $playlist->items()->create(['content_id' => $second->id, 'position' => 0]);

    $this->getJson("/api/v1/playlists/{$playlist->id}")
        ->assertOk()
        ->assertJsonPath('data.playlist.items.0.content.id', $second->id)
        ->assertJsonPath('data.playlist.items.1.content.id', $first->id);
});

it('reste stable après une modification de contenu sans changement de publication', function () {
    $content = publicContentRecord();
    $playlist = publicPlaylist();
    $playlist->items()->create(['content_id' => $content->id, 'position' => 0]);

    $year = Year::create(['label' => '2026-2027']);

    $this->getJson("/api/v1/playlists/{$playlist->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.playlist.items');

    $content->update(['year_id' => $year->id]);

    $this->getJson("/api/v1/playlists/{$playlist->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.playlist.items');
});
