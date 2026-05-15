<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

abstract class Feed extends Action
{
    public const ADMIN_RESOURCE = 'Dlabsit_XmlFeed::feed';

    public function __construct(
        Context $context,
        protected readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }
}
