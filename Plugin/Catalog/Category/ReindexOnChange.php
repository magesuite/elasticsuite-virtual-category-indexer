<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\Catalog\Category;

class ReindexOnChange
{
    public $productIds;

    /**
     * @var \Magento\Framework\Indexer\IndexerRegistry
     */
    protected $indexerRegistry;

    protected $catalogCategoryResourceModel;

    public function __construct(\Magento\Framework\Indexer\IndexerRegistry $indexerRegistry, \Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryResourceModel)
    {
        $this->indexerRegistry = $indexerRegistry;
        $this->catalogCategoryResourceModel = $catalogCategoryResourceModel;
    }

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $subject
     * @return void
     */
    public function beforeReindex(\Magento\Catalog\Api\Data\CategoryInterface $subject)
    {
        $isScheduled = $this->getIndexer()->isScheduled();
        $isVirtual = (bool) $subject->getIsVirtualCategory() === true && ($subject->getId());

        $shouldBeReindex = $subject->getData(
            \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE
        );

        if ($isVirtual && !$isScheduled && $shouldBeReindex) {

            $this->catalogCategoryResourceModel->setOldProductsIds($this->productIds);
            $this->getIndexer()->reindexRow($subject->getId());

            $subject->setIsChangedProductList(true);
            $subject->setAffectedProductIds($subject->getAffectedProductIds());
            $subject->setOrigData('is_virtual_category', 0);
        }
    }

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $subject
     * @return void
     */
    public function beforeSave(\Magento\Catalog\Api\Data\CategoryInterface $subject)
    {
        $virtualRuleChanged = $subject->getOrigData('virtual_rule') <=> $subject->getData('virtual_rule');
        $virtualCategoryRootChanged = $subject->getOrigData('virtual_category_root') <=> $subject->getData('virtual_category_root');
        $shouldBeReindex = $virtualRuleChanged || $virtualCategoryRootChanged;

        if ($shouldBeReindex) {
            $subject->setData(
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE,
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED
            );
        }
    }

    /**
     * Retrieve VirtualCategoryIndexer indexer.
     *
     * @return \Magento\Framework\Indexer\IndexerInterface
     */
    protected function getIndexer()
    {
        return $this->indexerRegistry->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::INDEXER_ID);
    }
}
