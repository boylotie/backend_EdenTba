<?php

use App\Models\User;
use App\Modules\Notifications\Models\UserDevice;
use Database\Seeders\RbacSeeder;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function expoToken(int $suffix = 1): string
{
    return "expo-token-{$suffix}";
}

function deviceUser(array $attrs = []): User
{
    return User::factory()->create($attrs);
}

function deviceAuthToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function deviceRecord(User $user, string $token): UserDevice
{
    return UserDevice::create([
        'user_id' => $user->id,
        'token' => $token,
        'provider' => 'expo',
    ]);
}

it('refuse l\'enregistrement d\'un appareil sans authentification', function () {
    $this->postJson('/api/v1/me/devices', ['token' => expoToken()])
        ->assertUnauthorized();
});

it('enregistre un token d\'appareil', function () {
    $user = deviceUser();

    $this->withToken(deviceAuthToken($user))
        ->postJson('/api/v1/me/devices', ['token' => expoToken(), 'platform' => 'android'])
        ->assertCreated()
        ->assertJsonPath('data.device.token', expoToken())
        ->assertJsonPath('data.device.provider', 'expo')
        ->assertJsonPath('data.device.platform', 'android');

    $this->assertDatabaseHas('user_devices', [
        'user_id' => $user->id,
        'token' => expoToken(),
        'provider' => 'expo',
    ]);
});

it('ré-enregistre le même token sans doublon', function () {
    $user = deviceUser();

    $this->withToken(deviceAuthToken($user))
        ->postJson('/api/v1/me/devices', ['token' => expoToken()])
        ->assertCreated();

    $this->withToken(deviceAuthToken($user))
        ->postJson('/api/v1/me/devices', ['token' => expoToken()])
        ->assertCreated();

    $this->assertDatabaseCount('user_devices', 1);
});

it('transfère la propriété d\'un token ré-enregistré par un autre utilisateur', function () {
    $first = deviceUser();
    $second = deviceUser();

    deviceRecord($first, expoToken());

    $this->withToken(deviceAuthToken($second))
        ->postJson('/api/v1/me/devices', ['token' => expoToken()])
        ->assertCreated();

    $this->assertDatabaseCount('user_devices', 1);
    $this->assertDatabaseHas('user_devices', ['token' => expoToken(), 'user_id' => $second->id]);
});

it('refuse un token vide (422)', function () {
    $user = deviceUser();

    $this->withToken(deviceAuthToken($user))
        ->postJson('/api/v1/me/devices', ['token' => ''])
        ->assertUnprocessable();
});

it('refuse une plateforme invalide (422)', function () {
    $user = deviceUser();

    $this->withToken(deviceAuthToken($user))
        ->postJson('/api/v1/me/devices', ['token' => expoToken(), 'platform' => 'desktop'])
        ->assertUnprocessable();
});

it('refuse le retrait d\'un appareil sans authentification', function () {
    $this->deleteJson('/api/v1/me/devices/'.expoToken())
        ->assertUnauthorized();
});

it('retire son token d\'appareil', function () {
    $user = deviceUser();
    deviceRecord($user, expoToken());

    $this->withToken(deviceAuthToken($user))
        ->deleteJson('/api/v1/me/devices/'.expoToken())
        ->assertOk()
        ->assertJsonPath('data.message', 'Appareil retiré.');

    $this->assertDatabaseMissing('user_devices', ['token' => expoToken()]);
});

it('refuse de retirer le token d\'un autre utilisateur (404)', function () {
    $user = deviceUser();
    $other = deviceUser();
    deviceRecord($other, expoToken());

    $this->withToken(deviceAuthToken($user))
        ->deleteJson('/api/v1/me/devices/'.expoToken())
        ->assertNotFound();
});

it('refuse de retirer un token inexistant (404)', function () {
    $user = deviceUser();

    $this->withToken(deviceAuthToken($user))
        ->deleteJson('/api/v1/me/devices/'.expoToken())
        ->assertNotFound();
});
