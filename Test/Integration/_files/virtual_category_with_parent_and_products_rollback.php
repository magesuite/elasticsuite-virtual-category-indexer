<?php

declare(strict_types=1);

/** @var \Magento\Framework\ObjectManagerInterface $objectManager */
$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

/** @var \Magento\Catalog\Model\CategoryRepository $categoryRepository */
$categoryRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(Magento\Catalog\Model\CategoryRepository::class);

/** @var \Magento\Catalog\Model\ProductRepository $categoryRepository */
$productRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(Magento\Catalog\Model\ProductRepository::class);

/** @var \Magento\Framework\Registry $registry */
$registry = $objectManager->get(\Magento\Framework\Registry::class);

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$collection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
$collection->addAttributeToFilter('entity_id', ['gt' => 2]);

foreach ($collection as $item) {
    try {
        $item->setIsVirtualCategory(false);
        $categoryRepository->save($item);
        $item->delete();
    } catch (\Exception | \Throwable $e) {
    } //phpcs:ignore
}

$collection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);
foreach ($collection as $item) {
    try {
        $item->delete();
    } catch (\Exception | \Throwable $e) {
    } //phpcs:ignore
}
$productRepository->cleanCache();

$connection = $collection->getResource()->getConnection();
$urlTableName = $collection->getTable('url_rewrite');
$connection->delete($urlTableName, 'entity_type = "category"');

$connection = $collection->getResource()->getConnection();
$urlTableName = $collection->getTable('catalog_category_product');
$connection->delete($urlTableName, 'entity_id > 0');

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
