<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Content\Models\Content;
use App\Modules\Speakers\Models\Speaker;
use Database\Seeders\RbacSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
});

function speakerAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function speakerToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

it('liste les speakers actifs (public)', function () {
    Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur', 'is_active' => true]);
    Speaker::create(['name' => 'Ancien Pierre', 'title' => 'frere', 'is_active' => false]);

    $this->getJson('/api/v1/speakers')
        ->assertOk()
        ->assertJsonCount(1, 'data.speakers')
        ->assertJsonPath('data.speakers.0.name', 'Pasteur Jean');
});

it('affiche le détail d un speaker et ses contenus publiés', function () {
    $speaker = Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur', 'is_active' => true]);

    Storage::disk('audio')->put('contents/test.mp3', 'audio');
    Content::create([
        'title' => 'Enseignement',
        'status' => 'published',
        'speaker_id' => $speaker->id,
        'file_path' => 'contents/test.mp3',
        'original_filename' => 'test.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ]);

    $this->getJson("/api/v1/speakers/{$speaker->id}")
        ->assertOk()
        ->assertJsonPath('data.speaker.name', 'Pasteur Jean')
        ->assertJsonCount(1, 'data.speaker.contents');
});

it('crée un speaker (admin)', function () {
    $this->withToken(speakerToken(speakerAdmin()))
        ->postJson('/api/v1/speakers', [
            'name' => 'Pasteur Paul',
            'title' => 'pasteur',
            'bio' => 'Pasteur principal.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.speaker.name', 'Pasteur Paul')
        ->assertJsonPath('data.speaker.title', 'pasteur');

    $this->assertDatabaseHas('speakers', ['name' => 'Pasteur Paul', 'title' => 'pasteur']);
});

it('valide les champs requis lors de la création', function () {
    $this->withToken(speakerToken(speakerAdmin()))
        ->postJson('/api/v1/speakers', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');

    $this->withToken(speakerToken(speakerAdmin()))
        ->postJson('/api/v1/speakers', ['name' => 'Test', 'title' => 'invalid'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('refuse un nom en double', function () {
    Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur']);

    $this->withToken(speakerToken(speakerAdmin()))
        ->postJson('/api/v1/speakers', ['name' => 'Pasteur Jean', 'title' => 'frere'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('modifie un speaker (admin)', function () {
    $speaker = Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur']);

    $this->withToken(speakerToken(speakerAdmin()))
        ->putJson("/api/v1/speakers/{$speaker->id}", [
            'name' => 'Pasteur Jean-Marc',
            'title' => 'pasteur',
        ])
        ->assertOk()
        ->assertJsonPath('data.speaker.name', 'Pasteur Jean-Marc');

    $this->assertDatabaseHas('speakers', ['id' => $speaker->id, 'name' => 'Pasteur Jean-Marc']);
});

it('supprime un speaker non utilisé', function () {
    $speaker = Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur']);

    $this->withToken(speakerToken(speakerAdmin()))
        ->deleteJson("/api/v1/speakers/{$speaker->id}")
        ->assertOk()
        ->assertJsonPath('data.message', 'Conférencier supprimé.');

    $this->assertDatabaseMissing('speakers', ['id' => $speaker->id]);
});

it('refuse la suppression d un speaker utilisé par des contenus', function () {
    $speaker = Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur']);

    Storage::disk('audio')->put('contents/test.mp3', 'audio');
    Content::create([
        'title' => 'Enseignement',
        'speaker_id' => $speaker->id,
        'file_path' => 'contents/test.mp3',
        'original_filename' => 'test.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ]);

    $this->withToken(speakerToken(speakerAdmin()))
        ->deleteJson("/api/v1/speakers/{$speaker->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'speaker_in_use');
});

it('refuse une écriture sans authentification', function () {
    $this->postJson('/api/v1/speakers', ['name' => 'x'])->assertStatus(401);
    $this->putJson('/api/v1/speakers/1', ['name' => 'x'])->assertStatus(401);
    $this->deleteJson('/api/v1/speakers/1')->assertStatus(401);
});

it('refuse la création sans permission speaker.create (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(speakerToken($user))
        ->postJson('/api/v1/speakers', ['name' => 'x', 'title' => 'autre'])
        ->assertForbidden();
});

it('crée un contenu avec speaker_id et retourne le speaker comme objet', function () {
    Storage::fake('audio');
    Storage::fake('content_images');
    $this->seed(RbacSeeder::class);

    $speaker = Speaker::create(['name' => 'Pasteur Jean', 'title' => 'pasteur']);

    $this->withToken(speakerToken(speakerAdmin()))
        ->post('/api/v1/contents', [
            'title' => 'Enseignement',
            'file' => UploadedFile::fake()->create('test.mp3', 100),
            'speaker_id' => $speaker->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.content.speaker.id', $speaker->id)
        ->assertJsonPath('data.content.speaker.name', 'Pasteur Jean')
        ->assertJsonPath('data.content.speaker.title', 'pasteur');
});
