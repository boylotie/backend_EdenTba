<?php

namespace App\Modules\Notifications\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP du service Expo Push (D-03). Aucun package externe : les
 * clients communautaires sont obsolètes ou inactifs, et l'API v2 Expo se
 * résume à un POST JSON sur `https://exp.host/--/api/v2/push/send`.
 *
 * Une réponse HTTP non-200 (fournisseur indisponible, lot invalide) lève une
 * exception afin de déclencher le retry du job (scénario A2).
 */
final class ExpoPushClient
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>> résultats par message (champ `data` d'Expo)
     */
    public function send(array $messages): array
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->asJson()
            ->acceptJson()
            ->post((string) config('services.expo.endpoint'), $messages);

        return $this->parse($response);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(Response $response): array
    {
        $response->throwUnlessStatus(200);

        $data = $response->json('data');

        return is_array($data) ? array_values($data) : [];
    }
}
