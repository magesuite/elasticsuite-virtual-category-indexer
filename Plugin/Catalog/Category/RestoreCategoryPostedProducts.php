<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\Catalog\Category;

class RestoreCategoryPostedProducts
{
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\CategoryPostedProductsContainer $categoryPostedProductsContainer;

    public function __construct(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\CategoryPostedProductsContainer $categoryPostedProductsContainer)
    {
        $this->categoryPostedProductsContainer = $categoryPostedProductsContainer;
    }

    public function beforeSave(\Magento\Catalog\Model\ResourceModel\Category $subject, $category)
    {
        $postedProducts = $this->categoryPostedProductsContainer->getPostedProducts();
        $this->categoryPostedProductsContainer->reset();

        if (!$category->getIsVirtualCategory() || empty($postedProducts) || !empty($category->getPostedProducts())) {
            return [$category];
        }

        $category->setPostedProducts($postedProducts);
        return [$category];
    }
}
