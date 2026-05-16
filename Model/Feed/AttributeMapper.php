<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed;

use Dlabsit\XmlFeed\Helper\Config;
use Dlabsit\XmlFeed\Logger\Logger;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Maps Magento product attributes to normalized values usable by any feed writer.
 */
class AttributeMapper
{
    private array $categoryPathCache = [];

    public function __construct(
        private readonly Config $config,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger $logger
    ) {
    }

    public function getUniqueId(Product $product, int $storeId): string
    {
        $source = $this->config->getUniqueIdSource($storeId);
        return match ($source) {
            'sku' => (string) $product->getSku(),
            'custom' => (string) $product->getData($this->config->getCustomAttributeCode($storeId)),
            default => (string) $product->getId(),
        };
    }

    public function getName(Product $product): string
    {
        return (string) $product->getName();
    }

    public function getUrl(Product $product): string
    {
        return (string) $product->getProductUrl();
    }

    public function getImageUrl(Product $product): string
    {
        $image = $product->getImage();
        if ($image && $image !== 'no_selection') {
            try {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                return $baseUrl . 'catalog/product' . $image;
            } catch (\Exception $e) {
                $this->logger->warning('Image URL failed for product ' . $product->getId());
            }
        }
        return '';
    }

    public function getAdditionalImages(Product $product, int $max = 10): array
    {
        $images = [];
        $gallery = $product->getMediaGalleryImages();
        if ($gallery === null) {
            return $images;
        }

        $count = 0;
        foreach ($gallery as $img) {
            if ($count >= $max) {
                break;
            }
            $url = $img->getUrl();
            if ($url && $img->getFile() !== $product->getImage()) {
                $images[] = $url;
                $count++;
            }
        }
        return $images;
    }

    /**
     * Get category path with the given separator (default: " > ").
     */
    public function getCategoryPath(Product $product, int $storeId, string $separator = ' > '): string
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        $deepest = '';
        $maxDepth = 0;

