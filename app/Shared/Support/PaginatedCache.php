<?php

namespace App\Shared\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Mise en cache de résultats paginés sans sérialiser l'objet
 * `LengthAwarePaginator` : seuls des tableaux purs (items + métadonnées de
 * pagination) sont stockés, et le paginateur est reconstruit à chaque lecture.
 * Évite l'erreur « incomplete object » à l'unserialize d'un paginateur caché.
 */
final class PaginatedCache
{
    /**
     * Pagination immédiate (sans cache) sous forme sérialisable.
     *
     * @template TValue of mixed
     *
     * @param  callable(): LengthAwarePaginator<int, TValue>  $builder
     * @param  (callable(TValue): array<string, mixed>)|null  $transform
     * @return LengthAwarePaginator<int, TValue>
     */
    public static function make(callable $builder, ?callable $transform = null): LengthAwarePaginator
    {
        return self::rebuild(self::toData($builder(), $transform));
    }

    /**
     * Pagination mise en cache : seuls des tableaux purs sont sérialisés.
     *
     * @template TValue of mixed
     *
     * @param  callable(): LengthAwarePaginator<int, TValue>  $builder
     * @param  (callable(TValue): array<string, mixed>)|null  $transform
     * @return LengthAwarePaginator<int, TValue>
     */
    public static function remember(string $key, int $ttl, callable $builder, ?callable $transform = null): LengthAwarePaginator
    {
        return self::rebuild(Cache::remember($key, $ttl, fn (): array => self::toData($builder(), $transform)));
    }

    /**
     * @param  array{items: list<mixed>, total: int, per_page: int, current_page: int, path: string}  $data
     * @return LengthAwarePaginator<int, mixed>
     */
    private static function rebuild(array $data): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $data['items'],
            (int) $data['total'],
            (int) $data['per_page'],
            (int) $data['current_page'],
            ['path' => (string) $data['path']],
        );
    }

    /**
     * @template TValue of mixed
     *
     * @param  LengthAwarePaginator<int, TValue>  $paginator
     * @param  (callable(TValue): array<string, mixed>)|null  $transform
     * @return array{items: list<mixed>, total: int, per_page: int, current_page: int, path: string}
     */
    private static function toData(LengthAwarePaginator $paginator, ?callable $transform): array
    {
        $items = $transform === null
            ? $paginator->getCollection()->toArray()
            : $paginator->getCollection()->map($transform)->values()->all();

        return [
            'items' => array_values($items),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'path' => $paginator->path(),
        ];
    }
}
