<?php

use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Support\OrganizationPublicCache;
use Illuminate\Support\Facades\Cache;

function publicYear(bool $isCurrent = false): Year
{
    return Year::create([
        'label' => $isCurrent ? '2026-2027' : '2025-2026',
        'theme' => 'Fidélité',
        'is_current' => $isCurrent,
    ]);
}

it('liste les années publiquement sans authentification', function () {
    publicYear();
    publicYear(isCurrent: true);

    $response = $this->getJson('/api/v1/organization/years');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.is_current', true);

    $cacheControl = array_map('trim', explode(',', $response->headers->get('Cache-Control') ?? ''));
    expect($cacheControl)->toContain('public');
    expect($cacheControl)->toContain('max-age=300');
});

it('retourne le détail d une année avec mois et semaines', function () {
    $year = publicYear();
    Month::create(['year_id' => $year->id, 'month_number' => 2, 'theme' => 'Espérance']);
    Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);

    $this->getJson("/api/v1/organization/years/{$year->id}")
        ->assertOk()
        ->assertJsonPath('data.year.label', '2025-2026')
        ->assertJsonCount(1, 'data.months')
        ->assertJsonPath('data.months.0.month_number', 2)
        ->assertJsonCount(1, 'data.weeks');
});

it('liste les mois d une année publiquement', function () {
    $year = publicYear();
    Month::create(['year_id' => $year->id, 'month_number' => 3, 'theme' => 'Célébrer']);

    $this->getJson("/api/v1/organization/years/{$year->id}/months")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.month_number', 3);
});

it('liste les programmes d une semaine publiquement', function () {
    $year = publicYear();
    $week = Week::create(['year_id' => $year->id, 'label' => 'Semaine 1']);
    Program::create(['week_id' => $week->id, 'day_of_week' => 7, 'start_time' => '09:00', 'duration_minutes' => 120, 'type' => 'Culte']);

    $this->getJson("/api/v1/organization/weeks/{$week->id}/programs")
        ->assertOk()
        ->assertJsonCount(1, 'data.programs')
        ->assertJsonPath('data.programs.0.type', 'Culte');
});

it('pagine la liste des années', function () {
    for ($i = 1; $i <= 12; $i++) {
        Year::create(['label' => "Année {$i}"]);
    }

    $this->getJson('/api/v1/organization/years?page=1')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.pagination.current_page', 1);

    $this->getJson('/api/v1/organization/years?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.current_page', 2);
});

it('sert les données depuis le cache sans requête redondante', function () {
    $year = publicYear();

    $this->getJson("/api/v1/organization/years/{$year->id}")->assertOk();
    $this->assertTrue(Cache::has('public.organization.v'.OrganizationPublicCache::version().'.year.'.$year->id));
});

it('revalide les données après expiration du cache', function () {
    $year = publicYear();

    $this->getJson("/api/v1/organization/years/{$year->id}")->assertOk();
    Cache::forget('public.organization.v'.OrganizationPublicCache::version().'.year.'.$year->id);

    $this->getJson("/api/v1/organization/years/{$year->id}")
        ->assertOk()
        ->assertJsonPath('data.year.id', $year->id);

    $this->assertTrue(Cache::has('public.organization.v'.OrganizationPublicCache::version().'.year.'.$year->id));
});

it('retourne 404 pour une année inconnue', function () {
    $this->getJson('/api/v1/organization/years/999')->assertStatus(404);
});

it('retourne 404 pour une semaine inconnue', function () {
    $this->getJson('/api/v1/organization/weeks/999/programs')->assertStatus(404);
});
