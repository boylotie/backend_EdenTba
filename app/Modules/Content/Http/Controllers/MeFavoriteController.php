<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Content\Http\Requests\StoreFavoriteRequest;
use App\Modules\Content\Models\Content;
use App\Modules\Content\Models\Favorite;
use App\Modules\Content\Support\ContentPresenter;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Favoris de l'utilisateur connecté (MOD-07-P5, US-034). Lecture ouverte D-02.
 */
class MeFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        /** @var list<array<string, mixed>> $favorites */
        $favorites = Favorite::query()
            ->where('user_id', $user->id)
            ->with(self::relations())
            ->latest()
            ->get()
            ->map(fn (Favorite $favorite): array => $this->payload($favorite))
            ->all();

        return ApiResponse::success(['favorites' => $favorites]);
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        $favorite = Favorite::firstOrCreate([
            'user_id' => $user->id,
            'content_id' => $request->integer('content_id'),
        ]);

        $favorite->loadMissing(self::relations());

        return ApiResponse::success(['favorite' => $this->payload($favorite)], status: 201);
    }

    public function destroy(Request $request, Content $content): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        Favorite::query()
            ->where('user_id', $user->id)
            ->where('content_id', $content->id)
            ->delete();

        return ApiResponse::success(['message' => 'Favori retiré.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Favorite $favorite): array
    {
        return [
            'id' => $favorite->id,
            'content_id' => $favorite->content_id,
            'created_at' => $favorite->created_at,
            'content' => $favorite->content !== null
                ? ContentPresenter::payload($favorite->content)
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    private static function relations(): array
    {
        return ['content.year:id,label', 'content.month:id,month_number', 'content.week:id,label', 'content.specialActivity:id,name'];
    }
}
