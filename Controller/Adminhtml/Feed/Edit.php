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
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Feed implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly Registry $coreRegistry,
        private readonly FeedRepositoryInterface $feedRepository
    ) {
        parent::__construct($context, $resultPageFactory);
    }

    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('feed_id');

        if ($id) {
            try {
                $feed = $this->feedRepository->getById($id);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This feed no longer exists.'));
                return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
            }
            $this->coreRegistry->register('dlabsit_xmlfeed_current_feed', $feed);
            $title = __('Edit Feed "%1"', $feed->getName());
        } else {
            $title = __('New Feed');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Dlabsit_XmlFeed::feed');
        $resultPage->getConfig()->getTitle()->prepend($title);
        $resultPage->addBreadcrumb(__('XML Feed'), __('XML Feed'));
        $resultPage->addBreadcrumb(__('Feeds'), __('Feeds'), $this->getUrl('*/*/index'));
        $resultPage->addBreadcrumb($title, $title);
        return $resultPage;
    }
}
