<?php
/**
 * Dlabsit XML Feed Module for Magento 2
 *
 * Pluggable product feed generator. Supports Skroutz.gr, Google Shopping,
 * Facebook/Meta Catalog, Bestprice.gr, Pricerunner.
 *
 * @category   Dlabsit
 * @package    Dlabsit_XmlFeed
 * @copyright  Copyright (c) 2026 Dlabsit (https://github.com/dlabsit)
 * @license    Open Software License ("OSL") v. 3.0 — https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Dlabsit_XmlFeed', __DIR__);
