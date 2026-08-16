<?php

use App\Modules\Content\Models\Content;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('audio');
});

function streamContent(array $attributes = []): Content
{
    Storage::disk('audio')->put('contents/predication.mp3', '0123456789');

    return Content::create([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Prédication du dimanche',
        'file_path' => 'contents/predication.mp3',
        'original_filename' => 'Prédication du dimanche.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 10,
        ...$attributes,
    ]);
}

it('stream le fichier en entier (200)', function () {
    $content = streamContent();

    $response = $this->get("/api/v1/contents/{$content->id}/stream");

    $response->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Cache-Control', 'max-age=300, public');

    expect($response->streamedContent())->toBe('0123456789');
});

it('sert une plage partielle (206) avec Content-Range', function () {
    $content = streamContent();

    $response = $this->withHeaders(['Range' => 'bytes=0-3'])
        ->get("/api/v1/contents/{$content->id}/stream");

    $response->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-3/10')
        ->assertHeader('Content-Length', '4');

    expect($response->streamedContent())->toBe('0123');
});

it('sert une plage suffixe (206)', function () {
    $content = streamContent();

    $response = $this->withHeaders(['Range' => 'bytes=-3'])
        ->get("/api/v1/contents/{$content->id}/stream");

    $response->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 7-9/10');

    expect($response->streamedContent())->toBe('789');
});

it('répond 416 pour une plage commençant après la fin du fichier', function () {
    $content = streamContent();

    $response = $this->withHeaders(['Range' => 'bytes=10-20'])
        ->get("/api/v1/contents/{$content->id}/stream");

    $response->assertStatus(416)
        ->assertHeader('Content-Range', 'bytes */10');
});

it('borne une plage qui dépasse la taille du fichier (206 clampé)', function () {
    $content = streamContent();

    $response = $this->withHeaders(['Range' => 'bytes=0-100'])
        ->get("/api/v1/contents/{$content->id}/stream");

    $response->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-9/10');

    expect($response->streamedContent())->toBe('0123456789');
});

it('répond 404 pour un contenu inexistant', function () {
    $this->get('/api/v1/contents/999999/stream')->assertNotFound();
});

it('répond 404 si le fichier est absent du stockage', function () {
    $content = Content::create([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Introuvable',
        'file_path' => 'contents/absent.mp3',
        'original_filename' => 'absent.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 10,
    ]);

    $this->get("/api/v1/contents/{$content->id}/stream")->assertNotFound();
});

it('répond 404 pour un contenu non publié (US-025)', function () {
    $content = streamContent(['status' => Content::STATUS_DRAFT]);

    $this->get("/api/v1/contents/{$content->id}/stream")->assertNotFound();
});
