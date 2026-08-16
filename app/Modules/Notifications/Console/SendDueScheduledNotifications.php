<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Models\ScheduledNotification;
use App\Modules\Notifications\Services\AdminBroadcastService;
use App\Shared\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi des notifications programmées (US-040) : diffuse les notifications
 * dont `scheduled_at` est atteinte et marque `sent_at`.
 *
 * Planifié toutes les minutes (routes/console.php). Chaque notification est
 * traitée indépendamment : un échec est journalisé sans interrompre les
 * suivants. La diffusion réutilise AdminBroadcastService (internes + push).
 */
final class SendDueScheduledNotifications extends Command
{
    protected $signature = 'notifications:send-due';

    protected $description = 'Envoie les notifications programmées dont la date est atteinte (US-040).';

    public function __construct(private readonly AdminBroadcastService $broadcast)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $due = ScheduledNotification::query()
            ->whereNull('sent_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $scheduled) {
            try {
                $this->broadcast->broadcast($scheduled->title, $scheduled->body);

                $scheduled->update(['sent_at' => now()]);

                AuditLogger::log(
                    'notifications.scheduled_sent',
                    ['title' => $scheduled->title],
                    entityType: 'scheduled_notification',
                    entityId: $scheduled->id,
                );

                $this->info("Notification envoyée : {$scheduled->title} (#{$scheduled->id}).");
            } catch (Throwable $exception) {
                Log::error('Envoi programmé en échec', [
                    'scheduled_notification_id' => $scheduled->id,
                    'title' => $scheduled->title,
                    'exception' => $exception,
                ]);

                $this->error("Échec d'envoi de la notification #{$scheduled->id} : {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
