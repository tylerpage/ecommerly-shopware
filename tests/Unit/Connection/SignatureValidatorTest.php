<?php declare(strict_types=1);

namespace Ecommerly\Connector\Tests\Unit\Connection;

use Ecommerly\Connector\Connection\SignatureValidator;
use PHPUnit\Framework\TestCase;

class SignatureValidatorTest extends TestCase
{
    /**
     * The whole point of this file.
     *
     * These constants are not derived here — they are the same values
     * Ecommerly's own tests/Unit/Services/Capabilities/Connectors/
     * RequestSignerTest.php pins, which were in turn confirmed against
     * a live Magento module. Pinning the same third-party constant in
     * both repositories is what makes the two implementations
     * genuinely cross-checked; re-deriving the formula here would only
     * prove this file agrees with itself, which is exactly the failure
     * mode a protocol break looks like from inside one repository.
     *
     * If this test fails, do not adjust the expected value. One of the
     * two sides has drifted, and every request between them is already
     * being rejected.
     */
    public function testSignatureMatchesTheCrossRepoReferenceVector(): void
    {
        $validator = new SignatureValidator;
        $bodyDigest = $validator->digestBody('{}');

        self::assertSame(
            '44136fa355b3678a1146ad16f7e8649e94fb4fc21fe77e8310c060f61caaff8a',
            $bodyDigest
        );

        self::assertSame(
            'eaaf3fa16d3c1435ccbfbd13093d979c225aa91eda27cc13d2a06cb43c86e6b6',
            $validator->computeSignature(
                'POST',
                '/ecommerly/capability/execute',
                $bodyDigest,
                1700000000,
                'fixed-nonce-for-test',
                'conn_test',
                'test-secret'
            )
        );
    }

    public function testMethodCasingDoesNotChangeTheSignature(): void
    {
        $validator = new SignatureValidator;
        $digest = $validator->digestBody('{}');

        self::assertSame(
            $validator->computeSignature('post', '/x', $digest, 1700000000, 'n', 'c', 's'),
            $validator->computeSignature('POST', '/x', $digest, 1700000000, 'n', 'c', 's')
        );
    }

    /**
     * Every component has to be bound into the signature, or a forger
     * gets a free field to vary. A body digest outside the signature
     * would be the worst of them: the request would authenticate while
     * its contents were swapped.
     */
    public function testChangingAnySingleComponentChangesTheSignature(): void
    {
        $validator = new SignatureValidator;
        $digest = $validator->digestBody('{}');
        $base = $validator->computeSignature('POST', '/x', $digest, 1700000000, 'n', 'c', 's');

        $variants = [
            $validator->computeSignature('GET', '/x', $digest, 1700000000, 'n', 'c', 's'),
            $validator->computeSignature('POST', '/y', $digest, 1700000000, 'n', 'c', 's'),
            $validator->computeSignature('POST', '/x', $validator->digestBody('{"a":1}'), 1700000000, 'n', 'c', 's'),
            $validator->computeSignature('POST', '/x', $digest, 1700000001, 'n', 'c', 's'),
            $validator->computeSignature('POST', '/x', $digest, 1700000000, 'm', 'c', 's'),
            $validator->computeSignature('POST', '/x', $digest, 1700000000, 'n', 'd', 's'),
            $validator->computeSignature('POST', '/x', $digest, 1700000000, 'n', 'c', 't'),
        ];

        foreach ($variants as $index => $variant) {
            self::assertNotSame($base, $variant, "Component {$index} is not bound into the signature.");
        }
    }

    public function testTimestampsOutsideTheToleranceAreStale(): void
    {
        $validator = new SignatureValidator;
        $now = 1700000000;
        $tolerance = SignatureValidator::TIMESTAMP_TOLERANCE_SECONDS;

        self::assertTrue($validator->isTimestampFresh($now, $now));
        self::assertTrue($validator->isTimestampFresh($now - $tolerance, $now));
        self::assertFalse($validator->isTimestampFresh($now - $tolerance - 1, $now));

        // Clock skew runs both ways: a store whose clock is behind
        // Ecommerly's would otherwise reject every request as
        // "from the future".
        self::assertTrue($validator->isTimestampFresh($now + $tolerance, $now));
        self::assertFalse($validator->isTimestampFresh($now + $tolerance + 1, $now));
    }
}
