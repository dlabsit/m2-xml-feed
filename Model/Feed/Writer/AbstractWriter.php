<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

use Dlabsit\XmlFeed\Api\FeedWriterInterface;
use Dlabsit\XmlFeed\Helper\Config;
use Dlabsit\XmlFeed\Logger\Logger;
use Dlabsit\XmlFeed\Model\Feed\AttributeMapper;
use Dlabsit\XmlFeed\Model\Feed\ProductCollector;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

abstract class AbstractWriter implements FeedWriterInterface
{
    protected \XMLWriter $xml;

    public function __construct(
        protected readonly Config $config,
        protected readonly AttributeMapper $mapper,
        protected readonly ProductCollector $collector,
        protected readonly Logger $logger
    ) {
    }

    abstract public function getCode(): string;

    abstract public function getLabel(): string;

    abstract public function getDefaultFilename(): string;

    abstract protected function startDocument(int $storeId): void;

    abstract protected function endDocument(): void;

    abstract protected function writeSimpleProduct(\Magento\Catalog\Model\Product $product, int $storeId): void;

    /**
     * Default: emit configurable as its variants. Writers can override
     * (e.g. Skroutz emits color-split groups, not individual children).
     */
    protected function writeConfigurableProduct(\Magento\Catalog\Model\Product $configurable, int $storeId): void
    {
        $children = $this->collector->getConfigurableChildren($configurable, $storeId);
        foreach ($children as $child) {
            $this->writeSimpleProduct($child, $storeId);
        }
    }

    public function write(string $filePath, \Generator $productSource, int $storeId): void
    {
        $this->xml = new \XMLWriter();
        $this->xml->openUri($filePath);
        $this->xml->setIndent(true);
        $this->xml->setIndentString('  ');
        $this->xml->startDocument('1.0', 'UTF-8');

        $this->startDocument($storeId);

        $count = 0;
        foreach ($productSource as $product) {
            try {
                if ($product->getTypeId() === Configurable::TYPE_CODE) {
                    $this->writeConfigurableProduct($product, $storeId);
                } else {
                    if (!$this->config->includeOutOfStock($storeId)) {
                        $qty = (int) $product->getData('qty');
                        $inStock = (bool) $product->getData('is_in_stock');
                        if (!$inStock || $qty <= 0) {
                            continue;
                        }
                    }
                    $this->writeSimpleProduct($product, $storeId);
                }
                $count++;
            } catch (\Exception $e) {
                $this->logger->error(
                    "Writer[{$this->getCode()}] product {$product->getId()} failed",
                    ['exception' => $e->getMessage()]
                );
            }
        }

        $this->endDocument();
        $this->xml->endDocument();
        $this->xml->flush();
    }

    // --- XMLWriter helpers ---

    protected function writeElement(string $name, string $value): void
    {
        $this->xml->writeElement($name, $this->sanitizeXmlText($value));
    }

    protected function writeCdata(string $name, string $value): void
    {
        $this->xml->startElement($name);
        $this->xml->writeCdata($this->sanitizeCdata($value));
        $this->xml->endElement();
    }

    /**
     * Strip control characters that are illegal in XML 1.0 (everything below
     * U+0020 except tab/LF/CR) and invalid UTF-8 byte sequences. Without
     * this, a single bad byte in any product name/description corrupts the
     * whole feed.
     */
    private function sanitizeXmlText(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            // iconv() emits a warning when it hits a bad byte even with
            // //IGNORE; convert that to a real exception once and catch it
            // so the generator doesn't abort on a single broken product.
            $previous = set_error_handler(static function () {
                return true;
            });
            try {
                $converted = iconv('UTF-8', 'UTF-8//IGNORE', $value);
            } finally {
                set_error_handler($previous);
            }
            $value = $converted !== false ? $converted : mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    }

    /**
     * Same as sanitizeXmlText, plus break the literal "]]>" sequence which
     * would prematurely close a CDATA section. Inserting a space between
     * "]]" and ">" keeps the visible text unchanged for any realistic
     * product-data use case.
     */
    private function sanitizeCdata(string $value): string
    {
        $value = $this->sanitizeXmlText($value);
        return str_replace(']]>', ']] >', $value);
    }

    protected function startElement(string $name): void
    {
        $this->xml->startElement($name);
    }

    protected function endElement(): void
    {
        $this->xml->endElement();
    }
}
