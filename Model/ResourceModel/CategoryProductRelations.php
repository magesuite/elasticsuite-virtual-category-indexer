<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel;

class CategoryProductRelations
{
    protected \Magento\Framework\App\ResourceConnection $resourceConnection;

    protected \Magento\Framework\DB\Adapter\AdapterInterface $connection;

    protected \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->connection = $resourceConnection->getConnection();
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    public function deleteAll(): int
    {
        $where = ['virtual_category_id IS NOT NULL'];
        $virtualCategoryIds = $this->getVirtualCategoryIds();

        if (!empty($virtualCategoryIds)) {
            $where[] = $this->connection->quoteInto('category_id IN (?)', $virtualCategoryIds);
        }

        return $this->connection->delete(
            $this->resourceConnection->getTableName('catalog_category_product'),
            implode(' OR ', $where)
        );
    }

    protected function getVirtualCategoryIds(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToFilter('is_virtual_category', ['eq' => 1]);

        return array_map('intval', $collection->getAllIds());
    }
}
