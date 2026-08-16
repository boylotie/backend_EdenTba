<?php

namespace App\Modules\Streaming\Support;

/**
 * Fabrique du client de source Icecast (MOD-11, diffusion navigateur).
 *
 * Sépare la construction du client (URL/password dynamiques issus des
 * paramètres système) de sa résolution par le conteneur, afin de rester
 * testable par binding d'instance.
 */
class RelaySourceConnector
{
    public function make(string $url, string $password): IcecastSourceClient
    {
        return new IcecastSourceClient($url, $password);
    }
}
