<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed;

use Dlabsit\XmlFeed\Api\Data\FeedInterface;
use Dlabsit\XmlFeed\Helper\Config;
use Dlabsit\XmlFeed\Logger\Logger;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Yields product batches for feed generation. Shared by all writers.
 */
class ProductCollector
{
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Config $config,
        private readonly StockHelper $stockHelper,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return \Generator<int, \Magento\Catalog\Model\Product>
     */
    public function collect(int $storeId, bool $includeConfigurableChildrenSeparately = false): \Generator
    {
        $batchSize = $this->config->getBatchSize($storeId);
        $page = 1;

        while (true) {
            $collection = $this->createCollection($storeId, $includeConfigurableChildrenSeparately);
            $collection->setPageSize($batchSize);
            $collection->setCurPage($page);

            $size = $collection->getSize();
            if ($size === 0) {
                break;
            }

            foreach ($collection as $product) {
                yield $product;
            }

            if ($page >= $collection->getLastPageNumber()) {
                break;
            }
            $page++;
        }
    }

    /**
     * Yield products for a specific Feed entity, honouring the feed's own
     * filter_mode and category_ids rather than the shared defaults.
     *
     * @return \Generator<int, \Magento\Catalog\Model\Product>
     */
    public function collectForFeed(FeedInterface $feed, int $storeId, bool $includeConfigurableChildrenSeparately = false): \Generator
    {
        $batchSize = $this->config->getBatchSize($storeId);
        $page = 1;

        while (true) {
            $collection = $this->createCollection($storeId, $includeConfigurableChildrenSeparately);
            $this->applyFeedCategoryFilter($collection, $feed);
            $collection->setPageSize($batchSize);
            $collection->setCurPage($page);

            $size = $collection->getSize();
            if ($size === 0) {
                break;
            }

            foreach ($collection as $product) {
                yield $product;
            }

            if ($page >= $collection->getLastPageNumber()) {
                break;
            }
            $page++;
        }
    }

    private function applyFeedCategoryFilter(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $collection,
        FeedInterface $feed
    ): void {
        $mode = $feed->getFilterMode();
        $ids = $feed->getCategoryIds();

        if ($mode === 'all' || empty($ids)) {
            return;
        }
        if ($mode === 'include') {
            $collection->addCategoriesFilter(['in' => $ids]);
        } elseif ($mode === 'exclude') {
            $collection->addCategoriesFilter(['nin' => $ids]);
        }
    }

    /**
     * Get simple children of a configurable product.
     */
    public function getConfigurableChildren(
        \Magento\Catalog\Model\Product $configurable,
        int $storeId
    ): array {
        /** @var Configurable $type */
        $type = $configurable->getTypeInstance();
        $children = $type->getUsedProducts($configurable);

        $valid = [];
        foreach ($children as $child) {
            if ((int) $child->getStatus() !== Status::STATUS_ENABLED) {
                continue;
            }
            if (!$this->config->includeOutOfStock($storeId)) {
                $stockItem = $child->getExtensionAttributes()?->getStockItem();
                if ($stockItem && !$stockItem->getIsInStock()) {
                    continue;
                }
            }
            $valid[] = $child;
        }
        return $valid;
    }

    private function createCollection(
        int $storeId,
        bool $includeConfigurableChildren
    ): \Magento\Catalog\Model\ResourceModel\Product\Collection {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);

        $collection->addAttributeToSelect([
            'name', 'url_key', 'price', 'special_price',
            'special_from_date', 'special_to_date',
            'image', 'small_image', 'description', 'short_description',
            'meta_description', 'weight', 'status', 'visibility',
            $this->config->getManufacturerAttribute($storeId),
            $this->config->getMpnAttribute($storeId),
            $this->config->getEanAttribute($storeId),
            $this->config->getColorAttribute($storeId),
            $this->config->getSizeAttribute($storeId),
        ]);

        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);

        $visibility = [
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ];
        if ($includeConfigurableChildren) {
            $visibility[] = Visibility::VISIBILITY_NOT_VISIBLE;
        }
        $collection->addAttributeToFilter('visibility', ['in' => $visibility]);

        $types = [Type::TYPE_SIMPLE, Configurable::TYPE_CODE];
        $collection->addAttributeToFilter('type_id', ['in' => $types]);

        if (!$this->config->includeOutOfStock($storeId)) {
            $this->stockHelper->addInStockFilterToCollection($collection);
        }

        $this->applyCategoryFilter($collection, $storeId);

        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );
        $collection->joinField(
            'is_in_stock',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );

        $collection->addMediaGalleryData();
        $collection->addUrlRewrite();

        return $collection;
    }

    private function applyCategoryFilter(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $collection,
        int $storeId
    ): void {
        $filterMode = $this->config->getFilterMode($storeId);
        $categoryIds = $this->config->getFilteredCategories($storeId);

        if ($filterMode === 'all' || empty($categoryIds)) {
            return;
        }

        if ($filterMode === 'include') {
            $collection->addCategoriesFilter(['in' => $categoryIds]);
        } elseif ($filterMode === 'exclude') {
            $collection->addCategoriesFilter(['nin' => $categoryIds]);
        }
    }
}
