<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Block\Adminhtml\Feed\Edit;

use Dlabsit\XmlFeed\Model\Feed;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Dlabsit\XmlFeed\Model\Config\Source\CategoryTree;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Store\Model\System\Store as SystemStore;

class Form extends Generic
{
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        private readonly Yesno $yesno,
        private readonly SystemStore $systemStore,
        private readonly CategoryTree $categorySource,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    protected function _prepareForm(): self
    {
        /** @var Feed|null $feed */
        $feed = $this->_coreRegistry->registry('dlabsit_xmlfeed_current_feed');
        $sessionData = (array) $this->_backendSession->getDlabsitXmlfeedFormData(true);

        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'action' => $this->getUrl('*/*/save'),
                'method' => 'post',
                'enctype' => 'multipart/form-data',
            ],
        ]);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Feed'), 'class' => 'fieldset-wide']);

        if ($feed && $feed->getFeedId()) {
            $fieldset->addField('feed_id', 'hidden', ['name' => 'feed_id']);
        }

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => __('Name'),
            'title' => __('Name'),
            'required' => true,
            'note' => __('Display name (internal use, not sent to the channel).'),
        ]);

        $fieldset->addField('slug', 'text', [
            'name' => 'slug',
            'label' => __('URL Slug'),
            'title' => __('URL Slug'),
            'required' => true,
            'note' => __('Served at /feed/&lt;slug&gt;. Use a-z, 0-9 and dashes only (e.g. google-adult).'),
        ]);

        $fieldset->addField('channel_code', 'select', [
            'name' => 'channel_code',
            'label' => __('Channel'),
            'title' => __('Channel'),
            'required' => true,
            'values' => $this->getChannelOptions(),
        ]);

        $fieldset->addField('store_id', 'select', [
            'name' => 'store_id',
            'label' => __('Store View'),
            'title' => __('Store View'),
            'required' => true,
            'values' => $this->systemStore->getStoreValuesForForm(false, true),
        ]);

        $fieldset->addField('is_active', 'select', [
            'name' => 'is_active',
            'label' => __('Active'),
            'title' => __('Active'),
            'values' => $this->yesno->toOptionArray(),
        ]);

        $fieldset->addField('filename', 'text', [
            'name' => 'filename',
            'label' => __('Filename'),
            'title' => __('Filename'),
            'note' => __('Output file under pub/media/xmlfeed/. Defaults to slug.xml if empty.'),
        ]);

        $fieldset->addField('sort_order', 'text', [
            'name' => 'sort_order',
            'label' => __('Sort Order'),
            'title' => __('Sort Order'),
            'class' => 'validate-digits',
        ]);

        $filter = $form->addFieldset('filter_fieldset', ['legend' => __('Category Filter')]);

        $filter->addField('filter_mode', 'select', [
            'name' => 'filter_mode',
            'label' => __('Filter Mode'),
            'title' => __('Filter Mode'),
            'values' => [
                ['value' => 'all',     'label' => __('All Categories')],
                ['value' => 'include', 'label' => __('Include Selected Only')],
                ['value' => 'exclude', 'label' => __('Exclude Selected')],
            ],
        ]);

        $filter->addField('category_ids', 'multiselect', [
            'name' => 'category_ids[]',
            'label' => __('Categories'),
            'title' => __('Categories'),
            'values' => $this->categorySource->toOptionArray(),
            'note' => __('Used by Include or Exclude modes; ignored when "All Categories" is selected.'),
        ]);

        $channel = $form->addFieldset('channel_settings_fieldset', ['legend' => __('Channel Settings')]);

        $channel->addField('channel_settings', 'textarea', [
            'name' => 'channel_settings',
            'label' => __('Settings (JSON)'),
            'title' => __('Settings (JSON)'),
            'note' => __(
                'Channel-specific overrides as JSON, e.g. {"general/store_name":"My Shop","shipping/country":"GR","shipping/price":"3.50"}. '
                . 'Keys use <em>group/field</em> format matching the legacy config paths.'
            ),
            'style' => 'height: 240px; font-family: monospace;',
        ]);

        $values = $this->buildValues($feed, $sessionData);
        $form->setValues($values);
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getChannelOptions(): array
    {
        return [
            ['value' => 'skroutz',     'label' => 'Skroutz.gr'],
            ['value' => 'google',      'label' => 'Google Shopping'],
            ['value' => 'facebook',    'label' => 'Facebook / Meta Catalog'],
            ['value' => 'bing',        'label' => 'Bing Shopping'],
            ['value' => 'bestprice',   'label' => 'Bestprice.gr'],
            ['value' => 'pricerunner', 'label' => 'Pricerunner'],
            ['value' => 'idealo',      'label' => 'Idealo'],
            ['value' => 'ceneo',       'label' => 'Ceneo.pl'],
            ['value' => 'kelkoo',      'label' => 'Kelkoo'],
            ['value' => 'shopflix',    'label' => 'Shopflix.gr'],
            ['value' => 'emag',        'label' => 'eMAG Marketplace'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildValues(?Feed $feed, array $sessionData): array
    {
        if (!empty($sessionData)) {
            return $sessionData;
        }
        if ($feed && $feed->getFeedId()) {
            $data = $feed->getData();
            // Rebuild JSON textarea from the array form
            $data['channel_settings'] = json_encode(
                $feed->getChannelSettings(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            // Convert the stored "1,2,3" string into an array so the
            // multiselect field pre-selects the right rows.
            $data['category_ids'] = $feed->getCategoryIds();
            return $data;
        }
        return [
            'is_active' => 1,
            'filter_mode' => 'all',
            'sort_order' => 10,
            'store_id' => 0,
            'channel_settings' => '{}',
        ];
    }
}
