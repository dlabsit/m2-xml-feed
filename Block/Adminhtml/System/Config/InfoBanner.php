<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Block\Adminhtml\System\Config;

use Dlabsit\Core\Block\Adminhtml\System\Config\InfoBanner as BaseInfoBanner;

class InfoBanner extends BaseInfoBanner
{
    protected function getModuleCode(): string
    {
        return 'xmlfeed';
    }
}
