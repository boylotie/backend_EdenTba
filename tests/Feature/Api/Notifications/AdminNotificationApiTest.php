<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Notifications\Jobs\SendAdminNotification;
use App\Modules\Notifications\Models\ScheduledNotification;
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

function notifAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::SUPER_ADMIN);

    return $admin;
}

function notifUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    return $user;
}

function notifToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('refuse l\'envoi immédiat sans authentification', function () {
    $this->postJson('/api/v1/notifications/send', ['title' => 'Annonce'])
        ->assertUnauthorized();
});

it('refuse la programmation sans authentification', function () {
    $this->postJson('/api/v1/notifications/schedule', [
        'title' => 'Annonce',
        'scheduled_at' => now()->addHour(),
    ])->assertUnauthorized();
});

it('refuse l\'envoi immédiat sans la permission notification.send', function () {
    $user = notifUser();

    $this->withToken(notifToken($user))
        ->postJson('/api/v1/notifications/send', ['title' => 'Annonce'])
        ->assertForbidden();
});

it('refuse la programmation sans la permission notification.schedule', function () {
    $user = notifUser();

    $this->withToken(notifToken($user))
        ->postJson('/api/v1/notifications/schedule', [
            'title' => 'Annonce',
            'scheduled_at' => now()->addHour(),
        ])
        ->assertForbidden();
});

it('soumet un envoi immédiat à tous les utilisateurs actifs (US-040)', function () {
    $admin = notifAdmin();
    User::factory()->create();
    User::factory()->create();

    $this->withToken(notifToken($admin))
        ->postJson('/api/v1/notifications/send', [
            'title' => 'Message de l\'administration',
            'body' => 'Corps du message',
        ])
        ->assertOk()
        ->assertJsonPath('data.message', 'Notification envoyée.')
        ->assertJsonPath('data.recipients', 3);

    Queue::assertPushed(SendAdminNotification::class, function (SendAdminNotification $job): bool {
        return $job->title === 'Message de l\'administration'
            && $job->body === 'Corps du message';
    });

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'notifications.send',
        'actor_id' => $admin->id,
    ]);
});

it('refuse un envoi sans titre (422)', function () {
    $admin = notifAdmin();

    $this->withToken(notifToken($admin))
        ->postJson('/api/v1/notifications/send', ['body' => 'Sans titre'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['title']]]);

    Queue::assertNotPushed(SendAdminNotification::class);
});

it('programme une notification à une date future (US-040)', function () {
    $admin = notifAdmin();

    $this->withToken(notifToken($admin))
        ->postJson('/api/v1/notifications/schedule', [
            'title' => 'Rappel du culte',
            'scheduled_at' => now()->addHour(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.scheduled_notification.title', 'Rappel du culte')
        ->assertJsonPath('data.scheduled_notification.sent_at', null);

    $scheduled = ScheduledNotification::firstOrFail();
    expect($scheduled->scheduled_at->isFuture())->toBeTrue()
        ->and($scheduled->created_by)->toBe($admin->id)
        ->and($scheduled->sent_at)->toBeNull();

    Queue::assertNotPushed(SendAdminNotification::class);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'notifications.schedule',
        'actor_id' => $admin->id,
        'entity_type' => 'scheduled_notification',
        'entity_id' => (string) $scheduled->id,
    ]);
});

it('refuse une date passée (422)', function () {
    $admin = notifAdmin();

    $this->withToken(notifToken($admin))
        ->postJson('/api/v1/notifications/schedule', [
            'title' => 'Rappel',
            'scheduled_at' => now()->subMinute(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['scheduled_at']]]);
});

it('exige une date de programmation (422)', function () {
    $admin = notifAdmin();

    $this->withToken(notifToken($admin))
        ->postJson('/api/v1/notifications/schedule', ['title' => 'Rappel'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['scheduled_at']]]);
});
