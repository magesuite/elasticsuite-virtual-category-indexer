<?php

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Controller\Adminhtml\Category\Virtual;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class ReindexTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    const DEFAULT_FRONTEND_STORE_ID = 1;

    protected ?\Magento\Framework\App\ResourceConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->_objectManager->get(\Magento\Framework\App\ResourceConnection::class);
    }

    /**
     * @magentoDataFixture MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_parent_and_products.php
     * @magentoConfigFixture virtual_category_indexer/general/enabled 1
     */
    public function testItDoesNotOverrideDefaultCategoryValues()
    {
        $categoryId = 5;

        $requestData = ['id' => $categoryId];

        $this->removeStoreViewValues($categoryId);

        $this->getRequest()->setPostValue($requestData);
        $this->getRequest()->setMethod(\Magento\Framework\App\Request\Http::METHOD_POST);
        $this->dispatch('backend/catalog/category_virtual/reindex');

        $this->assertCount(0, $this->getOverwrittenValues($categoryId));
    }

    protected function removeStoreViewValues(int $categoryId)
    {
        $connection = $this->connection->getConnection();

        $connection->delete(
            $this->connection->getTableName('catalog_category_entity_int'),
            ['entity_id = ?' => $categoryId, 'store_id = ?' => self::DEFAULT_FRONTEND_STORE_ID]
        );
    }

    protected function getOverwrittenValues(int $categoryId)
    {
        $connection = $this->connection->getConnection();

        $select = $connection
            ->select()
            ->from(['ccei' => $this->connection->getTableName('catalog_category_entity_int')])
            ->where('ccei.entity_id = ?', $categoryId)
            ->where('ccei.store_id = ?', self::DEFAULT_FRONTEND_STORE_ID);

        return $connection->fetchAll($select);
    }
}
