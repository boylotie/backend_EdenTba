<?php

namespace App\Modules\Streaming\Services;

use App\Modules\Streaming\Support\IcecastSourceClient;
use App\Modules\Streaming\Support\LiveChunkBuffer;
use App\Modules\Streaming\Support\RelaySourceConnector;
use App\Settings\SettingsService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logique du relais navigateur → Icecast (MOD-11, diffusion navigateur) :
 * relaie les chunks de la capture micro vers la source Icecast tant qu'un
 * direct est actif et que la capture micro tourne.
 *
 * Sépare la logique (testable via les bindings du conteneur) de la boucle
 * d'exécution portée par la commande `live:relay`.
 */
final class LiveRelayService
{
    public function __construct(
        private readonly LiveChunkBuffer $buffer,
        private readonly LiveService $live,
        private readonly SettingsService $settings,
        private readonly RelaySourceConnector $sources,
    ) {}

    public function processOnce(): void
    {
        $client = $this->sources->make(
            (string) $this->settings->get('stream_source_url'),
            (string) $this->settings->get('stream_source_password'),
        );

        $current = $this->live->current();
        $liveActive = $current !== null && $current->isLive();
        $micActive = $this->buffer->isMicActive();

        if ($liveActive && $micActive) {
            $mime = $this->buffer->micContentType();

            if ($mime !== null) {
                $client->setContentType($mime);
            }

            try {
                $client->connect();
            } catch (Throwable $exception) {
                Log::warning('Connexion source Icecast impossible', [
                    'error' => $client->lastError() ?? $exception->getMessage(),
                ]);

                return;
            }

            $this->forwardPending($client);

            return;
        }

        if ($client->isConnected()) {
            $client->close();
        }

        if (! $liveActive) {
            $this->buffer->purge();
        }
    }

    private function forwardPending(IcecastSourceClient $client): void
    {
        $lock = $this->buffer->relayLock();

        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            Log::warning('Une autre instance du relais est active.');

            return;
        }

        try {
            foreach ($this->buffer->pending() as $chunk) {
                $bytes = file_get_contents($chunk);

                if ($bytes === false || ! $client->write($bytes)) {
                    return;
                }

                @unlink($chunk);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
