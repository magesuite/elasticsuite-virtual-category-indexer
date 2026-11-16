<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Model\ElasticsuiteVirtualCategory\Model\Rule;

#[\Magento\TestFramework\Fixture\DbIsolation(true)]
#[\Magento\TestFramework\Fixture\AppArea(\Magento\Framework\App\Area::AREA_ADMINHTML)]
class CategorySearchQueryCacheTest extends \PHPUnit\Framework\TestCase
{
    protected ?\Magento\Framework\ObjectManagerInterface $objectManager;
    protected ?\Magento\TestFramework\Fixture\DataFixtureStorage $fixtures;
    protected ?\Magento\Framework\App\CacheInterface $cache;
    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule\CategorySearchQueryCache $categorySearchQueryCache;

    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->fixtures = \Magento\TestFramework\Fixture\DataFixtureStorageManager::getStorage();
        $this->cache = $this->objectManager->get(\Magento\Framework\App\CacheInterface::class);
        $this->categorySearchQueryCache = $this->objectManager->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule\CategorySearchQueryCache::class);
    }

    protected function tearDown(): void
    {
        $this->categorySearchQueryCache->finishBatch();
    }

    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'category')]
    public function testItKeepsQueriesOutOfTheSharedCacheOnlyWhileTheBatchIsInProgress(): void
    {
        $categoryId = (int)$this->fixtures->get('category')->getId();
        $cacheIdentifier = sprintf('getCategorySearchQuery|%d|%d|0|1', $this->getDefaultStoreId(), $categoryId);
        $this->categorySearchQueryCache->startBatch();

        $this->getStandardSearchQuery($categoryId, $this->getDefaultStoreId());

        $this->assertFalse($this->cache->load($cacheIdentifier), 'Queries built during the batch must not reach the shared cache.');
        $this->categorySearchQueryCache->finishBatch();
        $this->getStandardSearchQuery($categoryId, $this->getDefaultStoreId());
        $this->assertNotFalse($this->cache->load($cacheIdentifier), 'Queries built after the batch must reach the shared cache.');
    }

    protected function getStandardSearchQuery(int $categoryId, int $storeId): \Smile\ElasticsuiteCore\Search\Request\QueryInterface
    {
        $category = $this->objectManager->create(\Magento\Catalog\Model\Category::class)->setStoreId($storeId)->load($categoryId);

        return $category->getVirtualRule()->getCategorySearchQuery($category);
    }

    protected function getDefaultStoreId(): int
    {
        return (int)$this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getDefaultStoreView()->getId();
    }
}
