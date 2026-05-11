<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Setup\Patch\Data;

use Dlabsit\XmlFeed\Api\Data\FeedInterfaceFactory;
use Dlabsit\XmlFeed\Api\FeedRepositoryInterface;
use Dlabsit\XmlFeed\Model\ResourceModel\Feed\CollectionFactory as FeedCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * One-time migration: create one feed registry row per currently-enabled
 * channel per store, seeded from the legacy xmlfeed_<channel>/* config.
 *
 * Idempotent — skipped for any (slug, store_id) pair that already exists.
 */
class MigrateChannelConfigToFeeds implements DataPatchInterface
{
    private const CHANNELS = [
        'skroutz', 'google', 'facebook', 'bing', 'bestprice',
        'pricerunner', 'idealo', 'ceneo', 'kelkoo',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly FeedInterfaceFactory $feedFactory,
        private readonly FeedRepositoryInterface $repository,
        private readonly FeedCollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $sharedFilterMode = (string) ($this->scopeConfig->getValue(
            'xmlfeed/shared/filter_mode',
            ScopeInterface::SCOPE_STORE
        ) ?: 'all');
        $sharedCategoryIds = (string) ($this->scopeConfig->getValue(
            'xmlfeed/shared/categories',
            ScopeInterface::SCOPE_STORE
        ) ?: '');

        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();

            foreach (self::CHANNELS as $channel) {
                $enabled = (bool) $this->scopeConfig->getValue(
                    "xmlfeed_{$channel}/general/enabled",
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );

                // Only migrate channels the merchant explicitly enabled on a
                // 1.x install. Fresh installs (no legacy config) end up with
                // an empty registry so the admin can add feeds as needed.
                if (!$enabled) {
                    continue;
                }

                $filename = (string) ($this->scopeConfig->getValue(
                    "xmlfeed_{$channel}/general/filename",
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) ?: $channel . '.xml');

                $slug = $channel;

                if ($this->exists($slug, $storeId)) {
                    continue;
                }

                /** @var \Dlabsit\XmlFeed\Model\Feed $feed */
                $feed = $this->feedFactory->create();
                $feed->setSlug($slug);
                $feed->setChannelCode($channel);
                $feed->setStoreId($storeId);
                $feed->setIsActive($enabled);
                $feed->setName(ucfirst($channel) . ' — ' . $store->getCode());
                $feed->setFilename($filename);
                $feed->setFilterMode($sharedFilterMode);
                $feed->setCategoryIds($this->parseCategoryIds($sharedCategoryIds));
                $feed->setChannelSettings($this->collectChannelSettings($channel, $storeId));
                $feed->setSortOrder(10);

                $this->repository->save($feed);
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
    }

    private function exists(string $slug, int $storeId): bool
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('slug', $slug);
        $collection->addFieldToFilter('store_id', $storeId);
        return $collection->getSize() > 0;
    }

    /**
     * @return int[]
     */
    private function parseCategoryIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn ($id) => (int) trim((string) $id),
            explode(',', $raw)
        ), static fn ($id) => $id > 0));
    }

    /**
     * Harvest channel-specific legacy fields for this channel and return as
     * an associative array for channel_settings JSON.
     *
     * @return array<string, mixed>
     */
    private function collectChannelSettings(string $channel, int $storeId): array
    {
        // Read the whole channel section and keep everything under general/*,
        // taxonomy/*, apparel/*, shipping/*, etc. except 'enabled' and
        // 'filename' (promoted to first-class columns).
        $section = (array) ($this->scopeConfig->getValue(
            "xmlfeed_{$channel}",
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: []);

        $settings = [];
        foreach ($section as $group => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as $key => $value) {
                if ($group === 'general' && in_array($key, ['enabled', 'filename'], true)) {
                    continue;
                }
                $settings[$group . '/' . $key] = $value;
            }
        }
        return $settings;
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
