<?php

namespace App\Modules\Playlists\Services;

use App\Modules\Content\Models\Content;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistItem;
use App\Shared\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Gestion des playlists (US-036) : CRUD, ordre explicite des contenus,
 * association facultative à une activité ou à une période. Seuls des contenus
 * publiés sont ajoutables ; la visibilité publique est pilotée par `is_public`.
 */
final class PlaylistService
{
    /**
     * Clé de version du cache public des playlists : incrémentée à chaque
     * écriture (playlist ou élément) pour invalider les listes publiques.
     */
    public const PUBLIC_CACHE_VERSION_KEY = 'public.playlists.version';

    /**
     * @param  array{title: string, description?: string|null, is_public?: bool, special_activity_id?: int|null, year_id?: int|null, month_id?: int|null, week_id?: int|null}  $data
     */
    public function create(array $data): Playlist
    {
        $playlist = Playlist::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_public' => filter_var($data['is_public'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'special_activity_id' => $data['special_activity_id'] ?? null,
            'year_id' => $data['year_id'] ?? null,
            'month_id' => $data['month_id'] ?? null,
            'week_id' => $data['week_id'] ?? null,
        ]);

        AuditLogger::log(
            'playlists.create',
            ['title' => $playlist->title, 'is_public' => $playlist->is_public],
            entityType: 'playlist',
            entityId: $playlist->id,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);

        return $playlist;
    }

    /**
     * @param  array{title: string, description?: string|null, is_public?: bool, special_activity_id?: int|null, year_id?: int|null, month_id?: int|null, week_id?: int|null}  $data
     */
    public function update(Playlist $playlist, array $data): Playlist
    {
        $playlist->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_public' => filter_var($data['is_public'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'special_activity_id' => $data['special_activity_id'] ?? null,
            'year_id' => $data['year_id'] ?? null,
            'month_id' => $data['month_id'] ?? null,
            'week_id' => $data['week_id'] ?? null,
        ])->save();

        AuditLogger::log(
            'playlists.update',
            ['title' => $playlist->title, 'is_public' => $playlist->is_public],
            entityType: 'playlist',
            entityId: $playlist->id,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);

        return $playlist;
    }

    /**
     * Supprime une playlist et ses éléments (cascade).
     */
    public function delete(Playlist $playlist): void
    {
        $playlistId = $playlist->id;

        $playlist->delete();

        AuditLogger::log(
            'playlists.delete',
            ['title' => $playlist->title],
            entityType: 'playlist',
            entityId: $playlistId,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);
    }

    /**
     * Ajoute un contenu publié à une playlist, en fin de liste par défaut
     * (position auto) ou à une position explicite (si libre).
     *
     * @throws DomainException si le contenu n'est pas publié, déjà présent ou
     *                         si la position demandée est occupée
     */
    public function addItem(Playlist $playlist, int $contentId, ?int $position = null): PlaylistItem
    {
        $content = Content::query()->whereKey($contentId)->first();

        if ($content === null || $content->status !== Content::STATUS_PUBLISHED) {
            throw new DomainException("Le contenu n'est pas publié.");
        }

        if ($playlist->items()->where('content_id', $contentId)->exists()) {
            throw new DomainException('Ce contenu est déjà dans la playlist.');
        }

        if ($position === null) {
            $position = (($max = $playlist->items()->max('position')) === null) ? 0 : (int) $max + 1;
        } elseif ($playlist->items()->where('position', $position)->exists()) {
            throw new DomainException('Cette position est déjà occupée.');
        }

        $item = $playlist->items()->create(['content_id' => $contentId, 'position' => $position]);

        AuditLogger::log(
            'playlists.items.add',
            ['content_id' => $contentId, 'position' => $position],
            entityType: 'playlist',
            entityId: $playlist->id,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);

        return $item;
    }

    /**
     * Réordonne la playlist : la liste fournie doit contenir exactement les
     * contenus présents, dans l'ordre voulu (positions réassignées 0..n-1).
     *
     * @param  list<int>  $contentIds
     *
     * @throws DomainException si l'ensemble des contenus ne correspond pas
     */
    public function reorder(Playlist $playlist, array $contentIds): void
    {
        $currentIds = $playlist->items()->pluck('content_id')->sort()->values();
        $requestedIds = collect($contentIds)->sort()->values();

        if (! $currentIds->diff($requestedIds)->isEmpty() || ! $requestedIds->diff($currentIds)->isEmpty()) {
            throw new DomainException('La liste doit contenir exactement les contenus de la playlist.');
        }

        DB::transaction(function () use ($playlist, $contentIds): void {
            $playlist->items()->update(['position' => DB::raw('position + 1000000')]);

            foreach ($contentIds as $index => $contentId) {
                $playlist->items()->where('content_id', $contentId)->update(['position' => $index]);
            }
        });

        AuditLogger::log(
            'playlists.items.reorder',
            ['contents' => $contentIds],
            entityType: 'playlist',
            entityId: $playlist->id,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);
    }

    /**
     * Retire un contenu d'une playlist (l'élément doit appartenir à la
     * playlist — binding scoped côté contrôleur).
     */
    public function removeItem(Playlist $playlist, PlaylistItem $item): void
    {
        $contentId = $item->content_id;

        $item->delete();

        AuditLogger::log(
            'playlists.items.remove',
            ['content_id' => $contentId],
            entityType: 'playlist',
            entityId: $playlist->id,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);
    }
}
