<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Gender implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('-- Not Set --')],
            ['value' => 'male', 'label' => __('Male')],
            ['value' => 'female', 'label' => __('Female')],
            ['value' => 'unisex', 'label' => __('Unisex')],
        ];
    }
}
