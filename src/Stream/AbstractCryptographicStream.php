<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\Stream;

use Closure;
use Ilnur428\Psr7Decorators\Exception\InvalidStreamOperationException;
use Psr\Http\Message\StreamInterface;
use Throwable;

abstract class AbstractCryptographicStream implements StreamInterface
{
    private ?StreamInterface $stream;

    private ?Closure $update = null;

    private ?Closure $finish = null;

    private string $consumed = '';

    private string $buffer = '';

    private int $position = 0;

    private bool $finished = false;

    public function __construct(
        StreamInterface $stream,
        callable $update,
        callable $finish,
        private readonly int $sourceReadSize = 8192,
    ) {
        if ($sourceReadSize < 1) {
            throw new InvalidStreamOperationException('The source read size must be greater than zero.');
        }

        $this->stream = $stream;
        $this->update = Closure::fromCallable($update);
        $this->finish = Closure::fromCallable($finish);
    }

    public function __toString(): string
    {
        try {
            if ($this->stream === null) {
                return '';
            }

            try {
                $this->rewind();

                return $this->getContents();
            } catch (Throwable) {
            }

            while (! $this->eof()) {
                $chunk = $this->read($this->sourceReadSize);

                if ($chunk === '') {
                    break;
                }
            }

            return $this->consumed;
        } catch (Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->stream === null) {
            return;
        }

        $this->stream->close();
        $this->releaseState();
    }

    public function detach()
    {
        if ($this->stream === null) {
            return null;
        }

        $detached = $this->stream->detach();
        $this->releaseState();

        return $detached;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        $this->assertAttached();

        return $this->position;
    }

    public function eof(): bool
    {
        $this->assertAttached();

        return $this->finished && $this->buffer === '';
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new InvalidStreamOperationException('Cryptographic streams are not seekable.');
    }

    public function rewind(): void
    {
        throw new InvalidStreamOperationException('Cryptographic streams cannot be rewound.');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new InvalidStreamOperationException('Cryptographic streams are read-only decorators.');
    }

    public function isReadable(): bool
    {
        return $this->stream !== null && $this->stream->isReadable();
    }

    public function read(int $length): string
    {
        if ($length < 0) {
            throw new InvalidStreamOperationException('The length to read must be greater than or equal to zero.');
        }

        if ($length === 0) {
            return '';
        }

        $this->assertReadable();
        $this->fillBuffer($length);

        if ($this->buffer === '') {
            return '';
        }

        $result = substr($this->buffer, 0, $length);
        $this->buffer = (string) substr($this->buffer, strlen($result));
        $this->consumed .= $result;
        $this->position += strlen($result);

        return $result;
    }

    public function getContents(): string
    {
        $this->assertReadable();

        $contents = '';

        while (! $this->eof()) {
            $chunk = $this->read($this->sourceReadSize);

            if ($chunk === '') {
                break;
            }

            $contents .= $chunk;
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->stream === null) {
            return $key === null ? [] : null;
        }

        return $this->stream->getMetadata($key);
    }

    private function fillBuffer(int $requiredBytes): void
    {
        while (strlen($this->buffer) < $requiredBytes && ! $this->finished) {
            $chunk = $this->innerStream()->read($this->sourceReadSize);

            if ($chunk !== '') {
                $this->ensureCallbacksAvailable();
                $this->buffer .= ($this->update)($chunk);
            }

            if ($this->innerStream()->eof()) {
                $this->finishTransformation();
                break;
            }

            if ($chunk === '') {
                break;
            }
        }
    }

    private function finishTransformation(): void
    {
        if ($this->finished) {
            return;
        }

        $this->finished = true;
        $this->ensureCallbacksAvailable();
        $this->buffer .= ($this->finish)();
    }

    private function releaseState(): void
    {
        $this->stream = null;
        $this->update = null;
        $this->finish = null;
        $this->consumed = '';
        $this->buffer = '';
        $this->position = 0;
        $this->finished = true;
    }

    private function assertAttached(): void
    {
        if ($this->stream === null) {
            throw new InvalidStreamOperationException('The underlying stream has been detached.');
        }
    }

    private function assertReadable(): void
    {
        $this->assertAttached();

        if (! $this->stream->isReadable()) {
            throw new InvalidStreamOperationException('The underlying stream is not readable.');
        }
    }

    private function innerStream(): StreamInterface
    {
        $this->assertAttached();

        return $this->stream;
    }

    private function ensureCallbacksAvailable(): void
    {
        if ($this->update === null || $this->finish === null) {
            throw new InvalidStreamOperationException('The stream transformer is no longer available.');
        }
    }
}