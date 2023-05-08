<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\ElasticsuiteVirtualCategory\Plugin\Catalog\Category\SaveProductsPositions;

/**
 * Elasticsuite resets assigned products to category in plugin \Smile\ElasticsuiteVirtualCategory\Plugin\Catalog\Category\SaveProductsPositions.
 * That removes previously indexed category-product relations and an additional reindex is necessary.
 * To avoid this, we restore the assigned products that elasticsuite has removed.
 */
class StoreCategoryPostedProducts
{
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration;

    protected \Smile\ElasticsuiteVirtualCategory\Model\Category\Attribute\VirtualRule\SaveHandler $saveHandler;

    protected \Magento\Framework\Serialize\Serializer\Json $jsonSerializer;

    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\CategoryPostedProductsContainer $categoryPostedProductsContainer;

    public function __construct(
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration,
        \Smile\ElasticsuiteVirtualCategory\Model\Category\Attribute\VirtualRule\SaveHandler $saveHandler,
        \Magento\Framework\Serialize\Serializer\Json $jsonSerializer,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\CategoryPostedProductsContainer $categoryPostedProductsContainer
    ) {
        $this->configuration = $configuration;
        $this->saveHandler = $saveHandler;
        $this->jsonSerializer = $jsonSerializer;
        $this->categoryPostedProductsContainer = $categoryPostedProductsContainer;
    }

    public function beforeAroundSave(
        \Smile\ElasticsuiteVirtualCategory\Plugin\Catalog\Category\SaveProductsPositions $subject,
        \Magento\Catalog\Model\ResourceModel\Category $categoryResource,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $category
    ) {
        if (!$this->configuration->isEnabled() || !$category->getIsVirtualCategory() || empty($category->getPostedProducts())) {
            return [$categoryResource, $proceed, $category];
        }

        $virtualCategoryRootChanged = $category->getOrigData('virtual_category_root') <=> $category->getData('virtual_category_root');
        $shouldBeReindex = $virtualCategoryRootChanged || $this->isVirtualRuleChanged($category);

        if (!$shouldBeReindex) {
            $this->categoryPostedProductsContainer->setPostedProducts($category->getPostedProducts());
        }

        return [$categoryResource, $proceed, $category];
    }

    protected function isVirtualRuleChanged($category)
    {
        $clonedCategory = clone $category;
        $this->saveHandler->execute($clonedCategory);

        return $category->getOrigData('virtual_rule') <=> $clonedCategory->getData('virtual_rule');
    }
}
