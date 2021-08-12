<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\ElasticsuiteVirtualCategory\Model\Rule;

class GetCategorySearchQuery
{
    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration
     */
    protected $configuration;

    public function __construct(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param \Smile\ElasticsuiteVirtualCategory\Model\Rule $subject
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @param array $excludedCategories
     * @return array
     */
    public function beforeGetCategorySearchQuery(
        \Smile\ElasticsuiteVirtualCategory\Model\Rule $subject,
        \Magento\Catalog\Api\Data\CategoryInterface $category,
        array $excludedCategories = []
    ) {
        if ($this->configuration->isEnabled()) {
            $this->convertIsVirtualCategoryAttributeToNullIfIsVirtualQueryFalse($category);
        }

        return [$category, $excludedCategories];
    }

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     */
    protected function convertIsVirtualCategoryAttributeToNullIfIsVirtualQueryFalse(\Magento\Catalog\Api\Data\CategoryInterface $category): void
    {
        $extensionAttributes = $category->getExtensionAttributes();
        $reindexRequired = $category->getData(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE);

        if (!$extensionAttributes->getVirtualQuery() && !$reindexRequired) {
            $category->setIsVirtualCategory(null);
        }
    }
}
