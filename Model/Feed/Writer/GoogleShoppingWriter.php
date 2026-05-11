<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Google Shopping / Google Merchant Center feed writer.
 *
 * Format: RSS 2.0 + xmlns:g="http://base.google.com/ns/1.0"
 * All product tags use g: prefix.
 *
 * Key rules:
 * - Each variant = own <item> with own g:id
 * - Variants share g:item_group_id (= parent SKU/ID)
 * - Price format: "99.00 EUR" (space + ISO 4217)
 * - Availability: in_stock / out_of_stock / preorder / backorder (underscores)
 * - Condition: new / refurbished / used
 */
class GoogleShoppingWriter extends AbstractWriter
{
    protected const NS = 'http://base.google.com/ns/1.0';

    public function getCode(): string
    {
        return 'google';
    }

    public function getLabel(): string
    {
        return 'Google Shopping';
    }

    public function getDefaultFilename(): string
    {
        return 'google.xml';
    }

    protected function startDocument(int $storeId): void
    {
        $this->xml->startElement('rss');
        $this->xml->writeAttribute('version', '2.0');
        $this->xml->writeAttribute('xmlns:g', static::NS);

        $this->xml->startElement('channel');
        $storeName = $this->config->getFeedOption($this->getCode(), 'general/store_name', $storeId)
            ?: $this->getStoreName($storeId);
        $storeUrl = $this->getStoreUrl($storeId);

        $this->writeCdata('title', $storeName);
        $this->writeElement('link', $storeUrl);
        $this->writeCdata('description', $storeName . ' product feed');
    }

    protected function endDocument(): void
    {
        $this->xml->endElement(); // channel
        $this->xml->endElement(); // rss
    }

    protected function writeConfigurableProduct(Product $configurable, int $storeId): void
    {
        // Google: emit each child variant as own item sharing item_group_id
        $children = $this->collector->getConfigurableChildren($configurable, $storeId);
        if (empty($children)) {
            return;
        }

        $parentId = $this->mapper->getUniqueId($configurable, $storeId);
        foreach ($children as $child) {
            $this->writeItem($child, $storeId, $configurable, $parentId);
        }
    }

    protected function writeSimpleProduct(Product $product, int $storeId): void
    {
        $this->writeItem($product, $storeId, null, null);
    }

