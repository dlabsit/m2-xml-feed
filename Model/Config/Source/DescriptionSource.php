<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DescriptionSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'description', 'label' => __('Description')],
            ['value' => 'short_description', 'label' => __('Short Description')],
            ['value' => 'meta_description', 'label' => __('Meta Description')],
        ];
    }
}
