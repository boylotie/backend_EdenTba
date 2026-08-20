<?php

namespace App\Modules\Streaming\Console;

use App\Modules\Streaming\Services\LiveRelayService;
use Illuminate\Console\Command;

/**
 * Relais navigateur → Icecast (MOD-11, diffusion navigateur) : relaie les
 * chunks de la capture micro vers la source Icecast tant qu'un direct est
 * actif et que la capture micro tourne.
 *
 * Commande longue (boucle infinie). `--once` effectue une seule passe
 * (utile pour les tests et l'intégration par cron).
 *
 * La logique est déléguée à LiveRelayService pour rester testable sans
 * exécuter le noyau console.
 */
final class LiveRelayCommand extends Command
{
    protected $signature = 'live:relay
        {--once : Effectue une seule passe puis se termine}
        {--interval=0.5 : Délai d\'attente entre deux passes, en secondes}';

    protected $description = 'Relaye la capture micro navigateur vers la source Icecast (MOD-11).';

    public function __construct(private readonly LiveRelayService $relay)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $interval = (float) $this->option('interval');

        $this->info('Relais micro → Icecast démarré. Ctrl+C pour arrêter.');

        $live = app(\App\Modules\Streaming\Services\LiveService::class);
        $buffer = app(\App\Modules\Streaming\Support\LiveChunkBuffer::class);

        $current = $live->current();
        $isLive = $current !== null && $current->isLive();
        $micActive = $buffer->isMicActive();
        $chunks = $buffer->hasChunks();

        $this->info("État initial — Live: " . ($isLive ? 'oui' : 'non') . " | Micro: " . ($micActive ? 'actif' : 'inactif') . " | Chunks: " . ($chunks ? 'oui' : 'non'));

        do {
            try {
                $this->relay->processOnce();
            } catch (\Throwable $e) {
                $this->error('Erreur relais : ' . $e->getMessage());
            }

            if ($this->option('once')) {
                break;
            }

            usleep((int) ($interval * 1_000_000));
        } while (true);

        return self::SUCCESS;
    }
}
