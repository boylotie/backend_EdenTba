<?php

namespace App\Modules\Streaming\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Streaming\Services\LiveService;
use App\Modules\Streaming\Support\LiveChunkBuffer;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Réception de la capture micro navigateur (MOD-11, diffusion navigateur) :
 * les chunks audio sont mis en tampon puis relayés par le worker `live:relay`.
 *
 * Session web (admin) : authentification + permission `streaming.start`
 * garanties par les middlewares de la route.
 */
final class AdminLiveStreamController extends Controller
{
    public function __construct(
        private readonly LiveChunkBuffer $buffer,
        private readonly LiveService $live,
    ) {}

    /**
     * Reçoit un chunk audio de la capture micro. 409 si aucun direct en cours.
     */
    public function chunk(Request $request): JsonResponse
    {
        $current = $this->live->current();

        if ($current === null || ! $current->isLive()) {
            return response()->json(['message' => 'Aucun direct en cours.'], 409);
        }

        $bytes = $request->getContent();

        if ($bytes === '') {
            return response()->json([
                'sequence' => null,
                'buffered_bytes' => $this->buffer->totalBytes(),
            ]);
        }

        try {
            $sequence = $this->buffer->append($bytes);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->buffer->activateMic($request->header('Content-Type', 'audio/webm'));

        return response()->json([
            'sequence' => $sequence,
            'buffered_bytes' => $this->buffer->totalBytes(),
        ]);
    }

    /**
     * Termine la capture micro et le direct en cours.
     */
    public function stop(): JsonResponse
    {
        $this->buffer->deactivateMic();

        try {
            $this->live->stop();
        } catch (DomainException) {
            // Aucun direct en cours : l'état est déjà cohérent.
        }

        return response()->json(['message' => 'Capture micro arrêtée, direct terminé.']);
    }
}
