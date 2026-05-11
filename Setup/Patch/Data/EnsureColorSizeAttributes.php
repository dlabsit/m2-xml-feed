<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Table as TableSource;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Ensure standard Magento 'color' and 'size' product attributes exist.
 *
 * Magento sample-data installs include these by default, but clean
 * installs do NOT. Without them, configurable products and apparel
 * feeds (Skroutz fashion, Google Apparel category) cannot work.
 *
 * This patch creates them only if they are missing, mirroring the
 * sample-data definitions (select dropdown, global scope, dropdown
 * source model). If a merchant already has them, this patch is a no-op.
 */
class EnsureColorSizeAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly EavConfig $eavConfig
    ) {
    }

    public function apply(): self
    {
        /** @var EavSetup $eav */
        $eav = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $common = [
            'type' => 'int',
            'input' => 'select',
            'source' => TableSource::class,
            'required' => false,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'user_defined' => true,
            'searchable' => true,
            'filterable' => true,
            'comparable' => true,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'group' => 'General',
            'is_html_allowed_on_front' => false,
        ];

        if (!$this->eavConfig->getAttribute(Product::ENTITY, 'color')->getId()) {
            $eav->addAttribute(Product::ENTITY, 'color', array_merge($common, [
                'label' => 'Color',
                'sort_order' => 50,
            ]));
            $eav->addAttributeToGroup(Product::ENTITY, 'Default', 'General', 'color', 90);
        }

        if (!$this->eavConfig->getAttribute(Product::ENTITY, 'size')->getId()) {
            $eav->addAttribute(Product::ENTITY, 'size', array_merge($common, [
                'label' => 'Size',
                'sort_order' => 60,
            ]));
            $eav->addAttributeToGroup(Product::ENTITY, 'Default', 'General', 'size', 100);
        }

        // Refresh EAV cache so the new attributes are immediately visible
        $this->eavConfig->clear();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateProductAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
