<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\WhatsApp;

use Ilnur428\Psr7Decorators\Exception\InvalidMediaKeyException;

final class ExpandedMediaKey
{
    private const SIZE = 112;

    private function __construct(
        private readonly string $iv,
        private readonly string $cipherKey,
        private readonly string $macKey,
        private readonly string $refKey,
    ) {
    }

    public static function derive(MediaKey $mediaKey, WhatsAppMediaType $mediaType): self
    {
        $expanded = hash_hkdf('sha256', $mediaKey->bytes(), self::SIZE, $mediaType->applicationInfo(), '');

        if ($expanded === false || strlen($expanded) !== self::SIZE) {
            throw new InvalidMediaKeyException('Failed to derive WhatsApp media key material.');
        }

        return new self(
            substr($expanded, 0, 16),
            substr($expanded, 16, 32),
            substr($expanded, 48, 32),
            substr($expanded, 80),
        );
    }

    public function iv(): string
    {
        return $this->iv;
    }

    public function cipherKey(): string
    {
        return $this->cipherKey;
    }

    public function macKey(): string
    {
        return $this->macKey;
    }

    public function refKey(): string
    {
        return $this->refKey;
    }
}