<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * Pricerunner feed writer.
 *
 * Format:
 *   <?xml version="1.0" encoding="UTF-8"?>
 *   <products>
 *     <product>...</product>
 *   </products>
 *
 * Rules:
 * - No namespace
 * - Price is numeric (no currency symbol) in local currency
 * - StockStatus: InStock / OutOfStock / PreOrder  OR  numeric qty
 * - Each variant = own <product>
 * - Recommended identifiers: Ean (GTIN), MSku (MPN), Brand
 */
class PricerunnerWriter extends AbstractWriter
{
    public function getCode(): string
    {
        return 'pricerunner';
    }

    public function getLabel(): string
    {
        return 'Pricerunner';
    }

    public function getDefaultFilename(): string
    {
        return 'pricerunner.xml';
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
        $this->writeEntry($product, $storeId, null);
    }

    protected function writeConfigurableProduct(Product $configurable, int $storeId): void
    {
        $children = $this->collector->getConfigurableChildren($configurable, $storeId);
        foreach ($children as $child) {
            $this->writeEntry($child, $storeId, $configurable);
        }
    }

    private function writeEntry(Product $product, int $storeId, ?Product $parent): void
    {
        $this->xml->startElement('product');
        try {

            $id = $this->mapper->getUniqueId($product, $storeId);
            $name = $parent
                ? $this->mapper->getName($parent) . ' ' . trim($this->decorateVariant($product, $storeId))
                : $this->mapper->getName($product);

            $this->writeElement('ProductId', $id);
            $this->writeCdata('ProductName', trim($name));
            $this->writeElement('price', number_format($this->mapper->getPrice($product), 2, '.', ''));

            $shipping = $this->config->getFeedOption($this->getCode(), 'shipping/cost', $storeId);
            $this->writeElement('ShippingCost', number_format((float) ($shipping ?? '0'), 2, '.', ''));

            $qty = $this->mapper->getStockQty($product);
            $stockMode = $this->config->getFeedOption($this->getCode(), 'general/stock_mode', $storeId) ?: 'enum';
            if ($stockMode === 'quantity') {
                $this->writeElement('StockStatus', (string) $qty);
            } else {
                $status = $this->mapper->isInStock($product) && $qty > 0 ? 'InStock' : 'OutOfStock';
                $this->writeElement('StockStatus', $status);
            }

            $leadTime = $this->config->getFeedOption($this->getCode(), 'general/lead_time', $storeId) ?: '1-3 days';
            $this->writeElement('LeadTime', $leadTime);

            $brand = $this->mapper->getManufacturer($product, $storeId);
            $this->writeCdata('Brand', $brand);

            $mpn = $this->mapper->getMpn($product, $storeId);
            if ($mpn !== '') {
                $this->writeCdata('MSku', $mpn);
            }

            $ean = $this->mapper->getEan($product, $storeId);
            if ($ean !== '') {
                $this->writeElement('Ean', $ean);
            }

            $url = $parent ? $this->mapper->getUrl($parent) : $this->mapper->getUrl($product);
            $this->writeCdata('Product_URL', $url);

            $image = $this->mapper->getImageUrl($product) ?: ($parent ? $this->mapper->getImageUrl($parent) : '');
            if ($image !== '') {
                $this->writeCdata('Image_URL', $image);
            }

            $description = $this->mapper->getDescription($product, $storeId, 4000);
            if ($description !== '') {
                $this->writeCdata('Description', $description);
            }

            $merchantCategory = $this->mapper->getCategoryPath($product, $storeId, ' > ');
            if ($merchantCategory !== '') {
                $this->writeCdata('Category', $merchantCategory);
            }

            $color = $this->mapper->getColor($product, $storeId);
            if ($color !== '') {
                $this->writeCdata('Color', $color);
            }

            $size = $this->mapper->getSize($product, $storeId);
            if ($size !== '') {
                $this->writeCdata('Size', $size);
            }

        } finally {
            $this->xml->endElement();
        }
    }

    private function decorateVariant(Product $variant, int $storeId): string
    {
        $color = $this->mapper->getColor($variant, $storeId);
        $size = $this->mapper->getSize($variant, $storeId);
        $parts = array_filter([$color, $size]);
        return implode(' ', $parts);
    }
}
