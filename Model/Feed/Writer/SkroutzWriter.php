<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * Skroutz.gr feed writer. Custom XML format:
 *   <mywebstore><created_at/><products><product>...</product></products></mywebstore>
 *
 * Rules (per Skroutz spec):
 * - Configurable with colors → 1 <product> per color (id = "{parent}-{color_option_id}")
 * - Configurable with sizes  → parent has <variations><variation>...</variation></variations>
 * - Size-only (no color)     → 1 <product> with size variations
 * - All tags without prefix; CDATA for text fields
 * - Availability is localized string (e.g. "In stock", "Available from 1 to 3 days")
 */
class SkroutzWriter extends AbstractWriter
{
    /** @var array<string, true> Track emitted UniqueIDs per run to prevent dupes */
    private array $seenIds = [];

    public function getCode(): string
    {
        return 'skroutz';
    }

    public function getLabel(): string
    {
        return 'Skroutz.gr';
    }

    public function getDefaultFilename(): string
    {
        return 'skroutz.xml';
    }

    protected function startDocument(int $storeId): void
    {
        $this->seenIds = [];
        $this->xml->startElement('mywebstore');
        $this->xml->writeElement('created_at', date('Y-m-d H:i'));
        $this->xml->startElement('products');
    }

    protected function endDocument(): void
    {
        $this->xml->endElement(); // products
        $this->xml->endElement(); // mywebstore
    }

    protected function writeSimpleProduct(Product $product, int $storeId): void
    {
        $this->writeProductEntry([
            'id' => $this->mapper->getUniqueId($product, $storeId),
            'name' => $this->mapper->getName($product),
            'link' => $this->mapper->getUrl($product),
            'image' => $this->mapper->getImageUrl($product),
            'additional_images' => $this->mapper->getAdditionalImages($product, 15),
            'category' => $this->mapper->getCategoryPath($product, $storeId, ' > '),
            'price_with_vat' => number_format($this->mapper->getPrice($product), 2, '.', ''),
            'vat' => number_format((float) $this->getFeedSetting('vat_rate', $this->config->getDefaultVatRate($storeId)), 2, '.', ''),
            'manufacturer' => $this->mapper->getManufacturer($product, $storeId),
            'mpn' => $this->mapper->getMpn($product, $storeId),
            'ean' => $this->mapper->getEan($product, $storeId),
            'availability' => $this->skroutzAvailability($product, $storeId),
            'color' => $this->mapper->getColor($product, $storeId),
            'size' => $this->mapper->getSize($product, $storeId),
            'weight' => $this->resolveWeightGrams($product, null, $storeId),
            'description' => $this->mapper->getDescription($product, $storeId, 10000),
            'quantity' => $this->mapper->getStockQty($product),
        ]);
    }

