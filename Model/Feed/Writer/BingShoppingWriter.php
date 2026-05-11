<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed\Writer;

/**
 * Bing Shopping / Microsoft Merchant Center feed writer.
 *
 * Bing/MSFT officially supports the same RSS 2.0 + g: namespace format as
 * Google Shopping. Most stores can reuse their Google feed 1:1, but this
 * writer exists as a separate target so you can tune filename / defaults
 * independently.
 *
 * Differences from Google:
 * - No g:gtin check-digit validation enforced (MSFT is more lenient)
 * - Accepts same availability values (in_stock / out_of_stock / preorder / backorder)
 * - Price format identical: "99.00 EUR"
 * - Condition: new / refurbished / used
 * - Bing has own "bingads_labels" and bing-specific categories but these
 *   are optional enhancements, not required
 */
class BingShoppingWriter extends GoogleShoppingWriter
{
    public function getCode(): string
    {
        return 'bing';
    }

    public function getLabel(): string
    {
        return 'Bing Shopping / Microsoft Merchant';
    }

    public function getDefaultFilename(): string
    {
        return 'bing.xml';
    }
}
