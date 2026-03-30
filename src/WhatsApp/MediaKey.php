<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\WhatsApp;

use Ilnur428\Psr7Decorators\Exception\InvalidMediaKeyException;

final class MediaKey
{
    private const SIZE = 32;

    private function __construct(private readonly string $bytes)
    {
    }

    public static function generate(): self
    {
        return new self(random_bytes(self::SIZE));
    }

    public static function fromBytes(string $bytes): self
    {
        if (strlen($bytes) !== self::SIZE) {
            throw new InvalidMediaKeyException(sprintf('Media key must be exactly %d bytes.', self::SIZE));
        }

        return new self($bytes);
    }

    public function bytes(): string
    {
        return $this->bytes;
    }
}