<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Observer;

class RemoveVirtualProductFromParentCategories implements \Magento\Framework\Event\ObserverInterface
{
    protected \Magento\Framework\DB\Adapter\AdapterInterface $connection;

    public function __construct(\Magento\Framework\App\ResourceConnection $connection)
    {
        $this->connection = $connection->getConnection();
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $category = $observer->getCategory();

        if (!$category->getIsVirtualCategory()) {
            return;
        }

        $this->connection->delete(
            $this->connection->getTableName('catalog_category_product'),
            $this->connection->quoteInto(
                sprintf(
                    '%s = ? ',
                    \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_ID
                ),
                $category->getId()
            )
        );
    }
}
