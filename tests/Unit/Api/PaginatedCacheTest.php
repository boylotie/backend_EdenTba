<?php

use App\Shared\Support\PaginatedCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

function paginatedCacheRows(): LengthAwarePaginator
{
    return new LengthAwarePaginator(
        items: collect(['Alpha', 'Beta']),
        total: 5,
        perPage: 2,
        currentPage: 1,
    );
}

it('ne sérialise que des tableaux purs dans le cache', function () {
    $key = 'paginated-cache.test';

    $paginator = PaginatedCache::remember($key, 300, fn (): LengthAwarePaginator => paginatedCacheRows());

    expect(Cache::get($key))
        ->toBeArray()
        ->toHaveKeys(['items', 'total', 'per_page', 'current_page', 'path'])
        ->and(Cache::get($key)['items'])->toBe(['Alpha', 'Beta'])
        ->and($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->items())->toBe(['Alpha', 'Beta'])
        ->and($paginator->total())->toBe(5)
        ->and($paginator->perPage())->toBe(2)
        ->and($paginator->currentPage())->toBe(1);
});

it('reconstruit un paginateur identique depuis le cache', function () {
    $key = 'paginated-cache.roundtrip';

    PaginatedCache::remember($key, 300, fn (): LengthAwarePaginator => paginatedCacheRows());

    $cached = PaginatedCache::remember($key, 300, fn (): LengthAwarePaginator => paginatedCacheRows());

    expect($cached->items())->toBe(['Alpha', 'Beta'])
        ->and($cached->total())->toBe(5)
        ->and($cached->lastPage())->toBe(3)
        ->and($cached->hasMorePages())->toBeTrue();
});

it('applique le transformateur avant mise en cache', function () {
    $key = 'paginated-cache.transform';

    $paginator = PaginatedCache::remember(
        $key,
        300,
        fn (): LengthAwarePaginator => paginatedCacheRows(),
        fn (string $value): array => ['value' => strtoupper($value)],
    );

    expect(Cache::get($key)['items'])
        ->toBe([['value' => 'ALPHA'], ['value' => 'BETA']])
        ->and($paginator->items())->toBe([['value' => 'ALPHA'], ['value' => 'BETA']]);
});

it('builds un paginateur immédiat sans passer par le cache', function () {
    $paginator = PaginatedCache::make(fn (): LengthAwarePaginator => paginatedCacheRows());

    expect($paginator->items())->toBe(['Alpha', 'Beta'])
        ->and($paginator->total())->toBe(5);
});
