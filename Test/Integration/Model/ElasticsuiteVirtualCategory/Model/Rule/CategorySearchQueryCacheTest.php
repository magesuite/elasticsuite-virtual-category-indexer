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
    public function testItDropsTheStandardQueryBuiltEarlierInTheSameProcessSoTheVirtualRuleIsEvaluated(): void
    {
        $category = $this->fixtures->get('category');
        $standardQuery = $this->getStandardSearchQuery((int)$category->getId(), $this->getDefaultStoreId());

        $this->categorySearchQueryCache->clean($category);

        $this->assertStringContainsString('standard category', $standardQuery->getName());
        $this->assertStringContainsString('virtual category', $this->getVirtualRuleSearchQuery((int)$category->getId())->getName());
    }

    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction(\Magento\Store\Test\Fixture\Store::class, as: 'second_store')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, ['is_active' => false], 'virtual_root')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'category')]
    public function testItDropsQueriesOfOtherCategoriesBuiltForAnotherStore(): void
    {
        $virtualRootId = (int)$this->fixtures->get('virtual_root')->getId();
        $secondStoreId = (int)$this->fixtures->get('second_store')->getId();
        $this->activateCategoryInStore($virtualRootId, $secondStoreId);
        $defaultStoreQuery = $this->getStandardSearchQuery($virtualRootId, $this->getDefaultStoreId());

        $this->categorySearchQueryCache->clean($this->fixtures->get('category'));

        $this->assertStringNotContainsString('standard category', (string)$defaultStoreQuery->getName());
        $this->assertStringContainsString('standard category', (string)$this->getStandardSearchQuery($virtualRootId, $secondStoreId)->getName());
    }

    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'category')]
    public function testItKeepsQueriesBuiltDuringTheBatchOutOfTheSharedCache(): void
    {
        $categoryId = (int)$this->fixtures->get('category')->getId();
        $this->categorySearchQueryCache->startBatch();

        $this->getStandardSearchQuery($categoryId, $this->getDefaultStoreId());

        $this->assertFalse($this->cache->load(sprintf('getCategorySearchQuery|%d|%d|0|1', $this->getDefaultStoreId(), $categoryId)));
    }

    #[\Magento\TestFramework\Fixture\Config('smile_elasticsuite_catalogsearch_settings/catalogsearch/force_zero_results_for_disabled_categories', 0, 'store', 'default')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'category')]
    public function testItRemovesTheCachedQueryWhenZeroResultsForDisabledCategoriesIsOff(): void
    {
        $category = $this->fixtures->get('category');
        $this->getStandardSearchQuery((int)$category->getId(), $this->getDefaultStoreId());
        $cacheIdentifier = sprintf('getCategorySearchQuery|%d|%d|0|0', $this->getDefaultStoreId(), $category->getId());
        $this->assertNotEmpty($this->cache->load($cacheIdentifier));

        $this->categorySearchQueryCache->clean($category);

        $this->assertEmpty($this->cache->load($cacheIdentifier));
    }

    protected function getStandardSearchQuery(int $categoryId, int $storeId): \Smile\ElasticsuiteCore\Search\Request\QueryInterface
    {
        $category = $this->objectManager->create(\Magento\Catalog\Model\Category::class)->setStoreId($storeId)->load($categoryId);

        return $category->getVirtualRule()->getCategorySearchQuery($category);
    }

    protected function getVirtualRuleSearchQuery(int $categoryId): \Smile\ElasticsuiteCore\Search\Request\QueryInterface
    {
        $category = $this->objectManager->create(\Magento\Catalog\Model\Category::class)->setStoreId($this->getDefaultStoreId())->load($categoryId);
        $category->setIsVirtualCategory(true);
        $category->setHasDraftVirtualRule(true);

        $extensionAttributes = $category->getExtensionAttributes();
        $extensionAttributes->setVirtualQuery(true);
        $category->setExtensionAttributes($extensionAttributes);

        return $category->getVirtualRule()->getCategorySearchQuery($category);
    }

    protected function activateCategoryInStore(int $categoryId, int $storeId): void
    {
        $category = $this->objectManager->create(\Magento\Catalog\Model\Category::class)->setStoreId($storeId)->load($categoryId);
        $category->setIsActive(true);
        $category->getResource()->saveAttribute($category, 'is_active');
    }

    protected function getDefaultStoreId(): int
    {
        return (int)$this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getDefaultStoreView()->getId();
    }
}
