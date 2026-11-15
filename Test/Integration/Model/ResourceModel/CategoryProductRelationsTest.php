<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Model\ResourceModel;

#[\Magento\TestFramework\Fixture\DbIsolation(true)]
class CategoryProductRelationsTest extends \PHPUnit\Framework\TestCase
{
    protected ?\Magento\Framework\ObjectManagerInterface $objectManager;
    protected ?\Magento\Framework\DB\Adapter\AdapterInterface $connection;
    protected ?\Magento\TestFramework\Fixture\DataFixtureStorage $fixtures;
    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\CategoryProductRelations $relations;

    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->fixtures = \Magento\TestFramework\Fixture\DataFixtureStorageManager::getStorage();
        $resourceConnection = $this->objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $this->connection = $resourceConnection->getConnection();
        $this->relations = $this->objectManager->get(
            \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\CategoryProductRelations::class
        );
    }

    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'virtual1')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'virtual2')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'regular')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Category::class, as: 'parent')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Product::class, as: 'product1')]
    #[\Magento\TestFramework\Fixture\DataFixture(\Magento\Catalog\Test\Fixture\Product::class, as: 'product2')]
    public function testItDeletesOnlyRelationsOfVirtualCategories(): void
    {
        $virtual1 = $this->getId('virtual1');
        $virtual2 = $this->getId('virtual2');
        $regular = $this->getId('regular');
        $parent = $this->getId('parent');
        $product1 = $this->getId('product1');
        $product2 = $this->getId('product2');

        $this->setIsVirtualCategory($virtual1, 1);
        $this->setIsVirtualCategory($virtual2, 1);
        $this->setIsVirtualCategory($regular, 0);

        $this->connection->insertMultiple($this->connection->getTableName('catalog_category_product'), [
            ['category_id' => $virtual1, 'product_id' => $product1, 'position' => 0, 'virtual_category_id' => null],
            ['category_id' => $virtual2, 'product_id' => $product1, 'position' => 0, 'virtual_category_id' => null],
            ['category_id' => $regular, 'product_id' => $product1, 'position' => 0, 'virtual_category_id' => null],
            ['category_id' => $parent, 'product_id' => $product1, 'position' => 0, 'virtual_category_id' => $virtual1],
            ['category_id' => $parent, 'product_id' => $product2, 'position' => 0, 'virtual_category_id' => null],
        ]);

        $this->relations->deleteAll();

        $this->assertEqualsCanonicalizing(
            [[$regular, $product1], [$parent, $product2]],
            $this->getRelations([$virtual1, $virtual2, $regular, $parent])
        );
    }

    protected function getId(string $fixture): int
    {
        return (int)$this->fixtures->get($fixture)->getId();
    }

    protected function setIsVirtualCategory(int $categoryId, int $value): void
    {
        $category = $this->objectManager->create(\Magento\Catalog\Model\Category::class)->load($categoryId);
        $category->setData('is_virtual_category', $value);
        $category->getResource()->saveAttribute($category, 'is_virtual_category');
    }

    protected function getRelations(array $categoryIds): array
    {
        $select = $this->connection->select()
            ->from($this->connection->getTableName('catalog_category_product'), ['category_id', 'product_id'])
            ->where('category_id IN (?)', $categoryIds);

        return array_map(
            static fn (array $row): array => [(int)$row['category_id'], (int)$row['product_id']],
            $this->connection->fetchAll($select)
        );
    }
}
