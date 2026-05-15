<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Block\Adminhtml\Feed;

use Dlabsit\XmlFeed\Model\ResourceModel\Feed\CollectionFactory;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data as BackendHelper;

class Grid extends Extended
{
    public function __construct(
        Context $context,
        BackendHelper $backendHelper,
        private readonly CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $backendHelper, $data);
    }

    protected function _construct(): void
    {
        parent::_construct();
        $this->setId('dlabsit_xmlfeed_feed_grid');
        $this->setDefaultSort('sort_order');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection(): self
    {
        $collection = $this->collectionFactory->create();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns(): self
    {
        $this->addColumn('feed_id', [
            'header' => __('ID'),
            'index' => 'feed_id',
            'type' => 'number',
            'width' => '60px',
        ]);

        $this->addColumn('name', [
            'header' => __('Name'),
            'index' => 'name',
        ]);

        $this->addColumn('slug', [
            'header' => __('URL Slug'),
            'index' => 'slug',
            'frame_callback' => [$this, 'decorateSlug'],
        ]);

        $this->addColumn('channel_code', [
            'header' => __('Channel'),
            'index' => 'channel_code',
            'type' => 'options',
            'options' => $this->getChannelOptions(),
        ]);

        $this->addColumn('store_id', [
            'header' => __('Store ID'),
            'index' => 'store_id',
            'type' => 'number',
            'width' => '80px',
        ]);

        $this->addColumn('is_active', [
            'header' => __('Active'),
            'index' => 'is_active',
            'type' => 'options',
            'width' => '80px',
            'options' => [1 => __('Yes'), 0 => __('No')],
        ]);

        $this->addColumn('filename', [
            'header' => __('Filename'),
            'index' => 'filename',
        ]);

        $this->addColumn('action', [
            'header' => __('Action'),
            'width' => '260px',
            'type' => 'action',
            'getter' => 'getFeedId',
            'actions' => [
                [
                    'caption' => __('Edit'),
                    'url' => ['base' => '*/*/edit'],
                    'field' => 'feed_id',
                ],
                [
                    'caption' => __('Generate Now'),
                    'url' => ['base' => '*/*/generate'],
                    'field' => 'feed_id',
                ],
                [
                    'caption' => __('Delete'),
                    'url' => ['base' => '*/*/delete'],
                    'field' => 'feed_id',
                    'confirm' => __('Delete this feed? This cannot be undone.'),
                ],
            ],
            'filter' => false,
            'sortable' => false,
            'is_system' => true,
        ]);

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('feed_id');
        $this->getMassactionBlock()->setFormFieldName('feed_ids');

        $this->getMassactionBlock()->addItem('delete', [
            'label' => __('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => __('Delete the selected feeds?'),
        ]);

        $this->getMassactionBlock()->addItem('activate', [
            'label' => __('Enable'),
            'url' => $this->getUrl('*/*/massStatus', ['status' => 1]),
        ]);

        $this->getMassactionBlock()->addItem('deactivate', [
            'label' => __('Disable'),
            'url' => $this->getUrl('*/*/massStatus', ['status' => 0]),
        ]);

        return parent::_prepareMassaction();
    }

    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['feed_id' => $row->getFeedId()]);
    }

    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/index', ['_current' => true]);
    }

    public function decorateSlug(string $value, \Dlabsit\XmlFeed\Model\Feed $row): string
    {
        return '<code>/feed/' . $this->escapeHtml($value) . '</code>';
    }

    /**
     * @return array<string, string>
     */
    private function getChannelOptions(): array
    {
        return [
            'skroutz' => 'Skroutz.gr',
            'google' => 'Google Shopping',
            'facebook' => 'Facebook / Meta',
            'bing' => 'Bing Shopping',
            'bestprice' => 'Bestprice.gr',
            'pricerunner' => 'Pricerunner',
            'idealo' => 'Idealo',
            'ceneo' => 'Ceneo.pl',
            'kelkoo' => 'Kelkoo',
            'shopflix' => 'Shopflix.gr',
            'emag' => 'eMAG Marketplace',
        ];
    }
}