    protected function writeConfigurableProduct(Product $configurable, int $storeId): void
    {
        $children = $this->collector->getConfigurableChildren($configurable, $storeId);
        if (empty($children)) {
            return;
        }

        $colorAttr = $this->config->getColorAttribute($storeId);
        $sizeAttr = $this->config->getSizeAttribute($storeId);

        // Group by color; if no color data, everything in one group
        $hasColor = false;
        foreach ($children as $c) {
            if ($c->getData($colorAttr) !== null && $c->getData($colorAttr) !== '') {
                $hasColor = true;
                break;
            }
        }

        $colorGroups = [];
        if ($hasColor) {
            foreach ($children as $child) {
                $colorId = $child->getData($colorAttr) ?: 'default';
                $colorGroups[$colorId][] = $child;
            }
        } else {
            $colorGroups['default'] = $children;
        }

        foreach ($colorGroups as $colorId => $colorChildren) {
            $firstChild = $colorChildren[0];
            $colorLabel = $hasColor ? $this->getAttrLabel($firstChild, $colorAttr) : '';

            $parentId = $this->mapper->getUniqueId($configurable, $storeId);
            $uniqueId = $hasColor ? $parentId . '-' . $colorId : $parentId;

            $nameSuffix = $colorLabel !== '' ? ' ' . $colorLabel : '';

            // Give each color variant a unique link via a URL fragment — the
            // server ignores fragments, so this doesn't affect the actual page
            // but satisfies Skroutz's "Link must be unique" warning.
            $parentLink = $this->mapper->getUrl($configurable);
            $entryLink = $hasColor && $parentLink !== ''
                ? $parentLink . '#skroutz_color=' . rawurlencode((string) $colorId)
                : $parentLink;

            $sizeGroups = [];
            foreach ($colorChildren as $child) {
                $sizeVal = $child->getData($sizeAttr);
                if ($sizeVal !== null && $sizeVal !== '') {
                    $sizeGroups[$sizeVal][] = $child;
                }
            }

            $hasSizes = !empty($sizeGroups);

            $totalQty = 0;
            $sizes = [];
            $variations = [];

            if ($hasSizes) {
                foreach ($sizeGroups as $sizeId => $sizeChildren) {
                    $szChild = $sizeChildren[0];
                    $sizeLabel = $this->getAttrLabel($szChild, $sizeAttr);
                    $qty = $this->mapper->getStockQty($szChild);
                    $totalQty += $qty;
                    $sizes[] = $sizeLabel;

                    // Child stock isn't always hydrated from getUsedProducts;
                    // emit the default availability string whenever the parent
                    // configurable itself is in stock, so Skroutz doesn't
                    // report 0% completeness on variation availability.
                    $variations[] = [
                        'variationid' => $uniqueId . '-' . $sizeId,
                        'availability' => $this->defaultAvailability($storeId),
                        'size' => $sizeLabel,
                        'quantity' => $qty,
                        'link' => $entryLink,
                        'price' => number_format((float) $szChild->getFinalPrice(), 2, '.', ''),
                        'mpn' => $this->mapper->getMpn($szChild, $storeId),
                        'ean' => $this->mapper->getEan($szChild, $storeId),
                    ];
                }
            } else {
                foreach ($colorChildren as $child) {
                    $totalQty += $this->mapper->getStockQty($child);
                }
            }

            $minPrice = $this->getMinPrice($colorChildren);

            $entry = [
                'id' => $uniqueId,
                'name' => $this->mapper->getName($configurable) . $nameSuffix,
                'link' => $entryLink,
                'image' => $this->getColorImage($firstChild, $configurable),
                'additional_images' => $this->mapper->getAdditionalImages($configurable, 15),
                'category' => $this->mapper->getCategoryPath($configurable, $storeId, ' > '),
                'price_with_vat' => $minPrice,
                'vat' => number_format((float) $this->getFeedSetting('vat_rate', $this->config->getDefaultVatRate($storeId)), 2, '.', ''),
                'manufacturer' => $this->mapper->getManufacturer($configurable, $storeId),
                'mpn' => $this->pickFirstNonEmpty(array_column($colorChildren, $this->config->getMpnAttribute($storeId))),
                'ean' => $this->pickFirstNonEmpty(array_column($colorChildren, $this->config->getEanAttribute($storeId))),
                'availability' => $totalQty > 0 ? $this->defaultAvailability($storeId) : '',
                'color' => $colorLabel,
                'size' => implode(',', $sizes),
                'weight' => $this->resolveWeightGrams($firstChild, $configurable, $storeId),
                'description' => $this->mapper->getDescription($configurable, $storeId, 10000),
                'quantity' => $totalQty,
                'variations' => $variations,
            ];

            $this->writeProductEntry($entry);
        }
    }

