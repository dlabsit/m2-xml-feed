<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AgeGroup implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('-- Not Set --')],
            ['value' => 'newborn', 'label' => __('Newborn')],
            ['value' => 'infant', 'label' => __('Infant')],
            ['value' => 'toddler', 'label' => __('Toddler')],
            ['value' => 'kids', 'label' => __('Kids')],
            ['value' => 'adult', 'label' => __('Adult')],
        ];
    }
}
