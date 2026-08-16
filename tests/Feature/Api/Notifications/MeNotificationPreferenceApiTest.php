<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Content\Models\Content;
use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Models\UserDevice;
use App\Modules\Notifications\Models\UserNotificationPreference;
use App\Modules\Notifications\Services\DeviceTokenService;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Modules\Notifications\Services\NotificationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('audio');
    Queue::fake();
    Http::fake();

    User::whereIn('email', [
        'superadmin@example.com',
        'admin@example.com',
        'user@example.com',
    ])->update(['is_active' => false]);
});

function preferenceUser(array $attrs = []): User
{
    return User::factory()->create($attrs);
}

function preferenceToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function preferencePayload(array $overrides = []): array
{
    return array_merge([
        'content_published' => true,
        'admin_message' => true,
        'program_reminder' => true,
        'inactivity_reminder' => true,
    ], $overrides);
}

function preferenceRecord(User $user, string $type, bool $enabled): UserNotificationPreference
{
    return UserNotificationPreference::create([
        'user_id' => $user->id,
        'type' => $type,
        'enabled' => $enabled,
    ]);
}

function preferenceContent(array $attrs = []): Content
{
    Storage::disk('audio')->put('contents/preference.mp3', 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_DRAFT,
        'title' => 'Enseignement du culte',
        'file_path' => 'contents/preference.mp3',
        'original_filename' => 'preference.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

function preferencePublishAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::SUPER_ADMIN);

    return $admin;
}

function preferenceExpoToken(int $suffix): string
{
    return "expo-token-pref-{$suffix}";
}

function preferenceDevice(User $user, string $token): UserDevice
{
    return UserDevice::create([
        'user_id' => $user->id,
        'token' => $token,
        'provider' => 'expo',
    ]);
}

it('refuse la consultation sans authentification', function () {
    $this->getJson('/api/v1/me/notification-preferences')->assertStatus(401);
});

it('refuse la mise à jour sans authentification', function () {
    $this->putJson('/api/v1/me/notification-preferences', ['preferences' => preferencePayload()])->assertStatus(401);
});

it('retourne toutes les préférences activées par défaut', function () {
    $user = preferenceUser();

    $this->withToken(preferenceToken($user))
        ->getJson('/api/v1/me/notification-preferences')
        ->assertOk()
        ->assertJsonCount(4, 'data.preferences')
        ->assertJsonPath('data.preferences.content_published', true)
        ->assertJsonPath('data.preferences.admin_message', true)
        ->assertJsonPath('data.preferences.program_reminder', true)
        ->assertJsonPath('data.preferences.inactivity_reminder', true);
});

it('remplace les préférences par type', function () {
    $user = preferenceUser();

    $this->withToken(preferenceToken($user))
        ->putJson('/api/v1/me/notification-preferences', ['preferences' => preferencePayload([
            'content_published' => false,
            'admin_message' => false,
        ])])
        ->assertOk()
        ->assertJsonPath('data.preferences.content_published', false)
        ->assertJsonPath('data.preferences.admin_message', false)
        ->assertJsonPath('data.preferences.program_reminder', true);

    expect(app(NotificationPreferenceService::class)->isEnabled($user->id, NotificationService::TYPE_CONTENT_PUBLISHED))->toBeFalse()
        ->and(app(NotificationPreferenceService::class)->isEnabled($user->id, NotificationService::TYPE_PROGRAM_REMINDER))->toBeTrue();
});

it('rejette un type de notification inconnu (422)', function () {
    $user = preferenceUser();

    $this->withToken(preferenceToken($user))
        ->putJson('/api/v1/me/notification-preferences', ['preferences' => preferencePayload([
            'sonnerie' => true,
        ])])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['preferences']]]);
});

it('exige chaque type connu (422)', function () {
    $user = preferenceUser();

    $this->withToken(preferenceToken($user))
        ->putJson('/api/v1/me/notification-preferences', ['preferences' => ['content_published' => true]])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['preferences.admin_message']]]);
});

it('rejette une valeur non booléenne (422)', function () {
    $user = preferenceUser();

    $this->withToken(preferenceToken($user))
        ->putJson('/api/v1/me/notification-preferences', ['preferences' => preferencePayload([
            'content_published' => 'peut-être',
        ])])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['preferences.content_published']]]);
});

it('journalise la modification des préférences', function () {
    $user = preferenceUser();

    $this->withToken(preferenceToken($user))
        ->putJson('/api/v1/me/notification-preferences', ['preferences' => preferencePayload()])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'notifications.preferences.update',
        'actor_id' => $user->id,
    ]);
});

it('isEnabled vaut true par défaut et false après désactivation', function () {
    $user = preferenceUser();
    $service = app(NotificationPreferenceService::class);

    expect($service->isEnabled($user->id, NotificationService::TYPE_ADMIN_MESSAGE))->toBeTrue();

    preferenceRecord($user, NotificationService::TYPE_ADMIN_MESSAGE, false);

    expect($service->isEnabled($user->id, NotificationService::TYPE_ADMIN_MESSAGE))->toBeFalse();
});

it('exclut les utilisateurs ayant désactivé le type de la diffusion interne', function () {
    preferenceUser();
    $disabled = preferenceUser();
    preferenceRecord($disabled, NotificationService::TYPE_ADMIN_MESSAGE, false);

    $created = (new NotificationService)->createForAllActiveUsers(
        NotificationService::TYPE_ADMIN_MESSAGE,
        'Annonce',
    );

    expect($created)->toBe(1);
    $this->assertDatabaseMissing('user_notifications', ['user_id' => $disabled->id]);
});

it('exclut les tokens des utilisateurs ayant désactivé le type du push', function () {
    $disabled = preferenceUser();
    $enabled = preferenceUser();
    preferenceRecord($disabled, NotificationService::TYPE_CONTENT_PUBLISHED, false);

    $disabledToken = preferenceExpoToken(1);
    $enabledToken = preferenceExpoToken(2);
    preferenceDevice($disabled, $disabledToken);
    preferenceDevice($enabled, $enabledToken);

    $tokens = app(DeviceTokenService::class)->tokensOfActiveUsers(NotificationService::TYPE_CONTENT_PUBLISHED);

    expect($tokens)->toContain($enabledToken)
        ->not->toContain($disabledToken);
});

it('respecte la préférence content_published à la publication (interne et push)', function () {
    $admin = preferencePublishAdmin();
    $enabled = preferenceUser();
    $disabled = preferenceUser();
    preferenceRecord($disabled, NotificationService::TYPE_CONTENT_PUBLISHED, false);

    $enabledToken = preferenceExpoToken(3);
    $disabledToken = preferenceExpoToken(4);
    preferenceDevice($enabled, $enabledToken);
    preferenceDevice($disabled, $disabledToken);

    $content = preferenceContent();

    $this->withToken(preferenceToken($admin))
        ->putJson("/api/v1/contents/{$content->id}/status", ['status' => Content::STATUS_PUBLISHED])
        ->assertOk();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $enabled->id,
        'type' => NotificationService::TYPE_CONTENT_PUBLISHED,
        'entity_id' => $content->id,
    ]);
    $this->assertDatabaseMissing('user_notifications', [
        'user_id' => $disabled->id,
        'type' => NotificationService::TYPE_CONTENT_PUBLISHED,
        'entity_id' => $content->id,
    ]);

    Queue::assertPushed(SendPushNotifications::class, function (SendPushNotifications $job) use ($enabledToken, $disabledToken): bool {
        return in_array($enabledToken, $job->tokens, true)
            && ! in_array($disabledToken, $job->tokens, true);
    });
});
