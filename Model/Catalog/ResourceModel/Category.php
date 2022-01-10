<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel;

class Category
{
    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $connection;

    public function __construct(
        \Magento\Eav\Model\AttributeRepository $attributeRepository,
        \Magento\Framework\App\ResourceConnection $connection
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->connection = $connection;
    }

    /**
     * @param $category
     * @param int $status
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setReindexRequired(\Magento\Catalog\Api\Data\CategoryInterface $category, bool $status = true): bool
    {
        $attribute = $this->attributeRepository->get(
            \Magento\Catalog\Model\Category::ENTITY,
            \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE
        );

        $tableName = $attribute->getBackend()->getTable();

        try {
            $status = $status
                ? \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED
                : \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_NOT_REQUIRED;

            $category->setData(
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE,
                $status
            );

            $category->getResource()->saveAttribute(
                $category,
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE
            );

            $affectedRows = $this->connection->getConnection()->update(
                $tableName,
                [
                    'value' => (int) $status
                ],
                [
                    'entity_id = ?' => $category->getId(),
                    'attribute_id = ?' => $attribute->getId(),
                ]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
