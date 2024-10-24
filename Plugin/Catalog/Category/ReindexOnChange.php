<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\Catalog\Category;

class ReindexOnChange
{
    protected \Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryResourceModel;
    protected \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry;
    protected \Smile\ElasticsuiteVirtualCategory\Model\Category\Attribute\VirtualRule\SaveHandler $saveHandler;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryResourceModel,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Smile\ElasticsuiteVirtualCategory\Model\Category\Attribute\VirtualRule\SaveHandler $saveHandler,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel
    ) {
        $this->catalogCategoryResourceModel = $catalogCategoryResourceModel;
        $this->indexerRegistry = $indexerRegistry;
        $this->saveHandler = $saveHandler;
        $this->categoryResourceModel = $categoryResourceModel;
    }

    public function beforeReindex(\Magento\Catalog\Api\Data\CategoryInterface $subject): void
    {
        $isScheduled = $this->getIndexer()->isScheduled();
        $isVirtual = (bool)$subject->getIsVirtualCategory() === true && ($subject->getId());

        $shouldBeReindex = $subject->getData(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE);

        if ($isVirtual && !$isScheduled && $shouldBeReindex) {
            $this->getIndexer()->reindexRow($subject->getId());

            $subject->setIsChangedProductList(true);
            $subject->setAffectedProductIds($subject->getAffectedProductIds());
            $subject->setOrigData('is_virtual_category', 0);
        }
    }

    public function beforeSave(\Magento\Catalog\Api\Data\CategoryInterface $subject): void
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

    protected function getIndexer(): \Magento\Framework\Indexer\IndexerInterface
    {
        return $this->indexerRegistry->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::INDEXER_ID);
    }
}
