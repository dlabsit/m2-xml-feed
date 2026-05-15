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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class MassDelete extends Feed implements HttpPostActionInterface
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
        $ids = (array) $this->getRequest()->getParam('feed_ids', []);

        $deleted = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if (!$id) {
                continue;
            }
            try {
                $this->feedRepository->deleteById($id);
                $deleted++;
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Could not delete feed %1: %2', $id, $e->getMessage()));
            }
        }

        if ($deleted > 0) {
            $this->messageManager->addSuccessMessage(__('%1 feed(s) deleted.', $deleted));
        }

        return $redirect->setPath('*/*/index');
    }
}
