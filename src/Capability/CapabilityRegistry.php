<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability;

use Shopware\Core\Framework\Context;

/**
 * Routes a capability name to the handler that answers it.
 *
 * Handlers are collected through the `ecommerly.capability_handler`
 * service tag, so adding one is a service definition rather than an
 * edit here. An unknown or ungranted capability is reported
 * `unavailable` rather than `failed`: from Ecommerly's side "this
 * store cannot answer that" is a fact about the store, not a fault.
 */
class CapabilityRegistry
{
    /** @var array<string, CapabilityHandler> */
    private array $handlers = [];

    /**
     * @param iterable<CapabilityHandler> $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[$handler->capability()] = $handler;
        }
    }

    /**
     * @return list<string>
     */
    public function supported(): array
    {
        $supported = array_keys($this->handlers);
        sort($supported);

        return $supported;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param list<string>         $grantedScopes an empty list means
     *                                            the connection was
     *                                            paired without scope
     *                                            negotiation, so
     *                                            nothing is narrowed
     */
    public function handle(string $capability, array $arguments, Context $context, array $grantedScopes = []): Result
    {
        if (! isset($this->handlers[$capability])) {
            return Result::unavailable(
                'capability_not_supported',
                sprintf('This store does not support [%s].', $capability)
            );
        }

        if ($grantedScopes !== [] && ! in_array($capability, $grantedScopes, true)) {
            return Result::unavailable(
                'capability_not_granted',
                sprintf('This connection was not granted [%s].', $capability)
            );
        }

        return $this->handlers[$capability]->handle($arguments, $context);
    }
}
