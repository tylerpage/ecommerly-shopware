<?php declare(strict_types=1);

namespace Ecommerly\Connector\Connection;

/**
 * The inbound half of Ecommerly's signed-request protocol.
 *
 * computeSignature() must stay byte-for-byte identical to Ecommerly's
 * own App\Services\Capabilities\Connectors\RequestSigner::sign() and
 * to the Magento module's validator of the same name. Drift between
 * any two of them is a protocol break against live merchant stores,
 * not a local bug — so SignatureValidatorTest pins the same hardcoded
 * reference vector Ecommerly's RequestSignerTest pins, rather than
 * re-deriving the formula and agreeing with itself.
 */
class SignatureValidator
{
    /**
     * Seconds a request stays acceptable either side of now. The
     * nonce store's TTL must cover this whole window, or a replay
     * becomes possible in the gap between a nonce expiring and its
     * timestamp going stale.
     */
    public const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function computeSignature(
        string $method,
        string $path,
        string $bodyDigest,
        int $timestamp,
        string $nonce,
        string $connectionId,
        string $secret
    ): string {
        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $bodyDigest,
            (string) $timestamp,
            $nonce,
            $connectionId,
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    public function digestBody(string $rawBody): string
    {
        return hash('sha256', $rawBody);
    }

    /**
     * hash_equals, not ===: signature comparison must not leak how
     * much of a forged signature was correct via timing.
     */
    public function matches(string $expected, string $provided): bool
    {
        return hash_equals($expected, $provided);
    }

    public function isTimestampFresh(int $timestamp, ?int $now = null): bool
    {
        return abs(($now ?? time()) - $timestamp) <= self::TIMESTAMP_TOLERANCE_SECONDS;
    }
}
