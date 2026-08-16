<?php

namespace App\Modules\Streaming\Storage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Couche d'abstraction de stockage du visuel associé à une session live
 * (MOD-11-P2). Réutilise le disque privé des visuels (`content_images`) :
 * le fichier n'est jamais servi en accès brut, seule la route publique
 * d'image y accède.
 */
final class LiveImageStorage
{
    private function disk(): FilesystemAdapter
    {
        return Storage::disk('content_images');
    }

    /**
     * Stocke un visuel dans un sous-dossier dédié au live et retourne son
     * chemin relatif.
     */
    public function store(UploadedFile $file): string
    {
        $path = $this->disk()->putFile('live', $file);

        if ($path === false) {
            throw new RuntimeException("Impossible de stocker le visuel live sur le disque 'content_images'.");
        }

        return $path;
    }

    public function path(string $path): string
    {
        return $this->disk()->path($path);
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($path);
    }
}
