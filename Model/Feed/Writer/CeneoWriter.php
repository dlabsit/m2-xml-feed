<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Magento\Catalog\Model\Product;

/**
 * Ceneo.pl feed writer (Polish price comparison).
 *
 * Format (compact — many fields as attributes on <o>):
 *
 *   <offers version="1">
 *     <group name="default">
 *       <o id="SKU" url="..." price="199.00" avail="1" stock="25" weight="0.5" basket="1">
 *         <cat><![CDATA[Elektronika/Audio/Słuchawki]]></cat>
 *         <name><![CDATA[...]]></name>
 *         <imgs>
 *           <main url="..."/>
 *           <i url="..."/>
 *         </imgs>
 *         <desc><![CDATA[...]]></desc>
 *         <attrs>
 *           <a name="Producent">Acme</a>
 *           <a name="Kod_producenta">X1-BLK</a>
 *           <a name="EAN">5901234567890</a>
 *         </attrs>
 *       </o>
 *     </group>
 *   </offers>
 *
 * - Price in PLN (gross, no currency symbol)
 * - Category as PATH string with "/" separator
 * - avail integer codes: 0=immediate, 1=1-3d, 2=3-5d, 3=up to 7d, 4=up to 14d, 5=>14d, 99=OOS
 * - Each variant = own <o> (Ceneo has no first-class variant grouping)
 * - Out-of-stock products are typically omitted
 */
class CeneoWriter extends AbstractWriter
{
    public function getCode(): string
    {
        return 'ceneo';
    }

    public function getLabel(): string
    {
        return 'Ceneo.pl';
    }

    public function getDefaultFilename(): string
    {
        return 'ceneo.xml';
    }

    protected function startDocument(int $storeId): void
    {
        $this->xml->startElement('offers');
        $this->xml->writeAttribute('version', '1');
        $this->xml->startElement('group');
        $this->xml->writeAttribute('name', 'default');
    }

    protected function endDocument(): void
    {
        $this->xml->endElement(); // group
        $this->xml->endElement(); // offers
    }

    protected function writeSimpleProduct(Product $product, int $storeId): void
    {
        if (!$this->mapper->isInStock($product) || $this->mapper->getStockQty($product) <= 0) {
            return;
        }
        $this->writeOffer($product, $storeId, null);
    }

    protected function writeConfigurableProduct(Product $configurable, int $storeId): void
    {
        $children = $this->collector->getConfigurableChildren($configurable, $storeId);
        foreach ($children as $child) {
            if (!$this->mapper->isInStock($child) || $this->mapper->getStockQty($child) <= 0) {
                continue;
            }
            $this->writeOffer($child, $storeId, $configurable);
        }
    }

    private function writeOffer(Product $product, int $storeId, ?Product $parent): void
    {
        $this->xml->startElement('o');

        $id = $this->mapper->getUniqueId($product, $storeId);
        $url = $parent ? $this->mapper->getUrl($parent) : $this->mapper->getUrl($product);
        $price = number_format($this->mapper->getPrice($product), 2, '.', '');
        $avail = $this->ceneoAvailCode($storeId);
        $stock = $this->mapper->getStockQty($product);
        $weight = $this->mapper->getWeightGrams($product, $storeId) / 1000;

        $this->xml->writeAttribute('id', $id);
        $this->xml->writeAttribute('url', $url);
        $this->xml->writeAttribute('price', $price);
        $this->xml->writeAttribute('avail', (string) $avail);
        $this->xml->writeAttribute('stock', (string) $stock);
        if ($weight > 0) {
            $this->xml->writeAttribute('weight', (string) $weight);
        }

        $basket = $this->config->isFeedFlag($this->getCode(), 'general/basket_enabled', $storeId) ? '1' : '0';
        $this->xml->writeAttribute('basket', $basket);

        // Category path (Polish uses "/" by convention)
        $category = $this->mapper->getCategoryPath($product, $storeId, '/');
        if ($parent && $category === '') {
            $category = $this->mapper->getCategoryPath($parent, $storeId, '/');
        }
        if ($category !== '') {
            $this->writeCdata('cat', $category);
        }

        $name = $parent
            ? $this->mapper->getName($parent) . ' ' . trim($this->decorateVariant($product, $storeId))
            : $this->mapper->getName($product);
        $this->writeCdata('name', trim($name));

        // Images
        $mainImg = $this->mapper->getImageUrl($product) ?: ($parent ? $this->mapper->getImageUrl($parent) : '');
        $additional = $this->mapper->getAdditionalImages($product, 9);
        if ($mainImg !== '' || !empty($additional)) {
            $this->xml->startElement('imgs');
            if ($mainImg !== '') {
                $this->xml->startElement('main');
                $this->xml->writeAttribute('url', $mainImg);
                $this->xml->endElement();
            }
            foreach ($additional as $img) {
                $this->xml->startElement('i');
                $this->xml->writeAttribute('url', $img);
                $this->xml->endElement();
            }
            $this->xml->endElement();
        }

        $description = $this->mapper->getDescription($product, $storeId, 4000);
        if ($description !== '') {
            $this->writeCdata('desc', $description);
        }

        // Attributes
        $attrs = [];
        $brand = $this->mapper->getManufacturer($product, $storeId);
        if ($brand !== 'OEM') {
            $attrs['Producent'] = $brand;
        }
        $mpn = $this->mapper->getMpn($product, $storeId);
        if ($mpn !== '') {
            $attrs['Kod_producenta'] = $mpn;
        }
        $ean = $this->mapper->getEan($product, $storeId);
        if ($ean !== '') {
            $attrs['EAN'] = $ean;
        }
        $color = $this->mapper->getColor($product, $storeId);
        if ($color !== '') {
            $attrs['Kolor'] = $color;
        }
        $size = $this->mapper->getSize($product, $storeId);
        if ($size !== '') {
            $attrs['Rozmiar'] = $size;
        }

        if (!empty($attrs)) {
            $this->xml->startElement('attrs');
            foreach ($attrs as $name => $value) {
                $this->xml->startElement('a');
                $this->xml->writeAttribute('name', $name);
                $this->xml->text((string) $value);
                $this->xml->endElement();
            }
            $this->xml->endElement();
        }

        $this->xml->endElement(); // o
    }

    private function ceneoAvailCode(int $storeId): int
    {
        // Configurable per store, default "1-3 days" = 1
        $code = $this->config->getFeedOption($this->getCode(), 'general/avail_code', $storeId);
        return $code !== null ? (int) $code : 1;
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
