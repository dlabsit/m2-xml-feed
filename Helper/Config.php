<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Centralized config reader. General settings are under `xmlfeed/*` —
 * per-feed settings are under `xmlfeed_{code}/*` (e.g. xmlfeed_skroutz, xmlfeed_google).
 */
class Config extends AbstractHelper
{
    public const GENERAL = 'xmlfeed/general/';
    public const SHARED = 'xmlfeed/shared/';

    // --- Shared (applies to all feeds) ---

    public function getUniqueIdSource(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::SHARED . 'unique_id_source',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'product_id');
    }

    public function getCustomAttributeCode(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::SHARED . 'custom_attribute_code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getManufacturerAttribute(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::SHARED . 'manufacturer_attribute',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'manufacturer');
    }

    public function getMpnAttribute(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::SHARED . 'mpn_attribute',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'sku');
    }

    public function getEanAttribute(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::SHARED . 'ean_attribute',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getColorAttribute(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::SHARED . 'color_attribute',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'color');
    }

    public function getSizeAttribute(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::SHARED . 'size_attribute',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'size');
    }

    public function getWeightAttribute(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::SHARED . 'weight_attribute',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'weight');
    }

    public function getDescriptionSource(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::SHARED . 'description_source',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'description');
    }

    public function getFilterMode(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::SHARED . 'filter_mode',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'all');
    }

    public function getFilteredCategories(int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::SHARED . 'categories',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value !== '' ? array_map('intval', explode(',', $value)) : [];
    }

    public function includeOutOfStock(int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::SHARED . 'include_out_of_stock',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getBatchSize(int $storeId = null): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::SHARED . 'batch_size',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 500);
    }

    public function getDefaultVatRate(int $storeId = null): float
    {
        return (float) ($this->scopeConfig->getValue(
            self::SHARED . 'vat_rate',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 24.00);
    }

    public function getCronSchedule(int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::GENERAL . 'cron_schedule',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '0 */2 * * *');
    }

    public function isGzipEnabled(int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::SHARED . 'enable_gzip',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // --- Per-feed helpers ---

    public function isFeedEnabled(string $feedCode, int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            "xmlfeed_{$feedCode}/general/enabled",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getFeedFilename(string $feedCode, int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            "xmlfeed_{$feedCode}/general/filename",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get a feed-specific configuration value.
     */
    public function getFeedOption(string $feedCode, string $path, int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            "xmlfeed_{$feedCode}/{$path}",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value === null ? null : (string) $value;
    }

    public function isFeedFlag(string $feedCode, string $path, int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            "xmlfeed_{$feedCode}/{$path}",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // --- Skroutz Analytics (special-case, moved here for convenience) ---

    public function isAnalyticsEnabled(int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'xmlfeed_skroutz/analytics/enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getShopAccountId(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'xmlfeed_skroutz/analytics/shop_account_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
