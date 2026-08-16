<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Http\Requests\StoreListeningEventRequest;
use App\Modules\Analytics\Models\ListeningEvent;
use App\Modules\Content\Models\Content;
use App\Shared\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Enregistrement des événements d'écoute anonymisés (MOD-12-P1, US-048).
 * Route publique : aucun utilisateur n'est requis ni identifié (A2).
 */
class ListeningEventController extends Controller
{
    public function store(StoreListeningEventRequest $request): JsonResponse
    {
        $content = Content::query()->find($request->integer('content_id'));

        if ($content === null || ! $content->isPublished()) {
            throw new NotFoundHttpException('Ce contenu n\'est pas publié.');
        }

        $event = ListeningEvent::create([
            'content_id' => $content->id,
            'event_type' => (string) $request->string('event_type'),
            'position_seconds' => $request->filled('position_seconds') ? $request->integer('position_seconds') : null,
            'occurred_at' => now(),
        ]);

        return ApiResponse::success([
            'listening_event' => [
                'id' => $event->id,
                'content_id' => $event->content_id,
                'event_type' => $event->event_type,
                'position_seconds' => $event->position_seconds,
                'occurred_at' => $event->occurred_at,
            ],
        ], status: 201);
    }
}
