<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model;

use Dlabsit\XmlFeed\Api\Data\FeedInterface;
use Dlabsit\XmlFeed\Model\ResourceModel\Feed as FeedResource;
use Magento\Framework\Model\AbstractModel;

class Feed extends AbstractModel implements FeedInterface
{
    protected function _construct(): void
    {
        $this->_init(FeedResource::class);
    }

    public function getFeedId(): ?int
    {
        $id = $this->getData(self::FEED_ID);
        return $id !== null ? (int) $id : null;
    }

    public function setFeedId(int $id): self
    {
        return $this->setData(self::FEED_ID, $id);
    }

    public function getSlug(): string
    {
        return (string) $this->getData(self::SLUG);
    }

    public function setSlug(string $slug): self
    {
        return $this->setData(self::SLUG, $slug);
    }

    public function getChannelCode(): string
    {
        return (string) $this->getData(self::CHANNEL_CODE);
    }

    public function setChannelCode(string $code): self
    {
        return $this->setData(self::CHANNEL_CODE, $code);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getFilename(): string
    {
        return (string) $this->getData(self::FILENAME);
    }

    public function setFilename(string $filename): self
    {
        return $this->setData(self::FILENAME, $filename);
    }

    public function getFilterMode(): string
    {
        return (string) ($this->getData(self::FILTER_MODE) ?: 'all');
    }

    public function setFilterMode(string $mode): self
    {
        return $this->setData(self::FILTER_MODE, $mode);
    }

    public function getCategoryIds(): array
    {
        $raw = (string) $this->getData(self::CATEGORY_IDS);
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn ($id) => (int) trim((string) $id),
            explode(',', $raw)
        ), static fn ($id) => $id > 0));
    }

    public function setCategoryIds(array $ids): self
    {
        $clean = [];
        foreach ($ids as $id) {
            $int = (int) $id;
            if ($int > 0) {
                $clean[] = $int;
            }
        }
        return $this->setData(self::CATEGORY_IDS, implode(',', $clean));
    }

    public function getChannelSettings(): array
    {
        $raw = $this->getData(self::CHANNEL_SETTINGS);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException $e) {
            return [];
        }
    }

    public function setChannelSettings(array $settings): self
    {
        return $this->setData(self::CHANNEL_SETTINGS, json_encode($settings, JSON_UNESCAPED_UNICODE));
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $order): self
    {
        return $this->setData(self::SORT_ORDER, $order);
    }
}
