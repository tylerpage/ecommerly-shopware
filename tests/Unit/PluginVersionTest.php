<?php declare(strict_types=1);

namespace Ecommerly\Connector\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The manifest reports the container parameter, while Shopware
 * installs and updates the plugin by the composer version. If the two
 * drift, Ecommerly is told a module version the store is not running
 * — which is precisely the field an operator would trust when
 * diagnosing a protocol mismatch.
 */
class PluginVersionTest extends TestCase
{
    public function testTheServiceParameterMatchesTheComposerVersion(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);
        $services = simplexml_load_string((string) file_get_contents(__DIR__.'/../../src/Resources/config/services.xml'));

        self::assertIsArray($composer);
        self::assertNotFalse($services);

        $parameter = (string) $services->parameters->parameter[0];

        self::assertSame($composer['version'], $parameter);
    }

    public function testThePluginSupportsBothSupportedShopwareLines(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

        self::assertIsArray($composer);
        self::assertSame('~6.6.0 || ~6.7.0', $composer['require']['shopware/core']);

        // Requiring Commercial would make the plugin uninstallable on
        // Community, which is the edition the degradation path exists
        // to serve — see EditionDetector.
        self::assertArrayNotHasKey('shopware/commercial', $composer['require']);
    }
}
