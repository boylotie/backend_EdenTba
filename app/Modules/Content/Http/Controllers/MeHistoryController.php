<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Content\Http\Requests\UpdateListeningHistoryRequest;
use App\Modules\Content\Models\Content;
use App\Modules\Content\Models\ListeningHistory;
use App\Modules\Content\Support\ContentPresenter;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Historique d'écoute de l'utilisateur connecté (MOD-07-P5, US-035).
 * Limité aux 50 dernières lectures ; la reprise démarre au début si la
 * lecture précédente était terminée. Lecture ouverte D-02.
 */
class MeHistoryController extends Controller
{
    private const LIMIT = 50;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        /** @var list<array<string, mixed>> $history */
        $history = ListeningHistory::query()
            ->where('user_id', $user->id)
            ->with(self::relations())
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ListeningHistory $entry): array => $this->payload($entry))
            ->all();

        return ApiResponse::success(['history' => $history]);
    }

    public function update(Content $content, UpdateListeningHistoryRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Vous devez être authentifié.', 401);
        }

        if (! $content->isPublished()) {
            throw new NotFoundHttpException('Ce contenu n\'est pas publié.');
        }

        $position = $request->integer('position_seconds');
        $completed = $request->has('completed')
            ? (bool) $request->boolean('completed')
            : ($content->duration_seconds !== null && $position >= $content->duration_seconds);

        $entry = ListeningHistory::updateOrCreate(
            ['user_id' => $user->id, 'content_id' => $content->id],
            ['position_seconds' => $position, 'completed' => $completed],
        );

        $entry->loadMissing(self::relations());

        return ApiResponse::success(['history_entry' => $this->payload($entry)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ListeningHistory $entry): array
    {
        return [
            'id' => $entry->id,
            'content_id' => $entry->content_id,
            'position_seconds' => $entry->position_seconds,
            'completed' => $entry->completed,
            'resume_seconds' => $entry->completed ? 0 : $entry->position_seconds,
            'updated_at' => $entry->updated_at,
            'content' => $entry->content !== null
                ? ContentPresenter::payload($entry->content)
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
