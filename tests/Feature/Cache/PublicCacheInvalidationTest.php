<?php

use App\Modules\Content\Models\Content;
use App\Modules\Content\Services\ContentService;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Services\MonthService;
use App\Modules\Organization\Services\ProgramService;
use App\Modules\Organization\Services\WeekService;
use App\Modules\Organization\Services\YearService;
use App\Modules\Organization\Support\OrganizationPublicCache;
use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Modules\SpecialActivities\Services\ActivityTypeService;
use App\Modules\SpecialActivities\Services\SpecialActivityService;
use App\Modules\SpecialActivities\Support\SpecialActivityPublicCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('audio');
    Storage::fake('content_images');
    Cache::flush();
});

function cacheVersion(string $key): int
{
    return (int) Cache::get($key, 0);
}

function cacheAudioFile(): UploadedFile
{
    return UploadedFile::fake()->create('predication.mp3', 100);
}

function cachePublishedContent(array $attrs = []): Content
{
    Storage::disk('audio')->put('contents/test.mp3', 'audio');

    return Content::create(array_merge([
        'status' => Content::STATUS_PUBLISHED,
        'title' => 'Enseignement',
        'file_path' => 'contents/test.mp3',
        'original_filename' => 'test.mp3',
        'mime_type' => 'audio/mpeg',
        'size_bytes' => 5,
    ], $attrs));
}

function cacheOrgYear(): Year
{
    return Year::create(['label' => '2026-2027']);
}

function cacheOrgWeek(Year $year): Week
{
    return Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
}

function cacheActivityRecord(Week $week): SpecialActivity
{
    $type = ActivityType::firstOrCreate(['code' => 'convention'], ['label' => 'Convention']);

    return SpecialActivity::create([
        'week_id' => $week->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention de prière',
        'mode' => 'complement',
    ]);
}

it('invalide le cache public à chaque écriture de contenu', function () {
    $service = app(ContentService::class);
    $version = cacheVersion(ContentService::PUBLIC_CACHE_VERSION_KEY);

    $content = $service->create(cacheAudioFile(), ['title' => 'Enseignement']);
    expect(cacheVersion(ContentService::PUBLIC_CACHE_VERSION_KEY))->toBe($version + 1);

    $service->update($content, ['title' => 'Enseignement v2']);
    expect(cacheVersion(ContentService::PUBLIC_CACHE_VERSION_KEY))->toBe($version + 2);

    $service->delete($content);
    expect(cacheVersion(ContentService::PUBLIC_CACHE_VERSION_KEY))->toBe($version + 3);
});

it('revalide la liste publique des contenus après mise à jour', function () {
    $content = cachePublishedContent();

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Enseignement');

    app(ContentService::class)->update($content, ['title' => 'Enseignement actualisé']);

    $this->getJson('/api/v1/contents')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Enseignement actualisé');
});

it('invalide le cache public à chaque écriture d organisation', function () {
    $yearService = app(YearService::class);
    $monthService = app(MonthService::class);
    $weekService = app(WeekService::class);
    $programService = app(ProgramService::class);

    $version = cacheVersion(OrganizationPublicCache::VERSION_KEY);

    $year = $yearService->create(['label' => '2026-2027']);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 1);

    $week = $weekService->create($year, ['label' => 'Semaine 1']);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 2);

    $month = $year->months()->where('month_number', 1)->firstOrFail();
    $monthService->update($month, ['month_number' => 1, 'theme' => 'Renouveau']);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 3);

    $program = $programService->create($week, [
        'day_of_week' => 7,
        'start_time' => '09:00',
        'duration_minutes' => 120,
        'type' => 'Culte',
    ]);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 4);

    $programService->update($program, [
        'day_of_week' => 7,
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'type' => 'Étude',
    ]);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 5);

    $monthService->delete($month);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 6);

    $yearService->markCurrent($year);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 7);

    $yearService->update($year, ['label' => '2026-2028', 'is_current' => true]);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 8);

    $programService->delete($program);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 9);

    $weekService->delete($week);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 10);

    $yearService->delete($year);
    expect(cacheVersion(OrganizationPublicCache::VERSION_KEY))->toBe($version + 11);
});

it('revalide la liste publique des années après mise à jour', function () {
    $year = cacheOrgYear();

    $this->getJson('/api/v1/organization/years')
        ->assertOk()
        ->assertJsonPath('data.0.label', '2026-2027');

    app(YearService::class)->update($year, ['label' => '2026-2028']);

    $this->getJson('/api/v1/organization/years')
        ->assertOk()
        ->assertJsonPath('data.0.label', '2026-2028');
});

it('invalide le cache public à chaque écriture d activité spéciale', function () {
    $activityService = app(SpecialActivityService::class);
    $year = cacheOrgYear();
    $week = cacheOrgWeek($year);
    $type = ActivityType::firstOrCreate(['code' => 'convention'], ['label' => 'Convention']);

    $version = cacheVersion(SpecialActivityPublicCache::VERSION_KEY);

    $activity = $activityService->create([
        'week_id' => $week->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention de prière',
        'mode' => 'complement',
        'starts_on' => null,
        'ends_on' => null,
    ]);
    expect(cacheVersion(SpecialActivityPublicCache::VERSION_KEY))->toBe($version + 1);

    $session = $activityService->addSession($activity, [
        'day_of_week' => 1,
        'start_time' => '18:00',
        'duration_minutes' => 60,
        'place' => 'Salle principale',
        'theme' => null,
    ]);
    expect(cacheVersion(SpecialActivityPublicCache::VERSION_KEY))->toBe($version + 2);

    $activityService->updateSession($session, [
        'day_of_week' => 1,
        'start_time' => '19:00',
        'duration_minutes' => 90,
        'place' => 'Salle annexe',
        'theme' => null,
    ]);
    expect(cacheVersion(SpecialActivityPublicCache::VERSION_KEY))->toBe($version + 3);

    $activityService->deleteSession($session);
    expect(cacheVersion(SpecialActivityPublicCache::VERSION_KEY))->toBe($version + 4);

    $activityService->update($activity, [
        'week_id' => $week->id,
        'activity_type_id' => $type->id,
        'name' => 'Convention revisitée',
        'mode' => 'complement',
        'starts_on' => null,
        'ends_on' => null,
    ]);
    expect(cacheVersion(SpecialActivityPublicCache::VERSION_KEY))->toBe($version + 5);

    $activityService->delete($activity);
    expect(cacheVersion(SpecialActivityPublicCache::VERSION_KEY))->toBe($version + 6);

    app(ActivityTypeService::class)->update($type, [
        'code' => 'convention',
        'label' => 'Convention renommée',
        'is_active' => true,
    ]);
    expect(cacheVersion(SpecialActivityPublicCache::VERSION_KEY))->toBe($version + 7);

    app(ActivityTypeService::class)->delete($type);
    expect(cacheVersion(SpecialActivityPublicCache::VERSION_KEY))->toBe($version + 8);
});

it('revalide la liste publique des activités après mise à jour', function () {
    $week = cacheOrgWeek(cacheOrgYear());
    $activity = cacheActivityRecord($week);

    $this->getJson('/api/v1/special-activities')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Convention de prière');

    app(SpecialActivityService::class)->update($activity, [
        'week_id' => $week->id,
        'activity_type_id' => $activity->activity_type_id,
        'name' => 'Convention actualisée',
        'mode' => 'complement',
        'starts_on' => null,
        'ends_on' => null,
    ]);

    $this->getJson('/api/v1/special-activities')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Convention actualisée');
});
