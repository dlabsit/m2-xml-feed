<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * eMAG Marketplace feed writer (RO / BG / HU / PL).
 *
 * Format (per eMAG merchant/integration guidance, typical variant):
 *   <PRODUCTS>
 *     <PRODUCT>
 *       <PRODUCT_ID/> <PRODUCT_NUMBER/> <NAME/> <CATEGORY/>
 *       <MANUFACTURER/> <PRODUCT_URL/> <IMAGE_URL/>
 *       <PRICE/> <PRICE_SPECIAL/> <VAT/> <STOCK/> <EAN/>
 *       <HANDLING_TIME/> <GUARANTEE/> <DESCRIPTION/>
 *     </PRODUCT>
 *   </PRODUCTS>
 *
 * Rules (confirmed from eMAG public merchant documentation):
 * - Prices MUST be sent WITHOUT VAT (eMAG adds VAT based on the PRICE_VAT/VAT field).
 * - Stock is numeric.
 * - Product name format: "Brand, Model, Product Type, Parameters (color/size/...)".
 *   Do not include offer/company/price/delivery info in NAME.
 * - Image URLs must be direct links (JPG/PNG/GIF), no special characters.
 * - EAN mandatory except for eMAG categories that explicitly exempt it.
 * - HANDLING_TIME: integer days until dispatch (0 = same day).
 * - GUARANTEE: integer months of warranty.
 * - Markets differ only in currency/category mapping — the XML structure is shared.
 *
 * Configurable products are flattened to one <PRODUCT> per variant; each variant
 * has its own SKU (PRODUCT_NUMBER) and EAN, which is what eMAG expects.
 */
class EmagWriter extends AbstractWriter
{
    public function getCode(): string
    {
        return 'emag';
    }

    public function getLabel(): string
    {
        return 'eMAG Marketplace';
    }

    public function getDefaultFilename(): string
    {
        return 'emag.xml';
    }

    protected function startDocument(int $storeId): void
    {
        $this->xml->startElement('PRODUCTS');
    }

    protected function endDocument(): void
    {
        $this->xml->endElement(); // PRODUCTS
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
        $id = $this->mapper->getUniqueId($product, $storeId);
        $image = $this->mapper->getImageUrl($product);
        if ($image === '' && $parent) {
            $image = $this->mapper->getImageUrl($parent);
        }
        if ($id === '' || $image === '') {
            return;
        }

        $this->xml->startElement('PRODUCT');
        try {

            $this->writeElement('PRODUCT_ID', $id);
            $this->writeElement('PRODUCT_NUMBER', (string) $product->getSku());

            $name = $this->mapper->getName($product);
            if ($parent) {
                $color = $this->mapper->getColor($product, $storeId);
                $size = $this->mapper->getSize($product, $storeId);
                $extras = array_filter([$color, $size]);
                if (!empty($extras)) {
                    // eMAG wants the variant parameters in the NAME, comma-separated.
                    $name = $this->mapper->getName($parent) . ', ' . implode(', ', $extras);
                }
            }
            $this->writeCdata('NAME', $name);

            $category = $this->mapper->getCategoryPath($product, $storeId, ' > ');
            if ($category === '' && $parent) {
                $category = $this->mapper->getCategoryPath($parent, $storeId, ' > ');
            }
            $this->writeCdata('CATEGORY', $category);

            $brand = $this->mapper->getManufacturer($product, $storeId);
            $this->writeCdata('MANUFACTURER', $brand !== '' ? $brand : 'OEM');

            $this->writeCdata('PRODUCT_URL', $parent ? $this->mapper->getUrl($parent) : $this->mapper->getUrl($product));
            $this->writeCdata('IMAGE_URL', $image);

            foreach ($this->mapper->getAdditionalImages($product, 10) as $img) {
                $this->writeCdata('EXTRA_IMAGE_URL', $img);
            }

            // Price conversion: eMAG wants net price (excluding VAT).
            $priceInclVat = (float) $this->mapper->getPrice($product);
            $vatRate = (float) $this->config->getDefaultVatRate($storeId);
            $priceExclVat = $vatRate > 0 ? $priceInclVat / (1 + $vatRate / 100) : $priceInclVat;

            $this->writeElement('PRICE', number_format($priceExclVat, 2, '.', ''));
            $this->writeElement('PRICE_SPECIAL', number_format($priceExclVat, 2, '.', ''));

            if ($vatRate > 0) {
                $this->writeElement('VAT', number_format($vatRate, 0, '.', ''));
            }

            $stock = $this->mapper->isInStock($product) ? $this->mapper->getStockQty($product) : 0;
            $this->writeElement('STOCK', (string) max(0, $stock));

            $ean = $this->mapper->getEan($product, $storeId);
            if ($ean !== '') {
                $this->writeElement('EAN', $ean);
            }

            $handlingTime = (string) $this->config->getFeedOption(
                $this->getCode(),
                'general/handling_time',
                $storeId
            );
            if ($handlingTime !== '') {
                $this->writeElement('HANDLING_TIME', $handlingTime);
            }

            $guarantee = (string) $this->config->getFeedOption(
                $this->getCode(),
                'general/guarantee_months',
                $storeId
            );
            if ($guarantee !== '') {
                $this->writeElement('GUARANTEE', $guarantee);
            }

            $weight = $this->mapper->getWeightGrams($product, $storeId);
            if ($weight > 0) {
                $this->writeElement('WEIGHT', (string) $weight);
            }

            $description = $this->mapper->getDescription($product, $storeId, 10000);
            if ($description !== '') {
                $this->writeCdata('DESCRIPTION', $description);
            }

        } finally {
            $this->xml->endElement(); // PRODUCT
        }
    }
}
