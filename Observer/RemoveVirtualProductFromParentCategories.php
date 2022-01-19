<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Observer;

class RemoveVirtualProductFromParentCategories implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $connection;

    public function __construct(\Magento\Framework\App\ResourceConnection $connection)
    {
        $this->connection = $connection->getConnection();
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
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
