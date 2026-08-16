<?php

namespace App\Modules\Notifications\Console;

use App\Models\User;
use App\Modules\Notifications\Jobs\SendPushNotifications;
use App\Modules\Notifications\Services\DeviceTokenService;
use App\Modules\Notifications\Services\NotificationService;
use App\Settings\SettingsService;
use App\Shared\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rappel d'inactivité (US-043) : notifie les utilisateurs actifs dont la
 * dernière visite (`users.last_seen_at`) est absente ou plus ancienne que le
 * délai configuré (`rappel_inactivite_jours`).
 *
 * Planifié toutes les minutes. Idempotent : une seule notification
 * `inactivity_reminder` par utilisateur (firstOrCreate) — aucun spam. Les
 * push ne ciblent que les utilisateurs concernés. Un échec est journalisé
 * sans interrompre le traitement (A1).
 */
final class SendInactivityReminders extends Command
{
    protected $signature = 'reminders:send-inactivity';

    protected $description = 'Notifie les utilisateurs inactifs selon le délai configuré (US-043).';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
        private readonly DeviceTokenService $devices,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (bool) $this->settings->get('rappel_actif')) {
            $this->info('Rappels désactivés : aucun envoi.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays((int) $this->settings->get('rappel_inactivite_jours'));

        $users = User::query()
            ->where('is_active', true)
            ->whereDoesntHave(
                'notificationPreferences',
                fn (Builder $query): Builder => $query->where('type', NotificationService::TYPE_INACTIVITY_REMINDER)
                    ->where('enabled', false),
            )
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $cutoff);
            })
            ->get();

        $created = 0;
        $concernedIds = [];

        foreach ($users as $user) {
            try {
                $notification = $this->notifications->createUnique(
                    $user->id,
                    NotificationService::TYPE_INACTIVITY_REMINDER,
                    "Rappel d'inactivité",
                    "Cela fait un moment que vous n'avez pas consulté l'application.",
                    null,
                    null,
                );

                if ($notification->wasRecentlyCreated) {
                    $created++;
                    $concernedIds[] = $user->id;
                }
            } catch (Throwable $exception) {
                Log::error('Rappel d\'inactivité en échec', [
                    'user_id' => $user->id,
                    'exception' => $exception,
                ]);

                $this->error("Échec du rappel pour l'utilisateur #{$user->id} : {$exception->getMessage()}");
            }
        }

        if ($created > 0) {
            $tokens = $this->devices->tokensOfUsers($concernedIds);

            if ($tokens !== []) {
                SendPushNotifications::dispatch(
                    $tokens,
                    "Rappel d'inactivité",
                    'Découvrez les nouveaux contenus et programmes.',
                    type: NotificationService::TYPE_INACTIVITY_REMINDER,
                );
            }

            AuditLogger::log('reminders.inactivity_sent', ['users' => $created]);
        }

        $this->info("Rappel d'inactivité envoyé à {$created} utilisateur(s).");

        return self::SUCCESS;
    }
}
