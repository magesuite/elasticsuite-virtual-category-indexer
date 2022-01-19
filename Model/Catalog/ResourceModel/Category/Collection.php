<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category;

class Collection extends \Magento\Catalog\Model\ResourceModel\Category\Collection
{
    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAllVirtualCategoryIds(): array
    {
        $this->addAttributeToFilter('is_virtual_category', ['eq' => 1]);

        return $this->getAllIds();
    }
}
