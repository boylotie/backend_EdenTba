<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Content\Jobs\ExtractAudioMetadata;
use App\Modules\Content\Models\Content;
use App\Modules\Content\Storage\AudioStorage;
use App\Settings\SettingsService;
use Database\Seeders\RbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('audio');
});

function contentAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function contentToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('refuse l upload sans authentification', function () {
    $this->postJson('/api/v1/contents/upload', [
        'file' => UploadedFile::fake()->create('predication.mp3', 100),
    ])->assertStatus(401);
});

it('refuse l upload sans permission content.create (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(contentToken($user))
        ->postJson('/api/v1/contents/upload', [
            'file' => UploadedFile::fake()->create('predication.mp3', 100),
        ])
        ->assertForbidden();
});

it('stocke un fichier audio valide en privé', function () {
    $admin = contentAdmin();

    $response = $this->withToken(contentToken($admin))
        ->postJson('/api/v1/contents/upload', [
            'file' => UploadedFile::fake()->create('predication.mp3', 100),
            'title' => 'Prédication du dimanche',
            'description' => 'Enseignement sur la foi',
        ])
        ->assertCreated()
        ->assertJsonPath('data.content.title', 'Prédication du dimanche')
        ->assertJsonPath('data.content.mime_type', 'audio/mpeg');

    $content = Content::query()->firstOrFail();

    expect(Storage::disk('audio')->exists($content->file_path))->toBeTrue()
        ->and($content->size_bytes)->toBeGreaterThan(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'contents.upload',
        'entity_type' => 'content',
        'entity_id' => $content->id,
        'actor_id' => $admin->id,
    ]);
});

it('dispatche le traitement asynchrone des métadonnées', function () {
    Queue::fake();

    $this->withToken(contentToken(contentAdmin()))
        ->postJson('/api/v1/contents/upload', [
            'file' => UploadedFile::fake()->create('predication.mp3', 100),
        ])
        ->assertCreated();

    Queue::assertPushed(ExtractAudioMetadata::class);
});

it('titre par défaut dérivé du nom de fichier', function () {
    $this->withToken(contentToken(contentAdmin()))
        ->postJson('/api/v1/contents/upload', [
            'file' => UploadedFile::fake()->create('predication.mp3', 100),
        ])
        ->assertCreated()
        ->assertJsonPath('data.content.title', 'predication');
});

it('rejette un format non autorisé en 422', function () {
    $this->withToken(contentToken(contentAdmin()))
        ->postJson('/api/v1/contents/upload', [
            'file' => UploadedFile::fake()->create('virus.exe', 10),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['file']]]);
});

it('rejette un fichier trop volumineux en 422 (limite configurable)', function () {
    app(SettingsService::class)->replace(['audio_max_upload_mb' => 1]);

    $this->withToken(contentToken(contentAdmin()))
        ->postJson('/api/v1/contents/upload', [
            'file' => UploadedFile::fake()->create('predication.mp3', 2048),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['file']]]);
});

it('supprime le fichier si l enregistrement échoue (aucune donnée partielle)', function () {
    Queue::shouldReceive('push')->andThrow(new RuntimeException('boom'));

    $this->withToken(contentToken(contentAdmin()))
        ->postJson('/api/v1/contents/upload', [
            'file' => UploadedFile::fake()->create('predication.mp3', 100),
        ])
        ->assertStatus(500);

    expect(Content::count())->toBe(0)
        ->and(Storage::disk('audio')->allFiles('contents'))->toBeEmpty();
});

it('expose les formats autorisés via la couche de stockage', function () {
    expect(AudioStorage::allowedExtensions())->toBe(['mp3', 'm4a', 'wav', 'ogg', 'aac']);
});
