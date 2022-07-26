<?php

declare(strict_types=1);

\Magento\TestFramework\Workaround\Override\Fixture\Resolver::getInstance()->requireDataFixture('Magento/Store/_files/store_with_second_root_category.php');

$storeManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(\Magento\Store\Model\StoreManagerInterface::class);
/** @var \Magento\Store\Api\Data\StoreInterface $store */
$store = $storeManager->getStore('test_store_1');
$storeRootCategoryId = $store->getRootCategoryId();
$categoryPath = sprintf('1/%s/3333', $storeRootCategoryId);

$category = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\Catalog\Model\Category::class);
$category->isObjectNew(true);
$category->setId(3333)
    ->setCreatedAt('2014-06-23 09:50:07')
    ->setName('Category 3333')
    ->setParentId($storeRootCategoryId)
    ->setPath($categoryPath)
    ->setLevel(2)
    ->setAvailableSortBy(['position', 'name'])
    ->setDefaultSortBy('name')
    ->setIsActive(true)
    ->setPosition(1)
    ->save();

/** @var $product \Magento\Catalog\Model\Product */
$product = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\Catalog\Model\Product::class);
$product->setTypeId(
    \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
)->setId(
    9999
)->setAttributeSetId(
    4
)->setStoreId(
    $store->getId()
)->setWebsiteIds(
    [$store->getWebsiteId()]
)->setName(
    'Simple Product Three In Virtual Category'
)->setSku(
    'simple999'
)->setPrice(
    10
)->setWeight(
    18
)->setStockData(
    ['use_config_manage_stock' => 0]
)->setCategoryIds(
    [$storeRootCategoryId, $category->getId()]
)->setVisibility(
    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
)->setStatus(
    \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
)->save();

/** @var \Magento\Catalog\Model\CategoryRepository $categoryRepository */
$categoryRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(Magento\Catalog\Model\CategoryRepository::class);

/** @var \Magento\Catalog\Api\Data\CategoryInterface $category */
$category = $categoryRepository->get(3333);
$category->setIsVirtualCategory(true);
$category->setVirtualCategoryRoot($storeRootCategoryId);
$category->setIsActive(true);
$category->setVirtualRule(
    json_encode('{"type":"Smile\\ElasticsuiteVirtualCategory\\Model\\Rule\\Condition\\Combine","attribute":null,"operator":null,"value":"1","is_value_processed":null,"aggregator":"all"})')
);
$category->setIsChangedProductList(true);

$categoryRepository->save($category);

/** @var $product \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer */
$indexer = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::class);
$indexer->executeRow(3333);
