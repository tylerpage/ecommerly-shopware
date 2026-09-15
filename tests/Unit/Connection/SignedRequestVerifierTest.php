<?php declare(strict_types=1);

namespace Ecommerly\Connector\Tests\Unit\Connection;

use Ecommerly\Connector\Connection\ConnectionConfig;
use Ecommerly\Connector\Connection\NonceStore;
use Ecommerly\Connector\Connection\SignatureValidator;
use Ecommerly\Connector\Connection\SignedRequestVerifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

/**
 * The replay and forgery cases, written alongside the verifier rather
 * than after it (IMPLEMENTATION_PLAN.md Stage 3.2).
 *
 * The nonce store is real, backed by an in-memory adapter, because a
 * mocked one would make the replay test assert only that a mock was
 * called — and single-use enforcement is the one property here that
 * is about state rather than arithmetic.
 */
class SignedRequestVerifierTest extends TestCase
{
    private const CONNECTION_ID = 'conn_test';

    private const SECRET = 'test-secret';

    private const PATH = '/ecommerly/capability/execute';

    private ConnectionConfig&MockObject $config;

    private SignedRequestVerifier $verifier;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConnectionConfig::class);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('connectionId')->willReturn(self::CONNECTION_ID);
        $this->config->method('secret')->willReturn(self::SECRET);
        $this->config->method('previousSecret')->willReturn(null);

        $this->verifier = new SignedRequestVerifier(
            $this->config,
            new SignatureValidator,
            new NonceStore(new ArrayAdapter)
        );
    }

    public function testAnAuthenticRequestIsAccepted(): void
    {
        self::assertTrue($this->verifier->verify($this->signedRequest())->ok);
    }

    public function testAReusedNonceIsRejectedTheSecondTime(): void
    {
        $request = $this->signedRequest(nonce: 'nonce-used-twice');

        self::assertTrue($this->verifier->verify($request)->ok);

        $result = $this->verifier->verify($this->signedRequest(nonce: 'nonce-used-twice'));

        self::assertFalse($result->ok);
        self::assertSame('replayed_nonce', $result->errorCode);
    }

    public function testAnExpiredTimestampIsRejected(): void
    {
        $stale = time() - SignatureValidator::TIMESTAMP_TOLERANCE_SECONDS - 60;

        $result = $this->verifier->verify($this->signedRequest(timestamp: $stale));

        self::assertFalse($result->ok);
        self::assertSame('expired_timestamp', $result->errorCode);
    }

    /**
     * The signature covers a digest of the body, so swapping the body
     * after signing must invalidate it. If this ever passes, an
     * attacker who can observe one legitimate request can substitute
     * any capability arguments they like.
     */
    public function testATamperedBodyIsRejected(): void
    {
        $request = $this->signedRequest(body: '{"capability":"get_product"}');
        $tampered = Request::create(
            self::PATH,
            'POST',
            [],
            [],
            [],
            $this->serverHeaders($request),
            '{"capability":"search_orders"}'
        );

        $result = $this->verifier->verify($tampered);

        self::assertFalse($result->ok);
        self::assertSame('invalid_signature', $result->errorCode);
    }

    public function testAWrongConnectionIdIsRejected(): void
    {
        $result = $this->verifier->verify($this->signedRequest(connectionId: 'conn_someone_else'));

        self::assertFalse($result->ok);
        self::assertSame('unknown_connection', $result->errorCode);
    }

    public function testASignatureMadeWithTheWrongSecretIsRejected(): void
    {
        $result = $this->verifier->verify($this->signedRequest(secret: 'not-the-secret'));

        self::assertFalse($result->ok);
        self::assertSame('invalid_signature', $result->errorCode);
    }

    public function testARevokedConnectionRejectsEverything(): void
    {
        $config = $this->createMock(ConnectionConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('connectionId')->willReturn(null);

        $verifier = new SignedRequestVerifier($config, new SignatureValidator, new NonceStore(new ArrayAdapter));
        $result = $verifier->verify($this->signedRequest());

        self::assertFalse($result->ok);
        self::assertSame('connection_disabled', $result->errorCode);
    }

    public function testADisabledConnectionRejectsEvenAnAuthenticRequest(): void
    {
        $config = $this->createMock(ConnectionConfig::class);
        $config->method('isEnabled')->willReturn(false);

        $verifier = new SignedRequestVerifier($config, new SignatureValidator, new NonceStore(new ArrayAdapter));
        $result = $verifier->verify($this->signedRequest());

        self::assertFalse($result->ok);
        self::assertSame('connection_disabled', $result->errorCode);
    }

    public function testMissingSignatureHeadersAreRejectedWithoutRevealingWhichOne(): void
    {
        $result = $this->verifier->verify(Request::create(self::PATH, 'POST', [], [], [], [], '{}'));

        self::assertFalse($result->ok);
        self::assertSame('invalid_signature', $result->errorCode);
    }

    /**
     * Re-pairing rotates the secret and keeps the outgoing one valid
     * briefly, so a request already in flight when the rotation lands
     * still verifies instead of failing for no reason the operator
     * can see.
     */
    public function testARequestSignedWithTheOutgoingSecretIsAcceptedDuringTheRotationOverlap(): void
    {
        $config = $this->createMock(ConnectionConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('connectionId')->willReturn(self::CONNECTION_ID);
        $config->method('secret')->willReturn('the-new-secret');
        $config->method('previousSecret')->willReturn(self::SECRET);

        $verifier = new SignedRequestVerifier($config, new SignatureValidator, new NonceStore(new ArrayAdapter));

        self::assertTrue($verifier->verify($this->signedRequest())->ok);
    }

    private function signedRequest(
        ?int $timestamp = null,
        string $nonce = 'nonce-1',
        string $connectionId = self::CONNECTION_ID,
        string $secret = self::SECRET,
        string $body = '{}'
    ): Request {
        $timestamp ??= time();
        $validator = new SignatureValidator;

        $signature = $validator->computeSignature(
            'POST',
            self::PATH,
            $validator->digestBody($body),
            $timestamp,
            $nonce,
            $connectionId,
            $secret
        );

        return Request::create(self::PATH, 'POST', [], [], [], [
            'HTTP_ECOMMERLY_CONNECTION_ID' => $connectionId,
            'HTTP_ECOMMERLY_TIMESTAMP' => (string) $timestamp,
            'HTTP_ECOMMERLY_NONCE' => $nonce,
            'HTTP_ECOMMERLY_SIGNATURE' => $signature,
        ], $body);
    }

    /**
     * @return array<string, string>
     */
    private function serverHeaders(Request $request): array
    {
        return [
            'HTTP_ECOMMERLY_CONNECTION_ID' => (string) $request->headers->get(SignedRequestVerifier::HEADER_CONNECTION_ID),
            'HTTP_ECOMMERLY_TIMESTAMP' => (string) $request->headers->get(SignedRequestVerifier::HEADER_TIMESTAMP),
            'HTTP_ECOMMERLY_NONCE' => (string) $request->headers->get(SignedRequestVerifier::HEADER_NONCE),
            'HTTP_ECOMMERLY_SIGNATURE' => (string) $request->headers->get(SignedRequestVerifier::HEADER_SIGNATURE),
        ];
    }
}