    private function writeItem(
        Product $product,
        int $storeId,
        ?Product $parent = null,
        ?string $itemGroupId = null
    ): void {
        $this->xml->startElement('item');

        $id = $this->mapper->getUniqueId($product, $storeId);
        $name = mb_substr($this->mapper->getName($product), 0, 150);
        $link = $parent ? $this->mapper->getUrl($parent) : $this->mapper->getUrl($product);
        $image = $this->mapper->getImageUrl($product) ?: ($parent ? $this->mapper->getImageUrl($parent) : '');
        $description = $this->mapper->getDescription($product, $storeId, 5000);

        $this->writeElement('g:id', $id);
        $this->writeCdata('g:title', $name);
        $this->writeCdata('g:description', $description);
        $this->writeElement('g:link', $link);
        $this->writeElement('g:image_link', $image);

        foreach ($this->mapper->getAdditionalImages($product, 10) as $img) {
            $this->writeElement('g:additional_image_link', $img);
        }

        $this->writeElement('g:availability', $this->googleAvailability($product));
        $this->writeElement('g:condition', $this->condition($storeId));

        $currency = $this->getCurrencyCode($storeId);
        $price = number_format($this->mapper->getPrice($product), 2, '.', '');
        $this->writeElement('g:price', $price . ' ' . $currency);

        $specialPrice = $this->mapper->getSpecialPrice($product);
        if ($specialPrice !== null) {
            $this->writeElement('g:sale_price', number_format($specialPrice, 2, '.', '') . ' ' . $currency);
        }

        $brand = $this->mapper->getManufacturer($product, $storeId);
        $mpn = $this->mapper->getMpn($product, $storeId);
        $ean = $this->mapper->getEan($product, $storeId);

        if ($brand !== '' && $brand !== 'OEM') {
            $this->writeCdata('g:brand', $brand);
        }
        if ($ean !== '' && $this->isValidGtin($ean)) {
            $this->writeElement('g:gtin', $ean);
        }
        if ($mpn !== '') {
            $this->writeCdata('g:mpn', $mpn);
        }

        // identifier_exists=no only when no brand + no GTIN + no MPN
        if (($brand === '' || $brand === 'OEM') && $ean === '' && $mpn === '') {
            $this->writeElement('g:identifier_exists', 'no');
        }

        $googleCategory = $this->config->getFeedOption($this->getCode(), 'taxonomy/default_google_category', $storeId);
        if ($googleCategory) {
            $this->writeCdata('g:google_product_category', $googleCategory);
        }

        $productType = $this->mapper->getCategoryPath($product, $storeId, ' > ');
        if ($productType !== '') {
            $this->writeCdata('g:product_type', mb_substr($productType, 0, 750));
        }

        if ($itemGroupId) {
            $this->writeElement('g:item_group_id', $itemGroupId);
        }

        $color = $this->mapper->getColor($product, $storeId);
        if ($color !== '') {
            $this->writeCdata('g:color', mb_substr($color, 0, 100));
        }

        $size = $this->mapper->getSize($product, $storeId);
        if ($size !== '') {
            $this->writeCdata('g:size', mb_substr($size, 0, 100));
        }

        $gender = $this->config->getFeedOption($this->getCode(), 'apparel/default_gender', $storeId);
        if ($gender) {
            $this->writeElement('g:gender', $gender);
        }
        $ageGroup = $this->config->getFeedOption($this->getCode(), 'apparel/default_age_group', $storeId);
        if ($ageGroup) {
            $this->writeElement('g:age_group', $ageGroup);
        }

        $weight = $this->mapper->getWeightGrams($product, $storeId);
        if ($weight > 0) {
            $this->writeElement('g:shipping_weight', ($weight / 1000) . ' kg');
        }

        $shippingCountry = $this->config->getFeedOption($this->getCode(), 'shipping/country', $storeId);
        $shippingPrice = $this->config->getFeedOption($this->getCode(), 'shipping/price', $storeId);
        if ($shippingCountry && $shippingPrice !== null) {
            $this->xml->startElement('g:shipping');
            $this->writeElement('g:country', $shippingCountry);
            $service = $this->config->getFeedOption($this->getCode(), 'shipping/service', $storeId);
            if ($service) {
                $this->writeElement('g:service', $service);
            }
            $this->writeElement('g:price', number_format((float) $shippingPrice, 2, '.', '') . ' ' . $currency);
            $this->xml->endElement();
        }

        // Hook for subclasses (Facebook) to add extra tags
        $this->afterItemTags($product, $storeId, $parent, $itemGroupId);

        $this->xml->endElement(); // item
    }

    /**
     * Hook for subclasses to append extra tags before </item>.
     */
    protected function afterItemTags(
        Product $product,
        int $storeId,
        ?Product $parent,
        ?string $itemGroupId
    ): void {
        // default: nothing
    }

    protected function googleAvailability(Product $product): string
    {
        return $this->mapper->isInStock($product) && $this->mapper->getStockQty($product) > 0
            ? 'in_stock'
            : 'out_of_stock';
    }

    protected function condition(int $storeId): string
    {
        return $this->config->getFeedOption($this->getCode(), 'general/condition', $storeId) ?: 'new';
    }

    /**
     * Validate GTIN by check-digit and length (8/12/13/14).
     */
    protected function isValidGtin(string $gtin): bool
    {
        $gtin = preg_replace('/[^0-9]/', '', $gtin);
        $len = strlen($gtin);
        if (!in_array($len, [8, 12, 13, 14], true)) {
            return false;
        }
        $padded = str_pad($gtin, 14, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $padded[$i] * (($i % 2 === 0) ? 3 : 1);
        }
        $check = (10 - ($sum % 10)) % 10;
        return $check === (int) $padded[13];
    }

    protected function getCurrencyCode(int $storeId): string
    {
        try {
            /** @var \Magento\Store\Model\StoreManagerInterface $sm */
            $sm = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Store\Model\StoreManagerInterface::class);
            return $sm->getStore($storeId)->getCurrentCurrencyCode();
        } catch (\Exception $e) {
            return 'EUR';
        }
    }

    protected function getStoreName(int $storeId): string
    {
        try {
            $sm = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Store\Model\StoreManagerInterface::class);
            return (string) $sm->getStore($storeId)->getName();
        } catch (\Exception $e) {
            return 'Store';
        }
    }

    protected function getStoreUrl(int $storeId): string
    {
        try {
            $sm = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Store\Model\StoreManagerInterface::class);
            return (string) $sm->getStore($storeId)->getBaseUrl();
        } catch (\Exception $e) {
            return '';
        }
    }
}
