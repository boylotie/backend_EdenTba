<?php

namespace App\Modules\Streaming\Support;

use App\Modules\Content\Storage\ContentImageStorage;
use App\Modules\Streaming\Models\LiveSession;
use App\Modules\Streaming\Storage\LiveImageStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service HTTP du visuel associé à une session live (MOD-11-P2). Le fichier
 * reste privé ; seule cette route y accède. Aucun visuel → 404 (pas de fuite
 * d'existence).
 */
final class LiveImageStream
{
    private const CACHE_TTL = 300;

    public function __construct(private readonly LiveImageStorage $images) {}

    public function serve(LiveSession $session): BinaryFileResponse
    {
        if ($session->image_path === null) {
            throw new NotFoundHttpException('Aucun visuel associé à ce live.');
        }

        $path = $this->images->path($session->image_path);

        if (! is_file($path)) {
            throw new NotFoundHttpException('Le visuel est introuvable.');
        }

        $mime = ContentImageStorage::mimeFor((string) pathinfo($session->image_path, PATHINFO_EXTENSION));
        $filename = basename((string) str_replace('\\', '/', $session->image_path));
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
