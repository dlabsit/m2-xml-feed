<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\ResourceModel\Feed;

use Dlabsit\XmlFeed\Model\Feed;
use Dlabsit\XmlFeed\Model\ResourceModel\Feed as FeedResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'feed_id';

    protected function _construct(): void
    {
        $this->_init(Feed::class, FeedResource::class);
    }
}
