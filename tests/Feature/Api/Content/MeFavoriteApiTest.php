<?php

use App\Models\User;
use App\Modules\Content\Models\Content;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('audio');
});

function favoriteUser(): User
{
    return User::factory()->create();
}

function favoriteToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function favoriteContent(array $attrs = []): Content
{
    Storage::disk('audio')->put('contents/favorite.mp3', 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Enseignement favori',
        'file_path' => 'contents/favorite.mp3',
        'original_filename' => 'favorite.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

it('exige une authentification pour accéder aux favoris', function () {
    $this->getJson('/api/v1/me/favorites')->assertStatus(401);
});

it('ajoute, liste et retire un favori (US-034)', function () {
    $user = favoriteUser();
    $content = favoriteContent();

    $this->withToken(favoriteToken($user))
        ->postJson('/api/v1/me/favorites', ['content_id' => $content->id])
        ->assertCreated()
        ->assertJsonPath('data.favorite.content_id', $content->id)
        ->assertJsonPath('data.favorite.content.title', 'Enseignement favori');

    $this->withToken(favoriteToken($user))
        ->getJson('/api/v1/me/favorites')
        ->assertOk()
        ->assertJsonCount(1, 'data.favorites')
        ->assertJsonPath('data.favorites.0.content.id', $content->id);

    $this->withToken(favoriteToken($user))
        ->deleteJson("/api/v1/me/favorites/{$content->id}")
        ->assertOk()
        ->assertJsonPath('data.message', 'Favori retiré.');

    $this->withToken(favoriteToken($user))
        ->getJson('/api/v1/me/favorites')
        ->assertOk()
        ->assertJsonPath('data.favorites', []);
});

it('est idempotent à l ajout d un favori', function () {
    $user = favoriteUser();
    $content = favoriteContent();

    $this->withToken(favoriteToken($user))
        ->postJson('/api/v1/me/favorites', ['content_id' => $content->id])
        ->assertCreated();

    $this->withToken(favoriteToken($user))
        ->postJson('/api/v1/me/favorites', ['content_id' => $content->id])
        ->assertCreated();
});

it('isole les favoris entre utilisateurs', function () {
    $userA = favoriteUser();
    $userB = favoriteUser();
    $content = favoriteContent();

    $this->withToken(favoriteToken($userA))
        ->postJson('/api/v1/me/favorites', ['content_id' => $content->id])
        ->assertCreated();

    auth()->forgetGuards();

    $this->withToken(favoriteToken($userB))
        ->getJson('/api/v1/me/favorites')
        ->assertOk()
        ->assertJsonPath('data.favorites', []);
});

it('refuse de mettre en favori un contenu non publié ou inexistant', function () {
    $user = favoriteUser();
    $draft = favoriteContent(['status' => Content::STATUS_DRAFT]);

    $this->withToken(favoriteToken($user))
        ->postJson('/api/v1/me/favorites', ['content_id' => $draft->id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');

    $this->withToken(favoriteToken($user))
        ->postJson('/api/v1/me/favorites', ['content_id' => 999999])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('refuse un favori sans content_id', function () {
    $user = favoriteUser();

    $this->withToken(favoriteToken($user))
        ->postJson('/api/v1/me/favorites', [])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['content_id']]]);
});
