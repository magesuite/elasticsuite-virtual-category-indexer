<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Plugin\Catalog\Category;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class RestoreCategoryPostedProductsTest extends \PHPUnit\Framework\TestCase
{
    protected ?\Magento\Catalog\Model\CategoryRepository $categoryRepository;

    protected ?\Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryProductResource;

    protected ?\Magento\Catalog\Model\ResourceModel\Category $categoryResource;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->categoryRepository = $this->objectManager->create(\Magento\Catalog\Model\CategoryRepository::class);
        $this->catalogCategoryProductResource = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\CategoryProduct::class);
        $this->categoryResource = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\Category::class);
    }

    /**
     * @magentoDataFixture MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_parent_and_products.php
     * @magentoConfigFixture virtual_category_indexer/general/enabled 1
     */
    public function testItDoesNotRemoveAssignedProductsIfNotNeeded()
    {
        $virtualCategoryId = 5;
        $virtualCategory = $this->categoryRepository->get($virtualCategoryId);

        $assignedProductsCount = count($virtualCategory->getProductsPosition());
        $this->assertEquals($assignedProductsCount, $this->getAssignedProductsToCategoryCount($virtualCategoryId));

        $virtualCategory->setPostedProducts($virtualCategory->getProductsPosition());
        $virtualCategory->setSortedProducts('{}');

        $this->categoryResource->save($virtualCategory);
        $this->assertEquals($assignedProductsCount, $this->getAssignedProductsToCategoryCount($virtualCategoryId));

        $virtualCategory->setPostedProducts($virtualCategory->getProductsPosition());
        $virtualCategory->setSortedProducts('{}');
        $virtualCategory->setVirtualCategoryRoot($virtualCategoryId);

        $this->categoryResource->save($virtualCategory);
        $this->assertEquals(0, $this->getAssignedProductsToCategoryCount($virtualCategoryId));
    }

    protected function getAssignedProductsToCategoryCount($categoryId): int
    {
        $tableName = $this->catalogCategoryProductResource->getMainTable();
        $connection = $this->catalogCategoryProductResource->getConnection();

        $query = sprintf("SELECT COUNT(*) FROM %s WHERE category_id = %s", $tableName, $categoryId);

        return (int)$connection->fetchOne($query);
    }
}
