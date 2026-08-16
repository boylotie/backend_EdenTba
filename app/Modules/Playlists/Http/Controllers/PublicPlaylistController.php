<?php

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Models\Content;
use App\Modules\Content\Services\ContentService;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistItem;
use App\Modules\Playlists\Services\PlaylistService;
use App\Shared\Api\ApiResponse;
use App\Shared\Support\PaginatedCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lecture publique des playlists (MOD-08) : seules les playlists marquées
 * `is_public` sont exposées, et seuls les contenus publiés apparaissent dans
 * le détail (A2).
 */
class PublicPlaylistController extends Controller
{
    private const CACHE_TTL = 300;

    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $version = (int) Cache::get(PlaylistService::PUBLIC_CACHE_VERSION_KEY, 0);

        $paginator = PaginatedCache::remember(
            "public.playlists.v{$version}.list.{$page}",
            self::CACHE_TTL,
            fn (): LengthAwarePaginator => Playlist::query()
                ->where('is_public', true)
                ->withCount('items')
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'page', $page),
            fn (Playlist $playlist): array => $this->listPayload($playlist),
        );

        $response = ApiResponse::paginate($paginator);
        $response->setPublic();
        $response->setMaxAge(self::CACHE_TTL);

        return $response;
    }

    public function show(Playlist $playlist): JsonResponse
    {
        if (! $playlist->is_public) {
            throw new NotFoundHttpException('Cette playlist n\'est pas publique.');
        }

        $version = (int) Cache::get(PlaylistService::PUBLIC_CACHE_VERSION_KEY, 0);
        $contentsVersion = (int) Cache::get(ContentService::PUBLIC_CACHE_VERSION_KEY, 0);

        $data = Cache::remember(
            "public.playlists.v{$version}.c{$contentsVersion}.{$playlist->id}",
            self::CACHE_TTL,
            fn (): array => $this->publicPayload($playlist),
        );

        $response = ApiResponse::success($data);
        $response->setPublic();
        $response->setMaxAge(self::CACHE_TTL);

        return $response;
    }

    /**
     * Payload public d'une playlist : seuls les contenus publiés apparaissent.
     *
     * @return array<string, mixed>
     */
    private function publicPayload(Playlist $playlist): array
    {
        $items = $playlist->items()
            ->whereHas('content', fn ($query) => $query->where('status', Content::STATUS_PUBLISHED))
            ->with('content:id,title,speaker,duration_seconds,image_path')
            ->get(['id', 'content_id', 'position']);

        return [
            'playlist' => [
                'id' => $playlist->id,
                'title' => $playlist->title,
                'description' => $playlist->description,
                'items' => $items->map(function (PlaylistItem $item): array {
                    return [
                        'id' => $item->id,
                        'position' => $item->position,
                        'content' => [
                            'id' => $item->content->id,
                            'title' => $item->content->title,
                            'speaker' => $item->content->speaker,
                            'duration_seconds' => $item->content->duration_seconds,
                            'image_url' => $item->content->image_path !== null
                                ? '/api/v1/contents/'.$item->content->id.'/image'
                                : null,
                        ],
                    ];
                })->values(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(Playlist $playlist): array
    {
        return [
            'id' => $playlist->id,
            'title' => $playlist->title,
            'description' => $playlist->description,
            'items_count' => $playlist->items_count,
            'created_at' => $playlist->created_at,
            'updated_at' => $playlist->updated_at,
        ];
    }
}
