<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability;

use Shopware\Core\Framework\Context;

/**
 * One capability, fulfilled against this store.
 *
 * Implementations depend on DAL repositories and nothing HTTP-shaped:
 * no Request, no Response, no controller. That is what keeps them
 * reusable if this plugin ever gains a Shopware App counterpart,
 * where the same questions arrive over the Admin API instead of a
 * signed POST — the transport changes, these do not.
 *
 * Arguments have already been validated against the capability's JSON
 * Schema by Ecommerly's CapabilityDispatcher before they get here.
 * That is not a reason to trust them blindly — this store is the last
 * line — but it does mean a handler need not re-implement the schema.
 */
interface CapabilityHandler
{
    /**
     * The CapabilityId string this handler answers, e.g.
     * "get_store_context".
     */
    public function capability(): string;

    /**
     * @param array<string, mixed> $arguments
     */
    public function handle(array $arguments, Context $context): Result;
}
