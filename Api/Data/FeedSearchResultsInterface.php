<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface FeedSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return FeedInterface[]
     */
    public function getItems(): array;

    /**
     * @param FeedInterface[] $items
     */
    public function setItems(array $items): self;
}
