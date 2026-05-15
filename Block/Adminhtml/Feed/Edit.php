<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Block\Adminhtml\Feed;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;

class Edit extends Container
{
    public function __construct(
        Context $context,
        private readonly Registry $coreRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _construct(): void
    {
        $this->_objectId = 'feed_id';
        $this->_controller = 'adminhtml_feed';
        $this->_blockGroup = 'Dlabsit_XmlFeed';
        parent::_construct();

        $this->buttonList->update('save', 'label', __('Save Feed'));
        $this->buttonList->add(
            'save_and_continue',
            [
                'label' => __('Save and Continue Edit'),
                'class' => 'save',
                'data_attribute' => [
                    'mage-init' => [
                        'button' => ['event' => 'saveAndContinueEdit', 'target' => '#edit_form'],
                    ],
                ],
            ],
            -100
        );
    }

    public function getHeaderText(): \Magento\Framework\Phrase
    {
        $feed = $this->coreRegistry->registry('dlabsit_xmlfeed_current_feed');
        if ($feed && $feed->getFeedId()) {
            return __('Edit Feed "%1"', $feed->getName());
        }
        return __('New Feed');
    }
}
