<?php declare(strict_types=1);

namespace Ecommerly\Connector\Tests\Unit\Capability;

use Ecommerly\Connector\Capability\ScopeResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

class ScopeResolverTest extends TestCase
{
    public function testNoScopeArgumentMeansNoScopeAndNoError(): void
    {
        $resolved = (new ScopeResolver)->salesChannelId([]);

        self::assertNull($resolved['id']);
        self::assertNull($resolved['error']);
    }

    public function testTheNeutralSalesChannelAliasResolves(): void
    {
        $id = Uuid::randomHex();

        $resolved = (new ScopeResolver)->salesChannelId(['sales_channel_id' => $id]);

        self::assertSame($id, $resolved['id']);
        self::assertNull($resolved['error']);
    }

    /**
     * The shared schemas still type scope as Magento's integer
     * website_id/store_view_id, and Shopware has no integer a sales
     * channel could be named by. Guessing — the default channel, the
     * first one — would answer a question about the wrong storefront
     * and cite it as evidence, so this reports unavailable instead.
     *
     * This test is the evidence for adding neutral aliases to
     * Ecommerly's CapabilityRegistry; when that lands, the Magento
     * keys stay valid and simply stop being the only option.
     */
    public function testAMagentoShapedIntegerScopeIsUnavailableRatherThanGuessedAt(): void
    {
        foreach (['website_id', 'store_view_id'] as $key) {
            $resolved = (new ScopeResolver)->salesChannelId([$key => 1]);

            self::assertNull($resolved['id']);
            self::assertNotNull($resolved['error']);
            self::assertSame('unavailable', $resolved['error']->outcome);
            self::assertSame('scope_identifier_not_applicable', $resolved['error']->errorCode);
        }
    }

    public function testAStringThatIsNotAUuidIsAlsoRefused(): void
    {
        $resolved = (new ScopeResolver)->salesChannelId(['sales_channel_id' => 'main-website']);

        self::assertNull($resolved['id']);
        self::assertSame('scope_identifier_not_applicable', $resolved['error']->errorCode);
    }

    public function testLanguageResolvesThroughItsOwnNeutralAlias(): void
    {
        $id = Uuid::randomHex();

        self::assertSame($id, (new ScopeResolver)->languageId(['language_id' => $id])['id']);
    }
}
