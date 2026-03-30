<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\WhatsApp\Transformer;

use Ilnur428\Psr7Decorators\Exception\CryptoStreamException;
use Ilnur428\Psr7Decorators\WhatsApp\ExpandedMediaKey;
use Ilnur428\Psr7Decorators\WhatsApp\StreamingSidecar;

final class WhatsAppEncryptingTransformer
{
    private string $pending = '';

    private string $currentIv;

    private mixed $hmacContext;

    private bool $finished = false;

    public function __construct(
        private readonly ExpandedMediaKey $expandedKey,
        private readonly ?StreamingSidecar $sidecar = null,
    ) {
        $this->currentIv = $expandedKey->iv();
        $this->hmacContext = hash_init('sha256', HASH_HMAC, $expandedKey->macKey());
        hash_update($this->hmacContext, $expandedKey->iv());
    }

    public function update(string $chunk): string
    {
        $this->assertNotFinished();
        $this->pending .= $chunk;

        $processableLength = strlen($this->pending) - (strlen($this->pending) % 16);

        if ($processableLength === 0) {
            return '';
        }

        $plaintext = substr($this->pending, 0, $processableLength);
        $this->pending = (string) substr($this->pending, $processableLength);

        return $this->encryptBlocks($plaintext);
    }

    public function finish(): string
    {
        $this->assertNotFinished();
        $this->finished = true;

        $padded = $this->applyPkcs7Padding($this->pending);
        $ciphertext = $this->encryptBlocks($padded);
        $mac = substr(hash_final($this->hmacContext, true), 0, 10);

        if ($this->sidecar !== null) {
            $this->sidecar->finalize($mac);
        }

        $this->pending = '';

        return $ciphertext . $mac;
    }

    private function encryptBlocks(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $this->expandedKey->cipherKey(),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $this->currentIv,
        );

        if (! is_string($ciphertext)) {
            throw new CryptoStreamException('Failed to encrypt media stream.');
        }

        $this->currentIv = substr($ciphertext, -16);
        hash_update($this->hmacContext, $ciphertext);

        if ($this->sidecar !== null) {
            $this->sidecar->appendCiphertext($ciphertext);
        }

        return $ciphertext;
    }

    private function applyPkcs7Padding(string $plaintext): string
    {
        $paddingLength = 16 - (strlen($plaintext) % 16);

        if ($paddingLength === 0) {
            $paddingLength = 16;
        }

        return $plaintext . str_repeat(chr($paddingLength), $paddingLength);
    }

    private function assertNotFinished(): void
    {
        if ($this->finished) {
            throw new CryptoStreamException('The encrypting transformer has already been finalized.');
        }
    }
}