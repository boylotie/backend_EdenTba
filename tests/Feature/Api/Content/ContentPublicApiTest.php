<?php

use App\Modules\Content\Models\Content;
use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('audio');
    Storage::fake('content_images');
    Cache::flush();
});

function publicContentRecord(array $attrs = [], int $index = 0): Content
{
    Storage::disk('audio')->put("contents/test-{$index}.mp3", 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Enseignement n°'.($index + 1),
        'file_path' => "contents/test-{$index}.mp3",
        'original_filename' => "test-{$index}.mp3",
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

function publicOrg(): array
{
    $year = Year::create(['label' => '2026-2027']);
    $month = Month::create(['year_id' => $year->id, 'month_number' => 1]);
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    $type = ActivityType::firstOrCreate(['code' => 'convention'], ['label' => 'Convention']);
    $activity = SpecialActivity::create([
        'week_id' => $week->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention de prière',
        'mode' => 'complement',
    ]);

    return [$year, $month, $week, $activity];
}

it('liste les contenus en lecture publique', function () {
    publicContentRecord();

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Enseignement n°1')
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonStructure(['data' => [['id', 'title', 'image_url', 'year', 'month', 'week', 'special_activity']]])
        ->assertHeader('Cache-Control', 'max-age=300, public');
});

it('ordonne par sort_order puis par récence décroissante (US-027)', function () {
    publicContentRecord([], 1);
    publicContentRecord([], 2);
    publicContentRecord(['sort_order' => 5], 3);

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('data.0.id', 2)
        ->assertJsonPath('data.1.id', 1)
        ->assertJsonPath('data.2.id', 3);
});

it('pagine la liste publique', function () {
    for ($i = 0; $i < 12; $i++) {
        publicContentRecord([], $i);
    }

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 12)
        ->assertJsonPath('meta.pagination.last_page', 2)
        ->assertJsonCount(10, 'data');

    $this->getJson('/api/v1/contents?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filtre par année, mois, semaine et activité', function () {
    [$year, $month, $week, $activity] = publicOrg();
    publicContentRecord(['year_id' => $year->id, 'month_id' => $month->id, 'week_id' => $week->id, 'special_activity_id' => $activity->id]);
    publicContentRecord();

    $this->getJson("/api/v1/contents?year={$year->id}")
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.year.id', $year->id);

    $this->getJson("/api/v1/contents?month={$month->id}")
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    $this->getJson("/api/v1/contents?week={$week->id}")
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    $this->getJson("/api/v1/contents?activity={$activity->id}")
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
});

it('rejette un filtre invalide en 422', function () {
    $this->getJson('/api/v1/contents?activity=999999')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['activity']]]);
});

it('retourne une liste vide explicite sans résultat', function () {
    $emptyYear = Year::create(['label' => '2028-2029']);

    $this->getJson('/api/v1/contents?year='.$emptyYear->id)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.pagination.total', 0);
});

it('recherche par titre, description ou speaker', function () {
    publicContentRecord(['title' => 'La foi au quotidien', 'description' => 'Une méditation sur la foi', 'speaker' => 'Pasteur Jean']);
    publicContentRecord(['title' => 'La prière', 'description' => 'Enseignement sur la prière', 'speaker' => 'Frère Paul']);
    publicContentRecord(['title' => 'Louange', 'description' => 'Séance de louange', 'speaker' => 'Sœur Marie']);

    $this->getJson('/api/v1/contents?search=prière')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.title', 'La prière');

    $this->getJson('/api/v1/contents?search=Pasteur')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.title', 'La foi au quotidien');

    $this->getJson('/api/v1/contents?search=louange')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.title', 'Louange');
});

it('retourne une liste vide quand la recherche ne correspond à rien', function () {
    publicContentRecord();

    $this->getJson('/api/v1/contents?search=introuvable')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.pagination.total', 0);
});

it('combine recherche et filtres', function () {
    [$year] = publicOrg();
    publicContentRecord(['year_id' => $year->id, 'title' => 'La foi en marche']);
    publicContentRecord(['title' => 'La foi ailleurs']);

    $this->getJson("/api/v1/contents?year={$year->id}&search=foi")
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.title', 'La foi en marche');
});

it('n expose que les contenus publiés dans la liste publique', function () {
    publicContentRecord();
    $draft = publicContentRecord(['status' => Content::STATUS_DRAFT]);

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 1);

    $this->getJson("/api/v1/contents/{$draft->id}")->assertNotFound();
});

it('affiche le détail public avec les rattachements', function () {
    [$year, $month, $week, $activity] = publicOrg();
    $content = publicContentRecord(['year_id' => $year->id, 'month_id' => $month->id, 'week_id' => $week->id, 'special_activity_id' => $activity->id]);

    $this->getJson("/api/v1/contents/{$content->id}")
        ->assertOk()
        ->assertJsonPath('data.content.id', $content->id)
        ->assertJsonPath('data.content.week.label', 'Semaine 1')
        ->assertJsonPath('data.content.special_activity.name', 'Convention de prière')
        ->assertHeader('Cache-Control', 'max-age=300, public');
});

it('répond 404 pour un contenu inexistant', function () {
    $this->getJson('/api/v1/contents/999999')->assertNotFound();
});

it('sert le visuel public d un contenu', function () {
    Storage::disk('content_images')->put('images/cover.png', 'pngdata');
    $content = publicContentRecord(['image_path' => 'images/cover.png']);

    $response = $this->get("/api/v1/contents/{$content->id}/image");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=300, public');

    expect($response->streamedContent())->toBe('pngdata');
});

it('répond 404 si le contenu n a pas de visuel', function () {
    $content = publicContentRecord();

    $this->get("/api/v1/contents/{$content->id}/image")->assertNotFound();
});

it('répond 404 si le fichier visuel est absent du stockage', function () {
    $content = publicContentRecord(['image_path' => 'images/absent.png']);

    $this->get("/api/v1/contents/{$content->id}/image")->assertNotFound();
});
