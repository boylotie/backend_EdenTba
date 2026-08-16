<?php

namespace App\Modules\Content\Support;

use App\Modules\Content\Models\Content;
use App\Modules\Content\Storage\ContentImageStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service HTTP du visuel associé à un contenu (US-023). Le fichier reste privé ;
 * seule cette route y accède. Aucun visuel → 404 (pas de fuite d'existence).
 */
final class ImageStream
{
    private const CACHE_TTL = 300;

    public function __construct(private readonly ContentImageStorage $images) {}

    public function serve(Content $content): BinaryFileResponse
    {
        if ($content->image_path === null) {
            throw new NotFoundHttpException('Aucun visuel associé à ce contenu.');
        }

        $path = $this->images->path($content->image_path);

        if (! is_file($path)) {
            throw new NotFoundHttpException('Le visuel est introuvable.');
        }

        $mime = ContentImageStorage::mimeFor((string) pathinfo($content->image_path, PATHINFO_EXTENSION));
        $filename = basename((string) str_replace('\\', '/', $content->image_path));
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'image';

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $mime);
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
