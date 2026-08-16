<?php

namespace App\Modules\Streaming\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Streaming\Http\Requests\StartLiveRequest;
use App\Modules\Streaming\Models\LiveSession;
use App\Modules\Streaming\Services\LiveService;
use App\Modules\Streaming\Support\LiveImageStream;
use App\Shared\Api\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LiveController extends Controller
{
    public function __construct(
        private readonly LiveService $live,
        private readonly LiveImageStream $images,
    ) {}

    /**
     * État du live (US-046). Accès public ; l'URL d'écoute signée n'est
     * fournie qu'à un utilisateur authentifié et si le live est actif.
     */
    public function status(Request $request): JsonResponse
    {
        $payload = $this->live->status();
        $user = $request->user('sanctum');

        if ($user !== null) {
            $signed = $this->live->signedStreamUrl($this->live->current());

            if ($signed !== null) {
                $payload['stream_url'] = $signed['url'];
                $payload['stream_url_expires_at'] = $signed['expires_at'];
            }
        }

        return ApiResponse::success($payload);
    }

    /**
     * Démarrage du direct (US-046). 409 si un live est déjà en cours.
     */
    public function start(StartLiveRequest $request): JsonResponse
    {
        $this->authorize('start', LiveSession::class);

        try {
            $session = $this->live->start([
                'title' => $request->filled('title') ? (string) $request->string('title') : null,
                'description' => $request->filled('description') ? (string) $request->string('description') : null,
            ], $request->file('image'));
        } catch (DomainException) {
            return ApiResponse::error('live_already_started', 'Un direct est déjà en cours.', 409);
        }

        return ApiResponse::success(['live_session' => $session], status: 201);
    }

    /**
     * Arrêt du direct (US-046). 409 si aucun live en cours.
     */
    public function stop(Request $request): JsonResponse
    {
        $this->authorize('stop', LiveSession::class);

        try {
            $session = $this->live->stop();
        } catch (DomainException) {
            return ApiResponse::error('live_not_started', 'Aucun direct en cours.', 409);
        }

        return ApiResponse::success(['live_session' => $session]);
    }

    /**
     * Visuel du direct en cours (public). 404 si aucun visuel ou fichier absent.
     */
    public function image(): BinaryFileResponse
    {
        $session = $this->live->current();

        if ($session === null) {
            abort(404);
        }

        return $this->images->serve($session);
    }
}
