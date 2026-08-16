<?php

namespace App\Modules\Content\Support;

use App\Modules\Content\Models\Content;
use App\Modules\Content\Storage\AudioStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streaming HTTP sécurisé des fichiers audio (US-022).
 *
 * S'appuie sur le support natif `Range` de Symfony (BinaryFileResponse) :
 * réponses 206/416, `Accept-Ranges`, aucun chemin de stockage exposé. La
 * politique « publié uniquement » (A2) sera câblée avec les statuts (MOD-06) ;
 * à ce stade tout contenu enregistré est streamable.
 */
final class AudioStream
{
    private const CACHE_TTL = 300;

    public function __construct(private readonly AudioStorage $storage) {}

    public function serve(Content $content): BinaryFileResponse
    {
        $path = $this->storage->path($content->file_path);

        if (! is_file($path)) {
            throw new NotFoundHttpException('Le fichier audio est introuvable.');
        }

        $filename = basename((string) str_replace('\\', '/', $content->original_filename));
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'audio';

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $content->mime_type);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $filename,
            $fallback,
        ));
        $response->setPublic();
        $response->setMaxAge(self::CACHE_TTL);

        return $response;
    }
}
