<?php

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Block\Adminhtml\Category\Edit;

class ReindexButton extends \Magento\Catalog\Block\Adminhtml\Category\AbstractCategory implements \Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface
{
    /**
     * Save button
     *
     * @return array
     */
    public function getButtonData()
    {
        $category = $this->getCategory();

        if (!$category->isReadonly() && $this->hasStoreRootCategory()) {
            return [
                'label' => __('Reindex'),
                'class' => 'reindex-button',
                'on_click' => 'return false;',
                'data_attribute' => [
                    'mage-init' => [
                        'buttonAdapter' => [
                            'actions' => [
                                [
                                    'targetName' => 'category_form.category_form_data_source',
                                    'actionName' => 'reindexVirtualCategory',
                                    'params' => [
                                        false,
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'form-role' => 'reindex',
                    'bind' => [
                        'fadeVisible:$(\'input[name="is_virtual_category:checked"].checked'
                    ]
                ],
                'sort_order' => 30
            ];
        }

        return [];
    }
}
