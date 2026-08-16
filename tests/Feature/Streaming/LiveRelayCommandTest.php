<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Streaming\Models\LiveSession;
use App\Modules\Streaming\Services\LiveRelayService;
use App\Modules\Streaming\Support\IcecastSourceClient;
use App\Modules\Streaming\Support\LiveChunkBuffer;
use App\Modules\Streaming\Support\RelaySourceConnector;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);

    $this->directory = storage_path('framework/testing/live-relay');
    $this->buffer = new LiveChunkBuffer($this->directory);
    app()->instance(LiveChunkBuffer::class, $this->buffer);

    $this->user = User::factory()->create();
    $this->user->assignRole(Role::SUPER_ADMIN);
});

afterEach(function (): void {
    $this->buffer->purge();

    if (is_dir($this->directory)) {
        rmdir($this->directory);
    }
});

function liveRelaySession(): LiveSession
{
    return LiveSession::create([
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Culte du dimanche',
        'started_at' => now(),
        'created_by' => test()->user->id,
    ]);
}

function relayClient(): IcecastSourceClient
{
    test()->stream = fopen('php://temp', 'w+');
    $client = new IcecastSourceClient('http://stream.example.org:8000/live', 'secret', test()->stream);
    app()->instance(RelaySourceConnector::class, new class($client) extends RelaySourceConnector
    {
        public function __construct(private readonly IcecastSourceClient $client) {}

        public function make(string $url, string $password): IcecastSourceClient
        {
            return $this->client;
        }
    });

    return $client;
}

it('relaie les chunks vers la source Icecast et les supprime', function () {
    liveRelaySession();
    $this->buffer->append('PREMIER-CHUNK');
    $this->buffer->append('SECOND-CHUNK');
    $this->buffer->activateMic('audio/webm');

    $client = relayClient();

    app(LiveRelayService::class)->processOnce();

    expect($client->isConnected())->toBeTrue();
    expect($this->buffer->hasChunks())->toBeFalse();

    rewind($this->stream);
    $sent = stream_get_contents($this->stream);

    expect($sent)->toContain("PUT /live HTTP/1.0\r\n");
    expect($sent)->toContain('Authorization: Basic '.base64_encode('source:secret'));
    expect($sent)->toContain('PREMIER-CHUNKSECOND-CHUNK');

    $client->close();
});

it('purge le tampon quand aucun direct n est actif', function () {
    $this->buffer->append('A');
    $this->buffer->activateMic('audio/webm');

    $client = relayClient();

    app(LiveRelayService::class)->processOnce();

    expect($this->buffer->hasChunks())->toBeFalse();
    expect($client->isConnected())->toBeFalse();
});

it('ferme la connexion source quand la capture micro est arrêtée', function () {
    liveRelaySession();
    $this->buffer->append('A');

    $client = relayClient();
    $client->connect();

    app(LiveRelayService::class)->processOnce();

    expect($client->isConnected())->toBeFalse();
});

it('purge le tampon quand le direct est arrêté', function () {
    $session = liveRelaySession();
    $session->update(['state' => LiveSession::STATE_OFF, 'stopped_at' => now()]);
    $this->buffer->append('A');
    $this->buffer->activateMic('audio/webm');

    $client = relayClient();

    app(LiveRelayService::class)->processOnce();

    expect($this->buffer->hasChunks())->toBeFalse();
    expect($client->isConnected())->toBeFalse();
});

it('enregistre la commande live:relay', function () {
    expect(Artisan::all())->toHaveKey('live:relay');
});
