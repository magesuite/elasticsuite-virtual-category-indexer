<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\Model\ResourceModel\Category;

class Collection extends \Magento\Catalog\Model\ResourceModel\Category\Collection
{
    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAllVirtualCategoryIds(): array
    {
        $this->addAttributeToFilter('is_virtual_category', ['eq' => 1])
            ->addAttributeToFilter('is_active', 1);

        return $this->getAllIds();
    }
}
