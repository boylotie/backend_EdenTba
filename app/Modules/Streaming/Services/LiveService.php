<?php

namespace App\Modules\Streaming\Services;

use App\Modules\Streaming\Events\LiveStarted;
use App\Modules\Streaming\Events\LiveStopped;
use App\Modules\Streaming\Models\LiveSession;
use App\Modules\Streaming\Storage\LiveImageStorage;
use App\Modules\Streaming\Support\LiveChunkBuffer;
use App\Settings\SettingsService;
use App\Shared\Audit\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;

/**
 * Gestion du direct (MOD-11-P2, US-046) : démarrage, arrêt et état.
 *
 * Règle absolue (MOD-11-P1) : un live n'est réel que si l'infrastructure
 * Icecast reçoit un flux. Laravel enregistre uniquement l'état et les
 * métadonnées de la session de diffusion.
 */
final class LiveService
{
    public function __construct(
        private readonly LiveImageStorage $images,
        private readonly StreamUrlSigner $signer,
        private readonly SettingsService $settings,
        private readonly LiveChunkBuffer $buffer,
    ) {}

    /**
     * Dernière session déclarée, toutes états confondus.
     */
    public function current(): ?LiveSession
    {
        return LiveSession::query()->latest('id')->first();
    }

    /**
     * Démarre une session de direct. Refus si une session est déjà en cours.
     *
     * @param  array{title: string|null, description: string|null}  $data
     *
     * @throws DomainException si un live est déjà en cours
     */
    public function start(array $data, ?UploadedFile $image = null): LiveSession
    {
        if (($current = $this->current()) !== null && $current->isLive()) {
            throw new DomainException('Un direct est déjà en cours.');
        }

        $imagePath = $image !== null ? $this->images->store($image) : null;

        try {
            $session = LiveSession::create([
                'state' => LiveSession::STATE_LIVE,
                'title' => $data['title'],
                'description' => $data['description'],
                'image_path' => $imagePath,
                'started_at' => now(),
                'created_by' => auth()->id(),
            ]);
        } catch (\Throwable $exception) {
            if ($imagePath !== null) {
                $this->images->delete($imagePath);
            }

            throw $exception;
        }

        event(new LiveStarted($session));

        $this->buffer->purge();

        AuditLogger::log(
            'streaming.start',
            ['title' => $session->title],
            entityType: 'live_session',
            entityId: $session->id,
        );

        return $session;
    }

    /**
     * Arrête la session de direct en cours.
     *
     * @throws DomainException si aucune session en cours
     */
    public function stop(): LiveSession
    {
        $session = $this->current();

        if ($session === null || ! $session->isLive()) {
            throw new DomainException('Aucun direct en cours.');
        }

        $session->state = LiveSession::STATE_OFF;
        $session->stopped_at = now();
        $session->save();

        event(new LiveStopped($session));

        $this->buffer->purge();

        AuditLogger::log(
            'streaming.stop',
            ['title' => $session->title, 'duration_seconds' => $session->started_at?->diffInSeconds($session->stopped_at)],
            entityType: 'live_session',
            entityId: $session->id,
        );

        return $session;
    }

    /**
     * État du live pour l'API publique + authentifiée.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $session = $this->current();
        $state = $session === null ? 'absent' : ($session->isLive() ? 'live' : 'off');

        $payload = [
            'state' => $state,
            'metadata' => $session === null ? null : [
                'title' => $session->title,
                'image_url' => $session->image_path !== null ? '/api/v1/live/image' : null,
            ],
            'started_at' => $session?->started_at,
            'listeners' => null,
            'ping_interval_seconds' => (int) $this->settings->get('ping_interval_secondes'),
        ];

        return $payload;
    }

    /**
     * URL d'écoute signée (HMAC-SHA256, TTL configuré) pour la session en cours.
     * Uniquement fournie à un utilisateur authentifié et si le live est actif.
     *
     * @return array{url: string, expires_at: int}|null
     */
    public function signedStreamUrl(?LiveSession $session): ?array
    {
        if ($session === null || ! $session->isLive()) {
            return null;
        }

        $base = (string) $this->settings->get('stream_url_base');
        $ttl = (int) config('streaming.url_ttl_seconds');

        return $this->signer->sign($base, now()->addSeconds($ttl));
    }
}
