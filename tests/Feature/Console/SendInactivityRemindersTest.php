<?php

use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Models\UserDevice;
use App\Modules\Notifications\Services\NotificationService;
use App\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Http::fake();
});

function inactivityDevice(User $user, int $suffix): UserDevice
{
    return UserDevice::create([
        'user_id' => $user->id,
        'token' => "expo-token-inactivity-{$suffix}",
        'provider' => 'expo',
    ]);
}

it('notifie les utilisateurs dont la dernière visite est absente ou ancienne', function () {
    $inactive = User::factory()->create(['last_seen_at' => now()->subDays(40)]);
    $neverSeen = User::factory()->create();
    User::factory()->create(['last_seen_at' => now()->subDays(1)]);

    $this->artisan('reminders:send-inactivity')->assertSuccessful();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $inactive->id,
        'type' => NotificationService::TYPE_INACTIVITY_REMINDER,
    ]);
    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $neverSeen->id,
        'type' => NotificationService::TYPE_INACTIVITY_REMINDER,
    ]);

    $this->assertDatabaseCount('user_notifications', 2);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'reminders.inactivity_sent',
    ]);
});

it('ignore les utilisateurs récemment actifs et désactivés', function () {
    User::factory()->create(['last_seen_at' => now()->subDays(1)]);
    $disabled = User::factory()->create(['is_active' => false, 'last_seen_at' => now()->subDays(90)]);

    $this->artisan('reminders:send-inactivity')->assertSuccessful();

    $this->assertDatabaseCount('user_notifications', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('ne crée qu une notification par utilisateur', function () {
    User::factory()->create(['last_seen_at' => now()->subDays(40)]);

    $this->artisan('reminders:send-inactivity')->assertSuccessful();
    $this->artisan('reminders:send-inactivity')->assertSuccessful();

    $this->assertDatabaseCount('user_notifications', 1);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('planifie un push uniquement pour les utilisateurs concernés', function () {
    $concerned = User::factory()->create(['last_seen_at' => now()->subDays(40)]);
    $concernedToken = inactivityDevice($concerned, 1)->token;

    $noToken = User::factory()->create(['last_seen_at' => now()->subDays(40)]);

    $recent = User::factory()->create(['last_seen_at' => now()->subDays(1)]);
    inactivityDevice($recent, 2);

    $this->artisan('reminders:send-inactivity')->assertSuccessful();

    Queue::assertPushed(SendPushNotifications::class, function (SendPushNotifications $job) use ($concernedToken): bool {
        return in_array($concernedToken, $job->tokens, true)
            && $job->tokens === [$concernedToken];
    });
});

it('ne fait rien quand les rappels sont désactivés', function () {
    app(SettingsService::class)->replace(['rappel_actif' => false]);

    User::factory()->create(['last_seen_at' => now()->subDays(90)]);

    $this->artisan('reminders:send-inactivity')->assertSuccessful();

    $this->assertDatabaseCount('user_notifications', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('enregistre la planification toutes les minutes', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('reminders:send-inactivity')
        ->assertSuccessful();
});
