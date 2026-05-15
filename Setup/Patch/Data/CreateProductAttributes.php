<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Create skroutz_ean + skroutz_mpn product attributes.
 * Color and size attributes are handled in EnsureColorSizeAttributes patch.
 */
class CreateProductAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        /** @var EavSetup $eav */
        $eav = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $common = [
            'type' => 'varchar',
            'input' => 'text',
            'required' => false,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'user_defined' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'group' => 'XML Feed',
        ];

        $eav->addAttribute(Product::ENTITY, 'skroutz_ean', array_merge($common, [
            'label' => 'EAN / GTIN (XML Feed)',
            'sort_order' => 10,
        ]));

        $eav->addAttribute(Product::ENTITY, 'skroutz_mpn', array_merge($common, [
            'label' => 'MPN (XML Feed)',
            'sort_order' => 20,
        ]));

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
