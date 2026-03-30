<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\WhatsApp\Transformer;

use Ilnur428\Psr7Decorators\Exception\AuthenticationFailedException;
use Ilnur428\Psr7Decorators\Exception\CryptoStreamException;
use Ilnur428\Psr7Decorators\WhatsApp\ExpandedMediaKey;

final class WhatsAppDecryptingTransformer
{
    private const MAC_SIZE = 10;

    private string $pending = '';

    private string $currentIv;

    private string $lastPlainBlock = '';

    private mixed $hmacContext;

    private bool $finished = false;

    public function __construct(private readonly ExpandedMediaKey $expandedKey)
    {
        $this->currentIv = $expandedKey->iv();
        $this->hmacContext = hash_init('sha256', HASH_HMAC, $expandedKey->macKey());
        hash_update($this->hmacContext, $expandedKey->iv());
    }

    public function update(string $chunk): string
    {
        $this->assertNotFinished();
        $this->pending .= $chunk;

        $confirmedCiphertextLength = max(0, strlen($this->pending) - self::MAC_SIZE);
        $processableCiphertextLength = max(0, $confirmedCiphertextLength - 16);
        $processableCiphertextLength -= $processableCiphertextLength % 16;

        if ($processableCiphertextLength === 0) {
            return '';
        }

        $ciphertext = substr($this->pending, 0, $processableCiphertextLength);
        $this->pending = (string) substr($this->pending, $processableCiphertextLength);

        return $this->decryptConfirmedCiphertext($ciphertext);
    }

    public function finish(): string
    {
        $this->assertNotFinished();
        $this->finished = true;

        if (strlen($this->pending) < self::MAC_SIZE + 16) {
            throw new CryptoStreamException('Encrypted media payload is incomplete.');
        }

        $mac = substr($this->pending, -self::MAC_SIZE);
        $ciphertext = substr($this->pending, 0, -self::MAC_SIZE);

        if ($ciphertext === '' || strlen($ciphertext) % 16 !== 0) {
            throw new CryptoStreamException('Encrypted media payload has invalid AES-CBC block alignment.');
        }

        $plaintext = $this->decryptConfirmedCiphertext($ciphertext, true);
        $expectedMac = substr(hash_final($this->hmacContext, true), 0, self::MAC_SIZE);

        if (! hash_equals($expectedMac, $mac)) {
            throw new AuthenticationFailedException('Encrypted media MAC validation failed.');
        }

        if ($this->lastPlainBlock === '') {
            throw new CryptoStreamException('Encrypted media payload is missing the final plaintext block.');
        }

        $result = $plaintext . $this->removePkcs7Padding($this->lastPlainBlock);

        $this->pending = '';
        $this->lastPlainBlock = '';

        return $result;
    }

    private function decryptConfirmedCiphertext(string $ciphertext, bool $flush = false): string
    {
        if ($ciphertext === '') {
            return '';
        }

        hash_update($this->hmacContext, $ciphertext);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $this->expandedKey->cipherKey(),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $this->currentIv,
        );

        if (! is_string($plaintext)) {
            throw new CryptoStreamException('Failed to decrypt media stream.');
        }

        $this->currentIv = substr($ciphertext, -16);
        $plaintext = $this->lastPlainBlock . $plaintext;

        if ($flush) {
            $this->lastPlainBlock = (string) substr($plaintext, -16);

            return (string) substr($plaintext, 0, -16);
        }

        if (strlen($plaintext) <= 16) {
            $this->lastPlainBlock = $plaintext;

            return '';
        }

        $this->lastPlainBlock = (string) substr($plaintext, -16);

        return (string) substr($plaintext, 0, -16);
    }

    private function removePkcs7Padding(string $plaintext): string
    {
        $paddingLength = ord(substr($plaintext, -1));

        if ($paddingLength < 1 || $paddingLength > 16) {
            throw new CryptoStreamException('Invalid PKCS#7 padding length.');
        }

        $padding = substr($plaintext, -$paddingLength);

        if ($padding !== str_repeat(chr($paddingLength), $paddingLength)) {
            throw new CryptoStreamException('Invalid PKCS#7 padding bytes.');
        }

        return (string) substr($plaintext, 0, -$paddingLength);
    }

    private function assertNotFinished(): void
    {
        if ($this->finished) {
            throw new CryptoStreamException('The decrypting transformer has already been finalized.');
        }
    }
}