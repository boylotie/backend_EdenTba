<?php

namespace App\Modules\Streaming\Services;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Signature HMAC-SHA256 des URL d'écoute du live (MOD-11-P1, D-14).
 *
 * Le jeton est calculé sur la base publique du flux (`stream_url_base`,
 * paramètre système) et sur une échéance (`expires`), puis validé par le
 * reverse proxy devant Icecast. Laravel ne transporte jamais le flux.
 */
final class StreamUrlSigner
{
    /**
     * URL signée pour écoute.
     *
     * @return array{url: string, expires_at: int}
     *
     * @throws RuntimeException si la clé de signature n'est pas configurée
     */
    public function sign(string $baseUrl, CarbonInterface $expiresAt): array
    {
        $key = config('streaming.signing_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException("La clé 'streaming.signing_key' n'est pas configurée (env STREAMING_SIGNING_KEY).");
        }

        $expires = $expiresAt->getTimestamp();
        $token = hash_hmac('sha256', "{$baseUrl}|{$expires}", $key);

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $url = $baseUrl.$separator.http_build_query(['expires' => $expires, 'token' => $token]);

        return [
            'url' => $url,
            'expires_at' => $expires,
        ];
    }
}
