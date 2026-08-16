<?php

use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Models\ScheduledNotification;
use App\Modules\Notifications\Models\UserDevice;
use App\Modules\Notifications\Services\AdminBroadcastService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

beforeEach(function (): void {
    Queue::fake();
    Http::fake();
});

function dueScheduledNotification(array $attrs = []): ScheduledNotification
{
    return ScheduledNotification::create(array_merge([
        'title' => 'Message de l\'administration',
        'scheduled_at' => now()->subMinute(),
    ], $attrs));
}

function scheduledDeviceRecord(User $user, int $suffix): UserDevice
{
    return UserDevice::create([
        'user_id' => $user->id,
        'token' => "expo-token-{$suffix}",
        'provider' => 'expo',
    ]);
}

it('envoie les notifications programmées à date atteinte et marque sent_at', function () {
    User::factory()->create();
    $due = dueScheduledNotification();

    $this->artisan('notifications:send-due')->assertSuccessful();

    expect($due->refresh()->sent_at)->not->toBeNull();

    $this->assertDatabaseHas('user_notifications', [
        'type' => NotificationService::TYPE_ADMIN_MESSAGE,
        'title' => $due->title,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'notifications.scheduled_sent',
        'entity_type' => 'scheduled_notification',
        'entity_id' => (string) $due->id,
    ]);
});

it('ignore les notifications programmées dans le futur', function () {
    User::factory()->create();
    $future = dueScheduledNotification(['scheduled_at' => now()->addHour()]);

    $this->artisan('notifications:send-due')->assertSuccessful();

    expect($future->refresh()->sent_at)->toBeNull();

    $this->assertDatabaseCount('user_notifications', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('ne renvoie pas une notification déjà envoyée', function () {
    User::factory()->create();
    $sent = dueScheduledNotification(['sent_at' => now()]);

    $this->artisan('notifications:send-due')->assertSuccessful();

    $this->assertDatabaseCount('user_notifications', 0);
    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'notifications.scheduled_sent',
        'entity_id' => (string) $sent->id,
    ]);
});

it('planifie un push pour les notifications échues', function () {
    $active = User::factory()->create();
    $token = scheduledDeviceRecord($active, 1)->token;

    $due = dueScheduledNotification(['title' => 'Rappel du culte']);

    $this->artisan('notifications:send-due')->assertSuccessful();

    Queue::assertPushed(SendPushNotifications::class, function (SendPushNotifications $job) use ($token, $due): bool {
        return in_array($token, $job->tokens, true)
            && $job->title === $due->title;
    });
});

it('continue et marque sent_at même si une notification échoue', function () {
    $failing = dueScheduledNotification(['title' => 'En échec']);
    $healthy = dueScheduledNotification(['title' => 'Ok']);

    $mock = Mockery::mock(AdminBroadcastService::class);
    $mock->shouldReceive('broadcast')
        ->with('En échec', null)
        ->andThrow(new RuntimeException('Échec simulé.'));
    $mock->shouldReceive('broadcast')
        ->with('Ok', null)
        ->andReturn(1);
    $this->app->instance(AdminBroadcastService::class, $mock);

    $this->artisan('notifications:send-due')->assertSuccessful();

    expect($failing->refresh()->sent_at)->toBeNull()
        ->and($healthy->refresh()->sent_at)->not->toBeNull();
});

it('enregistre la planification toutes les minutes', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('notifications:send-due')
        ->assertSuccessful();
});
