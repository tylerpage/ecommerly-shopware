<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability;

/**
 * Opaque pagination cursors.
 *
 * Offset-encoded rather than keyset, which is the honest trade for a
 * read-only assistant: results can shift under a cursor if the
 * catalogue changes mid-investigation. Keyset paging would fix that
 * and is worth doing if paging ever drives something a merchant acts
 * on; for bounded question-answering it buys little and constrains
 * sort order.
 *
 * Opaque so callers cannot hand-craft one to page past the ceiling —
 * a malformed or negative cursor resolves to the first page rather
 * than erroring, since a bad cursor is not worth failing a question
 * over.
 */
final class Cursor
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    public static function offsetFrom(?string $cursor): int
    {
        if ($cursor === null || $cursor === '') {
            return 0;
        }

        $decoded = base64_decode($cursor, true);

        if ($decoded === false || ! preg_match('/^offset:(\d+)$/', $decoded, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    public static function encode(int $offset): string
    {
        return base64_encode('offset:'.$offset);
    }

    public static function limitFrom(mixed $limit): int
    {
        if (! is_int($limit) || $limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * Null when the page just returned is the last one, so a caller
     * never has to guess whether to ask again.
     */
    public static function next(int $offset, int $limit, int $total): ?string
    {
        $nextOffset = $offset + $limit;

        return $nextOffset < $total ? self::encode($nextOffset) : null;
    }
}
