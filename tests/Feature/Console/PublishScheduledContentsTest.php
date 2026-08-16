<?php

use App\Modules\Content\Events\ContentStatusChanged;
use App\Modules\Content\Models\Content;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

beforeEach(function (): void {
    Storage::fake('audio');
});

function scheduledContent(array $attrs = []): Content
{
    Storage::disk('audio')->put('contents/programme.mp3', 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_SCHEDULED,
        'title' => 'Programmé',
        'file_path' => 'contents/programme.mp3',
        'original_filename' => 'programme.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
        'scheduled_at' => now()->subMinute(),
    ], $attrs));
}

it('publie automatiquement un contenu programmé à date atteinte (US-026)', function () {
    Event::fake([ContentStatusChanged::class]);

    $due = scheduledContent();

    $this->artisan('contents:publish-due')->assertSuccessful();

    $this->assertDatabaseHas('contents', ['id' => $due->id, 'status' => Content::STATUS_PUBLISHED]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'contents.status_changed', 'entity_id' => (string) $due->id]);

    Event::assertDispatched(ContentStatusChanged::class, function (ContentStatusChanged $event) use ($due): bool {
        return $event->content->id === $due->id
            && $event->from === Content::STATUS_SCHEDULED
            && $event->to === Content::STATUS_PUBLISHED;
    });

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $due->id);
});

it('ne publie pas un contenu programmé dans le futur', function () {
    $future = scheduledContent(['scheduled_at' => now()->addHour()]);

    $this->artisan('contents:publish-due')->assertSuccessful();

    $this->assertDatabaseHas('contents', ['id' => $future->id, 'status' => Content::STATUS_SCHEDULED]);

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 0);
});

it('ne publie pas un contenu programmé sans date', function () {
    $noDate = scheduledContent(['scheduled_at' => null]);

    $this->artisan('contents:publish-due')->assertSuccessful();

    $this->assertDatabaseHas('contents', ['id' => $noDate->id, 'status' => Content::STATUS_SCHEDULED]);
});

it('ne touche pas aux contenus non programmés', function () {
    $draft = scheduledContent(['status' => Content::STATUS_DRAFT, 'scheduled_at' => now()->subMinute()]);
    $published = scheduledContent(['status' => Content::STATUS_PUBLISHED, 'scheduled_at' => now()->subMinute()]);

    $this->artisan('contents:publish-due')->assertSuccessful();

    $this->assertDatabaseHas('contents', ['id' => $draft->id, 'status' => Content::STATUS_DRAFT]);
    $this->assertDatabaseHas('contents', ['id' => $published->id, 'status' => Content::STATUS_PUBLISHED]);
});

it('continue et journalise le repli si un contenu échoue', function () {
    Log::spy();

    $failing = scheduledContent(['title' => 'En échec']);
    $healthy = scheduledContent(['title' => 'Ok']);

    Event::listen(ContentStatusChanged::class, function (ContentStatusChanged $event) use ($failing): void {
        if ($event->content->id === $failing->id) {
            throw new RuntimeException('Échec simulé.');
        }
    });

    $this->artisan('contents:publish-due')->assertSuccessful();

    Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($failing): bool {
        return $message === 'Publication programmée en échec'
            && ($context['content_id'] ?? null) === $failing->id;
    });

    $this->assertDatabaseHas('contents', ['id' => $healthy->id, 'status' => Content::STATUS_PUBLISHED]);
});

it('enregistre la planification toutes les minutes', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('contents:publish-due')
        ->assertSuccessful();
});
