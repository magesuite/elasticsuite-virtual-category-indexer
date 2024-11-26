<?php

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel;

class VirtualCategoryIndexer
{
    protected \Magento\Framework\App\ResourceConnection $resource;
    protected \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry
    ) {
        $this->resource = $resource;
        $this->indexerRegistry = $indexerRegistry;
    }

    public function scheduleReindex(int $categoryId): void
    {
        if (!$this->getIndexer()->isScheduled()) {
            return;
        }

        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('elasticsuite_virtual_category_indexer_cl');
        $connection->insert($tableName, ['entity_id' => $categoryId]);
    }

    public function getIndexer(): \Magento\Framework\Indexer\IndexerInterface
    {
        return $this->indexerRegistry->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::INDEXER_ID);
    }
}
