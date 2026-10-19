<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Model\Catalog\ResourceModel;

#[\Magento\TestFramework\Fixture\DbIsolation(true)]
#[\Magento\TestFramework\Fixture\AppArea(\Magento\Framework\App\Area::AREA_ADMINHTML)]
class CategoryTest extends \PHPUnit\Framework\TestCase
{
    protected ?\Magento\Framework\ObjectManagerInterface $objectManager;
    protected ?\Magento\TestFramework\Fixture\DataFixtureStorage $fixtures;
    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResource;

    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->fixtures = \Magento\TestFramework\Fixture\DataFixtureStorageManager::getStorage();
        $this->categoryResource = $this->objectManager->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category::class);
    }

    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction(\Magento\Store\Test\Fixture\Store::class, as: 'second_store')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, ['is_active' => false], 'category')]
    public function testItReturnsFirstStoreWhereCategoryIsActive(): void
    {
        $secondStoreId = (int)$this->fixtures->get('second_store')->getId();
        $categoryId = (int)$this->fixtures->get('category')->getId();
        $this->saveCategoryIsActiveInStore($categoryId, $secondStoreId, true);

        $this->assertSame($secondStoreId, $this->categoryResource->getFirstStoreId($categoryId));
    }

    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction(\Magento\Store\Test\Fixture\Store::class, as: 'second_store')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'category')]
    public function testItTreatsStoreWithoutOwnValueAsActiveWhenCategoryIsActiveByDefault(): void
    {
        $categoryId = (int)$this->fixtures->get('category')->getId();
        $this->saveCategoryIsActiveInStore($categoryId, $this->getDefaultStoreId(), false);

        $this->assertSame((int)$this->fixtures->get('second_store')->getId(), $this->categoryResource->getFirstStoreId($categoryId));
    }

    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction(\Magento\Store\Test\Fixture\Store::class, as: 'second_store')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, ['is_active' => false], 'category')]
    public function testItFallsBackToLowestStoreOfCategoryRootWhenCategoryIsInactiveEverywhere(): void
    {
        $categoryId = (int)$this->fixtures->get('category')->getId();

        $this->assertSame($this->getDefaultStoreId(), $this->categoryResource->getFirstStoreId($categoryId));
    }

    protected function saveCategoryIsActiveInStore(int $categoryId, int $storeId, bool $isActive): void
    {
        $category = $this->objectManager->create(\Magento\Catalog\Model\Category::class)->setStoreId($storeId)->load($categoryId);
        $category->setIsActive($isActive);
        $category->getResource()->saveAttribute($category, 'is_active');
    }

    protected function getDefaultStoreId(): int
    {
        return (int)$this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getDefaultStoreView()->getId();
    }
}
