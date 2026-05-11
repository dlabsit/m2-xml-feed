<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
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

class MassStatus extends Feed implements HttpPostActionInterface
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
        $status = (bool) $this->getRequest()->getParam('status');

        $updated = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if (!$id) {
                continue;
            }
            try {
                $feed = $this->feedRepository->getById($id);
                $feed->setIsActive($status);
                $this->feedRepository->save($feed);
                $updated++;
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Could not update feed %1: %2', $id, $e->getMessage()));
            }
        }

        if ($updated > 0) {
            $this->messageManager->addSuccessMessage(__(
                '%1 feed(s) %2.',
                $updated,
                $status ? __('enabled') : __('disabled')
            ));
        }

        return $redirect->setPath('*/*/index');
    }
}
