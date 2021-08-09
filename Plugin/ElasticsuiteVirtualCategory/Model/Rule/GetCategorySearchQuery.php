<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\ElasticsuiteVirtualCategory\Model\Rule;

class GetCategorySearchQuery
{
    /**
     * @param \Smile\ElasticsuiteVirtualCategory\Model\Rule $subject
     * @param $category
     * @param array $excludedCategories
     * @return array
     */
    public function beforeGetCategorySearchQuery(
        \Smile\ElasticsuiteVirtualCategory\Model\Rule $subject,
        $category,
        array $excludedCategories = []
    ) {
        $extensiionAttributes = $category->getExtensionAttributes();

        $reindexRequired = $category->getData(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE);

        if (!$extensiionAttributes->getVirtualQuery() || $reindexRequired) {
            $category->setIsVirtualCategory(null);
        }

        return [$category, $excludedCategories];
    }
}
