<?php declare(strict_types=1);

namespace Ecommerly\Connector\Capability\Handler;

use Ecommerly\Connector\Capability\CapabilityHandler;
use Ecommerly\Connector\Capability\Cursor;
use Ecommerly\Connector\Capability\Result;
use Ecommerly\Connector\Capability\ScopeResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * Finds products by name or product number.
 *
 * Deliberately a DAL filter rather than Criteria::setTerm(): the
 * scored product search is a sales-channel feature that pulls in
 * search rankings, the product search config and a sales-channel
 * context this connection does not have. A contains-match on name and
 * product number is predictable, explains itself to an operator, and
 * behaves the same on every store — which matters more here than
 * relevance ranking, because the answer gets cited as evidence.
 */
class SearchProductsHandler implements CapabilityHandler
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly ScopeResolver $scopes
    ) {
    }

    public function capability(): string
    {
        return 'search_products';
    }

    public function handle(array $arguments, Context $context): Result
    {
        $scope = $this->scopes->salesChannelId($arguments);

        if ($scope['error'] !== null) {
            return $scope['error'];
        }

        $query = $arguments['query'] ?? null;

        if (! is_string($query) || trim($query) === '') {
            return Result::failed('invalid_arguments', 'A non-empty query is required.');
        }

        $limit = Cursor::limitFrom($arguments['limit'] ?? null);
        $offset = Cursor::offsetFrom(isset($arguments['cursor']) && is_string($arguments['cursor']) ? $arguments['cursor'] : null);

        $criteria = new Criteria;
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('name', $query),
            new ContainsFilter('productNumber', $query),
        ]));

        if ($scope['id'] !== null) {
            $criteria->addFilter(new ContainsFilter('visibilities.salesChannelId', $scope['id']));
        }

        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $results = $this->productRepository->search($criteria, $context);

        $items = [];

        foreach ($results as $product) {
            $items[] = [
                'product_id' => $product->getId(),
                'sku' => $product->getProductNumber(),
                'name' => $product->getName(),
                'active' => $product->getActive(),
                'stock' => $product->getStock(),
            ];
        }

        return Result::success([
            'items' => $items,
            'total' => $results->getTotal(),
            'next_cursor' => Cursor::next($offset, $limit, $results->getTotal()),
        ]);
    }
}
