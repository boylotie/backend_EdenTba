<?php

namespace App\Modules\Content\Storage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Couche d'abstraction de stockage des visuels associés aux contenus (US-023).
 *
 * v1 : disque local privé (`storage/app/content-images`). Les fichiers ne sont
 * jamais servis en accès brut ; seule la route publique d'image y accède.
 */
final class ContentImageStorage
{
    private const MIME_BY_EXTENSION = [
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * Formats de visuel autorisés.
     *
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return ['jpeg', 'jpg', 'png', 'webp'];
    }

    public static function mimeFor(string $extension): string
    {
        return self::MIME_BY_EXTENSION[strtolower($extension)] ?? 'application/octet-stream';
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('content_images');
    }

    /**
     * Stocke un visuel dans un sous-dossier privé et retourne son chemin relatif.
     */
    public function store(UploadedFile $file): string
    {
        $path = $this->disk()->putFile('images', $file);

        if ($path === false) {
            throw new RuntimeException("Impossible de stocker le visuel sur le disque 'content_images'.");
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
