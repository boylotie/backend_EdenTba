<?php

namespace App\Modules\Streaming\Support;

use RuntimeException;

/**
 * Client de source Icecast (protocole ICY, HTTP PUT — MOD-11-P1 §4.1).
 *
 * Connexion persistante : les en-têtes PUT sont envoyés une fois, puis l'audio
 * est écrit en continu jusqu'à close(). Le flux n'est jamais stocké
 * durablement par Laravel.
 */
final class IcecastSourceClient
{
    /** @var resource|null */
    private $stream;

    private bool $handshakeSent = false;

    private string $contentType = 'audio/webm';

    private ?string $lastError = null;

    /**
     * @param  resource|null  $stream  flux pré-ouvert (tests) ; null = socket réel
     */
    public function __construct(
        private readonly string $url,
        private readonly string $password,
        $stream = null,
        private readonly float $connectTimeout = 10.0,
    ) {
        $this->stream = $stream;
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream);
    }

    /**
     * Ouvre la connexion source et envoie les en-têtes PUT (idempotent).
     *
     * @throws RuntimeException si l'URL est invalide ou la connexion impossible
     */
    public function connect(): void
    {
        if ($this->handshakeSent && $this->isConnected()) {
            return;
        }

        $parts = parse_url($this->url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException("URL source Icecast invalide : {$this->url}");
        }

        $scheme = strtolower($parts['scheme']);
        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $mount = $parts['path'] ?? '/live';

        if (! $this->isConnected()) {
            $this->close();

            $stream = stream_socket_client(
                ($scheme === 'https' ? 'tls://' : 'tcp://').$host.':'.$port,
                $errorCode,
                $errorMessage,
                $this->connectTimeout,
            );

            if ($stream === false) {
                $this->lastError = "Connexion source impossible : {$errorMessage}";

                throw new RuntimeException($this->lastError);
            }

            $this->stream = $stream;
        }

        $this->writeHeaders($host, $port, $mount);
        $this->handshakeSent = true;
    }

    /**
     * Écrit l'audio vers la source. Ferme la connexion en cas d'échec.
     */
    public function write(string $bytes): bool
    {
        if (! $this->isConnected()) {
            $this->lastError = 'Connexion source absente.';

            return false;
        }

        $written = fwrite($this->stream, $bytes);

        if ($written === false || $written === 0) {
            $this->lastError = 'Écriture source impossible (source coupée).';
            $this->close();

            return false;
        }

        return true;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
        $this->handshakeSent = false;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function writeHeaders(string $host, int $port, string $mount): void
    {
        $headers = [
            "PUT {$mount} HTTP/1.0",
            "Host: {$host}:{$port}",
            'User-Agent: EdenTBA-Relay/1.0',
            "Content-Type: {$this->contentType}",
            'Authorization: Basic '.base64_encode("source:{$this->password}"),
            'Connection: close',
            '',
            '',
        ];

        $this->write(implode("\r\n", $headers));
    }
}
