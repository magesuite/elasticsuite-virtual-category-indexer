<?php

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\Catalog\Model\CategoryLinkRepository;

class CheckIfExistsItemIsRelatedToVirtualCategory
{
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct $categoryProductResourceModel;
    protected \Magento\Framework\DB\Adapter\AdapterInterface $connection;

    public function __construct(
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct $categoryProductResourceModel,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->categoryProductResourceModel = $categoryProductResourceModel;
        $this->connection = $resourceConnection->getConnection();
    }

    public function aroundSave(
        \Magento\Catalog\Model\CategoryLinkRepository $subject,
        callable $proceed,
        \Magento\Catalog\Api\Data\CategoryProductLinkInterface $productLink
    ) {
        try {
            $result = $proceed($productLink);
        } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
            $isVirtualRow = $this->categoryProductResourceModel->isRelatedToVirtualCategory($productLink);

            if ($isVirtualRow === false) {
                throw $e;
            }
        }

        return true;
    }
}
