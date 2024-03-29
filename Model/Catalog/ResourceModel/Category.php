<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel;

class Category
{
    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $connection;

    /**
     * @var \Magento\Framework\EntityManager\MetadataPool
     */
    protected $metadataPool;

    public function __construct(
        \Magento\Eav\Model\AttributeRepository $attributeRepository,
        \Magento\Framework\App\ResourceConnection $connection,
        \Magento\Framework\EntityManager\MetadataPool $metadataPool
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->connection = $connection;
        $this->metadataPool = $metadataPool;
    }

    /**
     * @param $category
     * @param int $status
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setReindexRequired(\Magento\Catalog\Api\Data\CategoryInterface $category, bool $status = true): bool
    {
        $attribute = $this->attributeRepository->get(
            \Magento\Catalog\Model\Category::ENTITY,
            \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE
        );
        $linkField = $this->metadataPool->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class)->getLinkField();

        $tableName = $attribute->getBackend()->getTable();

        try {
            $status = $status
                ? \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED
                : \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_NOT_REQUIRED;

            $category->setData(
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE,
                $status
            );

            $this->connection->getConnection()->update(
                $tableName,
                [
                    'value' => (int) $status
                ],
                [
                    sprintf('%s = ?', $linkField) => $category->getId(),
                    'attribute_id = ?' => $attribute->getId(),
                ]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Return true if category in some store is active
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @return bool
     */
    public function getIsActiveInSomeStore(\Magento\Catalog\Api\Data\CategoryInterface $category): bool
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

        return (bool) $connection->fetchOne($select);
    }

    /**
     * @param int $categoryId
     * @return int
     */
    public function getFirstStoreId(int $categoryId): int
    {
        $connection = $this->connection->getConnection();
        $storeTableName = $connection->getTableName('store');
        $storeGroupTableName = $connection->getTableName('store_group');
        $categoryTableName = $connection->getTableName('catalog_category_entity');

        $rootCategoryIdQueryField = new \Zend_Db_Expr('SUBSTRING_INDEX(SUBSTRING(path FROM (LOCATE(\'/\', path)+1)), \'/\', 1) as root_category_id');
        $rootCategoryIdSelect = $connection->select()->from($categoryTableName, $rootCategoryIdQueryField)
            ->where('entity_id = ?', $categoryId);

        $select = $connection->select()
            ->from(['s' => $storeTableName], 's.store_id')
            ->join(
                ['sg' => $storeGroupTableName],
                's.`group_id` = sg.group_id and sg.root_category_id = (' . $rootCategoryIdSelect . ')',
                ''
            )
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }
}
