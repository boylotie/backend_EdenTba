<?php

use App\Modules\Streaming\Support\IcecastSourceClient;
use RuntimeException;

function relayTestStream()
{
    return fopen('php://temp', 'w+');
}

it('n est pas connecté avant connexion', function () {
    $client = new IcecastSourceClient('http://stream.example.org:8000/live', 'secret');

    expect($client->isConnected())->toBeFalse();
});

it('envoie les en-têtes PUT Icecast à la connexion', function () {
    $stream = relayTestStream();
    $client = new IcecastSourceClient('http://stream.example.org:8000/live', 'secret', $stream);

    $client->connect();
    rewind($stream);
    $request = stream_get_contents($stream);

    expect($request)->toContain("PUT /live HTTP/1.0\r\n");
    expect($request)->toContain('Host: stream.example.org:8000');
    expect($request)->toContain('Authorization: Basic '.base64_encode('source:secret'));
    expect($request)->toContain('Content-Type: audio/webm');
    expect($client->isConnected())->toBeTrue();

    $client->close();

    expect($client->isConnected())->toBeFalse();
});

it('applique le type MIME configuré', function () {
    $stream = relayTestStream();
    $client = new IcecastSourceClient('http://stream.example.org:8000/live', 'secret', $stream);
    $client->setContentType('audio/ogg;codecs=opus');

    $client->connect();
    rewind($stream);

    expect(stream_get_contents($stream))->toContain('Content-Type: audio/ogg;codecs=opus');

    $client->close();
});

it('écrit l audio après les en-têtes', function () {
    $stream = relayTestStream();
    $client = new IcecastSourceClient('http://stream.example.org:8000/live', 'secret', $stream);

    $client->connect();
    $client->write('AUDIO');
    rewind($stream);

    expect(stream_get_contents($stream))->toContain("\r\n\r\nAUDIO");

    $client->close();
});

it('refuse une URL source invalide', function () {
    $client = new IcecastSourceClient('www.example.org/live', 'secret');

    $client->connect();
})->throws(RuntimeException::class);
