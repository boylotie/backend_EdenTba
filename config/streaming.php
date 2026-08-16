<?php

return [

    /*
    | Clé HMAC-SHA256 utilisée pour signer les URL d'écoute du live
    | (MOD-11-P1, D-14). Secret d'environnement, jamais exposé : elle est
    | partagée avec le reverse proxy qui valide le jeton avant de servir le flux.
    */
    'signing_key' => env('STREAMING_SIGNING_KEY'),

    /*
    | Durée de validité d'une URL d'écoute signée, en secondes (défaut 10 min).
    | Le mobile renouvelle son jeton avant expiration (MOD-11-P1, axe 7).
    */
    'url_ttl_seconds' => (int) env('STREAMING_URL_TTL_SECONDS', 600),

];
