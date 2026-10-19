<?php

declare(strict_types=1);

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$createCategory = function (int $categoryId, string $name) use ($objectManager): \Magento\Catalog\Model\Category {
    $category = $objectManager->create(\Magento\Catalog\Model\Category::class);
    $category->isObjectNew(true);
    $category->addData([
        'entity_id' => $categoryId,
        'store_id' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
        'name' => $name,
        'parent_id' => 2,
        'path' => sprintf('1/2/%d', $categoryId),
        'level' => 2,
        'available_sort_by' => ['position'],
        'default_sort_by' => 'position',
        'is_active' => true,
        'is_anchor' => true,
        'position' => 1,
    ]);
    $category->save();

    return $category;
};

$makeVirtualWithRoot = function (\Magento\Catalog\Model\Category $category, int $virtualRootId): void {
    $category->setIsVirtualCategory(1);
    $category->setVirtualCategoryRoot($virtualRootId);
    $category->getResource()->saveAttribute($category, 'is_virtual_category');
    $category->getResource()->saveAttribute($category, 'virtual_category_root');
};

$createCategory(4441, 'Virtual Root Source');
$makeVirtualWithRoot($createCategory(4442, 'Virtual Root'), 4441);
$makeVirtualWithRoot($createCategory(4443, 'Virtual Category Under Virtual Root'), 4442);

$product = $objectManager->create(\Magento\Catalog\Model\Product::class);
$product->addData([
    'type_id' => \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE,
    'attribute_set_id' => 4,
    'website_ids' => [1],
    'name' => 'Virtual Root Source Product',
    'sku' => 'virtual_root_source_product',
    'price' => 10,
    'visibility' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
    'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
    'category_ids' => [4441],
    'stock_data' => ['use_config_manage_stock' => 0],
]);
$objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class)->save($product);
