<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\WhatsApp;

final class StreamingSidecar
{
    private const CHUNK_SIZE = 65536;
    private const MAC_SIZE = 10;

    private string $segmentBody = '';

    private string $segmentPrefix;

    private string $sidecar = '';

    private bool $finalized = false;

    public function __construct(private readonly string $macKey, string $initialIv)
    {
        if (strlen($macKey) !== 32) {
            throw new \InvalidArgumentException('WhatsApp sidecar MAC key must be exactly 32 bytes.');
        }

        if (strlen($initialIv) !== 16) {
            throw new \InvalidArgumentException('WhatsApp sidecar IV must be exactly 16 bytes.');
        }

        $this->segmentPrefix = $initialIv;
    }

    public static function forMediaKey(MediaKey $mediaKey, WhatsAppMediaType $mediaType): self
    {
        $expandedKey = ExpandedMediaKey::derive($mediaKey, $mediaType);

        return new self($expandedKey->macKey(), $expandedKey->iv());
    }

    public function appendCiphertext(string $ciphertext): void
    {
        $this->assertNotFinalized();

        while ($ciphertext !== '') {
            $requiredBytes = self::CHUNK_SIZE - strlen($this->segmentBody);
            $takeLength = min($requiredBytes, strlen($ciphertext));

            $this->segmentBody .= substr($ciphertext, 0, $takeLength);
            $ciphertext = (string) substr($ciphertext, $takeLength);

            if (strlen($this->segmentBody) === self::CHUNK_SIZE) {
                $this->sidecar .= $this->truncateMac($this->segmentPrefix . $this->segmentBody);
                $this->segmentPrefix = substr($this->segmentBody, -16);
                $this->segmentBody = '';
            }
        }
    }

    public function finalize(string $mediaMac = ''): string
    {
        if (! $this->finalized) {
            if ($mediaMac !== '' && strlen($mediaMac) !== self::MAC_SIZE) {
                throw new \InvalidArgumentException('WhatsApp media MAC must be exactly 10 bytes.');
            }

            if ($this->segmentBody !== '' || $mediaMac !== '') {
                $this->sidecar .= $this->truncateMac($this->segmentPrefix . $this->segmentBody . $mediaMac);
            }

            $this->segmentBody = '';
            $this->finalized = true;
        }

        return $this->sidecar;
    }

    public function sidecar(): string
    {
        return $this->sidecar;
    }

    private function truncateMac(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, $this->macKey, true), 0, self::MAC_SIZE);
    }

    private function assertNotFinalized(): void
    {
        if ($this->finalized) {
            throw new \LogicException('Sidecar generation has already been finalized.');
        }
    }
}