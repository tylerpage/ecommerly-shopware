<?php declare(strict_types=1);

namespace Ecommerly\Connector\Connection;

/**
 * Why a signed request was accepted or rejected.
 *
 * The error codes are a stable part of the protocol — Ecommerly logs
 * and surfaces them — while the messages are deliberately incurious:
 * no secrets, no stack traces, no filesystem paths, no database
 * detail, and never a hint about which check failed beyond the code
 * itself. A caller who cannot produce a valid signature learns
 * nothing from the difference between a wrong connection id and a
 * wrong secret.
 */
final class VerificationResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $errorCode,
        public readonly ?string $message
    ) {
    }

    public static function valid(): self
    {
        return new self(true, null, null);
    }

    public static function rejected(string $errorCode, string $message): self
    {
        return new self(false, $errorCode, $message);
    }
}
