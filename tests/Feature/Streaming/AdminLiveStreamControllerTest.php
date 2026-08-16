<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Streaming\Models\LiveSession;
use App\Modules\Streaming\Support\LiveChunkBuffer;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('content_images');

    $this->directory = storage_path('framework/testing/live-chunks');
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

function liveChunkSession(): LiveSession
{
    return LiveSession::create([
        'state' => LiveSession::STATE_LIVE,
        'title' => 'Culte du dimanche',
        'started_at' => now(),
        'created_by' => test()->user->id,
    ]);
}

function chunkRequest(string $content, string $contentType = 'audio/webm')
{
    return test()->call('POST', '/admin/live/stream-chunk', [], [], [], [
        'CONTENT_TYPE' => $contentType,
    ], $content);
}

it('redirige un visiteur non connecté', function () {
    chunkRequest('data')
        ->assertRedirect(route('login'));
});

it('refuse un utilisateur sans permission streaming.start', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN);
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $role->permissions()->detach(Permission::where('name', 'streaming.start')->firstOrFail()->id);

    $this->actingAs($user);

    chunkRequest('data')->assertForbidden();
});

it('refuse un chunk quand aucun direct n est en cours', function () {
    $this->actingAs($this->user);

    chunkRequest('data')->assertStatus(409);
});

it('refuse un chunk quand la session de direct est arrêtée', function () {
    $session = liveChunkSession();
    $session->update(['state' => LiveSession::STATE_OFF, 'stopped_at' => now()]);

    $this->actingAs($this->user);

    chunkRequest('data')->assertStatus(409);
});

it('reçoit un chunk et active la capture micro', function () {
    liveChunkSession();
    $this->actingAs($this->user);

    chunkRequest('AUDIO-BYTES', 'audio/webm;codecs=opus')
        ->assertOk()
        ->assertJson(['sequence' => 1, 'buffered_bytes' => 11]);

    expect($this->buffer->isMicActive())->toBeTrue();
    expect($this->buffer->micContentType())->toBe('audio/webm;codecs=opus');
    expect($this->buffer->totalBytes())->toBe(11);
});

it('numérote les chunks à la suite', function () {
    liveChunkSession();
    $this->actingAs($this->user);

    chunkRequest('A')->assertJson(['sequence' => 1]);
    chunkRequest('B')->assertJson(['sequence' => 2]);
    chunkRequest('C')->assertJson(['sequence' => 3]);
});

it('refuse un chunk trop volumineux', function () {
    liveChunkSession();
    $this->actingAs($this->user);

    chunkRequest(str_repeat('x', LiveChunkBuffer::MAX_CHUNK_BYTES + 1))
        ->assertStatus(422);
});

it('termine la capture micro et arrête le direct', function () {
    $session = liveChunkSession();
    $this->buffer->append('A');
    $this->buffer->activateMic('audio/webm');

    $this->actingAs($this->user)
        ->post('/admin/live/stream-stop')
        ->assertOk();

    expect($this->buffer->isMicActive())->toBeFalse();
    expect($this->buffer->hasChunks())->toBeFalse();
    expect($session->fresh()->state)->toBe(LiveSession::STATE_OFF);
});
