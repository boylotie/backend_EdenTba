<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\Events\ContentCreated;
use App\Modules\Content\Events\ContentDeleted;
use App\Modules\Content\Events\ContentStatusChanged;
use App\Modules\Content\Events\ContentUpdated;
use App\Modules\Content\Exceptions\InvalidContentTransitionException;
use App\Modules\Content\Jobs\ExtractAudioMetadata;
use App\Modules\Content\Models\Content;
use App\Modules\Content\Storage\AudioStorage;
use App\Modules\Content\Storage\ContentImageStorage;
use App\Shared\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Throwable;

final class ContentService
{
    /**
     * Clé de version du cache public des contenus : incrémentée à chaque
     * écriture (création, mise à jour, suppression, transition de statut)
     * pour invalider les listes/détails publics mis en cache.
     */
    public const PUBLIC_CACHE_VERSION_KEY = 'public.contents.version';

    public function __construct(
        private readonly AudioStorage $storage,
        private readonly ContentImageStorage $images,
    ) {}

    /**
     * Upload sécurisé (US-021) : stockage privé, référence en base, traitement
     * lourd asynchrone et journalisation. En cas d'échec d'enregistrement, le
     * fichier stocké est supprimé (aucune donnée partielle, A3).
     *
     * @param  array{title?: string|null, description?: string|null}  $data
     */
    public function upload(UploadedFile $file, array $data = []): Content
    {
        $path = $this->storage->store($file);
        $content = null;

        try {
            $content = Content::create([
                'title' => $data['title'] ?? $this->titleFromFilename($file->getClientOriginalName()),
                'description' => $data['description'] ?? null,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => AudioStorage::mimeFor((string) $file->getClientOriginalExtension()),
                'size_bytes' => $file->getSize(),
            ]);

            Queue::push(new ExtractAudioMetadata($content));

            AuditLogger::log(
                'contents.upload',
                [
                    'title' => $content->title,
                    'mime_type' => $content->mime_type,
                    'size_bytes' => $content->size_bytes,
                ],
                entityType: 'content',
                entityId: $content->id,
            );
        } catch (Throwable $exception) {
            $content?->delete();
            $this->storage->delete($path);

            throw $exception;
        }

        return $content;
    }

    /**
     * Crée un contenu avec fichier audio, métadonnées et rattachement (US-023).
     * Traitement lourd asynchrone, journalisation ; en cas d'échec, fichiers
     * (audio + visuel) et enregistrement sont supprimés (aucune donnée
     * partielle, A3).
     *
     * @param  array{title: string, description?: string|null, duration_seconds?: int|null, speaker?: string|null, speaker_id?: int|null, year_id?: int|null, month_id?: int|null, week_id?: int|null, special_activity_id?: int|null, day_of_week?: int|null, scheduled_at?: string|null, sort_order?: int|null}  $data
     */
    public function create(UploadedFile $file, array $data, ?UploadedFile $image = null): Content
    {
        $filePath = $this->storage->store($file);
        $imagePath = $image !== null ? $this->images->store($image) : null;
        $content = null;

        try {
            $content = Content::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'speaker' => $data['speaker'] ?? null,
                'speaker_id' => $data['speaker_id'] ?? null,
                'year_id' => $data['year_id'] ?? null,
                'month_id' => $data['month_id'] ?? null,
                'week_id' => $data['week_id'] ?? null,
                'special_activity_id' => $data['special_activity_id'] ?? null,
                'day_of_week' => $data['day_of_week'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'file_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => AudioStorage::mimeFor((string) $file->getClientOriginalExtension()),
                'size_bytes' => $file->getSize(),
                'image_path' => $imagePath,
            ]);

            Queue::push(new ExtractAudioMetadata($content));
        } catch (Throwable $exception) {
            $content?->delete();

            $this->storage->delete($filePath);

            if ($imagePath !== null) {
                $this->images->delete($imagePath);
            }

            throw $exception;
        }

        AuditLogger::log(
            'contents.create',
            [
                'title' => $content->title,
                'year_id' => $content->year_id,
                'month_id' => $content->month_id,
                'week_id' => $content->week_id,
                'special_activity_id' => $content->special_activity_id,
            ],
            entityType: 'content',
            entityId: $content->id,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);

