<?php declare(strict_types=1);

namespace Ecommerly\Connector\Connection;

use Symfony\Component\HttpFoundation\Request;

/**
 * The gate every Ecommerly endpoint sits behind (PRD §6.3).
 *
 * Check order is deliberate. The nonce is consumed last, after the
 * signature has already proved the caller holds the shared secret —
 * consuming it earlier would let anyone who can reach the URL burn
 * nonces belonging to a legitimate connection, turning an
 * unauthenticated request into a denial of service against the real
 * integration.
 */
class SignedRequestVerifier
{
    public const HEADER_CONNECTION_ID = 'Ecommerly-Connection-Id';

    public const HEADER_TIMESTAMP = 'Ecommerly-Timestamp';

    public const HEADER_NONCE = 'Ecommerly-Nonce';

    public const HEADER_SIGNATURE = 'Ecommerly-Signature';

    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly SignatureValidator $validator,
        private readonly NonceStore $nonces
    ) {
    }

    public function verify(Request $request): VerificationResult
    {
        if (! $this->config->isEnabled()) {
            return VerificationResult::rejected('connection_disabled', 'This connection is not accepting requests.');
        }

        $connectionId = $this->config->connectionId();
        $secret = $connectionId === null ? null : $this->config->secret();

        if ($connectionId === null || $secret === null) {
            return VerificationResult::rejected('connection_disabled', 'This connection is not accepting requests.');
        }

        $providedConnectionId = (string) $request->headers->get(self::HEADER_CONNECTION_ID, '');
        $timestamp = (string) $request->headers->get(self::HEADER_TIMESTAMP, '');
        $nonce = (string) $request->headers->get(self::HEADER_NONCE, '');
        $signature = (string) $request->headers->get(self::HEADER_SIGNATURE, '');

        if ($providedConnectionId === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            return VerificationResult::rejected('invalid_signature', 'The request signature could not be verified.');
        }

        if (! hash_equals($connectionId, $providedConnectionId)) {
            return VerificationResult::rejected('unknown_connection', 'The request signature could not be verified.');
        }

        if (! ctype_digit($timestamp) || ! $this->validator->isTimestampFresh((int) $timestamp)) {
            return VerificationResult::rejected('expired_timestamp', 'The request timestamp is outside the accepted window.');
        }

        if (! $this->signatureMatches($request, $connectionId, (int) $timestamp, $nonce, $signature, $secret)) {
            return VerificationResult::rejected('invalid_signature', 'The request signature could not be verified.');
        }

        if ($this->nonces->isReplay($connectionId, $nonce)) {
            return VerificationResult::rejected('replayed_nonce', 'This request has already been processed.');
        }

        return VerificationResult::valid();
    }

    /**
     * getPathInfo(), not getRequestUri(): the path Ecommerly signs is
     * the one it appends to the store's base URL, with no query
     * string and no front-controller prefix. A store installed under
     * a subdirectory must therefore be registered in Ecommerly with
     * that subdirectory in its base URL, or the two sides will
     * canonicalise different paths and every signature will fail.
     */
    private function signatureMatches(
        Request $request,
        string $connectionId,
        int $timestamp,
        string $nonce,
        string $signature,
        string $secret
    ): bool {
        $digest = $this->validator->digestBody($request->getContent());

        foreach (array_filter([$secret, $this->config->previousSecret()]) as $candidate) {
            $expected = $this->validator->computeSignature(
                $request->getMethod(),
                $request->getPathInfo(),
                $digest,
                $timestamp,
                $nonce,
                $connectionId,
                $candidate
            );

            if ($this->validator->matches($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
