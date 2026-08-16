<?php

use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Services\DeviceTokenService;
use App\Modules\Notifications\Services\ExpoPushClient;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

it('envoie les messages au service Expo pour chaque token', function () {
    Http::fake([
        'https://exp.host/*' => Http::response([
            'data' => [
                ['status' => 'ok', 'id' => 'msg-1'],
                ['status' => 'ok', 'id' => 'msg-2'],
            ],
        ], 200),
    ]);

    $tokens = [expoToken(1), expoToken(2)];
    $job = new SendPushNotifications($tokens, 'Titre', 'Corps', 'content', 42);
    $job->handle(new ExpoPushClient, new DeviceTokenService);

    Http::assertSent(function ($request) use ($tokens): bool {
        $body = $request->data();

        return $request->url() === config('services.expo.endpoint')
            && $body[0]['to'] === $tokens[0]
            && $body[1]['to'] === $tokens[1]
            && $body[0]['title'] === 'Titre'
            && $body[0]['body'] === 'Corps'
            && $body[0]['data']['entity_type'] === 'content'
            && $body[0]['data']['entity_id'] === 42;
    });
});

it('inclut le type et le canal Android dans les données du message', function () {
    Http::fake([
        'https://exp.host/*' => Http::response([
            'data' => [
                ['status' => 'ok', 'id' => 'msg-1'],
            ],
        ], 200),
    ]);

    $job = new SendPushNotifications(
        [expoToken(1)],
        'Rappel',
        'Corps',
        null,
        null,
        NotificationService::TYPE_PROGRAM_REMINDER,
    );
    $job->handle(new ExpoPushClient, new DeviceTokenService);

    Http::assertSent(function ($request): bool {
        $data = $request->data()[0]['data'];

        return $data['type'] === NotificationService::TYPE_PROGRAM_REMINDER
            && $data['channelId'] === 'program_reminder'
            && ! isset($data['entity_type']);
    });
});

it('ne fait aucun appel sans token', function () {
    Http::fake();

    $job = new SendPushNotifications([], 'Titre');
    $job->handle(new ExpoPushClient, new DeviceTokenService);

    Http::assertNothingSent();
});

it('retire un token signalé DeviceNotRegistered', function () {
    Http::fake([
        'https://exp.host/*' => Http::response([
            'data' => [
                ['status' => 'error', 'message' => 'DeviceNotRegistered', 'details' => ['error' => 'DeviceNotRegistered']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $token = expoToken(1);
    deviceRecord($user, $token);

    $job = new SendPushNotifications([$token], 'Titre');
    $job->handle(new ExpoPushClient, new DeviceTokenService);

    $this->assertDatabaseMissing('user_devices', ['token' => $token]);
});

it('conserve le token sur une erreur sans lien avec l\'enregistrement', function () {
    Http::fake([
        'https://exp.host/*' => Http::response([
            'data' => [
                ['status' => 'error', 'message' => 'MessageTooBig', 'details' => ['error' => 'MessageTooBig']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $token = expoToken(1);
    deviceRecord($user, $token);

    $job = new SendPushNotifications([$token], 'Titre');
    $job->handle(new ExpoPushClient, new DeviceTokenService);

    $this->assertDatabaseHas('user_devices', ['token' => $token]);
});

it('lève une exception si le fournisseur répond en erreur (retry)', function () {
    Http::fake([
        'https://exp.host/*' => Http::response(['data' => []], 500),
    ]);

    $job = new SendPushNotifications([expoToken(1)], 'Titre');

    expect(fn () => $job->handle(new ExpoPushClient, new DeviceTokenService))
        ->toThrow(HttpClientException::class);
});

it('configure le retry du job', function () {
    $job = new SendPushNotifications([], 'Titre');

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([5, 15]);
});
