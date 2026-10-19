<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Model\Indexer;

#[\Magento\TestFramework\Fixture\DbIsolation(true)]
#[\Magento\TestFramework\Fixture\AppArea(\Magento\Framework\App\Area::AREA_ADMINHTML)]
class VirtualCategoryIndexerTest extends \PHPUnit\Framework\TestCase
{
    protected const VIRTUAL_ROOT_ID = 4442;
    protected const CATEGORY_UNDER_VIRTUAL_ROOT_ID = 4443;

    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer $virtualCategoryIndexer;
    protected ?\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct $categoryProductResource;
    protected ?\Magento\Catalog\Api\ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->virtualCategoryIndexer = $objectManager->create(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::class);
        $this->categoryProductResource = $objectManager->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct::class);
        $this->productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
    }

    #[\Magento\TestFramework\Fixture\Config('virtual_category_indexer/general/enabled', 1)]
    #[\Magento\TestFramework\Fixture\DataFixtureBeforeTransaction('MageSuite_ElasticsuiteVirtualCategoryIndexer::Test/Integration/_files/virtual_category_with_virtual_root.php')]
    public function testItMaterializesAVirtualRootFromItsRuleAfterADependentCategoryLookedItUpInTheSameRun(): void
    {
        $this->virtualCategoryIndexer->executeList([self::CATEGORY_UNDER_VIRTUAL_ROOT_ID, self::VIRTUAL_ROOT_ID]);

        $productId = (int)$this->productRepository->get('virtual_root_source_product')->getId();
        $this->assertContains($productId, array_map('intval', $this->categoryProductResource->getProductsIds(self::VIRTUAL_ROOT_ID)));
    }
}
