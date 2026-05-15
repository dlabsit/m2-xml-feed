<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * Facebook / Meta Commerce Catalog feed writer.
 *
 * Uses the SAME namespace as Google (xmlns:g="http://base.google.com/ns/1.0").
 * ~95% identical to Google Shopping output, with these Facebook specifics:
 *
 * - Availability uses SPACES, not underscores: "in stock" / "out of stock"
 * - Title max 200 chars (Google = 150) — we keep 150 to be safe for both
 * - Description max 9999 chars, plain text (no HTML)
 * - Extra tags: g:fb_product_category, g:rich_text_description,
 *   g:quantity_to_sell_on_facebook
 */
class FacebookCatalogWriter extends GoogleShoppingWriter
{
    public function getCode(): string
    {
        return 'facebook';
    }

    public function getLabel(): string
    {
        return 'Facebook / Meta Catalog';
    }

    public function getDefaultFilename(): string
    {
        return 'facebook.xml';
    }

    protected function googleAvailability(Product $product): string
    {
        // Facebook uses space instead of underscore
        return $this->mapper->isInStock($product) && $this->mapper->getStockQty($product) > 0
            ? 'in stock'
            : 'out of stock';
    }

    protected function afterItemTags(
        Product $product,
        int $storeId,
        ?Product $parent,
        ?string $itemGroupId
    ): void {
        // Facebook-specific: fb_product_category
        $fbCategory = $this->config->getFeedOption(
            $this->getCode(),
            'taxonomy/default_fb_category',
            $storeId
        );
        if ($fbCategory) {
            $this->writeCdata('g:fb_product_category', $fbCategory);
        }

        // rich_text_description: CDATA HTML from product description
        if ($this->config->isFeedFlag($this->getCode(), 'general/include_rich_description', $storeId)) {
            $rich = $this->mapper->getRawDescription($product, $storeId);
            if ($rich !== '') {
                $this->writeCdata('g:rich_text_description', $rich);
            }
        }

        // quantity_to_sell_on_facebook — for Shops checkout
        if ($this->config->isFeedFlag($this->getCode(), 'general/include_quantity', $storeId)) {
            $qty = $this->mapper->getStockQty($product);
            if ($qty > 0) {
                $this->writeElement('g:quantity_to_sell_on_facebook', (string) $qty);
            }
        }
    }
}
