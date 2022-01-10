<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\Catalog\Category;

class ReindexOnChange
{
    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category
     */
    protected $categoryResourceModel;

    public $productIds;

    /**
     * @var \Magento\Framework\Indexer\IndexerRegistry
     */
    protected $indexerRegistry;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\CategoryProduct
     */
    protected $catalogCategoryResourceModel;

    /**
     * @var \Smile\ElasticsuiteVirtualCategory\Model\Category\Attribute\VirtualRule\SaveHandler
     */
    protected $saveHandler;

    public function __construct(
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel,
        \Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryResourceModel,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Smile\ElasticsuiteVirtualCategory\Model\Category\Attribute\VirtualRule\SaveHandler $saveHandler
    ) {
        $this->catalogCategoryResourceModel = $catalogCategoryResourceModel;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->indexerRegistry = $indexerRegistry;
        $this->saveHandler = $saveHandler;
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
        $category = clone $subject;
        $this->saveHandler->execute($category);

        $virtualRuleChanged = $subject->getOrigData('virtual_rule') <=> $category->getData('virtual_rule');
        $virtualCategoryRootChanged = $subject->getOrigData('virtual_category_root') <=> $subject->getData('virtual_category_root');
        $shouldBeReindex = $virtualRuleChanged || $virtualCategoryRootChanged;

        if ($shouldBeReindex) {
            $this->categoryResourceModel->setReindexRequired($subject);
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
