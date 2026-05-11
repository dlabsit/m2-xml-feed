<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model;

use Dlabsit\XmlFeed\Api\Data\FeedInterface;
use Dlabsit\XmlFeed\Api\Data\FeedSearchResultsInterface;
use Dlabsit\XmlFeed\Api\Data\FeedSearchResultsInterfaceFactory;
use Dlabsit\XmlFeed\Api\FeedRepositoryInterface;
use Dlabsit\XmlFeed\Model\ResourceModel\Feed as FeedResource;
use Dlabsit\XmlFeed\Model\ResourceModel\Feed\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class FeedRepository implements FeedRepositoryInterface
{
    public function __construct(
        private readonly FeedResource $resource,
        private readonly FeedFactory $feedFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly FeedSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function save(FeedInterface $feed): FeedInterface
    {
        try {
            $this->resource->save($this->toModel($feed));
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save feed: %1', $e->getMessage()), $e);
        }
        return $feed;
    }

    public function getById(int $feedId): FeedInterface
    {
        $feed = $this->feedFactory->create();
        $this->resource->load($feed, $feedId);
        if (!$feed->getFeedId()) {
            throw new NoSuchEntityException(__('Feed with id %1 does not exist.', $feedId));
        }
        return $feed;
    }

    public function getBySlug(string $slug, int $storeId = 0): FeedInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('slug', $slug);
        $collection->addFieldToFilter('store_id', ['in' => [0, $storeId]]);
        $collection->setOrder('store_id', 'DESC'); // store-scoped wins over admin default
        $collection->setPageSize(1);
        /** @var \Dlabsit\XmlFeed\Model\Feed|false $feed */
        $feed = $collection->getFirstItem();
        if (!$feed || !$feed->getFeedId()) {
            throw new NoSuchEntityException(__('No feed found for slug "%1".', $slug));
        }
        return $feed;
    }

    public function getList(SearchCriteriaInterface $criteria): FeedSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);

        /** @var FeedSearchResultsInterface $results */
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($criteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }

    public function delete(FeedInterface $feed): bool
    {
        try {
            $this->resource->delete($this->toModel($feed));
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete feed: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $feedId): bool
    {
        return $this->delete($this->getById($feedId));
    }

    private function toModel(FeedInterface $feed): Feed
    {
        if ($feed instanceof Feed) {
            return $feed;
        }
        throw new CouldNotSaveException(__('Unexpected feed instance type.'));
    }
}
