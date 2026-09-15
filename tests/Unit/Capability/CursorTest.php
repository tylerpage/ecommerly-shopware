<?php declare(strict_types=1);

namespace Ecommerly\Connector\Tests\Unit\Capability;

use Ecommerly\Connector\Capability\Cursor;
use PHPUnit\Framework\TestCase;

class CursorTest extends TestCase
{
    public function testACursorRoundTripsThroughItsOpaqueEncoding(): void
    {
        self::assertSame(50, Cursor::offsetFrom(Cursor::encode(50)));
    }

    /**
     * A bad cursor is not worth failing a merchant's question over,
     * and it must not become a way to reach an arbitrary offset.
     */
    public function testAMalformedCursorFallsBackToTheFirstPage(): void
    {
        self::assertSame(0, Cursor::offsetFrom(null));
        self::assertSame(0, Cursor::offsetFrom(''));
        self::assertSame(0, Cursor::offsetFrom('not-base64!'));
        self::assertSame(0, Cursor::offsetFrom(base64_encode('offset:-10')));
        self::assertSame(0, Cursor::offsetFrom(base64_encode('DROP TABLE product')));
    }

    /**
     * The ceiling is server-side and absolute: the caller is an LLM
     * whose requested limit is an argument like any other, and an
     * unbounded page would be an unbounded prompt.
     */
    public function testTheLimitIsClampedToTheServerSideCeiling(): void
    {
        self::assertSame(Cursor::DEFAULT_LIMIT, Cursor::limitFrom(null));
        self::assertSame(Cursor::DEFAULT_LIMIT, Cursor::limitFrom(0));
        self::assertSame(Cursor::DEFAULT_LIMIT, Cursor::limitFrom(-5));
        self::assertSame(Cursor::DEFAULT_LIMIT, Cursor::limitFrom('25'));
        self::assertSame(10, Cursor::limitFrom(10));
        self::assertSame(Cursor::MAX_LIMIT, Cursor::limitFrom(1000));
    }

    public function testTheLastPageReportsNoNextCursor(): void
    {
        self::assertNull(Cursor::next(0, 25, 25));
        self::assertNull(Cursor::next(75, 25, 100));
        self::assertSame(Cursor::encode(25), Cursor::next(0, 25, 100));
    }
}
