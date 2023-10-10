<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel;

class CategoryProduct extends \Magento\Catalog\Model\ResourceModel\CategoryProduct
{
    /**
     * Product ids before category reindexed
     */
    protected ?array $oldProductsIds = null;

    protected \Magento\Catalog\Model\CategoryRepository $categoryRepository;

    protected \Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory $virtualCategoryPreviewFactory;

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
     * @param string|int $categoryId
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

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @param bool $isActive
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignProductsToParentCategory(\Magento\Catalog\Api\Data\CategoryInterface $category, bool $isActive): void
    {
        $parentCategories = $category->getParentCategories();

        foreach ($parentCategories as $parentCategory) {

            if ($parentCategory->getId() == $category->getId()) {
                continue;
            }

            $this->removeVirtualCategoryProductsFromParentCategory($parentCategory, $category);

            if ($isActive) {
                $this->addVirtualCategoryProductsToParentCategory($parentCategory, $category);
            }
        }
    }

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $parentCategory
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function removeVirtualCategoryProductsFromParentCategory(
        \Magento\Catalog\Api\Data\CategoryInterface $parentCategory,
        \Magento\Catalog\Api\Data\CategoryInterface $category
    ): void {
        $connection = $this->getConnection();

        $cond = $connection->quoteInto('virtual_category_id = ?', $category->getId());
        $cond .= $connection->quoteInto(' AND category_id = ?', $parentCategory->getId());

        $connection->delete($this->getMainTable(), $cond);
    }

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $parentCategory
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function addVirtualCategoryProductsToParentCategory(
        \Magento\Catalog\Api\Data\CategoryInterface $parentCategory,
        \Magento\Catalog\Api\Data\CategoryInterface $category
    ): void {
        $products = $this->getVirtualCategoryProducts($parentCategory, $category);

        if (empty($products)) {
            return;
        }

        $connection = $this->getConnection();
        $connection->insertMultiple($this->getMainTable(), $products);
    }

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $parentCategory
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getVirtualCategoryProducts(
        \Magento\Catalog\Api\Data\CategoryInterface $parentCategory,
        \Magento\Catalog\Api\Data\CategoryInterface $category
    ): array {
        $connection = $this->getConnection();

        $categories = [$parentCategory->getId(), $category->getId()];

        $select = $connection->select()->from($this->getMainTable() . ' as e')
            ->joinLeft(
                $this->getMainTable() . ' as e2',
                $connection->quoteInto('e2.category_id = ? and e2.product_id = e.product_id', $parentCategory->getId()),
                ''
            )
            ->where('e.category_id = ?', $category->getId())
            ->where('e2.product_id is null');

        $products = $connection->fetchAll($select);

        foreach ($products as &$product) {
            unset($product['entity_id']);
            $product['virtual_category_id'] = $category->getId();
            $product['category_id'] = $parentCategory->getId();
        }

        return $products;
    }

    /**
     * @param \Magento\Catalog\Api\Data\CategoryProductLinkInterface $productLink
     * @return bool
     */
    public function isRelatedToVirtualCategory(\Magento\Catalog\Api\Data\CategoryProductLinkInterface $productLink): bool
    {
        $connection = $this->getConnection();

        $tableName = $connection->getTableName('catalog_category_product');
        $productTableName = $connection->getTableName('catalog_product_entity');
        $select = $connection->select()->from(['link' => $tableName], 'virtual_category_id')
            ->join(
                ['entity' => $productTableName],
                $connection->quoteInto('link.product_id = entity.entity_id and entity.sku = ?', $productLink->getSku()),
                ''
            )
            ->where('category_id = ?', $productLink->getCategoryId());

        return (bool) $connection->fetchOne($select);
    }
}
