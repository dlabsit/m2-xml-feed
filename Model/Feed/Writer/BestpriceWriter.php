<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * Bestprice.gr feed writer.
 *
 * Format:
 *   <store>
 *     <date>YYYY-MM-DD HH:MM</date>
 *     <products>
 *       <product>...</product>
 *     </products>
 *   </store>
 *
 * Rules:
 * - No namespace prefix; all tags in default namespace
 * - Category path separator: "->"
 * - Multiple images inside <imagesURL><img1/><img2/>...
 * - Availability is localized Greek string
 * - Variants emitted as one product row per fully-qualified variant
 *   (like Skroutz but without <variations> sub-elements)
 */
class BestpriceWriter extends AbstractWriter
{
    public function getCode(): string
    {
        return 'bestprice';
    }

    public function getLabel(): string
    {
        return 'Bestprice.gr';
    }

    public function getDefaultFilename(): string
    {
        return 'bestprice.xml';
    }

    protected function startDocument(int $storeId): void
    {
        $this->xml->startElement('store');
        $this->xml->writeElement('date', date('Y-m-d H:i'));
        $this->xml->startElement('products');
    }

    protected function endDocument(): void
    {
        $this->xml->endElement(); // products
        $this->xml->endElement(); // store
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
            $name = $this->mapper->getName($product);
            if ($parent) {
                // Decorate name with color/size for variant entries
                $color = $this->mapper->getColor($product, $storeId);
                $size = $this->mapper->getSize($product, $storeId);
                $extras = array_filter([$color, $size]);
                if (!empty($extras)) {
                    $name = $this->mapper->getName($parent) . ' - ' . implode(' / ', $extras);
                }
            }

            $this->writeElement('productId', $id);
            $this->writeCdata('title', $name);
            $this->writeCdata('productURL', $parent ? $this->mapper->getUrl($parent) : $this->mapper->getUrl($product));

            $images = [];
            $main = $this->mapper->getImageUrl($product);
            if ($main === '' && $parent) {
                $main = $this->mapper->getImageUrl($parent);
            }
            if ($main !== '') {
                $images[] = $main;
            }
            foreach ($this->mapper->getAdditionalImages($product, 9) as $img) {
                $images[] = $img;
            }
            if (!empty($images)) {
                $this->xml->startElement('imagesURL');
                foreach ($images as $i => $img) {
                    $this->writeCdata('img' . ($i + 1), $img);
                }
                $this->xml->endElement();
            }

            $this->writeElement('price', number_format($this->mapper->getPrice($product), 2, '.', ''));

            $categoryPath = $this->mapper->getCategoryPath($product, $storeId, '->');
            if ($parent && $categoryPath === '') {
                $categoryPath = $this->mapper->getCategoryPath($parent, $storeId, '->');
            }
            $this->writeCdata('category_path', $categoryPath);

            $this->writeElement('availability', $this->bestpriceAvailability($product, $storeId));

            $brand = $this->mapper->getManufacturer($product, $storeId);
            if ($brand !== 'OEM') {
                $this->writeCdata('brand', $brand);
            }

            $mpn = $this->mapper->getMpn($product, $storeId);
            if ($mpn !== '') {
                $this->writeCdata('MPN', $mpn);
            }

            $ean = $this->mapper->getEan($product, $storeId);
            if ($ean !== '') {
                $this->writeElement('EAN', $ean);
            }

            $color = $this->mapper->getColor($product, $storeId);
            if ($color !== '') {
                $this->writeCdata('color', $color);
            }

            $size = $this->mapper->getSize($product, $storeId);
            if ($size !== '') {
                $this->writeCdata('size', $size);
            }

            $weight = $this->resolveWeightGrams($product, null, $storeId);
            if ($weight > 0) {
                $this->writeElement('weight', (string) $weight);
            }

            $stock = $this->mapper->isInStock($product) && $this->mapper->getStockQty($product) > 0 ? 'Y' : 'N';
            $this->writeElement('stock', $stock);

            // Warranty (optional config)
            $warrantyProvider = $this->config->getFeedOption($this->getCode(), 'warranty/provider', $storeId);
            $warrantyDuration = $this->config->getFeedOption($this->getCode(), 'warranty/duration', $storeId);
            if ($warrantyProvider) {
                $this->writeElement('warranty_provider', $warrantyProvider);
            }
            if ($warrantyDuration) {
                $this->writeElement('warranty_duration', $warrantyDuration);
            }

            $description = $this->mapper->getDescription($product, $storeId, 4000);
            if ($description !== '') {
                $this->writeCdata('description', $description);
            }

        } finally {
            $this->xml->endElement(); // product
        }
    }

    private function bestpriceAvailability(Product $product, int $storeId): string
    {
        if (!$this->mapper->isInStock($product) || $this->mapper->getStockQty($product) <= 0) {
            return 'Εξαντλήθηκε';
        }
        return (string) ($this->config->getFeedOption(
            $this->getCode(),
            'general/default_availability',
            $storeId
        ) ?: 'Παράδοση σε 1–3 ημέρες');
    }
}
