<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Services\AdminBroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi immédiat d'un message d'administration (US-040) : la requête HTTP ne
 * fait que soumettre le job, la diffusion (internes + push) est asynchrone.
 */
class SendAdminNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> secondes d'attente entre chaque tentative */
    public array $backoff = [5, 15];

    public int $timeout = 30;

    public function __construct(
        public string $title,
        public ?string $body = null,
    ) {}

    public function handle(AdminBroadcastService $broadcast): void
    {
        $broadcast->broadcast($this->title, $this->body);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('audit')->error('notifications.send.failed', [
            'title' => $this->title,
            'error' => $exception?->getMessage(),
        ]);
    }
}
