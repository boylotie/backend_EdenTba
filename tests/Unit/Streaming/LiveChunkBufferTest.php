<?php

use App\Modules\Streaming\Support\LiveChunkBuffer;
use RuntimeException;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'live-buffer-'.uniqid();
    $this->buffer = new LiveChunkBuffer($this->directory);
});

afterEach(function (): void {
    $this->buffer->purge();

    if (is_dir($this->directory)) {
        rmdir($this->directory);
    }
});

it('ajoute des chunks ordonnés et numérote les séquences', function () {
    expect($this->buffer->append('A'))->toBe(1);
    expect($this->buffer->append('BB'))->toBe(2);

    expect($this->buffer->totalBytes())->toBe(3);
    expect($this->buffer->hasChunks())->toBeTrue();
});

it('retourne les chunks en attente du plus ancien au plus récent', function () {
    $this->buffer->append('premier');
    $this->buffer->append('second');

    $paths = $this->buffer->pending();

    expect($paths)->toHaveCount(2);
    expect(file_get_contents($paths[0]))->toBe('premier');
    expect(file_get_contents($paths[1]))->toBe('second');
});

it('redémarre la séquence après une purge', function () {
    $this->buffer->append('A');
    $this->buffer->purge();

    expect($this->buffer->append('B'))->toBe(1);
});

it('refuse un chunk trop volumineux', function () {
    $this->buffer->append(str_repeat('x', LiveChunkBuffer::MAX_CHUNK_BYTES + 1));
})->throws(RuntimeException::class);

it('active puis désactive le marqueur de capture micro', function () {
    expect($this->buffer->isMicActive())->toBeFalse();

    $this->buffer->activateMic('audio/webm;codecs=opus');

    expect($this->buffer->isMicActive())->toBeTrue();
    expect($this->buffer->micContentType())->toBe('audio/webm;codecs=opus');

    $this->buffer->deactivateMic();

    expect($this->buffer->isMicActive())->toBeFalse();
    expect($this->buffer->micContentType())->toBeNull();
});

it('vide le tampon et les marqueurs lors d une purge', function () {
    $this->buffer->append('A');
    $this->buffer->activateMic('audio/webm');

    $this->buffer->purge();

    expect($this->buffer->hasChunks())->toBeFalse();
    expect($this->buffer->isMicActive())->toBeFalse();
    expect($this->buffer->micContentType())->toBeNull();
    expect($this->buffer->totalBytes())->toBe(0);
});
