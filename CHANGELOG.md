## [1.0] - 2026-03-30

### Added

- PSR-7 stream decorators for on-read encryption and decryption.
- WhatsApp media encryption and decryption support based on HKDF, AES-256-CBC, and truncated HMAC SHA-256.
- Streaming sidecar generation for streamable WhatsApp media without rereading the source stream.
- Test coverage for PSR-7 stream behavior and provided WhatsApp fixtures.