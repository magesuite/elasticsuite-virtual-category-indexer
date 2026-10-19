<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Controller\Adminhtml\Category\Virtual;

#[\Magento\TestFramework\Fixture\DbIsolation(true)]
#[\Magento\TestFramework\Fixture\AppIsolation(true)]
class PreviewTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    protected const STORE_ID = 1;
    protected const SOURCE_CATEGORY_ID = 4441;
    protected const VIRTUAL_ROOT_ID = 4442;

    protected ?\Magento\Framework\App\CacheInterface $cache;
    protected ?\Magento\Framework\Serialize\SerializerInterface $serializer;
    protected ?\Magento\Catalog\Model\CategoryFactory $categoryFactory;
    protected ?\Magento\Catalog\Api\ProductRepositoryInterface $productRepository;
    protected ?\ReflectionProperty $ruleLocalCacheProperty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->_objectManager->get(\Magento\Framework\App\CacheInterface::class);
        $this->serializer = $this->_objectManager->get(\Magento\Framework\Serialize\SerializerInterface::class);
        $this->categoryFactory = $this->_objectManager->get(\Magento\Catalog\Model\CategoryFactory::class);
        $this->productRepository = $this->_objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $this->ruleLocalCacheProperty = new \ReflectionProperty(\Smile\ElasticsuiteVirtualCategory\Model\Rule::class, 'localCache');
        $this->ruleLocalCacheProperty->setValue(null, []);
    }

    #[\Magento\TestFramework\Fixture\Config('virtual_category_indexer/general/enabled', 1)]
    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction('MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_virtual_root.php')]
    public function testItPreviewsTheRuleEvenWhenTheStorefrontCachedTheMaterializedQueryOfTheCategory(): void
    {
        $this->cacheStorefrontQuery(self::VIRTUAL_ROOT_ID);
        $this->assertNotEmpty($this->cache->load(sprintf('getCategorySearchQuery|%d|%d|0|1', self::STORE_ID, self::VIRTUAL_ROOT_ID)));

        $this->getRequest()->setParams([
            'store' => self::STORE_ID,
            'entity_id' => self::VIRTUAL_ROOT_ID,
            'is_virtual_category' => 1,
            'virtual_category_root' => self::SOURCE_CATEGORY_ID,
            'page_size' => 20,
        ]);
        $this->dispatch('backend/virtualcategory/category_virtual/preview');

        $preview = $this->serializer->unserialize($this->getResponse()->getBody());
        $productId = (int)$this->productRepository->get('virtual_root_source_product')->getId();
        $this->assertContains($productId, array_map('intval', array_column($preview['products'], 'id')));
    }

    protected function cacheStorefrontQuery(int $categoryId): void
    {
        $category = $this->categoryFactory->create()->setStoreId(self::STORE_ID)->load($categoryId);
        $category->getVirtualRule()->getCategorySearchQuery($category);
        $this->ruleLocalCacheProperty->setValue(null, []);
    }
}
