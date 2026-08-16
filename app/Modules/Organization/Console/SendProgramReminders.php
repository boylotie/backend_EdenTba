<?php

namespace App\Modules\Organization\Console;

use App\Modules\Notifications\Services\AdminBroadcastService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\ProgramReminder;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Settings\SettingsService;
use App\Shared\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rappels de programme (US-043) : envoie un rappel pour les programmes de
 * l'année courante dont l'occurrence (jour de semaine + heure) tombe N jours
 * après aujourd'hui (`rappel_jours_avant`), à l'heure fixe configurée
 * (`rappel_heure_programme`, D-10).
 *
 * Planifié toutes les minutes : il n'agit que pendant l'heure fixe, ce qui
 * permet les reprises en cas d'échec (A1). L'envoi unique par occurrence est
 * garanti par la table `program_reminders` (`notified_at` posé seulement après
 * succès).
 */
final class SendProgramReminders extends Command
{
    protected $signature = 'reminders:send-programs';

    protected $description = "Envoie les rappels de programme à l'heure fixe, N jours avant l'occurrence (US-043).";

    public function __construct(
        private readonly SettingsService $settings,
        private readonly AdminBroadcastService $broadcast,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (bool) $this->settings->get('rappel_actif')) {
            $this->info('Rappels désactivés : aucun envoi.');

            return self::SUCCESS;
        }

        $hour = $this->reminderHour();

        if (now()->hour !== $hour) {
            return self::SUCCESS;
        }

        $targetDate = now()->startOfDay()->addDays((int) $this->settings->get('rappel_jours_avant'));
        $weekday = $targetDate->dayOfWeekIso;

        $programs = Year::query()
            ->where('is_current', true)
            ->with(['weeks.programs'])
            ->get()
            ->flatMap(fn (Year $year) => $year->weeks->flatMap(fn (Week $week) => $week->programs))
            ->filter(fn (Program $program): bool => $program->day_of_week === $weekday);

        $sent = 0;

        foreach ($programs as $program) {
            if (ProgramReminder::query()
                ->where('program_id', $program->id)
                ->where('occurrence_date', $targetDate->toDateString())
                ->whereNotNull('notified_at')
                ->exists()) {
                continue;
            }

            try {
                $title = "Rappel : {$program->type}";
                $body = "Le {$targetDate->format('d/m/Y')} à {$program->start_time}.";

                $this->broadcast->broadcast($title, $body, NotificationService::TYPE_PROGRAM_REMINDER);

                ProgramReminder::updateOrCreate(
                    ['program_id' => $program->id, 'occurrence_date' => $targetDate->toDateString()],
                    ['notified_at' => now()],
                );

                AuditLogger::log(
                    'reminders.program_sent',
                    ['occurrence_date' => $targetDate->toDateString(), 'type' => $program->type],
                    entityType: 'program',
                    entityId: $program->id,
                );

                $sent++;

                $this->info("Rappel envoyé : {$program->type} (#{$program->id}).");
            } catch (Throwable $exception) {
                Log::error('Rappel de programme en échec', [
                    'program_id' => $program->id,
                    'occurrence_date' => $targetDate->toDateString(),
                    'exception' => $exception,
                ]);

                $this->error("Échec du rappel du programme #{$program->id} : {$exception->getMessage()}");
            }
        }

        if ($sent === 0) {
            $this->info('Aucun rappel de programme à envoyer.');
        }

        return self::SUCCESS;
    }

    /**
     * Heure fixe configurée (format H:i, validé par les paramètres) réduite
     * à l'heure courante : la fenêtre d'action couvre toute l'heure, ce qui
     * permet les reprises en cas d'échec.
     */
    private function reminderHour(): int
    {
        return (int) substr((string) $this->settings->get('rappel_heure_programme'), 0, 2);
    }
}
