<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability\Handler;

use Ecommerly\Connector\Capability\CapabilityHandler;
use Ecommerly\Connector\Capability\EditionDetector;
use Ecommerly\Connector\Capability\Result;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * The scope vocabulary every other capability is expressed in, which
 * is why it is built first: without knowing a store's sales channels
 * and languages, no later question can be asked about the right one.
 *
 * Shopware's sales channel is the nearest equivalent of Magento's
 * website/store-view pair, and this is the payload that lets
 * Ecommerly learn that mapping from the store rather than assume it.
 */
class GetStoreContextHandler implements CapabilityHandler
{
    private const MAX_ENTRIES = 200;

    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $currencyRepository,
        private readonly EntityRepository $customerGroupRepository,
        private readonly EditionDetector $edition,
        private readonly string $pluginVersion
    ) {
    }

    public function capability(): string
    {
        return 'get_store_context';
    }

    public function handle(array $arguments, Context $context): Result
    {
        return Result::success([
            'platform' => 'shopware',
            'edition' => $this->edition->edition(),
            'platform_version' => $this->edition->shopwareVersion(),
            'module_version' => $this->pluginVersion,
            'sales_channels' => $this->salesChannels($context),
            'languages' => $this->languages($context),
            'currencies' => $this->currencies($context),
            'customer_groups' => $this->customerGroups($context),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesChannels(Context $context): array
    {
        $entries = [];

        foreach ($this->salesChannelRepository->search($this->boundedCriteria(), $context) as $salesChannel) {
            $entries[] = [
                'id' => $salesChannel->getId(),
                'name' => $salesChannel->getName(),
                'active' => $salesChannel->getActive(),
                'type_id' => $salesChannel->getTypeId(),
            ];
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function languages(Context $context): array
    {
        $criteria = $this->boundedCriteria();
        $criteria->addAssociation('locale');

        $entries = [];

        foreach ($this->languageRepository->search($criteria, $context) as $language) {
            $entries[] = [
                'id' => $language->getId(),
                'name' => $language->getName(),
                'locale' => $language->getLocale()?->getCode(),
            ];
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function currencies(Context $context): array
    {
        $entries = [];

        foreach ($this->currencyRepository->search($this->boundedCriteria(), $context) as $currency) {
            $entries[] = [
                'id' => $currency->getId(),
                'iso_code' => $currency->getIsoCode(),
                'name' => $currency->getName(),
            ];
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customerGroups(Context $context): array
    {
        $entries = [];

        foreach ($this->customerGroupRepository->search($this->boundedCriteria(), $context) as $group) {
            $entries[] = [
                'id' => $group->getId(),
                'name' => $group->getName(),
            ];
        }

        return $entries;
    }

    /**
     * Every read is bounded. A store with thousands of sales channels
     * is unusual but an unbounded result would be an unbounded
     * response body and an unbounded prompt downstream.
     */
    private function boundedCriteria(): Criteria
    {
        return (new Criteria)->setLimit(self::MAX_ENTRIES);
    }
}
