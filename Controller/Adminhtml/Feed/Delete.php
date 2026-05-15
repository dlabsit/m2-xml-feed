<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Controller\Adminhtml\Feed;

use Dlabsit\XmlFeed\Api\FeedRepositoryInterface;
use Dlabsit\XmlFeed\Controller\Adminhtml\Feed;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class Delete extends Feed implements HttpGetActionInterface, HttpPostActionInterface
{
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly FeedRepositoryInterface $feedRepository
    ) {
        parent::__construct($context, $resultPageFactory);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $id = (int) $this->getRequest()->getParam('feed_id');

        if (!$id) {
            return $redirect->setPath('*/*/index');
        }

        try {
            $this->feedRepository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('Feed deleted.'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This feed no longer exists.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Could not delete the feed.'));
        }

        return $redirect->setPath('*/*/index');
    }
}
