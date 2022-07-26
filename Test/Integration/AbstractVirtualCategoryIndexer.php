<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration;

/**
 * @magentoDbIsolation enabled
 */
class AbstractVirtualCategoryIndexer extends \PHPUnit\Framework\TestCase
{
    protected ?\Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryProductResource;
    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $catalogCategoryResource;
    protected ?\Magento\Catalog\Model\Category $categoryModel;
    protected ?\Magento\Framework\App\ObjectManager $objectManager;
    protected ?\Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory $previewFactory;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->catalogCategoryProductResource = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\CategoryProduct::class);
        $this->catalogCategoryResource = $this->objectManager->create(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category::class);
        $this->categoryModel = $this->objectManager->create(\Magento\Catalog\Model\Category::class);
        $this->previewFactory = $this->objectManager->create(\Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory::class);
    }

    /**
     * @param int $categoryId
     */
    protected function assertVirtualCategoryAndStandardCategory(int $categoryId): void
    {
        $standardCategoryProducts = $this->getCategoryProducts($categoryId);
        $virtualCategoryProducts = $this->getCategoryProducts($categoryId, true);
        $catalogCategoryProducts = $this->getCatalogCategoryProducts($categoryId);

        $this->assertEquals(
            0,
            $catalogCategoryProducts <=> $standardCategoryProducts,
            'Failed to compare $catalogCategoryProducts and $standardCategoryProducts'
        );

        $this->assertEquals(
            0,
            $catalogCategoryProducts <=> $virtualCategoryProducts,
            'Failed to compare $catalogCategoryProducts and $virtualCategoryProducts'
        );
    }

    /**
     * @return array
     */
    protected function getCatalogCategoryProducts($categoryId): array
    {
        $tableName = $this->catalogCategoryProductResource->getMainTable();
        $connection = $this->catalogCategoryProductResource->getConnection();
        $query = sprintf("SELECT product_id from %s WHERE category_id = %s order by product_id asc", $tableName, $this->categoryModel->getId());

        $productIds = $connection->fetchCol($query);
        sort($productIds);

        return $productIds;
    }

    /**
     * @param int $categoryId
     * @param int $virtualCategoryRootId
     * @throws \Exception
     */
    protected function saveVirtualCategory(int $categoryId, int $virtualCategoryRootId): void
    {
        $storeId = $this->catalogCategoryResource->getFirstStoreId($categoryId);
        $this->categoryModel->setStoreId($storeId)->load($categoryId);
        $this->categoryModel->setIsVirtualCategory(true);
        $this->categoryModel->setVirtualCategoryRoot($virtualCategoryRootId);
        $this->categoryModel->save();
        $this->categoryModel->setIsChangedProductList(true);
    }

    /**
     * @param int $categoryId
     * @param bool $virtualCategory
     * @return array
     */
    protected function getCategoryProducts(int $categoryId, bool $virtualCategory = false): array
    {
        $storeId = $this->catalogCategoryResource->getFirstStoreId($categoryId);
        $this->categoryModel->setStoreId($storeId)->load($categoryId);
        $this->categoryModel->setIsVirtualCategory($virtualCategory);

        return $this->getProductIdsFromElasticSearch();
    }

    /**
     * @return array
     */
    protected function getProductIdsFromElasticSearch(): array
    {
        $previewModel = $this->previewFactory->create(['category' => $this->categoryModel, 'size' => 0, 'search' => '']);
        $size = $previewModel->getRawData()['size'];
        $previewModel = $this->previewFactory->create(['category' => $this->categoryModel, 'size' => $size, 'search' => '']);

        $products = $previewModel->getRawData()['products'];
        $productIds = [];

        foreach ($products as $product) {
            $productIds[] = $product->getId();
        }

        sort($productIds);

        return $productIds;
    }
}
