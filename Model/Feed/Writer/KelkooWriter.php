<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * Kelkoo feed writer (European price comparison — FR, UK, IT, DE, ES, NL).
 *
 * Format: Kelkoo's own XML schema — NOT RSS 2.0.
 *
 *   <products>
 *     <product>
 *       <id>SKU-123</id>
 *       <title><![CDATA[Name]]></title>
 *       <product-url>https://...</product-url>
 *       <price>599.00</price>
 *       <brand>Acme</brand>
 *       <description><![CDATA[...]]></description>
 *       <image-url>https://...</image-url>
 *       <ean>...</ean>
 *       <mpn>...</mpn>
 *       <merchant-category>Home > Tools</merchant-category>
 *       <availability>1</availability>
 *       <delivery-cost>4.99</delivery-cost>
 *       <delivery-time>24h</delivery-time>
 *       <condition>new</condition>
 *       <colour>red;black</colour>
 *     </product>
 *   </products>
 *
 * - Price: numeric only (currency inferred from Kelkoo country/locale)
 * - Availability: 1 (in stock) to 6 (out of stock)
 * - Each variant = own <product>
 * - merchant-category is mandatory; kelkoo-category-id optional
 */
class KelkooWriter extends AbstractWriter
{
    public function getCode(): string
    {
        return 'kelkoo';
    }

    public function getLabel(): string
    {
        return 'Kelkoo';
    }

    public function getDefaultFilename(): string
    {
        return 'kelkoo.xml';
    }

    protected function startDocument(int $storeId): void
    {
        $this->xml->startElement('products');
    }

    protected function endDocument(): void
    {
        $this->xml->endElement();
    }

    protected function writeSimpleProduct(Product $product, int $storeId): void
    {
        $this->writeProduct($product, $storeId, null);
    }

    protected function writeConfigurableProduct(Product $configurable, int $storeId): void
    {
        $children = $this->collector->getConfigurableChildren($configurable, $storeId);
        foreach ($children as $child) {
            $this->writeProduct($child, $storeId, $configurable);
        }
    }

    private function writeProduct(Product $product, int $storeId, ?Product $parent): void
    {
        $this->xml->startElement('product');

        // Required fields
        $this->writeElement('id', $this->mapper->getUniqueId($product, $storeId));

        $title = $parent
            ? $this->mapper->getName($parent) . ' ' . trim($this->decorateVariant($product, $storeId))
            : $this->mapper->getName($product);
        // Kelkoo title limit ~80 chars
        $this->writeCdata('title', mb_substr(trim($title), 0, 80));

        $url = $parent ? $this->mapper->getUrl($parent) : $this->mapper->getUrl($product);
        $this->writeElement('product-url', $url);

        $this->writeElement('price', number_format($this->mapper->getPrice($product), 2, '.', ''));

        $merchantCategory = $this->mapper->getCategoryPath($product, $storeId, ' > ');
        if ($parent && $merchantCategory === '') {
            $merchantCategory = $this->mapper->getCategoryPath($parent, $storeId, ' > ');
        }
        $this->writeCdata('merchant-category', $merchantCategory);

        // Recommended
        $brand = $this->mapper->getManufacturer($product, $storeId);
        if ($brand !== 'OEM') {
            $this->writeCdata('brand', $brand);
        }

        // Description (~300 chars, no HTML)
        $description = $this->mapper->getDescription($product, $storeId, 300);
        if ($description !== '') {
            $this->writeCdata('description', $description);
        }

        $mainImg = $this->mapper->getImageUrl($product) ?: ($parent ? $this->mapper->getImageUrl($parent) : '');
        if ($mainImg !== '') {
            $this->writeElement('image-url', $mainImg);
        }

        // Up to 3 additional images
        $addImages = array_slice($this->mapper->getAdditionalImages($product, 3), 0, 3);
        foreach ($addImages as $i => $img) {
            $this->writeElement('additional-image-url', $img);
        }

        $ean = $this->mapper->getEan($product, $storeId);
        if ($ean !== '') {
            $this->writeElement('ean', $ean);
        }

        $mpn = $this->mapper->getMpn($product, $storeId);
        if ($mpn !== '') {
            $this->writeCdata('mpn', $mpn);
        }

        $sku = $product->getSku();
        if ($sku) {
            $this->writeCdata('sku', $sku);
        }

        // Kelkoo taxonomy (optional)
        $kelkooCatId = $this->config->getFeedOption($this->getCode(), 'general/default_category_id', $storeId);
        if ($kelkooCatId) {
            $this->writeElement('kelkoo-category-id', $kelkooCatId);
        }

        // Availability (1 = in stock, 6 = out of stock)
        $availability = $this->mapper->isInStock($product) && $this->mapper->getStockQty($product) > 0 ? '1' : '6';
        $this->writeElement('availability', $availability);

        // Delivery
        $deliveryCost = $this->config->getFeedOption($this->getCode(), 'delivery/cost', $storeId);
        if ($deliveryCost !== null) {
            $this->writeElement('delivery-cost', number_format((float) $deliveryCost, 2, '.', ''));
        }
        $deliveryTime = $this->config->getFeedOption($this->getCode(), 'delivery/time', $storeId) ?: '24h';
        $this->writeCdata('delivery-time', $deliveryTime);

        $condition = $this->config->getFeedOption($this->getCode(), 'general/condition', $storeId) ?: 'new';
        $this->writeElement('condition', $condition);

        $color = $this->mapper->getColor($product, $storeId);
        if ($color !== '') {
            // Kelkoo uses semicolons for multi-value colours
            $this->writeCdata('colour', str_replace(',', ';', $color));
        }

        $size = $this->mapper->getSize($product, $storeId);
        if ($size !== '') {
            $this->writeCdata('size', $size);
        }

        // Warranty (optional)
        $warranty = $this->config->getFeedOption($this->getCode(), 'general/warranty', $storeId);
        if ($warranty) {
            $this->writeCdata('warranty', $warranty);
        }

        $this->xml->endElement(); // product
    }

    private function decorateVariant(Product $variant, int $storeId): string
    {
        $parts = array_filter([
            $this->mapper->getColor($variant, $storeId),
            $this->mapper->getSize($variant, $storeId),
        ]);
        return implode(' ', $parts);
    }
}
