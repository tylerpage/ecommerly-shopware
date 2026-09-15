<?php declare(strict_types=1);

namespace Ecommerly\Connector\Connection;

use RuntimeException;

/**
 * Encrypts the connection secret before it goes into system_config.
 *
 * Magento has encrypted configuration built in; Shopware's
 * SystemConfigService does not, and a shared HMAC secret sitting in
 * plaintext in a database row is the kind of thing that ends up in a
 * database dump, a support export, or a staging clone. AES-256-GCM
 * with a key derived from the installation's own APP_SECRET keeps it
 * out of those, and the AEAD tag means a tampered row fails to
 * decrypt rather than silently yielding garbage.
 *
 * This is not protection against someone who already has both the
 * database and the application's environment — nothing storable on
 * the same host could be. It narrows the exposure to that case.
 */
class SecretCipher
{
    private const CIPHER = 'aes-256-gcm';

    private const IV_BYTES = 12;

    private const TAG_BYTES = 16;

    public function __construct(private readonly string $appSecret)
    {
        if ($this->appSecret === '') {
            throw new RuntimeException('APP_SECRET is empty; the Ecommerly connection secret cannot be encrypted.');
        }
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt the Ecommerly connection secret.');
        }

        return base64_encode($iv.$tag.$ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) <= self::IV_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('The stored Ecommerly connection secret is malformed.');
        }

        $plaintext = openssl_decrypt(
            substr($raw, self::IV_BYTES + self::TAG_BYTES),
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::IV_BYTES),
            substr($raw, self::IV_BYTES, self::TAG_BYTES)
        );

        if ($plaintext === false) {
            throw new RuntimeException('The stored Ecommerly connection secret could not be decrypted.');
        }

        return $plaintext;
    }

    private function key(): string
    {
        return hash('sha256', 'ecommerly-connector:'.$this->appSecret, true);
    }
}
