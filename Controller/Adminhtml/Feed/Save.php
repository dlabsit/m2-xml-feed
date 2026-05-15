<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Controller\Adminhtml\Feed;

use Dlabsit\XmlFeed\Api\Data\FeedInterfaceFactory;
use Dlabsit\XmlFeed\Api\FeedRepositoryInterface;
use Dlabsit\XmlFeed\Controller\Adminhtml\Feed;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class Save extends Feed implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FeedInterfaceFactory $feedFactory
    ) {
        parent::__construct($context, $resultPageFactory);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $data = (array) $this->getRequest()->getPostValue();

        if (empty($data)) {
            return $redirect->setPath('*/*/index');
        }

        try {
            $id = (int) ($data['feed_id'] ?? 0);
            /** @var \Dlabsit\XmlFeed\Model\Feed $feed */
            $feed = $id
                ? $this->feedRepository->getById($id)
                : $this->feedFactory->create();

            $this->applyValues($feed, $data);
            $this->feedRepository->save($feed);

            $this->messageManager->addSuccessMessage(__('Feed "%1" saved.', $feed->getName()));
            $this->_getSession()->setDlabsitXmlfeedFormData(false);

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['feed_id' => $feed->getFeedId()]);
            }
            return $redirect->setPath('*/*/index');
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This feed no longer exists.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_getSession()->setDlabsitXmlfeedFormData($data);
            return $redirect->setPath('*/*/edit', ['feed_id' => $id]);
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Could not save the feed.'));
            $this->_getSession()->setDlabsitXmlfeedFormData($data);
            return $redirect->setPath('*/*/edit', ['feed_id' => $id]);
        }

        return $redirect->setPath('*/*/index');
    }

    private function applyValues(\Dlabsit\XmlFeed\Model\Feed $feed, array $data): void
    {
        $slug = $this->sanitizeSlug((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            throw new LocalizedException(__('Slug is required and must use a-z, 0-9 and dashes only.'));
        }

        $feed->setSlug($slug);
        $feed->setChannelCode((string) ($data['channel_code'] ?? ''));
        $feed->setStoreId((int) ($data['store_id'] ?? 0));
        $feed->setIsActive(!empty($data['is_active']));

        $name = trim((string) ($data['name'] ?? ''));
        $feed->setName($name !== '' ? $name : $slug);

        $filename = trim((string) ($data['filename'] ?? ''));
        $feed->setFilename($filename !== '' ? $filename : ($slug . '.xml'));

        $feed->setFilterMode((string) ($data['filter_mode'] ?? 'all'));

        $categoryIds = $data['category_ids'] ?? [];
        if (is_string($categoryIds)) {
            $categoryIds = explode(',', $categoryIds);
        }
        $feed->setCategoryIds((array) $categoryIds);

        $feed->setSortOrder((int) ($data['sort_order'] ?? 10));

        $channelSettings = $data['channel_settings'] ?? [];
        if (is_string($channelSettings) && $channelSettings !== '') {
            try {
                $channelSettings = json_decode($channelSettings, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new LocalizedException(__('Channel Settings JSON is invalid: %1', $e->getMessage()));
            }
        }
        $feed->setChannelSettings(is_array($channelSettings) ? $channelSettings : []);
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        return preg_replace('/[^a-z0-9\-_]/', '', $slug) ?? '';
    }
}
