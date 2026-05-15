<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * All active categories under the store's root, indented by depth so a
 * multiselect renders the full hierarchy instead of just first-level children.
 */
class CategoryTree implements OptionSourceInterface
{
    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->categoryCollectionFactory->create();
        $collection
            ->addAttributeToSelect(['name', 'is_active'])
            ->addAttributeToFilter('entity_id', ['gt' => 1]) // skip the absolute root
            ->addAttributeToSort('path', 'ASC')
            ->addAttributeToSort('position', 'ASC');

        // Index by id so we can resolve the full parent chain cheaply.
        $byId = [];
        foreach ($collection as $category) {
            $byId[(int) $category->getId()] = [
                'id'   => (int) $category->getId(),
                'name' => (string) $category->getName(),
                'path' => (string) $category->getPath(),
                'level' => (int) $category->getLevel(),
            ];
        }

        $options = [];
        foreach ($byId as $cat) {
            // Skip only Magento's absolute root (level 0 / ID 1) — it's
            // hidden and non-selectable. Store roots and everything under
            // them are shown so multi-store setups can pick any category.
            if ($cat['level'] < 1) {
                continue;
            }
            $indent = str_repeat('— ', max(0, $cat['level'] - 1));
            $options[] = [
                'value' => $cat['id'],
                'label' => $indent . $cat['name'] . ' (ID ' . $cat['id'] . ')',
            ];
        }

        return $options;
    }
}