    private function writeProductEntry(array $data): void
    {
        // Skip rows that would fail Skroutz required-field validation.
        $id    = (string) ($data['id'] ?? '');
        $image = (string) ($data['image'] ?? '');
        $cat   = (string) ($data['category'] ?? '');
        if ($id === '' || $image === '' || $cat === '') {
            return;
        }
        // UniqueID dedup: skip if this id was already emitted in this run.
        if (isset($this->seenIds[$id])) {
            return;
        }
        $this->seenIds[$id] = true;

        $this->xml->startElement('product');
        try {
            $this->writeElement('id', $id);
            $this->writeCdata('name', (string) ($data['name'] ?? ''));
            $this->writeCdata('link', (string) ($data['link'] ?? ''));
            $this->writeCdata('image', $image);

            foreach ((array) ($data['additional_images'] ?? []) as $img) {
                $this->writeCdata('additional_imageurl', $img);
            }

            $this->writeCdata('category', (string) ($data['category'] ?? ''));
            $this->writeElement('price_with_vat', (string) ($data['price_with_vat'] ?? '0.00'));
            $this->writeElement('vat', (string) ($data['vat'] ?? '24.00'));
            $this->writeCdata('manufacturer', (string) ($data['manufacturer'] ?? 'OEM'));
            $this->writeCdata('mpn', (string) ($data['mpn'] ?? ''));
            $this->writeElement('ean', (string) ($data['ean'] ?? ''));
            $this->writeElement('availability', (string) ($data['availability'] ?? ''));
            $this->writeElement('size', (string) ($data['size'] ?? ''));

            $weight = (int) ($data['weight'] ?? 0);
            if ($weight > 0) {
                $this->writeElement('weight', (string) $weight);
            }

            if (!empty($data['color'])) {
                $this->writeElement('color', (string) $data['color']);
            }

            $this->writeCdata('description', (string) ($data['description'] ?? ''));
            $this->writeElement('quantity', (string) ($data['quantity'] ?? '0'));

            if (!empty($data['variations'])) {
                $this->xml->startElement('variations');
                foreach ($data['variations'] as $v) {
                    $this->xml->startElement('variation');
                    $this->writeElement('variationid', (string) ($v['variationid'] ?? ''));
                    $this->writeElement('availability', (string) ($v['availability'] ?? ''));
                    $this->writeElement('size', (string) ($v['size'] ?? ''));
                    $this->writeElement('quantity', (string) ($v['quantity'] ?? '0'));
                    $this->writeCdata('link', (string) ($v['link'] ?? ''));
                    $this->writeElement('price', (string) ($v['price'] ?? '0.00'));
                    $this->writeCdata('mpn', (string) ($v['mpn'] ?? ''));
                    $this->writeElement('ean', (string) ($v['ean'] ?? ''));
                    $this->xml->endElement();
                }
                $this->xml->endElement();
            }

        } finally {
            $this->xml->endElement(); // product
        }
    }

    private function skroutzAvailability(Product $product, int $storeId): string
    {
        return $this->mapper->isInStock($product) && $this->mapper->getStockQty($product) > 0
            ? $this->defaultAvailability($storeId)
            : '';
    }

    private function defaultAvailability(int $storeId): string
    {
        return (string) ($this->config->getFeedOption('skroutz', 'general/default_availability', $storeId)
            ?: 'Available from 1 to 3 days');
    }

    private function getAttrLabel(Product $product, string $attrCode): string
    {
        $label = $product->getAttributeText($attrCode);
        if (is_array($label)) {
            $label = implode(', ', $label);
        }
        return (string) $label;
    }

    private function getColorImage(Product $child, Product $parent): string
    {
        $image = $child->getImage();
        if ($image && $image !== 'no_selection') {
            return $this->mapper->getImageUrl($child);
        }
        return $this->mapper->getImageUrl($parent);
    }

    private function getMinPrice(array $children): string
    {
        $min = PHP_FLOAT_MAX;
        foreach ($children as $c) {
            $p = (float) $c->getFinalPrice();
            if ($p > 0 && $p < $min) {
                $min = $p;
            }
        }
        return $min < PHP_FLOAT_MAX ? number_format($min, 2, '.', '') : '0.00';
    }

    private function pickFirstNonEmpty(array $values): string
    {
        foreach ($values as $v) {
            if ($v !== null && $v !== '') {
                return (string) $v;
            }
        }
        return '';
    }
}
