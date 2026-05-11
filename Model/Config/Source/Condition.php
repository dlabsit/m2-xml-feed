<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Condition implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'new', 'label' => __('New')],
            ['value' => 'refurbished', 'label' => __('Refurbished')],
            ['value' => 'used', 'label' => __('Used')],
        ];
    }
}
