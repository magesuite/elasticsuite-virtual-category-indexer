<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel;

class Category
{
    protected \Magento\Eav\Model\AttributeRepository $attributeRepository;
    protected \Magento\Framework\App\ResourceConnection $connection;
    protected \Magento\Framework\EntityManager\MetadataPool $metadataPool;

    public function __construct(
        \Magento\Eav\Model\AttributeRepository $attributeRepository,
        \Magento\Framework\App\ResourceConnection $connection,
        \Magento\Framework\EntityManager\MetadataPool $metadataPool
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->connection = $connection;
        $this->metadataPool = $metadataPool;
    }

    public function isActiveInSomeStore(\Magento\Catalog\Api\Data\CategoryInterface $category): bool
    {
        $isActiveAttribute = $category->getResource()->getAttribute('is_active');
        $tableName = $isActiveAttribute->getBackend()->getTable();
        $linkField = $this->metadataPool->getMetadata(\Magento\Catalog\Api\Data\CategoryInterface::class)->getLinkField();

        $connection = $this->connection->getConnection();

        $select = $connection->select()
            ->from($tableName, 'count(*) as c')
            ->where('attribute_id = ?', $isActiveAttribute->getId())
            ->where(sprintf('%s = ?', $linkField), $category->getRowId() ?? $category->getId())
            ->where('value = 1');

        return (bool)$connection->fetchOne($select);
    }

    public function getFirstStoreId(int $categoryId): int
    {
        $connection = $this->connection->getConnection();
        $storeIdSelect = $this->getStoreIdUnderCategoryRootSelect($categoryId);
        $activeStoreId = (int)$connection->fetchOne($this->addCategoryIsActiveFilter(clone $storeIdSelect));

        if ($activeStoreId) {
            return $activeStoreId;
        }

        return (int)$connection->fetchOne($storeIdSelect);
    }

    protected function getStoreIdUnderCategoryRootSelect(int $categoryId): \Magento\Framework\DB\Select
    {
        $rootCategoryIdExpression = new \Zend_Db_Expr("SUBSTRING_INDEX(SUBSTRING_INDEX(category.path, '/', 2), '/', -1)");

        return $this->connection->getConnection()->select()
            ->from(['category' => $this->connection->getTableName('catalog_category_entity')], [])
            ->join(['store_group' => $this->connection->getTableName('store_group')], sprintf('store_group.root_category_id = %s', $rootCategoryIdExpression), [])
            ->join(['store' => $this->connection->getTableName('store')], 'store.group_id = store_group.group_id', ['store_id'])
            ->where('category.entity_id = ?', $categoryId)
            ->order('store.store_id ASC')
            ->limit(1);
    }

    protected function addCategoryIsActiveFilter(\Magento\Framework\DB\Select $storeIdSelect): \Magento\Framework\DB\Select
    {
        $connection = $this->connection->getConnection();
        $isActiveAttribute = $this->attributeRepository->get(\Magento\Catalog\Model\Category::ENTITY, 'is_active');
        $isActiveTableName = $isActiveAttribute->getBackendTable();
        $attributeId = (int)$isActiveAttribute->getAttributeId();

        return $storeIdSelect
            ->joinLeft(['store_is_active' => $isActiveTableName], $this->getIsActiveJoinCondition('store_is_active', $attributeId, 'store_is_active.store_id = store.store_id'), [])
            ->joinLeft(
                ['default_is_active' => $isActiveTableName],
                $this->getIsActiveJoinCondition('default_is_active', $attributeId, $connection->quoteInto('default_is_active.store_id = ?', \Magento\Store\Model\Store::DEFAULT_STORE_ID)),
                []
            )
            ->where('store.is_active = ?', 1)
            ->where('COALESCE(store_is_active.value, default_is_active.value) = ?', 1);
    }

    protected function getIsActiveJoinCondition(string $tableAlias, int $attributeId, string $storeCondition): string
    {
        $connection = $this->connection->getConnection();
        $linkField = $this->metadataPool->getMetadata(\Magento\Catalog\Api\Data\CategoryInterface::class)->getLinkField();

        return implode(' AND ', [
            sprintf('%1$s.%2$s = category.%2$s', $tableAlias, $linkField),
            $connection->quoteInto(sprintf('%s.attribute_id = ?', $tableAlias), $attributeId),
            $storeCondition,
        ]);
    }
}
