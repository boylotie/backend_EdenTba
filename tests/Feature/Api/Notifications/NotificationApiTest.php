<?php

use App\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Services\NotificationService;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function notificationUser(array $attrs = []): User
{
    return User::factory()->create($attrs);
}

function notificationToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function notificationRecord(User $user, array $attrs = []): Notification
{
    return Notification::create(array_merge([
        'user_id' => $user->id,
        'type' => NotificationService::TYPE_CONTENT_PUBLISHED,
        'title' => 'Nouveau contenu publié',
        'entity_type' => NotificationService::ENTITY_CONTENT,
        'entity_id' => 1,
    ], $attrs));
}

it('refuse la liste des notifications sans authentification', function () {
    $this->getJson('/api/v1/me/notifications')
        ->assertUnauthorized();
});

it('liste les notifications de l\'utilisateur connecté', function () {
    $user = notificationUser();
    notificationRecord($user);
    notificationRecord($user, ['type' => 'reminder', 'title' => 'Rappel']);

    $this->withToken(notificationToken($user))
        ->getJson('/api/v1/me/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.unread_count', 2)
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.title', 'Rappel')
        ->assertJsonPath('data.0.read', false)
        ->assertJsonPath('data.0.entity.type', NotificationService::ENTITY_CONTENT)
        ->assertJsonPath('data.1.title', 'Nouveau contenu publié');
});

it('ne montre que ses propres notifications', function () {
    $user = notificationUser();
    $other = notificationUser();
    notificationRecord($other, ['title' => 'Notification d\'un autre']);

    $this->withToken(notificationToken($user))
        ->getJson('/api/v1/me/notifications')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.pagination.total', 0);
});

it('filtre les notifications non lues', function () {
    $user = notificationUser();
    $read = notificationRecord($user, ['title' => 'Lue']);
    $read->update(['read_at' => now()]);
    notificationRecord($user, ['title' => 'Non lue', 'entity_id' => 2]);

    $this->withToken(notificationToken($user))
        ->getJson('/api/v1/me/notifications?unread=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Non lue');
});

it('marque une notification comme lue', function () {
    $user = notificationUser();
    $notification = notificationRecord($user);

    $this->withToken(notificationToken($user))
        ->putJson("/api/v1/me/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.notification.read', true)
        ->assertJsonPath('data.notification.id', $notification->id);

    $this->assertNotNull($notification->refresh()->read_at);
});

it('reste lu si déjà marquée lue (idempotence)', function () {
    $user = notificationUser();
    $notification = notificationRecord($user, ['read_at' => now()]);

    $this->withToken(notificationToken($user))
        ->putJson("/api/v1/me/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.notification.read', true);

    $this->assertDatabaseHas('user_notifications', ['id' => $notification->id, 'read_at' => $notification->read_at]);
});

it('refuse de marquer la notification d\'un autre utilisateur (404)', function () {
    $user = notificationUser();
    $other = notificationUser();
    $notification = notificationRecord($other);

    $this->withToken(notificationToken($user))
        ->putJson("/api/v1/me/notifications/{$notification->id}/read")
        ->assertNotFound();
});

it('refuse de marquer une notification inexistante (404)', function () {
    $user = notificationUser();

    $this->withToken(notificationToken($user))
        ->putJson('/api/v1/me/notifications/999999/read')
        ->assertNotFound();
});
