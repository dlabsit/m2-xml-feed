<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Controller\Adminhtml\Feed;

use Dlabsit\XmlFeed\Controller\Adminhtml\Feed;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

class Index extends Feed implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Dlabsit_XmlFeed::feed');
        $resultPage->getConfig()->getTitle()->prepend(__('XML Feeds'));
        $resultPage->addBreadcrumb(__('XML Feed'), __('XML Feed'));
        $resultPage->addBreadcrumb(__('Feeds'), __('Feeds'));
        return $resultPage;
    }
}
