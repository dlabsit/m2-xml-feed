<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * Shopflix.gr (Wellcomm) feed writer.
 *
 * Format (per Wellcomm/Shopflix merchant specification):
 *   <MPITEMS>
 *     <CREATED_AT>YYYY-MM-DD HH:MM</CREATED_AT>
 *     <MPITEM>
 *       <ID/> <TITLE/> <LINK/> <IMAGE/> ...
 *     </MPITEM>
 *   </MPITEMS>
 *
 * Validator: https://wellcomm.com.gr/xml-val/
 *
 * Rules:
 * - Uppercase tag names, default namespace
 * - Text fields wrapped in CDATA (name/link/images/category/description)
 * - Availability as localized Greek string ("Διαθέσιμο", "Εξαντλήθηκε", etc.)
 * - Configurable products flattened to 1 MPITEM per variant (like Bestprice),
 *   not color-grouped like Skroutz — each variant has its own SKU/EAN/price
 */
class ShopflixWriter extends AbstractWriter
{
    public function getCode(): string
    {
        return 'shopflix';
    }

    public function getLabel(): string
    {
        return 'Shopflix.gr';
    }

    public function getDefaultFilename(): string
    {
        return 'shopflix.xml';
    }

    protected function startDocument(int $storeId): void
    {
        $this->xml->startElement('MPITEMS');
        $this->xml->writeElement('CREATED_AT', date('Y-m-d H:i'));
    }

    protected function endDocument(): void
    {
        $this->xml->endElement(); // MPITEMS
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
        // Skip rows that would fail Shopflix required-field validation.
        $id = $this->mapper->getUniqueId($product, $storeId);
        $image = $this->mapper->getImageUrl($product);
        if ($image === '' && $parent) {
            $image = $this->mapper->getImageUrl($parent);
        }
        if ($id === '' || $image === '') {
            return;
        }

        $this->xml->startElement('MPITEM');

        $this->writeElement('ID', $id);
        $this->writeCdata('SKU', (string) $product->getSku());

        $name = $this->mapper->getName($product);
        if ($parent) {
            $color = $this->mapper->getColor($product, $storeId);
            $size = $this->mapper->getSize($product, $storeId);
            $extras = array_filter([$color, $size]);
            if (!empty($extras)) {
                $name = $this->mapper->getName($parent) . ' - ' . implode(' / ', $extras);
            }
        }
        $this->writeCdata('TITLE', $name);

        $this->writeCdata('LINK', $parent ? $this->mapper->getUrl($parent) : $this->mapper->getUrl($product));
        $this->writeCdata('IMAGE', $image);

        foreach ($this->mapper->getAdditionalImages($product, 10) as $img) {
            $this->writeCdata('ADDITIONAL_IMAGE_URL', $img);
        }

        $category = $this->mapper->getCategoryPath($product, $storeId, ' > ');
        if ($category === '' && $parent) {
            $category = $this->mapper->getCategoryPath($parent, $storeId, ' > ');
        }
        $this->writeCdata('CATEGORY', $category);

        $this->writeElement('PRICE', number_format($this->mapper->getPrice($product), 2, '.', ''));

        $vatRate = (float) $this->config->getDefaultVatRate($storeId);
        if ($vatRate > 0) {
            $this->writeElement('VAT', number_format($vatRate, 2, '.', ''));
        }

        $brand = $this->mapper->getManufacturer($product, $storeId);
        if ($brand !== '' && $brand !== 'OEM') {
            $this->writeCdata('BRAND', $brand);
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
            $this->writeCdata('COLOR', $color);
        }

        $size = $this->mapper->getSize($product, $storeId);
        if ($size !== '') {
            $this->writeCdata('SIZE', $size);
        }

        $weight = $this->mapper->getWeightGrams($product, $storeId);
        if ($weight > 0) {
            $this->writeElement('WEIGHT', (string) $weight);
        }

        $qty = $this->mapper->getStockQty($product);
        $this->writeElement('QUANTITY', (string) $qty);
        $this->writeElement('AVAILABILITY', $this->shopflixAvailability($product, $storeId));

        $description = $this->mapper->getDescription($product, $storeId, 10000);
        if ($description !== '') {
            $this->writeCdata('DESCRIPTION', $description);
        }

        $this->xml->endElement(); // MPITEM
    }

    private function shopflixAvailability(Product $product, int $storeId): string
    {
        if (!$this->mapper->isInStock($product) || $this->mapper->getStockQty($product) <= 0) {
            return (string) ($this->config->getFeedOption(
                $this->getCode(),
                'general/oos_availability',
                $storeId
            ) ?: 'Εξαντλήθηκε');
        }
        return (string) ($this->config->getFeedOption(
            $this->getCode(),
            'general/default_availability',
            $storeId
        ) ?: 'Διαθέσιμο');
    }
}
