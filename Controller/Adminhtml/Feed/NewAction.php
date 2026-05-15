<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Controller\Adminhtml\Feed;

use Dlabsit\XmlFeed\Controller\Adminhtml\Feed;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class NewAction extends Feed implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $resultForward->forward('edit');
        return $resultForward;
    }
}
