<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Content\Models\Content;
use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Database\Seeders\RbacSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RbacSeeder::class);
    Storage::fake('audio');
    Storage::fake('content_images');
});

function crudAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN);

    return $user;
}

function crudToken(User $user): string
{
    return $user->createToken('mobile')->plainTextToken;
}

function crudAudioFile(): UploadedFile
{
    return UploadedFile::fake()->create('predication.mp3', 100);
}

function crudOrgYear(): Year
{
    return Year::create(['label' => '2026-2027']);
}

function crudOrgMonth(Year $year): Month
{
    return Month::create(['year_id' => $year->id, 'month_number' => 1]);
}

function crudOrgWeek(Year $year): Week
{
    return Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
}

function crudOrgActivity(Week $week): SpecialActivity
{
    $type = ActivityType::firstOrCreate(['code' => 'convention'], ['label' => 'Convention']);

    return SpecialActivity::create([
        'week_id' => $week->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention de prière',
        'mode' => 'complement',
    ]);
}

function crudContentRecord(array $attrs = []): Content
{
    Storage::disk('audio')->put('contents/test.mp3', 'audio');

    return Content::create(array_merge([
        'title' => 'Enseignement',
        'file_path' => 'contents/test.mp3',
        'original_filename' => 'test.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

function crudPayload(Year $year, Month $month, Week $week, SpecialActivity $activity): array
{
    return [
        'title' => 'Prédication du dimanche',
        'description' => 'Enseignement sur la foi',
        'duration_seconds' => 3600,
        'speaker' => 'Frère Paul',
        'year_id' => $year->id,
        'month_id' => $month->id,
        'week_id' => $week->id,
        'special_activity_id' => $activity->id,
        'scheduled_at' => '2027-01-10 18:00:00',
        'sort_order' => 2,
    ];
}

it('refuse une écriture sans authentification', function () {
    $this->postJson('/api/v1/contents', ['title' => 'x'])->assertStatus(401);
    $this->putJson('/api/v1/contents/1', ['title' => 'x'])->assertStatus(401);
    $this->deleteJson('/api/v1/contents/1')->assertStatus(401);
});

it('refuse la création sans permission content.create (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(crudToken($user))
        ->post('/api/v1/contents', ['title' => 'x', 'file' => crudAudioFile()])
        ->assertForbidden();
});

it('refuse la mise à jour sans permission content.update (403)', function () {
    $content = crudContentRecord();

    $this->withToken(crudToken(crudAdmin()))
        ->putJson("/api/v1/contents/{$content->id}", ['title' => 'x'])
        ->assertOk();

    // Chaque requête HTTP réelle re-résout l'authentification ; on relâche le
    // garde pour que le 2e appel parte bien en tant que simple utilisateur.
    $this->app['auth']->forgetGuards();

    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(crudToken($user))
        ->putJson("/api/v1/contents/{$content->id}", ['title' => 'x'])
        ->assertForbidden();
});

it('refuse la suppression sans permission content.delete (403)', function () {
    $content = crudContentRecord();

    $user = User::factory()->create();
    $user->assignRole(Role::USER);

    $this->withToken(crudToken($user))
        ->deleteJson("/api/v1/contents/{$content->id}")
        ->assertForbidden();
});

it('crée un contenu avec métadonnées et rattachement cohérent', function () {
    $year = crudOrgYear();
    $month = crudOrgMonth($year);
    $week = crudOrgWeek($year);
    $activity = crudOrgActivity($week);

    $response = $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', array_merge(crudPayload($year, $month, $week, $activity), [
            'file' => crudAudioFile(),
        ]))
        ->assertCreated()
        ->assertJsonPath('data.content.title', 'Prédication du dimanche')
        ->assertJsonPath('data.content.duration_seconds', 3600)
        ->assertJsonPath('data.content.year.id', $year->id)
        ->assertJsonPath('data.content.month.month_number', 1)
        ->assertJsonPath('data.content.week.label', 'Semaine 1')
        ->assertJsonPath('data.content.special_activity.name', 'Convention de prière');

    $id = $response->json('data.content.id');

    $this->assertDatabaseHas('contents', ['id' => $id, 'sort_order' => 2, 'speaker' => 'Frère Paul']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'contents.create', 'entity_id' => $id]);
});

