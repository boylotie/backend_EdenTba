<?php

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Http\Requests\AddPlaylistItemRequest;
use App\Modules\Playlists\Http\Requests\ReorderPlaylistItemsRequest;
use App\Modules\Playlists\Http\Requests\StorePlaylistRequest;
use App\Modules\Playlists\Http\Requests\UpdatePlaylistRequest;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistItem;
use App\Modules\Playlists\Services\PlaylistService;
use App\Shared\Api\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

class PlaylistController extends Controller
{
    public function __construct(private readonly PlaylistService $playlists) {}

    public function store(StorePlaylistRequest $request): JsonResponse
    {
        $this->authorize('create', Playlist::class);

        $playlist = $this->playlists->create($this->playlistData($request));

        return ApiResponse::success(['playlist' => $this->payload($playlist)], status: 201);
    }

    public function update(Playlist $playlist, UpdatePlaylistRequest $request): JsonResponse
    {
        $this->authorize('update', $playlist);

        $playlist = $this->playlists->update($playlist, $this->playlistData($request));

        return ApiResponse::success(['playlist' => $this->payload($playlist)]);
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        $this->authorize('delete', $playlist);

        $this->playlists->delete($playlist);

        return ApiResponse::success(['message' => 'Playlist supprimée.']);
    }

    public function storeItem(Playlist $playlist, AddPlaylistItemRequest $request): JsonResponse
    {
        $this->authorize('update', $playlist);

        try {
            $item = $this->playlists->addItem(
                $playlist,
                (int) $request->integer('content_id'),
                $request->filled('position') ? (int) $request->integer('position') : null,
            );
        } catch (DomainException $exception) {
            return ApiResponse::error('playlist_item_rejected', $exception->getMessage(), 422);
        }

        return ApiResponse::success(['playlist_item' => $this->itemPayload($item)], status: 201);
    }

    public function reorderItems(Playlist $playlist, ReorderPlaylistItemsRequest $request): JsonResponse
    {
        $this->authorize('update', $playlist);

        try {
            $this->playlists->reorder($playlist, array_values(array_map('intval', $request->input('items', []))));
        } catch (DomainException $exception) {
            return ApiResponse::error('playlist_items_mismatch', $exception->getMessage(), 422);
        }

        $items = $playlist->items()
            ->with('content:id,title,status')
            ->get(['id', 'content_id', 'position']);

        return ApiResponse::success([
            'playlist' => $this->payload($playlist),
            'items' => $items->map(fn (PlaylistItem $item): array => $this->itemPayload($item))->values(),
        ]);
    }

    public function destroyItem(Playlist $playlist, PlaylistItem $playlistItem): JsonResponse
    {
        $this->authorize('update', $playlist);

        $this->playlists->removeItem($playlist, $playlistItem);

        return ApiResponse::success(['message' => 'Contenu retiré de la playlist.']);
    }

    /**
     * @return array{title: string, description: string|null, is_public: bool, special_activity_id: int|null, year_id: int|null, month_id: int|null, week_id: int|null}
     */
    private function playlistData(StorePlaylistRequest|UpdatePlaylistRequest $request): array
    {
        return [
            'title' => (string) $request->string('title'),
            'description' => $request->filled('description') ? (string) $request->string('description') : null,
            'is_public' => $request->boolean('is_public'),
            'special_activity_id' => $request->filled('special_activity_id') ? (int) $request->integer('special_activity_id') : null,
            'year_id' => $request->filled('year_id') ? (int) $request->integer('year_id') : null,
            'month_id' => $request->filled('month_id') ? (int) $request->integer('month_id') : null,
            'week_id' => $request->filled('week_id') ? (int) $request->integer('week_id') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Playlist $playlist): array
    {
        return [
            'id' => $playlist->id,
            'title' => $playlist->title,
            'description' => $playlist->description,
            'is_public' => $playlist->is_public,
            'items_count' => $playlist->items_count ?? $playlist->items()->count(),
            'special_activity' => $playlist->specialActivity !== null
                ? ['id' => $playlist->specialActivity->id, 'name' => $playlist->specialActivity->name]
                : null,
            'year' => $playlist->year !== null ? ['id' => $playlist->year->id, 'label' => $playlist->year->label] : null,
            'month' => $playlist->month !== null ? ['id' => $playlist->month->id, 'month_number' => $playlist->month->month_number] : null,
            'week' => $playlist->week !== null ? ['id' => $playlist->week->id, 'label' => $playlist->week->label] : null,
            'created_at' => $playlist->created_at,
            'updated_at' => $playlist->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(PlaylistItem $item): array
    {
        return [
            'id' => $item->id,
            'content_id' => $item->content_id,
            'position' => $item->position,
            'content' => $item->content !== null ? ['id' => $item->content->id, 'title' => $item->content->title] : null,
        ];
    }
}
