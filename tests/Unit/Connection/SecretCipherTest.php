<?php declare(strict_types=1);

namespace Ecommerly\Connector\Tests\Unit\Connection;

use Ecommerly\Connector\Connection\SecretCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecretCipherTest extends TestCase
{
    public function testARoundTripReturnsTheOriginalSecret(): void
    {
        $cipher = new SecretCipher('an-app-secret');

        self::assertSame('s3cr3t-value', $cipher->decrypt($cipher->encrypt('s3cr3t-value')));
    }

    /**
     * A fresh IV per call, so two encryptions of the same secret do
     * not produce the same row — otherwise the ciphertext itself
     * would reveal that a rotation changed nothing.
     */
    public function testTheSameSecretEncryptsDifferentlyEachTime(): void
    {
        $cipher = new SecretCipher('an-app-secret');

        self::assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same'));
    }

    /**
     * The AEAD tag is what makes this fail loudly. Without it a
     * tampered row could decrypt to arbitrary bytes and the store
     * would sign requests with a secret an attacker chose.
     */
    public function testATamperedCiphertextIsRejectedRatherThanDecrypted(): void
    {
        $cipher = new SecretCipher('an-app-secret');
        $encrypted = $cipher->encrypt('s3cr3t-value');

        $raw = base64_decode($encrypted, true);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] === 'a' ? 'b' : 'a';

        $this->expectException(RuntimeException::class);
        $cipher->decrypt(base64_encode($raw));
    }

    public function testAnotherInstallationsKeyCannotDecryptThisOne(): void
    {
        $encrypted = (new SecretCipher('installation-a'))->encrypt('s3cr3t-value');

        $this->expectException(RuntimeException::class);
        (new SecretCipher('installation-b'))->decrypt($encrypted);
    }

    public function testAnEmptyAppSecretIsRefusedRatherThanSilentlyWeakening(): void
    {
        $this->expectException(RuntimeException::class);
        new SecretCipher('');
    }
}
