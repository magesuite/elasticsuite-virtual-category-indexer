<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel;

class CategoryProduct extends \Magento\Catalog\Model\ResourceModel\CategoryProduct
{
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

    public function reindexVirtualCategory(\Magento\Catalog\Api\Data\CategoryInterface $category): array
    {
        if (!$category->getIsVirtualCategory()) {
            return [];
        }

        $tableName = $this->getMainTable();
        $connection = $this->getConnection();

        $query = $connection->quoteInto("DELETE FROM $tableName WHERE category_id = ?", $category->getId());
        $connection->query($query);

        $products = $this->getProducts($category);

        $data = $this->getProductsToInsert($products, (int)$category->getId());

        if (!$data) {
            return [];
        }

        $connection->insertMultiple($tableName, $data);

        return array_column($data, 'product_id');
    }

    protected function getProducts(\Magento\Catalog\Api\Data\CategoryInterface $category): array
    {
        $previewModel = $this->virtualCategoryPreviewFactory->create(['category' => $category, 'size' => 0, 'search' => '']);
        $size = $previewModel->getRawData(true)['size'];

        $previewModel = $this->virtualCategoryPreviewFactory->create(['category' => $category, 'size' => $size, 'search' => '']);
        $products = $previewModel->getRawData(true)['products'];

        return $products;
    }

    protected function getProductsToInsert(array $products, int $categoryId): array
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

    public function getProductsIds(int $categoryId): array
    {
        $tableName = $this->getMainTable();
        $connection = $this->getConnection();

        $query = $connection->quoteInto("SELECT product_id from $tableName WHERE category_id = ?", $categoryId);

        return $connection->fetchCol($query);
    }

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

    protected function removeVirtualCategoryProductsFromParentCategory(
        \Magento\Catalog\Api\Data\CategoryInterface $parentCategory,
        \Magento\Catalog\Api\Data\CategoryInterface $category
    ): void {
        $connection = $this->getConnection();

        $cond = $connection->quoteInto('virtual_category_id = ?', $category->getId());
        $cond .= $connection->quoteInto(' AND category_id = ?', $parentCategory->getId());

        $connection->delete($this->getMainTable(), $cond);
    }

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

    protected function getVirtualCategoryProducts(
        \Magento\Catalog\Api\Data\CategoryInterface $parentCategory,
        \Magento\Catalog\Api\Data\CategoryInterface $category
    ): array {
        $connection = $this->getConnection();

        $select = $connection->select()->from(['e' => $this->getMainTable()])
            ->joinLeft(
                ['e2' => $this->getMainTable()],
                $connection->quoteInto('e2.category_id = ? AND e2.product_id = e.product_id', $parentCategory->getId()),
                ''
            )
            ->where('e.category_id = ?', $category->getId())
            ->where('e2.product_id IS NULL');

        $products = $connection->fetchAll($select);

        foreach ($products as &$product) {
            unset($product['entity_id']);
            $product['virtual_category_id'] = $category->getId();
            $product['category_id'] = $parentCategory->getId();
        }

        return $products;
    }

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

        return (bool)$connection->fetchOne($select);
    }
}
