<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Services\DeviceTokenService;
use App\Modules\Notifications\Services\ExpoPushClient;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi de push notifications via Expo Push Service (US-039, D-03).
 *
 * L'envoi passe TOUJOURS par un job (jamais dans la requête HTTP). Un
 * fournisseur indisponible déclenche le retry du job (tries/backoff, A2) ;
 * un token invalide ou expiré signalé par Expo est retiré (A1).
 */
class SendPushNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> secondes d'attente entre chaque tentative */
    public array $backoff = [5, 15];

    public int $timeout = 30;

    /**
     * @param  list<string>  $tokens
     */
    public function __construct(
        public array $tokens,
        public string $title,
        public ?string $body = null,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?string $type = null,
    ) {}

    public function handle(ExpoPushClient $client, DeviceTokenService $devices): void
    {
        if ($this->tokens === []) {
            return;
        }

        $data = [];

        if ($this->entityType !== null) {
            $data['entity_type'] = $this->entityType;
            $data['entity_id'] = $this->entityId;
        }

        if ($this->type !== null) {
            $data['type'] = $this->type;
        }

        $channelId = $this->channelIdFor($this->type);

        if ($channelId !== null) {
            $data['channelId'] = $channelId;
        }

        $messages = array_map(
            fn (string $token): array => [
                'to' => $token,
                'title' => $this->title,
                'body' => $this->body,
                'data' => $data !== [] ? $data : null,
            ],
            $this->tokens,
        );

        $results = $client->send($messages);

        foreach ($results as $index => $result) {
            $token = $this->tokens[$index] ?? null;

            if ($token !== null && $this->isDeadToken($result)) {
                $devices->removeByToken($token);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('audit')->error('push.send.failed', [
            'tokens_count' => count($this->tokens),
            'error' => $exception?->getMessage(),
        ]);
    }

    private function isDeadToken(mixed $result): bool
    {
        if (! is_array($result) || ($result['status'] ?? null) !== 'error') {
            return false;
        }

        $details = $result['details'] ?? null;

        return is_array($details) && ($details['error'] ?? null) === 'DeviceNotRegistered';
    }

    /**
     * Canal Android ciblé selon le type (MOD-10-P3) : les rappels utilisent
     * des canaux dédiés (son/vibration distincts côté client) ; les autres
     * types passent par le canal par défaut.
     */
    private function channelIdFor(?string $type): ?string
    {
        return match ($type) {
            NotificationService::TYPE_PROGRAM_REMINDER => 'program_reminder',
            NotificationService::TYPE_INACTIVITY_REMINDER => 'inactivity_reminder',
            default => null,
        };
    }
}
