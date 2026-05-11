<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Feed extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('dlabsit_xmlfeed_feed', 'feed_id');
    }
}
