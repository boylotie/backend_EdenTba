<?php

use App\Models\User;
use App\Modules\Notifications\Jobs\SendAdminNotification;
use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Models\UserDevice;
use App\Modules\Notifications\Services\AdminBroadcastService;
use App\Modules\Notifications\Services\DeviceTokenService;
use App\Modules\Notifications\Services\NotificationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Queue::fake();
    Http::fake();

    User::whereIn('email', [
        'superadmin@example.com',
        'admin@example.com',
        'user@example.com',
    ])->update(['is_active' => false]);
});

function broadcastUserDevice(User $user, int $suffix): UserDevice
{
    return UserDevice::create([
        'user_id' => $user->id,
        'token' => "expo-token-{$suffix}",
        'provider' => 'expo',
    ]);
}

it('crée une notification interne pour chaque utilisateur actif', function () {
    User::factory()->create();
    User::factory()->create();
    User::factory()->create(['is_active' => false]);

    (new SendAdminNotification('Message de l\'administration', 'Corps du message'))
        ->handle(new AdminBroadcastService(new NotificationService, new DeviceTokenService));

    $this->assertDatabaseCount('user_notifications', 2);
    $this->assertDatabaseHas('user_notifications', [
        'type' => NotificationService::TYPE_ADMIN_MESSAGE,
        'title' => 'Message de l\'administration',
        'body' => 'Corps du message',
        'entity_type' => null,
        'entity_id' => null,
    ]);
});

it('exclut les utilisateurs inactifs', function () {
    User::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);

    (new SendAdminNotification('Annonce'))->handle(new AdminBroadcastService(new NotificationService, new DeviceTokenService));

    $this->assertDatabaseCount('user_notifications', 1);
    $this->assertDatabaseMissing('user_notifications', ['user_id' => $inactive->id]);
});

it('planifie un push avec les tokens des utilisateurs actifs', function () {
    $active = User::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);

    $token = broadcastUserDevice($active, 1)->token;
    $inactiveToken = broadcastUserDevice($inactive, 2)->token;

    (new SendAdminNotification('Annonce', 'Corps'))->handle(new AdminBroadcastService(new NotificationService, new DeviceTokenService));

    Queue::assertPushed(SendPushNotifications::class, function (SendPushNotifications $job) use ($token, $inactiveToken): bool {
        return in_array($token, $job->tokens, true)
            && ! in_array($inactiveToken, $job->tokens, true)
            && $job->title === 'Annonce'
            && $job->body === 'Corps';
    });
});

it('ne planifie aucun push sans token d\'appareil', function () {
    User::factory()->create();

    (new SendAdminNotification('Annonce'))->handle(new AdminBroadcastService(new NotificationService, new DeviceTokenService));

    Queue::assertNotPushed(SendPushNotifications::class);
});

it('configure le retry du job', function () {
    $job = new SendAdminNotification('Annonce');

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([5, 15]);
});
