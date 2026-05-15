<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PricerunnerStockMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'enum', 'label' => __('Enum (InStock / OutOfStock)')],
            ['value' => 'quantity', 'label' => __('Numeric Quantity')],
        ];
    }
}
