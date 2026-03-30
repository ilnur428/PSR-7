# PSR-7 Stream Decorators

The package provides decorators for the `Psr\Http\Message\StreamInterface`, which transparently encrypt and decrypt the stream when reading.
The package includes an implementation of the WhatsApp media file encryption algorithm.
The supported version of PSR-7 in the package is fixed to `psr/http-message:^2.0`.

## Installation

```bash
composer require ilnur428/psr7-decorators
```

## Basic ideas

- `EncryptingStream` reads the source stream and returns the encrypted bytes.
- `Cryptingstream` reads the source stream and returns the decrypted bytes.
- `WhatsAppMediaCipher` implements HKDF, AES-256-CBC, HMAC SHA-256 and MAC verification according to the job specification.
- `StreamingSidecar` allows you to generate WhatsApp sidecar during encryption without additional reads of the source stream.
- Decorators use the ready-made `Psr\Http\Message\StreamInterface` from `psr/http-message`, rather than the native stream interface.
- Decorators are intentionally only readable and do not support `seek()`: this is a safe behavior for streaming state-dependent transformations.

## Usage example

```php
use Ilnur428\Psr7Decorators\Stream\DecryptingStream;
use Ilnur428\Psr7Decorators\Stream\EncryptingStream;
use Ilnur428\Psr7Decorators\WhatsApp\MediaKey;
use Ilnur428\Psr7Decorators\WhatsApp\StreamingSidecar;
use Ilnur428\Psr7Decorators\WhatsApp\WhatsAppMediaCipher;
use Ilnur428\Psr7Decorators\WhatsApp\WhatsAppMediaType;
use Nyholm\Psr7\Stream;

$plainBody = Stream::create('sensitive payload');
$mediaKey = MediaKey::generate();
$sidecar = StreamingSidecar::forMediaKey($mediaKey, WhatsAppMediaType::VIDEO);

$algorithm = new WhatsAppMediaCipher(WhatsAppMediaType::VIDEO, $mediaKey, $sidecar);

$encryptedBody = new EncryptingStream($plainBody, $algorithm);
$ciphertext = $encryptedBody->getContents();
$videoSidecar = $sidecar->finalize();

$decryptedBody = new DecryptingStream(Stream::create($ciphertext), $algorithm);
$plaintext = $decryptedBody->getContents();
```

## WhatsApp API

- `WhatsAppMediaType` describes the context of HKDF: `IMAGE`, `VIDEO`, `AUDIO`, `DOCUMENT`.
- `MediaKey` encapsulates a binary `mediaKey` with a length of 32 bytes.
- `WhatsAppMediaCipher` creates encryption and decryption transformers for stream decorators.
- `StreamingSidecar` collects the sidecar from the ciphertext without re-reading the original stream.

To use an existing key:

```php
$mediaKey = MediaKey::fromBytes(file_get_contents('VIDEO.key'));
$cipher = new WhatsAppMediaCipher(WhatsAppMediaType::VIDEO, $mediaKey);
```

## API Guarantees

- Decorators are compatible with PSR-7 `StreamInterface`.
- The size of the resulting stream is considered unknown and `getSize()` returns `null`.
- After `detach()` or `close()`, the decorator becomes invalid, just like a regular PSR-7 stream.
- Erroneous operations like `write()` or `seek()` ends with a predictable exception.
- Decryption checks HMAC and will result in an exception if the MAC is corrupted.

## Public classes

- `Ilnur428\Psr7Decorators\Stream\EncryptingStream`
- `Ilnur428\Psr7Decorators\Stream\DecryptingStream`
- `Ilnur428\Psr7Decorators\WhatsApp\WhatsAppMediaCipher`
- `Ilnur428\Psr7Decorators\WhatsApp\WhatsAppMediaType`
- `Ilnur428\Psr7Decorators\WhatsApp\MediaKey`
- `Ilnur428\Psr7Decorators\WhatsApp\StreamingSidecar`