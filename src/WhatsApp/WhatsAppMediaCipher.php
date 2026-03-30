<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\WhatsApp;

use Ilnur428\Psr7Decorators\WhatsApp\Transformer\WhatsAppDecryptingTransformer;
use Ilnur428\Psr7Decorators\WhatsApp\Transformer\WhatsAppEncryptingTransformer;

final class WhatsAppMediaCipher
{
    private readonly ExpandedMediaKey $expandedKey;

    public function __construct(
        private readonly WhatsAppMediaType $mediaType,
        private readonly MediaKey $mediaKey,
        private readonly ?StreamingSidecar $sidecar = null,
    ) {
        $this->expandedKey = ExpandedMediaKey::derive($mediaKey, $mediaType);
    }

    public static function withGeneratedKey(
        WhatsAppMediaType $mediaType,
        ?StreamingSidecar $sidecar = null,
    ): self {
        return new self($mediaType, MediaKey::generate(), $sidecar);
    }

    public function createEncryptingTransformer(): WhatsAppEncryptingTransformer
    {
        return new WhatsAppEncryptingTransformer($this->expandedKey, $this->sidecar);
    }

    public function createDecryptingTransformer(): WhatsAppDecryptingTransformer
    {
        return new WhatsAppDecryptingTransformer($this->expandedKey);
    }

    public function mediaType(): WhatsAppMediaType
    {
        return $this->mediaType;
    }

    public function mediaKey(): MediaKey
    {
        return $this->mediaKey;
    }

    public function expandedKey(): ExpandedMediaKey
    {
        return $this->expandedKey;
    }
}