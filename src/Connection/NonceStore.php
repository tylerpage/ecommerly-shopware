<?php declare(strict_types=1);

namespace Ecommerly\Connector\Connection;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Single-use enforcement for request nonces, backed by Shopware's
 * object cache.
 *
 * The TTL is twice the signature's timestamp tolerance, not equal to
 * it: a timestamp is accepted up to the tolerance either side of now,
 * so the span in which one request stays replayable is 2x the
 * tolerance. A TTL equal to the tolerance would let a nonce expire
 * while its timestamp was still fresh, which is precisely the window
 * a replay needs.
 */
class NonceStore
{
    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    /**
     * Records the nonce and reports whether it had already been used.
     * Deliberately one operation: a separate has()/remember() pair
     * would leave a race between two concurrent replays.
     */
    public function isReplay(string $connectionId, string $nonce): bool
    {
        $item = $this->cache->getItem($this->key($connectionId, $nonce));

        if ($item->isHit()) {
            return true;
        }

        $item->set(true);
        $item->expiresAfter(2 * SignatureValidator::TIMESTAMP_TOLERANCE_SECONDS);
        $this->cache->save($item);

        return false;
    }

    /**
     * Hashed because PSR-6 reserves characters that a connection id
     * or nonce could in principle contain, and a rejected cache key
     * would throw rather than fail closed.
     */
    private function key(string $connectionId, string $nonce): string
    {
        return 'ecommerly_nonce_'.hash('sha256', $connectionId.':'.$nonce);
    }
}
