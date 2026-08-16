<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Content\Models\Content;
use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Services\NotificationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('audio');
    Storage::fake('content_images');
    Queue::fake();
    Http::fake();

    User::whereIn('email', [
        'superadmin@example.com',
        'admin@example.com',
        'user@example.com',
    ])->update(['is_active' => false]);
});

function notificationContent(array $attrs = []): Content
{
    Storage::disk('audio')->put('contents/notification.mp3', 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_DRAFT,
        'title' => 'Enseignement du culte',
        'file_path' => 'contents/notification.mp3',
        'original_filename' => 'notification.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

function notificationPublishAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::SUPER_ADMIN);

    return $admin;
}

it('crée une notification « contenu publié » pour chaque utilisateur actif', function () {
    $admin = notificationPublishAdmin();
    $active = User::factory()->create();
    User::factory()->create();

    $content = notificationContent();

    $this->withToken(notificationToken($admin))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk();

    $this->assertDatabaseCount('user_notifications', 3);
    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $active->id,
        'type' => NotificationService::TYPE_CONTENT_PUBLISHED,
        'entity_type' => NotificationService::ENTITY_CONTENT,
        'entity_id' => $content->id,
        'title' => $content->title,
    ]);
});

it('ignore les utilisateurs inactifs', function () {
    $admin = notificationPublishAdmin();
    User::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);

    $content = notificationContent();

    $this->withToken(notificationToken($admin))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk();

    $this->assertDatabaseCount('user_notifications', 2);
    $this->assertDatabaseMissing('user_notifications', ['user_id' => $inactive->id]);
});

it('ne crée pas de notification pour une transition vers un statut non publié', function () {
    $admin = notificationPublishAdmin();
    User::factory()->create();

    $content = notificationContent();

    $this->withToken(notificationToken($admin))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_ARCHIVED])
        ->assertOk();

    $this->assertDatabaseCount('user_notifications', 0);
});

it('ne duplique pas les notifications lors d\'une re-publication', function () {
    $admin = notificationPublishAdmin();
    User::factory()->create();

    $content = notificationContent();
    $token = notificationToken($admin);

    $this->withToken($token)->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])->assertOk();
    $this->withToken($token)->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_UNPUBLISHED])->assertOk();
    $this->withToken($token)->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])->assertOk();

    $this->assertDatabaseCount('user_notifications', 2);
});

it('est idempotent : deux événements identiques ne dupliquent pas (A2)', function () {
    $user = User::factory()->create();
    $content = notificationContent();

    $service = new NotificationService;
    $service->createForContentPublished($content);
    $service->createForContentPublished($content);

    $this->assertDatabaseCount('user_notifications', 1);
    $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id]);
});

it('ne crée aucun enregistrement sans destinataire actif (A1)', function () {
    User::factory()->create(['is_active' => false]);
    $content = notificationContent();

    $count = (new NotificationService)->createForContentPublished($content);

    expect($count)->toBe(0);
    $this->assertDatabaseCount('user_notifications', 0);
});

it('planifie un push avec les tokens des utilisateurs actifs', function () {
    $admin = notificationPublishAdmin();
    $active = User::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);

    $token = expoToken(1);
    $inactiveToken = expoToken(2);
    deviceRecord($active, $token);
    deviceRecord($inactive, $inactiveToken);

    $content = notificationContent();

    $this->withToken(notificationToken($admin))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk();

    Queue::assertPushed(SendPushNotifications::class, function (SendPushNotifications $job) use ($token, $inactiveToken, $content): bool {
        return in_array($token, $job->tokens, true)
            && ! in_array($inactiveToken, $job->tokens, true)
            && $job->title === $content->title
            && $job->entityType === NotificationService::ENTITY_CONTENT
            && $job->entityId === $content->id;
    });
});

it('ne planifie aucun push sans token d\'appareil', function () {
    $admin = notificationPublishAdmin();
    $content = notificationContent();

    $this->withToken(notificationToken($admin))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk();

    Queue::assertNotPushed(SendPushNotifications::class);
});
