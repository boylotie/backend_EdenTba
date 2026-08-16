<?php

namespace App\Modules\Content\Console;

use App\Modules\Content\Models\Content;
use App\Modules\Content\Services\ContentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publication programmée (US-026) : bascule automatique en « published » des
 * contenus au statut « scheduled » dont la date de publication est atteinte.
 *
 * Planifié toutes les minutes (routes/console.php). Chaque contenu est traité
 * indépendamment : un échec est journalisé sans interrompre les suivants
 * (repli). La transition réutilise ContentService::transition() (matrice,
 * événement, audit, invalidation du cache public).
 */
final class PublishScheduledContents extends Command
{
    protected $signature = 'contents:publish-due';

    protected $description = 'Publie les contenus programmés dont la date est atteinte (US-026).';

    public function __construct(private readonly ContentService $contents)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $due = Content::query()
            ->where('status', Content::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $content) {
            try {
                $this->contents->transition($content, Content::STATUS_PUBLISHED);

                $this->info("Contenu publié : {$content->title} (#{$content->id}).");
            } catch (Throwable $exception) {
                Log::error('Publication programmée en échec', [
                    'content_id' => $content->id,
                    'title' => $content->title,
                    'exception' => $exception,
                ]);

                $this->error("Échec de publication du contenu #{$content->id} : {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
