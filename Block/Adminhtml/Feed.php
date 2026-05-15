<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Block\Adminhtml;

use Magento\Backend\Block\Widget\Grid\Container;

class Feed extends Container
{
    protected function _construct(): void
    {
        $this->_controller = 'adminhtml_feed';
        $this->_blockGroup = 'Dlabsit_XmlFeed';
        $this->_headerText = __('XML Feeds');
        $this->_addButtonLabel = __('Add New Feed');
        parent::_construct();
    }
}
