<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Api\Data;

interface FeedInterface
{
    public const FEED_ID = 'feed_id';
    public const SLUG = 'slug';
    public const CHANNEL_CODE = 'channel_code';
    public const STORE_ID = 'store_id';
    public const IS_ACTIVE = 'is_active';
    public const NAME = 'name';
    public const FILENAME = 'filename';
    public const FILTER_MODE = 'filter_mode';
    public const CATEGORY_IDS = 'category_ids';
    public const CHANNEL_SETTINGS = 'channel_settings';
    public const SORT_ORDER = 'sort_order';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getFeedId(): ?int;
    public function setFeedId(int $id): self;

    public function getSlug(): string;
    public function setSlug(string $slug): self;

    public function getChannelCode(): string;
    public function setChannelCode(string $code): self;

    public function getStoreId(): int;
    public function setStoreId(int $storeId): self;

    public function isActive(): bool;
    public function setIsActive(bool $isActive): self;

    public function getName(): string;
    public function setName(string $name): self;

    public function getFilename(): string;
    public function setFilename(string $filename): self;

    public function getFilterMode(): string;
    public function setFilterMode(string $mode): self;

    /**
     * @return int[]
     */
    public function getCategoryIds(): array;
    public function setCategoryIds(array $ids): self;

    /**
     * @return array<string, mixed>
     */
    public function getChannelSettings(): array;

    /**
     * @param array<string, mixed> $settings
     */
    public function setChannelSettings(array $settings): self;

    public function getSortOrder(): int;
    public function setSortOrder(int $order): self;
}
