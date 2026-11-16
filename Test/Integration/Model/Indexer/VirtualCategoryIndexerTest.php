<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Model\Indexer;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[\Magento\TestFramework\Fixture\DbIsolation(true)]
#[\Magento\TestFramework\Fixture\AppArea(\Magento\Framework\App\Area::AREA_ADMINHTML)]
class VirtualCategoryIndexerTest extends \PHPUnit\Framework\TestCase
{
    protected const SOURCE_CATEGORY_ID = 4441;
    protected const VIRTUAL_ROOT_ID = 4442;
    protected const CATEGORY_UNDER_VIRTUAL_ROOT_ID = 4443;

    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer $virtualCategoryIndexer;
    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct $categoryProductResource;
    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResource;
    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule\CategorySearchQueryCache $categorySearchQueryCache;
    protected ?\Magento\Catalog\Api\ProductRepositoryInterface $productRepository;
    protected ?\Magento\Catalog\Model\CategoryFactory $categoryFactory;
    protected ?\Magento\Framework\App\CacheInterface $cache;
    protected ?\Magento\Framework\Serialize\Serializer\Serialize $phpSerializer;
    protected ?\Smile\ElasticsuiteCore\Search\Request\Query\QueryFactory $queryFactory;
    protected ?\Smile\ElasticsuiteCore\Api\Search\ContextInterface $searchContext;
    protected ?\Smile\ElasticsuiteCatalog\Model\Product\Search\Request\Container\Filter\CurrentCategory $currentCategoryFilter;

    protected function setUp(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->virtualCategoryIndexer = $objectManager->create(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::class);
        $this->categoryProductResource = $objectManager->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct::class);
        $this->categoryResource = $objectManager->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category::class);
        $this->categorySearchQueryCache = $objectManager->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule\CategorySearchQueryCache::class);
        $this->productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $this->categoryFactory = $objectManager->get(\Magento\Catalog\Model\CategoryFactory::class);
        $this->cache = $objectManager->get(\Magento\Framework\App\CacheInterface::class);
        $this->phpSerializer = $objectManager->get(\Magento\Framework\Serialize\Serializer\Serialize::class);
        $this->queryFactory = $objectManager->get(\Smile\ElasticsuiteCore\Search\Request\Query\QueryFactory::class);
        $this->searchContext = $objectManager->get(\Smile\ElasticsuiteCore\Api\Search\ContextInterface::class);
        $this->currentCategoryFilter = $objectManager->get(\Smile\ElasticsuiteCatalog\Model\Product\Search\Request\Container\Filter\CurrentCategory::class);
    }

    #[\Magento\TestFramework\Fixture\Config('virtual_category_indexer/general/enabled', 1)]
    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction('MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_virtual_root.php')]
    public function testItMaterializesAVirtualRootFromItsRuleAfterADependentCategoryLookedItUpInTheSameRun(): void
    {
        $this->virtualCategoryIndexer->executeList([self::CATEGORY_UNDER_VIRTUAL_ROOT_ID, self::VIRTUAL_ROOT_ID]);

        $this->assertContains($this->getSourceProductId(), $this->getLinkedProductIds(self::VIRTUAL_ROOT_ID));
    }

    #[\Magento\TestFramework\Fixture\Config('virtual_category_indexer/general/enabled', 1)]
    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction('MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_virtual_root.php')]
    public function testItIgnoresAStaleSharedQueryOfTheSourceCategoryOnlyWhileTheRunIsInProgress(): void
    {
        $storeId = $this->categoryResource->getFirstStoreId(self::VIRTUAL_ROOT_ID);
        $staleQuery = $this->createNoResultsQuery();
        $this->saveSharedQuery(self::SOURCE_CATEGORY_ID, $storeId, $staleQuery);

        $this->virtualCategoryIndexer->executeList([self::VIRTUAL_ROOT_ID]);

        $this->assertContains($this->getSourceProductId(), $this->getLinkedProductIds(self::VIRTUAL_ROOT_ID), 'The run must not read the stale shared query.');
        $this->categorySearchQueryCache->resetLocalCache();
        $sourceCategory = $this->loadCategory(self::SOURCE_CATEGORY_ID, $storeId);
        $this->assertEquals($staleQuery, $sourceCategory->getVirtualRule()->getCategorySearchQuery($sourceCategory), 'The storefront must read the shared cache once the run is finished.');
    }

    #[\Magento\TestFramework\Fixture\AppArea(\Magento\Framework\App\Area::AREA_FRONTEND)]
    #[\Magento\TestFramework\Fixture\Config('virtual_category_indexer/general/enabled', 1)]
    #[\Magento\TestFramework\Fixture\Config('catalog/layered_navigation/display_category', 0, 'store', 'default')]
    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction('MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_virtual_root.php')]
    public function testItIgnoresAStaleSharedQueryOfTheIndexedCategoryWhenTheStorefrontFiltersByTheCurrentCategory(): void
    {
        $storeId = $this->categoryResource->getFirstStoreId(self::VIRTUAL_ROOT_ID);
        $staleQuery = $this->createNoResultsQuery();
        $this->saveSharedQuery(self::VIRTUAL_ROOT_ID, $storeId, $staleQuery);
        $this->assertEquals($staleQuery, $this->getCurrentCategoryFilterQuery(self::VIRTUAL_ROOT_ID, $storeId), 'The storefront filter must read the stale shared query.');

        $this->virtualCategoryIndexer->executeList([self::VIRTUAL_ROOT_ID]);

        $this->assertContains($this->getSourceProductId(), $this->getLinkedProductIds(self::VIRTUAL_ROOT_ID), 'The run must not filter by the stale shared query.');
    }

    protected function createNoResultsQuery(): \Smile\ElasticsuiteCore\Search\Request\QueryInterface
    {
        return $this->queryFactory->create(\Smile\ElasticsuiteCore\Search\Request\QueryInterface::TYPE_TERMS, ['field' => 'entity_id', 'values' => [0]]);
    }

    protected function saveSharedQuery(int $categoryId, int $storeId, \Smile\ElasticsuiteCore\Search\Request\QueryInterface $query): void
    {
        $this->cache->save(
            $this->phpSerializer->serialize($query),
            sprintf('getCategorySearchQuery|%d|%d|0|1', $storeId, $categoryId),
            [sprintf('%s_%d', \Magento\Catalog\Model\Category::CACHE_TAG, $categoryId)]
        );
    }

    protected function loadCategory(int $categoryId, int $storeId): \Magento\Catalog\Model\Category
    {
        return $this->categoryFactory->create()->setStoreId($storeId)->load($categoryId);
    }

    protected function getCurrentCategoryFilterQuery(int $categoryId, int $storeId): ?\Smile\ElasticsuiteCore\Search\Request\QueryInterface
    {
        $this->searchContext->setCurrentCategory($this->loadCategory($categoryId, $storeId));

        return $this->currentCategoryFilter->getFilterQuery();
    }

    protected function getSourceProductId(): int
    {
        return (int)$this->productRepository->get('virtual_root_source_product')->getId();
    }

    protected function getLinkedProductIds(int $categoryId): array
    {
        return array_map('intval', $this->categoryProductResource->getProductsIds($categoryId));
    }
}
