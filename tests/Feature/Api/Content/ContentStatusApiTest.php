<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Content\Events\ContentStatusChanged;
use App\Modules\Content\Models\Content;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('audio');
    Storage::fake('content_images');
});

function statusAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function statusToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function statusContent(array $attrs = []): Content
{
    Storage::disk('audio')->put('contents/status.mp3', 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_DRAFT,
        'title' => 'Enseignement',
        'file_path' => 'contents/status.mp3',
        'original_filename' => 'status.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

it('refuse une transition sans authentification', function () {
    $content = statusContent();

    $this->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertUnauthorized();
});

it('refuse une transition sans permission content.publish (403)', function () {
    $content = statusContent();

    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(statusToken($user))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('publie un contenu : transition, audit et événement émis', function () {
    Event::fake([ContentStatusChanged::class]);

    $content = statusContent();

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk()
        ->assertJsonPath('data.content.status', Content::STATUS_PUBLISHED);

    $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => Content::STATUS_PUBLISHED]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'contents.status_changed', 'entity_id' => (string) $content->id]);

    Event::assertDispatched(ContentStatusChanged::class, function (ContentStatusChanged $event) use ($content): bool {
        return $event->content->id === $content->id
            && $event->from === Content::STATUS_DRAFT
            && $event->to === Content::STATUS_PUBLISHED;
    });
});

it('rejette une transition interdite (published vers draft)', function () {
    $content = statusContent(['status' => Content::STATUS_PUBLISHED]);

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_DRAFT])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_transition');

    $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => Content::STATUS_PUBLISHED]);
});

it('rejette une transition vers le même statut (422)', function () {
    $content = statusContent(['status' => Content::STATUS_PUBLISHED]);

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_transition');
});

it('rejette un statut inconnu en 422', function () {
    $content = statusContent();

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => 'en_diffusion'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['status']]]);
});

it('fait disparaître un contenu de la lecture publique quand il est dépublie', function () {
    $content = statusContent(['status' => Content::STATUS_PUBLISHED]);

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_UNPUBLISHED])
        ->assertOk()
        ->assertJsonPath('data.content.status', Content::STATUS_UNPUBLISHED);

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 0);

    $this->getJson("/api/v1/contents/{$content->id}")->assertNotFound();
    $this->get("/api/v1/contents/{$content->id}/stream")->assertNotFound();
});

it('republie un contenu dépublie', function () {
    $content = statusContent(['status' => Content::STATUS_UNPUBLISHED]);

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk()
        ->assertJsonPath('data.content.status', Content::STATUS_PUBLISHED);

    $this->getJson("/api/v1/contents/{$content->id}")->assertOk();
});

it('exige une date future lors de la programmation (US-026)', function () {
    $content = statusContent();

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_SCHEDULED])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['scheduled_at']]]);

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", [
            'status' => Content::STATUS_SCHEDULED,
            'scheduled_at' => now()->subMinute()->toDateTimeString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['scheduled_at']]]);
});

it('programme puis publie un contenu', function () {
    $content = statusContent();
    $scheduledAt = now()->addHour()->startOfSecond();

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", [
            'status' => Content::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt->toDateTimeString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.content.status', Content::STATUS_SCHEDULED)
        ->assertJsonPath('data.content.scheduled_at', $scheduledAt->toISOString());

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk()
        ->assertJsonPath('data.content.status', Content::STATUS_PUBLISHED);
});

it('archive définitivement un contenu', function () {
    $content = statusContent(['status' => Content::STATUS_PUBLISHED]);

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_ARCHIVED])
        ->assertOk();

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_transition');
});

it('invalide le cache public après une transition', function () {
    $content = statusContent();

    $this->getJson('/api/v1/contents')->assertJsonPath('meta.pagination.total', 0);

    $this->withToken(statusToken(statusAdmin()))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk();

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $content->id);
});
