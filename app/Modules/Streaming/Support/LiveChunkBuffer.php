<?php

namespace App\Modules\Streaming\Support;

use RuntimeException;

/**
 * Mémoire tampon de la capture micro navigateur (MOD-11, diffusion navigateur) :
 * fichiers de chunks audio ordonnés sous storage/app/live.
 *
 * Le navigateur envoie des chunks (MediaRecorder) ; le worker `live:relay`
 * les relit dans l'ordre et les pousse vers la source Icecast. La publication
 * est active tant qu'une session de direct est en cours ET que le marqueur
 * « micro actif » est présent.
 */
final class LiveChunkBuffer
{
    public const MAX_CHUNK_BYTES = 1_048_576;

    public const MAX_BUFFER_BYTES = 314_572_800;

    private const CHUNK_PREFIX = 'chunk-';

    private const CHUNK_SUFFIX = '.data';

    private const MIC_MARKER = 'mic.active';

    private const MIME_FILE = 'mic.mime';

    private const RELAY_LOCK = 'relay.lock';

    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? storage_path('app/live');
    }

    /**
     * Ajoute un chunk audio et retourne son numéro de séquence.
     *
     * @throws RuntimeException si le chunk dépasse la taille max ou si le
     *                          tampon total est plein
     */
    public function append(string $bytes): int
    {
        $length = strlen($bytes);

        if ($length > self::MAX_CHUNK_BYTES) {
            throw new RuntimeException('Chunk audio trop volumineux.');
        }

        if ($this->totalBytes() + $length > self::MAX_BUFFER_BYTES) {
            throw new RuntimeException('Tampon audio plein : démarrez le relais live:relay.');
        }

        $this->ensureDirectory();

        $sequence = $this->nextSequence();
        $path = $this->chunkPath($sequence);
        $temporary = $path.'.tmp';

        if (file_put_contents($temporary, $bytes) === false || ! rename($temporary, $path)) {
            throw new RuntimeException("Écriture du chunk {$sequence} impossible.");
        }

        return $sequence;
    }

    /**
     * Chemins des chunks non encore relayés, du plus ancien au plus récent.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        $files = glob($this->directory.DIRECTORY_SEPARATOR.self::CHUNK_PREFIX.'*'.self::CHUNK_SUFFIX) ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    public function hasChunks(): bool
    {
        return $this->pending() !== [];
    }

    public function totalBytes(): int
    {
        $total = 0;

        foreach ($this->pending() as $path) {
            $size = filesize($path);

            if ($size !== false) {
                $total += $size;
            }
        }

        return $total;
    }

    public function activateMic(string $mimeType): void
    {
        $this->ensureDirectory();

        file_put_contents($this->directory.DIRECTORY_SEPARATOR.self::MIME_FILE, $mimeType);
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.self::MIC_MARKER, (string) time());
    }

    public function isMicActive(): bool
    {
        return is_file($this->directory.DIRECTORY_SEPARATOR.self::MIC_MARKER);
    }

    public function micContentType(): ?string
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.self::MIME_FILE;

        if (! is_file($path)) {
            return null;
        }

        $value = (string) file_get_contents($path);

        return $value === '' ? null : $value;
    }

    /**
     * Désactive la capture micro et vide le tampon.
     */
    public function deactivateMic(): void
    {
        $this->purge();
    }

    /**
     * Vide le tampon et retire les marqueurs (fin de direct / capture).
     */
    public function purge(): void
    {
        foreach ($this->pending() as $path) {
            @unlink($path);
        }

        @unlink($this->directory.DIRECTORY_SEPARATOR.self::MIC_MARKER);
        @unlink($this->directory.DIRECTORY_SEPARATOR.self::MIME_FILE);
        @unlink($this->directory.DIRECTORY_SEPARATOR.self::RELAY_LOCK);
    }

    /**
     * Verrou d'exclusion mutuelle du relais (un seul worker forward à la fois).
     *
     * @return resource
     *
     * @throws RuntimeException si le verrou ne peut pas être ouvert
     */
    public function relayLock()
    {
        $this->ensureDirectory();

        $handle = fopen($this->directory.DIRECTORY_SEPARATOR.self::RELAY_LOCK, 'c');

        if ($handle === false) {
            throw new RuntimeException('Ouverture du verrou du relais impossible.');
        }

        return $handle;
    }

    private function nextSequence(): int
    {
        $sequence = 0;

        foreach ($this->pending() as $path) {
            $sequence = max($sequence, (int) substr(basename($path), strlen(self::CHUNK_PREFIX), 10));
        }

        return $sequence + 1;
    }

    private function chunkPath(int $sequence): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.self::CHUNK_PREFIX.str_pad((string) $sequence, 10, '0', STR_PAD_LEFT).self::CHUNK_SUFFIX;
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("Création du répertoire {$this->directory} impossible.");
        }
    }
}
