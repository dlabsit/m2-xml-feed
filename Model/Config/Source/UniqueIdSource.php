<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class UniqueIdSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'product_id', 'label' => __('Product ID (entity_id)')],
            ['value' => 'sku', 'label' => __('SKU')],
            ['value' => 'custom', 'label' => __('Custom Attribute')],
        ];
    }
}
