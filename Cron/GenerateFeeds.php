<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Cron;

use Dlabsit\XmlFeed\Logger\Logger;
use Dlabsit\XmlFeed\Model\Feed\Generator;
use Dlabsit\XmlFeed\Model\ResourceModel\Feed\CollectionFactory as FeedCollectionFactory;

class GenerateFeeds
{
    public function __construct(
        private readonly Generator $generator,
        private readonly FeedCollectionFactory $collectionFactory,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('sort_order', 'ASC');

        /** @var \Dlabsit\XmlFeed\Model\Feed $feed */
        foreach ($collection as $feed) {
            try {
                $path = $this->generator->generateForFeed($feed);
                $this->logger->info(sprintf(
                    "Cron generated feed '%s' (store %d) → %s",
                    $feed->getSlug(),
                    $feed->getStoreId(),
                    $path
                ));
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    "Cron failed for feed '%s' (store %d): %s",
                    $feed->getSlug(),
                    $feed->getStoreId(),
                    $e->getMessage()
                ));
            }
        }
    }
}
