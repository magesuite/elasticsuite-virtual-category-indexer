<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration;

/**
 * @magentoDbIsolation enabled
 */
class VirtualCategoryIndexerTwoWebsitesTest extends AbstractVirtualCategoryIndexer
{
    /**
     * @magentoAppArea adminhtml
     * @magentoDataFixtureBeforeTransaction MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_parent_and_products.php
     * @magentoDataFixtureBeforeTransaction MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_parent_and_products_second_website.php
     * @magentoConfigFixture virtual_category_indexer/general/enabled 1
     * @return void
     */
    public function testReindexRow(): void
    {
        $categoryId = 5;
        $this->assertVirtualCategoryAndStandardCategory($categoryId);

        $categoryId = 3333;
        $this->assertVirtualCategoryAndStandardCategory($categoryId);
    }
}
