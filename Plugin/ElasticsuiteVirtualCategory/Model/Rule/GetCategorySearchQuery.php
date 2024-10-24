<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\ElasticsuiteVirtualCategory\Model\Rule;

class GetCategorySearchQuery
{
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration;

    public function __construct(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function beforeGetCategorySearchQuery(\Smile\ElasticsuiteVirtualCategory\Model\Rule $subject, $category, array $excludedCategories = [])
    {
        if (!is_object($category)) {
            return [$category, $excludedCategories];
        }

        if ($this->configuration->isEnabled()) {
            $this->convertIsVirtualCategoryAttributeToNullIfIsVirtualQueryFalse($category);
        }

        return [$category, $excludedCategories];
    }

    protected function convertIsVirtualCategoryAttributeToNullIfIsVirtualQueryFalse(\Magento\Catalog\Api\Data\CategoryInterface $category): void
    {
        $extensionAttributes = $category->getExtensionAttributes();
        $reindexRequired = $category->getData(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE);

        if (!$extensionAttributes->getVirtualQuery() && !$reindexRequired) {
            $category->setIsVirtualCategory(0);
        }
    }
}
