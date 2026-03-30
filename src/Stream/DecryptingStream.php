<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\Stream;

use Psr\Http\Message\StreamInterface;
use Ilnur428\Psr7Decorators\WhatsApp\WhatsAppMediaCipher;

final class DecryptingStream extends AbstractCryptographicStream
{
    public function __construct(
        StreamInterface $stream,
        WhatsAppMediaCipher $algorithm,
        int $sourceReadSize = 8192,
    ) {
        $transformer = $algorithm->createDecryptingTransformer();

        parent::__construct($stream, $transformer->update(...), $transformer->finish(...), $sourceReadSize);
    }
}