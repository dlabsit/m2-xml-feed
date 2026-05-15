<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * Idealo feed writer (XML variant).
 *
 * Idealo's canonical spec is CSV (https://idealo.github.io/csv-importer),
 * but their pipeline accepts XML too. This writer emits XML with element
 * names matching Idealo's column names 1:1.
 *
 * Required fields:
 *   sku, brand, title, url, price, delivery
 *   + at least one deliveryCosts_* + at least one paymentCosts_*
 *
 * Idealo does not use an availability enum — availability is communicated
 * via the `delivery` field (delivery time). Out-of-stock products should
 * be OMITTED from the feed (or delivery raised).
 *
 * Price: numeric, no currency symbol, decimal point.
 * Category: categoryPath (optional).
 * Variants: each variant = own row with unique sku.
 */
class IdealoWriter extends AbstractWriter
{
    public function getCode(): string
    {
        return 'idealo';
    }

    public function getLabel(): string
    {
        return 'Idealo';
    }

    public function getDefaultFilename(): string
    {
        return 'idealo.xml';
    }

    protected function startDocument(int $storeId): void
    {
        // Idealo doesn't mandate a root element name; use <products>/<offers>.
        $this->xml->startElement('products');
    }

    protected function endDocument(): void
    {
        $this->xml->endElement();
    }

    protected function writeSimpleProduct(Product $product, int $storeId): void
    {
        // Idealo: skip out-of-stock (spec recommends omitting them)
        if (!$this->mapper->isInStock($product) || $this->mapper->getStockQty($product) <= 0) {
            return;
        }
        $this->writeRow($product, $storeId, null);
    }

    protected function writeConfigurableProduct(Product $configurable, int $storeId): void
    {
        $children = $this->collector->getConfigurableChildren($configurable, $storeId);
        foreach ($children as $child) {
            if (!$this->mapper->isInStock($child) || $this->mapper->getStockQty($child) <= 0) {
                continue;
            }
            $this->writeRow($child, $storeId, $configurable);
        }
    }

    private function writeRow(Product $product, int $storeId, ?Product $parent): void
    {
        $this->xml->startElement('product');

        // Required fields
        $this->writeCdata('sku', $this->mapper->getUniqueId($product, $storeId));

        $brand = $this->mapper->getManufacturer($product, $storeId);
        $this->writeCdata('brand', $brand);

        $title = $parent
            ? $this->mapper->getName($parent) . ' ' . trim($this->decorateVariant($product, $storeId))
            : $this->mapper->getName($product);
        $this->writeCdata('title', trim($title));

        $url = $parent ? $this->mapper->getUrl($parent) : $this->mapper->getUrl($product);
        $this->writeCdata('url', $url);

        $this->writeElement('price', number_format($this->mapper->getPrice($product), 2, '.', ''));

        $delivery = $this->config->getFeedOption($this->getCode(), 'general/delivery', $storeId) ?: '1-3 days';
        $this->writeCdata('delivery', $delivery);

        $deliveryCost = $this->config->getFeedOption($this->getCode(), 'delivery_costs/dhl', $storeId) ?: '0.00';
        $this->writeElement('deliveryCosts_DHL', number_format((float) $deliveryCost, 2, '.', ''));

        $paymentCost = $this->config->getFeedOption($this->getCode(), 'payment_costs/prepayment', $storeId) ?: '0.00';
        $this->writeElement('paymentCosts_Prepayment', number_format((float) $paymentCost, 2, '.', ''));

        // Optional fields
        $description = $this->mapper->getDescription($product, $storeId, 4000);
        if ($description !== '') {
            $this->writeCdata('description', $description);
        }

        // Multiple image URLs, semicolon-separated (Idealo convention)
        $images = [];
        $main = $this->mapper->getImageUrl($product) ?: ($parent ? $this->mapper->getImageUrl($parent) : '');
        if ($main !== '') {
            $images[] = $main;
        }
        foreach ($this->mapper->getAdditionalImages($product, 4) as $img) {
            $images[] = $img;
        }
        if (!empty($images)) {
            $this->writeCdata('imageUrls', implode(';', $images));
        }

        $ean = $this->mapper->getEan($product, $storeId);
        if ($ean !== '') {
            $this->writeElement('eans', $ean);
        }

        $mpn = $this->mapper->getMpn($product, $storeId);
        if ($mpn !== '') {
            $this->writeCdata('hans', $mpn);
        }

        $category = $this->mapper->getCategoryPath($product, $storeId, ' > ');
        if ($category !== '') {
            $this->writeCdata('categoryPath', $category);
        }

        $condition = $this->config->getFeedOption($this->getCode(), 'general/condition', $storeId) ?: 'Neu';
        $this->writeCdata('condition', $condition);

        $color = $this->mapper->getColor($product, $storeId);
        if ($color !== '') {
            $this->writeCdata('color', $color);
        }

        $size = $this->mapper->getSize($product, $storeId);
        if ($size !== '') {
            $this->writeCdata('size', $size);
        }

        $this->xml->endElement();
    }

    private function decorateVariant(Product $variant, int $storeId): string
    {
        $color = $this->mapper->getColor($variant, $storeId);
        $size = $this->mapper->getSize($variant, $storeId);
        $parts = array_filter([$color, $size]);
        return implode(' ', $parts);
    }
}
