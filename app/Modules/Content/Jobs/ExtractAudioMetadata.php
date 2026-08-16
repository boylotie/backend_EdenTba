<?php

namespace App\Modules\Content\Jobs;

use App\Modules\Content\Models\Content;
use App\Modules\Content\Storage\AudioStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Traitements lourds post-upload (US-021) : extraction asynchrone de la durée.
 *
 * v1 : la durée est extraite via ffprobe lorsqu'il est disponible ; sinon elle
 * reste nulle et peut être fournie manuellement (MOD-05-P2). Aucun blocage de
 * la réponse d'upload (job en file « audio »).
 */
class ExtractAudioMetadata implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Content $content) {}

    public function handle(AudioStorage $storage): void
    {
        if ($this->content->duration_seconds !== null) {
            return;
        }

        $path = $storage->path($this->content->file_path);

        if (! is_file($path)) {
            return;
        }

        try {
            $process = new Process([
                (string) config('content.ffprobe_binary'),
                '-v',
                'error',
                '-show_entries',
                'format=duration',
                '-of',
                'default=noprint_wrappers=1:nokey=1',
                $path,
            ]);

            $process->run();

            if (! $process->isSuccessful()) {
                return;
            }

            $duration = (float) trim((string) $process->getOutput());

            if ($duration > 0) {
                $this->content->forceFill(['duration_seconds' => (int) round($duration)])->save();
            }
        } catch (Throwable) {
            // ffprobe absent/indisponible : la durée sera fournie manuellement.
        }
    }
}
