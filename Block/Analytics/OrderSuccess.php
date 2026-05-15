<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Block\Analytics;

use Dlabsit\XmlFeed\Helper\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class OrderSuccess extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        $storeId = (int) $this->_storeManager->getStore()->getId();
        return $this->config->isAnalyticsEnabled($storeId)
            && $this->config->getShopAccountId($storeId) !== '';
    }

    public function getOrder(): ?Order
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    public function getOrderData(): ?array
    {
        $order = $this->getOrder();
        if (!$order || !$order->getId()) {
            return null;
        }

        $revenue = (float) $order->getGrandTotal();
        $shipping = (float) $order->getShippingAmount();
        $tax = (float) $order->getTaxAmount();

        $payment = $order->getPayment();
        if ($payment && $payment->getMethod() === 'cashondelivery') {
            $codFee = (float) $order->getData('cod_fee');
            $revenue -= $codFee;
            $shipping -= $codFee;
        }

        return [
            'order_id' => $order->getIncrementId(),
            'revenue' => number_format($revenue, 2, '.', ''),
            'shipping' => number_format($shipping, 2, '.', ''),
            'tax' => number_format($tax, 2, '.', ''),
            'paid_by' => $this->mapPaymentMethod($order),
            'paid_by_descr' => $order->getPayment()?->getMethodInstance()->getTitle() ?? '',
        ];
    }

    public function getOrderItems(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }
        $storeId = (int) $this->_storeManager->getStore()->getId();
        $items = [];

        foreach ($order->getAllVisibleItems() as $item) {
            $productId = $this->resolveProductId($item, $storeId);
            $items[] = [
                'order_id' => $order->getIncrementId(),
                'product_id' => $productId,
                'name' => $item->getName(),
                'price' => number_format((float) $item->getPriceInclTax(), 2, '.', ''),
                'quantity' => (int) $item->getQtyOrdered(),
            ];
        }
        return $items;
    }

    private function resolveProductId(\Magento\Sales\Model\Order\Item $item, int $storeId): string
    {
        $source = $this->config->getUniqueIdSource($storeId);

        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $parentId = match ($source) {
                'sku' => $item->getSku(),
                default => (string) $item->getProductId(),
            };

            $childItem = $this->getChildItem($item);
            if ($childItem) {
                $product = $childItem->getProduct();
                if ($product) {
                    $colorAttr = $this->config->getColorAttribute($storeId);
                    $colorValue = $product->getData($colorAttr);
                    if ($colorValue) {
                        return $parentId . '-' . $colorValue;
                    }
                }
            }
            return $parentId;
        }

        return match ($source) {
            'sku' => $item->getSku(),
            default => (string) $item->getProductId(),
        };
    }

    private function getChildItem(\Magento\Sales\Model\Order\Item $item): ?\Magento\Sales\Model\Order\Item
    {
        $children = $item->getChildrenItems();
        return !empty($children) ? reset($children) : null;
    }

    private function mapPaymentMethod(Order $order): string
    {
        $method = $order->getPayment()?->getMethod() ?? '';
        if (str_contains($method, 'paypal')) {
            return 'paypal';
        }
        return match ($method) {
            'cashondelivery' => 'cash_on_delivery',
            'banktransfer' => 'bank_transfer',
            'checkmo' => 'bank_transfer',
            default => 'card',
        };
    }

    protected function _toHtml(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }
        return parent::_toHtml();
    }
}
