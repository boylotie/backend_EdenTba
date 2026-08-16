<?php

namespace App\Modules\Content\Storage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Couche d'abstraction de stockage des fichiers audio (D-05).
 *
 * v1 : disque local privé (`storage/app/audio`). L'accès au fichier brut est
 * interdit ; seule l'API de streaming sert les flux. Le passage à un stockage
 * objet (S3-compatible) se limite à changer la configuration du disque.
 */
final class AudioStorage
{
    private const MIME_BY_EXTENSION = [
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'aac' => 'audio/aac',
    ];

    /**
     * Formats autorisés (D-07).
     *
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return array_keys(self::MIME_BY_EXTENSION);
    }

    public static function mimeFor(string $extension): string
    {
        return self::MIME_BY_EXTENSION[strtolower($extension)] ?? 'application/octet-stream';
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('audio');
    }

    /**
     * Stocke un fichier uploadé dans un sous-dossier privé et retourne son chemin relatif.
     */
    public function store(UploadedFile $file): string
    {
        $path = $this->disk()->putFile('contents', $file);

        if ($path === false) {
            throw new RuntimeException("Impossible de stocker le fichier sur le disque 'audio'.");
        }

        return $path;
    }

    public function path(string $path): string
    {
        return $this->disk()->path($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($path);
    }
}
