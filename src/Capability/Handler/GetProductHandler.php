<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability\Handler;

use Ecommerly\Connector\Capability\CapabilityHandler;
use Ecommerly\Connector\Capability\Result;
use Ecommerly\Connector\Capability\ScopeResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * One product, by whichever identifier the caller has.
 *
 * Ecommerly's schema offers three: sku, product_id and url_key. Two
 * of them translate cleanly — sku is Shopware's productNumber, and
 * url_key resolves through the SEO url table. product_id does not:
 * the shared schema types it as a positive integer, because Magento
 * numbers its entities, whereas every Shopware id is a UUID. There is
 * no product an integer could name here, so this reports unavailable
 * with a code saying so rather than inventing a lookup.
 *
 * That is not a Shopware limitation, it is the shared schema still
 * speaking Magento's dialect, and it is the clearest argument for
 * adding neutral aliases to those schemas on the Ecommerly side.
 */
class GetProductHandler implements CapabilityHandler
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $seoUrlRepository,
        private readonly ScopeResolver $scopes
    ) {
    }

    public function capability(): string
    {
        return 'get_product';
    }

    public function handle(array $arguments, Context $context): Result
    {
        $scope = $this->scopes->salesChannelId($arguments);

        if ($scope['error'] !== null) {
            return $scope['error'];
        }

        $criteria = $this->criteriaFor($arguments, $context);

        if ($criteria instanceof Result) {
            return $criteria;
        }

        $criteria->addAssociation('categories');
        $criteria->addAssociation('visibilities');
        $criteria->setLimit(1);

        $product = $this->productRepository->search($criteria, $context)->first();

        if ($product === null) {
            return Result::unavailable('product_not_found', 'No product matches that identifier in this store.');
        }

        $categories = [];

        foreach ($product->getCategories() ?? [] as $category) {
            $categories[] = ['id' => $category->getId(), 'name' => $category->getName()];
        }

        $visibilities = [];

        foreach ($product->getVisibilities() ?? [] as $visibility) {
            $visibilities[] = [
                'sales_channel_id' => $visibility->getSalesChannelId(),
                'visibility' => $visibility->getVisibility(),
            ];
        }

        return Result::success([
            'product_id' => $product->getId(),
            'sku' => $product->getProductNumber(),
            'name' => $product->getName(),
            'active' => $product->getActive(),
            'stock' => $product->getStock(),
            'available_stock' => $product->getAvailableStock(),
            'categories' => $categories,
            'sales_channel_visibility' => $visibilities,
        ]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function criteriaFor(array $arguments, Context $context): Criteria|Result
    {
        if (isset($arguments['sku']) && is_string($arguments['sku'])) {
            return (new Criteria)->addFilter(new EqualsFilter('productNumber', $arguments['sku']));
        }

        if (isset($arguments['product_id'])) {
            $productId = $arguments['product_id'];

            if (is_string($productId) && Uuid::isValid($productId)) {
                return new Criteria([$productId]);
            }

            return Result::unavailable(
                'identifier_not_applicable',
                'This store identifies a product by UUID or product number; product_id was given as a value this platform has no equivalent for.'
            );
        }

        if (isset($arguments['url_key']) && is_string($arguments['url_key'])) {
            return $this->criteriaFromUrlKey($arguments['url_key'], $context);
        }

        return Result::failed('invalid_arguments', 'Provide exactly one of sku, product_id, or url_key.');
    }

    private function criteriaFromUrlKey(string $urlKey, Context $context): Criteria|Result
    {
        $criteria = (new Criteria)
            ->addFilter(new EqualsFilter('routeName', 'frontend.detail.page'))
            ->addFilter(new EqualsFilter('seoPathInfo', ltrim($urlKey, '/')));
        $criteria->setLimit(1);

        $seoUrl = $this->seoUrlRepository->search($criteria, $context)->first();

        if ($seoUrl === null) {
            return Result::unavailable('product_not_found', 'No product matches that identifier in this store.');
        }

        return new Criteria([$seoUrl->getForeignKey()]);
    }
}
