<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Api;

use Dlabsit\XmlFeed\Api\Data\FeedInterface;
use Dlabsit\XmlFeed\Api\Data\FeedSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface FeedRepositoryInterface
{
    /**
     * @throws CouldNotSaveException
     */
    public function save(FeedInterface $feed): FeedInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $feedId): FeedInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getBySlug(string $slug, int $storeId = 0): FeedInterface;

    public function getList(SearchCriteriaInterface $criteria): FeedSearchResultsInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(FeedInterface $feed): bool;

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $feedId): bool;
}
