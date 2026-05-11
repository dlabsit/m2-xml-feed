<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FilterMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'all', 'label' => __('All Categories')],
            ['value' => 'include', 'label' => __('Include Selected Only')],
            ['value' => 'exclude', 'label' => __('Exclude Selected')],
        ];
    }
}