        event(new ContentCreated($content));

        return $content;
    }

    /**
     * Met à jour un contenu ; un nouveau fichier audio ou visuel remplace
     * l'existant (les anciens fichiers sont supprimés du stockage).
     *
     * @param  array{title: string, description?: string|null, duration_seconds?: int|null, speaker?: string|null, speaker_id?: int|null, year_id?: int|null, month_id?: int|null, week_id?: int|null, special_activity_id?: int|null, day_of_week?: int|null, scheduled_at?: string|null, sort_order?: int|null}  $data
     */
    public function update(Content $content, array $data, ?UploadedFile $file = null, ?UploadedFile $image = null): Content
    {
        $previousFile = null;
        $previousImage = null;

        if ($file !== null) {
            $previousFile = $content->file_path;
            $content->file_path = $this->storage->store($file);
            $content->original_filename = $file->getClientOriginalName();
            $content->mime_type = AudioStorage::mimeFor((string) $file->getClientOriginalExtension());
            $content->size_bytes = $file->getSize();
        }

        if ($image !== null) {
            $previousImage = $content->image_path;
            $content->image_path = $this->images->store($image);
        }

        try {
            $content->fill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'speaker' => $data['speaker'] ?? null,
                'speaker_id' => $data['speaker_id'] ?? null,
                'year_id' => $data['year_id'] ?? null,
                'month_id' => $data['month_id'] ?? null,
                'week_id' => $data['week_id'] ?? null,
                'special_activity_id' => $data['special_activity_id'] ?? null,
                'day_of_week' => $data['day_of_week'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ])->save();
        } catch (Throwable $exception) {
            if ($file !== null) {
                $this->storage->delete($content->file_path);
            }

            if ($image !== null && $content->image_path !== null) {
                $this->images->delete($content->image_path);
            }

            throw $exception;
        }

        if ($previousFile !== null && $previousFile !== $content->file_path) {
            $this->storage->delete($previousFile);
        }

        if ($previousImage !== null) {
            $this->images->delete($previousImage);
        }

        AuditLogger::log(
            'contents.update',
            [
                'title' => $content->title,
                'year_id' => $content->year_id,
                'month_id' => $content->month_id,
                'week_id' => $content->week_id,
                'special_activity_id' => $content->special_activity_id,
            ],
            entityType: 'content',
            entityId: $content->id,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);

        event(new ContentUpdated($content));

        return $content;
    }

    /**
     * Supprime un contenu : enregistrement, fichier audio et visuel (US-023).
     */
    public function delete(Content $content): void
    {
        $contentId = $content->id;
        $title = $content->title;

        AuditLogger::log(
            'contents.delete',
            ['title' => $title],
            entityType: 'content',
            entityId: $contentId,
        );

        $content->delete();

        $this->storage->delete($content->file_path);

        if ($content->image_path !== null) {
            $this->images->delete($content->image_path);
        }

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);

        event(new ContentDeleted($contentId, $title));
    }

    /**
     * Applique une transition de statut (US-025) : matrice validée, événement
     * métier émis, journalisation et invalidation du cache public. Lors d'une
     * programmation (`scheduled`, US-026), la date cible est enregistrée.
     */
    public function transition(Content $content, string $to, ?CarbonInterface $scheduledAt = null): Content
    {
        $from = $content->status;

        if (! $content->isTransitionAllowed($to)) {
            throw new InvalidContentTransitionException(
                "Transition de « {$from} » vers « {$to} » non autorisée.",
            );
        }

        $content->status = $to;

        if ($to === Content::STATUS_SCHEDULED && $scheduledAt !== null) {
            $content->scheduled_at = $scheduledAt;
        }

        $content->save();

        event(new ContentStatusChanged($content, $from, $to));

        AuditLogger::log(
            'contents.status_changed',
            [
                'from' => $from,
                'to' => $to,
                'title' => $content->title,
            ],
            entityType: 'content',
            entityId: $content->id,
        );

        Cache::increment(self::PUBLIC_CACHE_VERSION_KEY);

        return $content;
    }

    private function titleFromFilename(string $filename): string
    {
        $title = pathinfo($filename, PATHINFO_FILENAME);

        return trim($title) === '' ? $filename : $title;
    }
}
