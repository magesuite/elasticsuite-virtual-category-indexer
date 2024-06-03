<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel;

class CategoryProductRelations
{
    protected \Magento\Framework\DB\Adapter\AdapterInterface $connection;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->connection = $resourceConnection->getConnection();
    }

    public function deleteAll(): int
    {
        $relationsTableName = $this->connection->getTableName('catalog_category_product');
        $attributeTableName = $this->connection->getTableName('catalog_category_entity_int');

        $subselect = $this->connection->select()->from(['ea' => $this->connection->getTableName('eav_attribute')], 'attribute_id')
            ->where('ea.attribute_code = ?', 'is_virtual_category');

        $subSelect2 = $this->connection->select()
            ->from(['ccei' => $attributeTableName], 'entity_id')
            ->where('ccei.attribute_id = ?', $subselect)
            ->where('ccei.value = ?', 1);

        $select = $this->connection->select()
            ->from(['ccp' => $attributeTableName], 'entity_id')
            ->where('attribute_id IN (?)', $subSelect2)
            ->orWhere('virtual_category_id IS NOT NULL');

        $where = ['category_id IN (?)' => $select];

        return $this->connection->delete($relationsTableName, $where);
    }
}