it('crée un contenu avec jour, notes de prédication et compte-rendu d approbation', function () {
    $response = $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', [
            'title' => 'Prédication du dimanche',
            'file' => crudAudioFile(),
            'day_of_week' => 7,
            'notes' => "Plan : la foi, l'espérance.",
            'approved_by' => 'Pasteur Jean',
            'approval_comment' => 'Compte-rendu validé.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.content.day_of_week', 7)
        ->assertJsonPath('data.content.notes', "Plan : la foi, l'espérance.")
        ->assertJsonMissingPath('data.content.approved_by')
        ->assertJsonMissingPath('data.content.approval_comment');

    $content = Content::findOrFail($response->json('data.content.id'));

    expect($content->approved_by)->toBe('Pasteur Jean')
        ->and($content->approval_comment)->toBe('Compte-rendu validé.')
        ->and($content->approved_at)->not->toBeNull();
});

it('efface le compte-rendu d approbation quand l approbateur est retiré', function () {
    $content = crudContentRecord([
        'approved_by' => 'Pasteur Jean',
        'approval_comment' => 'Compte-rendu validé.',
        'approved_at' => now(),
    ]);

    $this->withToken(crudToken(crudAdmin()))
        ->putJson("/api/v1/contents/{$content->id}", [
            'title' => 'Enseignement',
            'approved_by' => '',
        ])
        ->assertOk();

    $content->refresh();

    expect($content->approved_by)->toBeNull()
        ->and($content->approval_comment)->toBeNull()
        ->and($content->approved_at)->toBeNull();
});

it('rejette un jour de semaine hors plage en 422', function () {
    $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', [
            'title' => 'Contenu',
            'file' => crudAudioFile(),
            'day_of_week' => 8,
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['day_of_week']]]);
});

it('rejette un contenu sans titre en 422', function () {
    $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', ['file' => crudAudioFile()])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['title']]]);
});

it('rejette un mois hors de l année indiquée en 422', function () {
    $year = crudOrgYear();
    $otherYear = Year::create(['label' => '2025-2026']);
    $month = crudOrgMonth($otherYear);
    $week = crudOrgWeek($year);
    $activity = crudOrgActivity($week);

    $payload = crudPayload($year, $month, $week, $activity);

    $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', array_merge($payload, ['file' => crudAudioFile()]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['month_id']]]);
});

it('rejette une semaine hors de l année indiquée en 422', function () {
    $year = crudOrgYear();
    $otherYear = Year::create(['label' => '2025-2026']);
    $month = crudOrgMonth($year);
    $week = crudOrgWeek($otherYear);
    $activity = crudOrgActivity($week);

    $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', array_merge(crudPayload($year, $month, $week, $activity), ['file' => crudAudioFile()]))
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['week_id']]]);
});

it('rejette une activité rattachée à une autre semaine en 422', function () {
    $year = crudOrgYear();
    $month = crudOrgMonth($year);
    $week = crudOrgWeek($year);
    $otherWeek = Week::create(['year_id' => $year->id, 'label' => 'Semaine 2']);
    $activity = crudOrgActivity($otherWeek);

    $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', array_merge(crudPayload($year, $month, $week, $activity), ['file' => crudAudioFile()]))
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['special_activity_id']]]);
});

it('stocke un visuel lors de la création', function () {
    $response = $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', [
            'title' => 'Enseignement',
            'file' => crudAudioFile(),
            'image' => UploadedFile::fake()->image('cover.png'),
        ])
        ->assertCreated();

    $id = $response->json('data.content.id');

    $content = Content::findOrFail($id);

    expect(Storage::disk('content_images')->exists($content->image_path))->toBeTrue()
        ->and($response->json('data.content.image_url'))->toBe("/api/v1/contents/{$id}/image");
});

it('met à jour un contenu', function () {
    $content = crudContentRecord();

    $this->withToken(crudToken(crudAdmin()))
        ->putJson("/api/v1/contents/{$content->id}", [
            'title' => 'Enseignement du mercredi',
            'speaker' => 'Frère Marc',
            'duration_seconds' => 1800,
        ])
        ->assertOk()
        ->assertJsonPath('data.content.title', 'Enseignement du mercredi')
        ->assertJsonPath('data.content.speaker', 'Frère Marc');

    $this->assertDatabaseHas('contents', ['id' => $content->id, 'title' => 'Enseignement du mercredi']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'contents.update', 'entity_id' => $content->id]);
});

