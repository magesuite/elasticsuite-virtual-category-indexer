<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration;

class VirtualCategoryIndexerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    protected $objectManager;

    /** @var \Magento\Catalog\Model\Category */
    private $categoryModel;

    /** @var \Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory */
    private $previewFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\CategoryProduct
     */
    protected $catalogCategoryProductResource;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->categoryModel = $this->objectManager->create(\Magento\Catalog\Model\Category::class);
        $this->previewFactory = $this->objectManager->create(\Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory::class);
        $this->catalogCategoryProductResource = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\CategoryProduct::class);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDataFixture Magento/Catalog/_files/category.php
     * @magentoDbIsolation enabled
     * @return void
     */
    public function testReindexRow()
    {
        $categoryId = 2;
        $virtualCategoryRootId = 1;

        $this->saveVirtualCategory($categoryId, $virtualCategoryRootId);

        $standardCategoryProducts = $this->getStandardCategoryProducts($categoryId);
        $virtualCategoryProducts = $this->getVirtualCategoryProducts($categoryId);
        $catalogCategoryProducts = $this->getCatalogCategoryProducts($categoryId);

        $this->assertEquals(
            0,
            $catalogCategoryProducts <=> $standardCategoryProducts,
            'Fail when compare $catalogCategoryProducts and $standardCategoryProducts'
        );

        $this->assertEquals(
            0,
            $catalogCategoryProducts <=> $virtualCategoryProducts,
            'Fail when compare $catalogCategoryProducts and $virtualCategoryProducts'
        );
    }

    /**
     * @return int
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
        $this->categoryModel->setStoreId(1)->load($categoryId);
        $this->categoryModel->setIsVirtualCategory(true);
        $this->categoryModel->setVirtualCategoryRoot($virtualCategoryRootId);
        $this->categoryModel->save();
        $this->categoryModel->setIsChangedProductList(true);
        $this->categoryModel->reindex();
    }

    /**
     * @return int
     */
    protected function getStandardCategoryProducts($categoryId): array
    {
        $this->categoryModel->setStoreId(1)->load($categoryId);
        $this->categoryModel->setIsVirtualCategory(false);

        return $this->getProductIdsFromElasticSearch();
    }

    /**
     * @param int $categoryId
     * @return int
     */
    protected function getVirtualCategoryProducts(int $categoryId): array
    {
        $this->categoryModel->setStoreId(1)->load($categoryId);
        $this->categoryModel->setIsVirtualCategory(true);

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
