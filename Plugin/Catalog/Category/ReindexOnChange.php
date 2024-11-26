<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\Catalog\Category;

class ReindexOnChange
{
    protected \Smile\ElasticsuiteVirtualCategory\Model\Category\Attribute\VirtualRule\SaveHandler $saveHandler;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\VirtualCategoryIndexer $virtualCategoryIndexerResourceModel;

    protected bool $shouldReindex = false;

    public function __construct(
        \Smile\ElasticsuiteVirtualCategory\Model\Category\Attribute\VirtualRule\SaveHandler $saveHandler,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\VirtualCategoryIndexer $virtualCategoryIndexerResourceModel
    ) {
        $this->saveHandler = $saveHandler;
        $this->virtualCategoryIndexerResourceModel = $virtualCategoryIndexerResourceModel;
    }

    public function beforeReindex(\Magento\Catalog\Api\Data\CategoryInterface $subject): void
    {
        $isScheduled = $this->virtualCategoryIndexerResourceModel->getIndexer()->isScheduled();
        $isVirtual = (bool)$subject->getIsVirtualCategory() === true && ($subject->getId());

        if (!$isScheduled && $isVirtual && $this->shouldReindex) {
            $this->virtualCategoryIndexerResourceModel->getIndexer()->reindexRow($subject->getId());

            $subject->setIsChangedProductList(true);
            $subject->setAffectedProductIds($subject->getAffectedProductIds());
            $subject->setOrigData('is_virtual_category', 0);

            $this->shouldReindex = false;
        }
    }

    public function beforeSave(\Magento\Catalog\Api\Data\CategoryInterface $subject): void
    {
        $category = clone $subject;
        $this->saveHandler->execute($category);

        $virtualRuleChanged = $subject->getOrigData('virtual_rule') <=> $category->getData('virtual_rule');
        $virtualCategoryRootChanged = $subject->getOrigData('virtual_category_root') <=> $subject->getData('virtual_category_root');

        if ($virtualRuleChanged || $virtualCategoryRootChanged) {
            $this->shouldReindex = true;
        }
    }
}
