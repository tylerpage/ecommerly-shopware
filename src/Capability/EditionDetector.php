<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability;

/**
 * Which Shopware edition this store runs, so Ecommerly can degrade
 * honestly instead of guessing.
 *
 * Shopware splits Community and Commercial the way Magento splits
 * Open Source and Adobe Commerce, and capabilities that need a
 * Commercial-only subsystem must report `unavailable` rather than
 * infer an answer from what Community exposes.
 *
 * The plugin deliberately does not require shopware/commercial in
 * composer.json — it has to install and run on Community, which is
 * exactly the case where the degradation matters. Detection is
 * therefore by active bundle rather than by class import.
 *
 * Commercial has shipped under more than one bundle name, so both
 * known names are accepted, and activeBundles() is reported in the
 * manifest: if detection is ever wrong on a real store, the manifest
 * says what the store actually has loaded rather than leaving it to
 * be guessed at from a support ticket.
 */
class EditionDetector
{
    public const COMMUNITY = 'community';

    public const COMMERCIAL = 'commercial';

    private const COMMERCIAL_BUNDLES = ['SwagCommercial', 'Commercial'];

    /**
     * @param array<string, class-string> $bundles %kernel.bundles%
     */
    public function __construct(
        private readonly array $bundles,
        private readonly string $shopwareVersion
    ) {
    }

    public function edition(): string
    {
        return $this->isCommercial() ? self::COMMERCIAL : self::COMMUNITY;
    }

    public function isCommercial(): bool
    {
        foreach (self::COMMERCIAL_BUNDLES as $name) {
            if (isset($this->bundles[$name])) {
                return true;
            }
        }

        return false;
    }

    public function shopwareVersion(): string
    {
        return $this->shopwareVersion;
    }

    /**
     * @return list<string>
     */
    public function activeBundles(): array
    {
        return array_values(array_keys($this->bundles));
    }
}
