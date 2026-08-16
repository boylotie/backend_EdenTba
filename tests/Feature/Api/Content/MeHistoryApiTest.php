<?php

use App\Models\User;
use App\Modules\Content\Models\Content;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('audio');
});

function historyUser(): User
{
    return User::factory()->create();
}

function historyToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function historyContent(array $attrs = [], int $index = 0): Content
{
    Storage::disk('audio')->put("contents/hist-{$index}.mp3", 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Enseignement n°'.($index + 1),
        'file_path' => "contents/hist-{$index}.mp3",
        'original_filename' => "hist-{$index}.mp3",
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

it('exige une authentification pour accéder à l historique', function () {
    $this->getJson('/api/v1/me/history')->assertStatus(401);
});

it('enregistre et expose une position d écoute (US-035)', function () {
    $user = historyUser();
    $content = historyContent(['duration_seconds' => 600]);

    $this->withToken(historyToken($user))
        ->putJson("/api/v1/me/history/{$content->id}", ['position_seconds' => 120])
        ->assertOk()
        ->assertJsonPath('data.history_entry.position_seconds', 120)
        ->assertJsonPath('data.history_entry.completed', false)
        ->assertJsonPath('data.history_entry.resume_seconds', 120);

    $this->withToken(historyToken($user))
        ->getJson('/api/v1/me/history')
        ->assertOk()
        ->assertJsonCount(1, 'data.history')
        ->assertJsonPath('data.history.0.content.id', $content->id)
        ->assertJsonPath('data.history.0.position_seconds', 120);
});

it('reprend depuis le début si la lecture était terminée', function () {
    $user = historyUser();
    $content = historyContent(['duration_seconds' => 600]);

    $this->withToken(historyToken($user))
        ->putJson("/api/v1/me/history/{$content->id}", ['position_seconds' => 600])
        ->assertOk()
        ->assertJsonPath('data.history_entry.completed', true)
        ->assertJsonPath('data.history_entry.resume_seconds', 0);
});

it('marque terminé sur demande explicite', function () {
    $user = historyUser();
    $content = historyContent();

    $this->withToken(historyToken($user))
        ->putJson("/api/v1/me/history/{$content->id}", ['position_seconds' => 0, 'completed' => true])
        ->assertOk()
        ->assertJsonPath('data.history_entry.completed', true)
        ->assertJsonPath('data.history_entry.resume_seconds', 0);
});

it('écrase la position précédente (upsert par contenu)', function () {
    $user = historyUser();
    $content = historyContent(['duration_seconds' => 600]);

    $this->withToken(historyToken($user))->putJson("/api/v1/me/history/{$content->id}", ['position_seconds' => 60])->assertOk();
    $this->withToken(historyToken($user))->putJson("/api/v1/me/history/{$content->id}", ['position_seconds' => 90])->assertOk();

    $this->withToken(historyToken($user))
        ->getJson('/api/v1/me/history')
        ->assertOk()
        ->assertJsonCount(1, 'data.history')
        ->assertJsonPath('data.history.0.position_seconds', 90);
});

it('limite l historique aux 50 dernières lectures', function () {
    $user = historyUser();

    foreach (range(1, 55) as $i) {
        $content = historyContent([], $i);
        $this->withToken(historyToken($user))->putJson("/api/v1/me/history/{$content->id}", ['position_seconds' => $i])->assertOk();
    }

    $this->withToken(historyToken($user))
        ->getJson('/api/v1/me/history')
        ->assertOk()
        ->assertJsonCount(50, 'data.history');
});

it('refuse une position négative', function () {
    $user = historyUser();
    $content = historyContent();

    $this->withToken(historyToken($user))
        ->putJson("/api/v1/me/history/{$content->id}", ['position_seconds' => -1])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('répond 404 pour un contenu non publié', function () {
    $user = historyUser();
    $draft = historyContent(['status' => Content::STATUS_DRAFT]);

    $this->withToken(historyToken($user))
        ->putJson("/api/v1/me/history/{$draft->id}", ['position_seconds' => 10])
        ->assertNotFound();
});