it('met à jour le jour, les notes et l approbation', function () {
    $content = crudContentRecord();

    $this->withToken(crudToken(crudAdmin()))
        ->putJson("/api/v1/contents/{$content->id}", [
            'title' => 'Enseignement du mardi',
            'day_of_week' => 2,
            'notes' => 'Notes de l enseignement.',
            'approved_by' => 'Sœur Marie',
        ])
        ->assertOk()
        ->assertJsonPath('data.content.day_of_week', 2)
        ->assertJsonPath('data.content.notes', 'Notes de l enseignement.');

    $content->refresh();

    expect($content->day_of_week)->toBe(2)
        ->and($content->notes)->toBe('Notes de l enseignement.')
        ->and($content->approved_by)->toBe('Sœur Marie')
        ->and($content->approved_at)->not->toBeNull();
});

it('remplace le visuel lors de la mise à jour', function () {
    $this->withToken(crudToken(crudAdmin()))
        ->post('/api/v1/contents', [
            'title' => 'Enseignement',
            'file' => crudAudioFile(),
            'image' => UploadedFile::fake()->image('cover.png'),
        ])
        ->assertCreated();

    $content = Content::query()->firstOrFail();
    $oldImage = $content->image_path;

    $this->withToken(crudToken(crudAdmin()))
        ->put("/api/v1/contents/{$content->id}", [
            'title' => 'Enseignement v2',
            'image' => UploadedFile::fake()->image('cover2.webp'),
        ])
        ->assertOk();

    $content->refresh();

    expect(Storage::disk('content_images')->exists($content->image_path))->toBeTrue()
        ->and($content->image_path)->not->toBe($oldImage)
        ->and(Storage::disk('content_images')->exists($oldImage))->toBeFalse();
});

it('supprime un contenu et ses fichiers', function () {
    Storage::disk('content_images')->put('images/cover.png', 'png');
    $content = crudContentRecord(['image_path' => 'images/cover.png']);

    $this->withToken(crudToken(crudAdmin()))
        ->deleteJson("/api/v1/contents/{$content->id}")
        ->assertOk()
        ->assertJsonPath('data.message', 'Contenu supprimé.');

    $this->assertDatabaseMissing('contents', ['id' => $content->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'contents.delete', 'entity_id' => $content->id]);

    expect(Storage::disk('audio')->exists('contents/test.mp3'))->toBeFalse()
        ->and(Storage::disk('content_images')->exists('images/cover.png'))->toBeFalse();
});

it('refuse la suppression d une semaine contenant des contenus', function () {
    $year = crudOrgYear();
    $week = crudOrgWeek($year);
    crudContentRecord(['year_id' => $year->id, 'week_id' => $week->id]);

    $this->withToken(crudToken(crudAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}/weeks/{$week->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'week_in_use');
});

it('refuse la suppression d une activité référencée par des contenus', function () {
    $year = crudOrgYear();
    $week = crudOrgWeek($year);
    $activity = crudOrgActivity($week);
    crudContentRecord(['year_id' => $year->id, 'week_id' => $week->id, 'special_activity_id' => $activity->id]);

    $this->withToken(crudToken(crudAdmin()))
        ->deleteJson("/api/v1/special-activities/{$activity->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'activity_in_use');
});

it('refuse la suppression d une année contenant des contenus', function () {
    $year = crudOrgYear();
    crudContentRecord(['year_id' => $year->id]);

    $this->withToken(crudToken(crudAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'year_in_use');
});

it('refuse la suppression d un mois contenant des contenus', function () {
    $year = crudOrgYear();
    $month = crudOrgMonth($year);
    crudContentRecord(['year_id' => $year->id, 'month_id' => $month->id]);

    $this->withToken(crudToken(crudAdmin()))
        ->deleteJson("/api/v1/years/{$year->id}/months/{$month->id}")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'month_in_use');
});
