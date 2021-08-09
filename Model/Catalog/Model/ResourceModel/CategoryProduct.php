<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\Model\ResourceModel;

class CategoryProduct extends \Magento\Catalog\Model\ResourceModel\CategoryProduct
{
    /**
     * Product ids before category reindexed
     * @var array
     */
    protected $oldProductsIds;

    /**
     * @var \Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory
     */
    protected $virtualCategoryPreviewFactory;

    public function __construct(
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory $virtualCategoryPreviewFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);

        $this->categoryRepository = $categoryRepository;
        $this->virtualCategoryPreviewFactory = $virtualCategoryPreviewFactory;
    }

    /**
     * @param int $categoryId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function reindexVirtualCategory(\Magento\Catalog\Api\Data\CategoryInterface $category)
    {
        if (!$category->getIsVirtualCategory()) {
            return [];
        }

        $tableName = $this->getMainTable();
        $connection = $this->getConnection();

        $query = $connection->quoteInto("DELETE FROM $tableName WHERE category_id = ?", $category->getId());
        $connection->query($query);

        $products = $this->getProducts($category);

        $data = $this->getProductsToInsert($products, $category->getId());

        if ($data) {
            $connection->insertMultiple($tableName, $data);
        }

        return $this->getProductsIds($category->getId());
    }

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @return array
     */
    protected function getProducts(\Magento\Catalog\Api\Data\CategoryInterface $category)
    {
        $previewModel = $this->virtualCategoryPreviewFactory->create(['category' => $category, 'size' => 0, 'search' => '']);
        $size = $previewModel->getRawData()['size'];

        $previewModel = $this->virtualCategoryPreviewFactory->create(['category' => $category, 'size' => $size, 'search' => '']);
        $products  = $previewModel->getRawData()['products'];

        return $products;
    }

    /**
     * @param array $products
     * @param int $categoryId
     * @return array
     */
    protected function getProductsToInsert(array $products, $categoryId): array
    {
        $data = [];

        foreach ($products as $productId => $product) {
            $data[] = [
                'category_id' => $categoryId,
                'product_id' => $productId,
            ];
        }

        return $data;
    }

    /**
     * @param $categoryId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getProductsIds($categoryId)
    {
        $tableName = $this->getMainTable();
        $connection = $this->getConnection();

        $query = $connection->quoteInto("SELECT product_id from $tableName WHERE category_id = ?", $categoryId);

        return $connection->fetchCol($query);
    }

    /**
     * @param $productsIds
     * @return void
     */
    public function setOldProductsIds($productsIds)
    {
        $this->oldProductsIds = $productsIds;
    }

    /**
     * @return array
     */
    public function getOldProductIds()
    {
        return $this->oldProductsIds;
    }
}
