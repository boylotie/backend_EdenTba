<?php

use App\Modules\Analytics\Models\ListeningEvent;
use App\Modules\Content\Models\Content;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('audio');
});

function listeningEventContent(array $attrs = [], int $index = 0): Content
{
    Storage::disk('audio')->put("contents/listening-{$index}.mp3", 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Écoute n°'.($index + 1),
        'file_path' => "contents/listening-{$index}.mp3",
        'original_filename' => "listening-{$index}.mp3",
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

it('enregistre un événement de lecture anonymisé (US-048, 201)', function () {
    $content = listeningEventContent();

    $this->postJson('/api/v1/listening-events', [
        'content_id' => $content->id,
        'event_type' => ListeningEvent::EVENT_PLAY,
    ])
        ->assertCreated()
        ->assertJsonPath('data.listening_event.content_id', $content->id)
        ->assertJsonPath('data.listening_event.event_type', ListeningEvent::EVENT_PLAY)
        ->assertJsonPath('data.listening_event.position_seconds', null);

    $this->assertDatabaseHas('listening_events', [
        'content_id' => $content->id,
        'event_type' => ListeningEvent::EVENT_PLAY,
    ]);
});

it('enregistre un événement completed avec sa position', function () {
    $content = listeningEventContent(['duration_seconds' => 600]);

    $this->postJson('/api/v1/listening-events', [
        'content_id' => $content->id,
        'event_type' => ListeningEvent::EVENT_COMPLETED,
        'position_seconds' => 600,
    ])
        ->assertCreated()
        ->assertJsonPath('data.listening_event.event_type', ListeningEvent::EVENT_COMPLETED)
        ->assertJsonPath('data.listening_event.position_seconds', 600);
});

it('ne collecte aucune donnée personnelle (A2)', function () {
    $content = listeningEventContent();

    $this->postJson('/api/v1/listening-events', [
        'content_id' => $content->id,
        'event_type' => ListeningEvent::EVENT_PLAY,
    ])->assertCreated();

    $columns = Schema::getColumnListing('listening_events');

    expect($columns)->not->toContain('user_id')
        ->and($columns)->not->toContain('ip_address')
        ->and($columns)->not->toContain('device_id')
        ->and($columns)->not->toContain('user_agent');
});

it('refuse un contenu non publié (404, D-02)', function () {
    $content = listeningEventContent(['status' => Content::STATUS_DRAFT]);

    $this->postJson('/api/v1/listening-events', [
        'content_id' => $content->id,
        'event_type' => ListeningEvent::EVENT_PLAY,
    ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->assertDatabaseCount('listening_events', 0);
});

it('refuse un type dévénement inconnu (422)', function () {
    $content = listeningEventContent();

    $this->postJson('/api/v1/listening-events', [
        'content_id' => $content->id,
        'event_type' => 'paused',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['event_type']]]);
});

it('refuse un contenu inexistant (422)', function () {
    $this->postJson('/api/v1/listening-events', [
        'content_id' => 999999,
        'event_type' => ListeningEvent::EVENT_PLAY,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['content_id']]]);
});

it('accepte des événements sans authentification (lecture ouverte)', function () {
    $content = listeningEventContent();

    $this->postJson('/api/v1/listening-events', [
        'content_id' => $content->id,
        'event_type' => ListeningEvent::EVENT_PLAY,
    ])->assertCreated();
});
