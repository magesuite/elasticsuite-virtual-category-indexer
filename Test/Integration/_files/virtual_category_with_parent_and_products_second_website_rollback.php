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
        $categoryRepository->delete($item);
    } catch (\Exception | \Throwable $e) {
    } //phpcs:ignore
}

$collection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);
foreach ($collection as $item) {
    try {
        $productRepository->delete($item);
    } catch (\Exception | \Throwable $e) {
    } //phpcs:ignore
}

$connection = $collection->getResource()->getConnection();
$urlTableName = $collection->getTable('url_rewrite');
$connection->delete($urlTableName, 'entity_type = "category"');

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);

\Magento\TestFramework\Workaround\Override\Fixture\Resolver::getInstance()->requireDataFixture('Magento/Store/_files/store_with_second_root_category_rollback.php');
