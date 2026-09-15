<?php declare(strict_types=1);

namespace Ecommerly\Connector\Connection;

final class PairingResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $connectionId,
        public readonly ?string $message
    ) {
    }

    public static function paired(string $connectionId): self
    {
        return new self(true, $connectionId, null);
    }

    public static function failed(string $message): self
    {
        return new self(false, null, $message);
    }
}