        foreach ($categoryIds as $categoryId) {
            $path = $this->resolveCategoryPath((int) $categoryId, $storeId, $separator);
            $depth = substr_count($path, $separator);
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
                $deepest = $path;
            }
        }

        return $deepest;
    }

    private function resolveCategoryPath(int $categoryId, int $storeId, string $separator): string
    {
        $key = $categoryId . '-' . $storeId . '-' . hash('sha256', $separator);
        if (isset($this->categoryPathCache[$key])) {
            return $this->categoryPathCache[$key];
        }

        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addAttributeToSelect('name');
            $collection->addFieldToFilter('entity_id', $categoryId);
            $category = $collection->getFirstItem();

            if (!$category->getId()) {
                return $this->categoryPathCache[$key] = '';
            }

            // Magento category path looks like "1/2/5/12/34" where 1 is the
            // absolute (hidden) root and the second segment is the store's
            // root category. We want everything UNDER the store's root so
            // the full taxonomy depth reaches Skroutz — determine the store
            // root dynamically instead of hardcoding ID 2.
            $storeRootId = (int) $this->storeManager->getStore($storeId)->getRootCategoryId();

            $pathIds = array_values(array_filter(
                explode('/', (string) $category->getPath()),
                static fn ($id) => $id !== ''
            ));

            // Drop the absolute root (always 1) and everything up to and
            // including the store's root category.
            $rootCutIndex = array_search((string) $storeRootId, $pathIds, true);
            if ($rootCutIndex !== false) {
                $pathIds = array_slice($pathIds, $rootCutIndex + 1);
            } else {
                // Fallback: drop just the absolute root.
                $pathIds = array_values(array_filter($pathIds, static fn ($id) => (int) $id !== 1));
            }

            if (empty($pathIds)) {
                return $this->categoryPathCache[$key] = (string) $category->getName();
            }

            $pathColl = $this->categoryCollectionFactory->create();
            $pathColl->setStoreId($storeId);
            $pathColl->addAttributeToSelect('name');
            $pathColl->addFieldToFilter('entity_id', ['in' => $pathIds]);

            // Build a lookup by id so we preserve hierarchical order from
            // $pathIds (collections don't guarantee it).
            $byId = [];
            foreach ($pathColl as $cat) {
                $byId[(int) $cat->getId()] = (string) $cat->getName();
            }

            $names = [];
            foreach ($pathIds as $pathId) {
                $name = $byId[(int) $pathId] ?? null;
                if ($name !== null && $name !== '') {
                    $names[] = $name;
                }
            }

            return $this->categoryPathCache[$key] = implode($separator, $names);
        } catch (\Exception $e) {
            $this->logger->error('Category path failed', ['category_id' => $categoryId, 'error' => $e->getMessage()]);
            return $this->categoryPathCache[$key] = '';
        }
    }

    public function getPrice(Product $product): float
    {
        return (float) $product->getFinalPrice();
    }

    public function getSpecialPrice(Product $product): ?float
    {
        $special = $product->getSpecialPrice();
        if ($special === null || $special === '' || (float) $special <= 0) {
            return null;
        }
        $from = $product->getSpecialFromDate();
        $to = $product->getSpecialToDate();
        $now = time();
        if ($from && strtotime($from) > $now) {
            return null;
        }
        if ($to && strtotime($to) < $now) {
            return null;
        }
        return (float) $special;
    }

    public function getManufacturer(Product $product, int $storeId): string
    {
        $attrCode = $this->config->getManufacturerAttribute($storeId);
        $value = $product->getAttributeText($attrCode);
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        return $value ? (string) $value : 'OEM';
    }

    public function getMpn(Product $product, int $storeId): string
    {
        return (string) $product->getData($this->config->getMpnAttribute($storeId));
    }

    public function getEan(Product $product, int $storeId): string
    {
        return (string) $product->getData($this->config->getEanAttribute($storeId));
    }

    public function getDescription(Product $product, int $storeId, int $maxLength = 5000): string
    {
        $source = $this->config->getDescriptionSource($storeId);
        $value = (string) $product->getData($source);
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }

    public function getRawDescription(Product $product, int $storeId): string
    {
        $source = $this->config->getDescriptionSource($storeId);
        return (string) $product->getData($source);
    }

    public function getColor(Product $product, int $storeId): string
    {
        $attrCode = $this->config->getColorAttribute($storeId);
        $value = $product->getAttributeText($attrCode);
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        return (string) $value;
    }

    public function getSize(Product $product, int $storeId): string
    {
        $attrCode = $this->config->getSizeAttribute($storeId);
        $value = $product->getAttributeText($attrCode);
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        return (string) $value;
    }

    public function getWeightGrams(Product $product, int $storeId): int
    {
        $attrCode = $this->config->getWeightAttribute($storeId);
        $w = (float) $product->getData($attrCode);
        if ($w <= 0) {
            // ProductCollector's addAttributeToSelect does not always populate
            // the value on yielded products; fall back to a direct EAV read.
            try {
                $raw = $product->getResource()->getAttributeRawValue(
                    (int) $product->getId(),
                    $attrCode,
                    $storeId
                );
                $w = (float) (is_array($raw) ? ($raw[$attrCode] ?? 0) : $raw);
            } catch (\Exception $e) {
                return 0;
            }
            if ($w <= 0) {
                return 0;
            }
        }
        return (int) ($w * 1000);
    }

    public function getStockQty(Product $product): int
    {
        // Try joined qty first (from ProductCollector)
        if ($product->getData('qty') !== null) {
            return max(0, (int) $product->getData('qty'));
        }
        $stockItem = $product->getExtensionAttributes()?->getStockItem();
        if ($stockItem) {
            return max(0, (int) $stockItem->getQty());
        }
        return 0;
    }

    public function isInStock(Product $product): bool
    {
        if ($product->getData('is_in_stock') !== null) {
            return (bool) $product->getData('is_in_stock');
        }
        $stockItem = $product->getExtensionAttributes()?->getStockItem();
        return $stockItem ? (bool) $stockItem->getIsInStock() : false;
    }

    public function clearCache(): void
    {
        $this->categoryPathCache = [];
    }
}
