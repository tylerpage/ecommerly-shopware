<?php declare(strict_types=1);

namespace Ecommerly\Connector\Tests\Unit\Capability;

use Ecommerly\Connector\Capability\CapabilityHandler;
use Ecommerly\Connector\Capability\CapabilityRegistry;
use Ecommerly\Connector\Capability\Result;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class CapabilityRegistryTest extends TestCase
{
    public function testItRoutesACapabilityToTheHandlerThatDeclaresIt(): void
    {
        $registry = new CapabilityRegistry([$this->handler('get_product', Result::success(['sku' => 'SW-1']))]);

        $result = $registry->handle('get_product', [], Context::createDefaultContext());

        self::assertSame('success', $result->outcome);
        self::assertSame(['sku' => 'SW-1'], $result->data);
    }

    /**
     * Unavailable, not failed. "This store cannot answer that" is a
     * fact about the store that Ecommerly reports as a limitation; a
     * failure would read as a fault and invite a retry.
     */
    public function testAnUnknownCapabilityIsUnavailableRatherThanFailed(): void
    {
        $registry = new CapabilityRegistry([]);

        $result = $registry->handle('get_cron_health', [], Context::createDefaultContext());

        self::assertSame('unavailable', $result->outcome);
        self::assertSame('capability_not_supported', $result->errorCode);
    }

    public function testACapabilityOutsideTheGrantedScopesIsRefused(): void
    {
        $registry = new CapabilityRegistry([
            $this->handler('search_orders', Result::success(['orders' => []])),
        ]);

        $result = $registry->handle('search_orders', [], Context::createDefaultContext(), ['get_product']);

        self::assertSame('unavailable', $result->outcome);
        self::assertSame('capability_not_granted', $result->errorCode);
    }

    /**
     * Pairing does not negotiate scopes yet, so a connection arrives
     * with an empty list. That has to mean "nothing was narrowed",
     * not "nothing is allowed" — the opposite default would break
     * every paired store the moment this shipped.
     */
    public function testAnEmptyScopeListDoesNotNarrowAnything(): void
    {
        $registry = new CapabilityRegistry([$this->handler('get_product', Result::success([]))]);

        self::assertSame('success', $registry->handle('get_product', [], Context::createDefaultContext(), [])->outcome);
    }

    public function testSupportedCapabilitiesAreReportedForTheManifest(): void
    {
        $registry = new CapabilityRegistry([
            $this->handler('search_products', Result::success([])),
            $this->handler('get_product', Result::success([])),
        ]);

        self::assertSame(['get_product', 'search_products'], $registry->supported());
    }

    private function handler(string $capability, Result $result): CapabilityHandler
    {
        return new class($capability, $result) implements CapabilityHandler
        {
            public function __construct(private readonly string $capability, private readonly Result $result)
            {
            }

            public function capability(): string
            {
                return $this->capability;
            }

            public function handle(array $arguments, Context $context): Result
            {
                return $this->result;
            }
        };
    }
}
