<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration;

/**
 * @magentoDbIsolation enabled
 */
class CategoryTreeProductsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    protected $objectManager;
    /**
     * @var \Magento\Catalog\Model\CategoryRepository
     */
    protected $categoryRepository;
    /**
     * @var \Magento\Indexer\Model\IndexerFactory
     */
    protected $indexerFactory;
    /**
     * @var \Magento\Framework\EntityManager\MetadataPool
     */
    protected $metadataPool;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->categoryRepository = $this->objectManager->create(\Magento\Catalog\Model\CategoryRepository::class);
        $this->indexerFactory = $this->objectManager->create(\Magento\Indexer\Model\IndexerFactory::class);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDataFixture MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_parent_and_products.php
     * @magentoConfigFixture virtual_category_indexer/general/enabled 1
     */
    public function testTestParentCategories()
    {
        $category = $this->categoryRepository->get(5);
        $productsIds = $category->getProductCollection()->getAllIds();

        $categoryIds = explode('/', $category->getPath());

        foreach ($categoryIds as $categoryId) {

            if ($categoryId == $category->getId()) {
                continue;
            }

            $parent = $this->categoryRepository->get($categoryId);

            if ($parent->getParentId() <= 1) {
                continue;
            }

            $indexer = $this->indexerFactory->create();
            $indexer->load(\Magento\Catalog\Model\Indexer\Category\Product::INDEXER_ID);
            $indexer->reindexRow($categoryId);

            $parentCollection = $parent->getProductCollection()->addAttributeToFilter('entity_id', ['in' => $productsIds]);

            static::assertEquals($productsIds, $parentCollection->getAllIds(), 'Virtual products not exists in parent category');
        }
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDataFixture MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_parent_and_products.php
     * @magentoConfigFixture virtual_category_indexer/general/enabled 1
     */
    public function testDisableVirtualCategory()
    {
        $category = $this->categoryRepository->get(5);
        $productsIds = $category->getProductCollection()->getAllIds();

        $category->setIsActive(false);
        $category->getResource()->saveAttribute($category, 'is_active');
        $category->setStoreId(0);
        $category->getResource()->saveAttribute($category, 'is_active');
        $category->setStoreId(1);

        $indexer = $this->indexerFactory->create();
        $indexer->load(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::INDEXER_ID);
        $indexer->reindexRow(5);

        $categoryIds = explode('/', $category->getPath());

        foreach ($categoryIds as $categoryId) {

            if ($categoryId == $category->getId()) {
                continue;
            }

            $parent = $this->categoryRepository->get($categoryId);

            if ($parent->getParentId() <= 1) {
                continue;
            }

            $indexer = $this->indexerFactory->create();
            $indexer->load(\Magento\Catalog\Model\Indexer\Category\Product::INDEXER_ID);
            $indexer->reindexRow($categoryId);

            $parentCollection = $parent->getProductCollection()
                ->addAttributeToFilter('entity_id', ['in' => $productsIds]);

            static::assertNotEquals($productsIds, $parentCollection->getAllIds(), 'Virtual products exists in parent category but shouldn\'t');
        }
    }
}
