<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributes implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Please Select --')]];

        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('frontend_input', ['neq' => 'hidden']);
        $collection->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $label = $attribute->getFrontendLabel();
            $code = $attribute->getAttributeCode();
            $displayLabel = $label ?: ucfirst(str_replace('_', ' ', $code));
            $options[] = [
                'value' => $code,
                'label' => $displayLabel . ' (' . $code . ')',
            ];
        }

        return $options;
    }
}
