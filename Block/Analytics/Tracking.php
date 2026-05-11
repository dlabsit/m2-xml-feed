<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Block\Analytics;

use Dlabsit\XmlFeed\Helper\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Tracking extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        $storeId = (int) $this->_storeManager->getStore()->getId();
        return $this->config->isAnalyticsEnabled($storeId);
    }

    public function getShopAccountId(): string
    {
        $storeId = (int) $this->_storeManager->getStore()->getId();
        return $this->config->getShopAccountId($storeId);
    }

    protected function _toHtml(): string
    {
        if (!$this->isEnabled() || $this->getShopAccountId() === '') {
            return '';
        }
        return parent::_toHtml();
    }
}
