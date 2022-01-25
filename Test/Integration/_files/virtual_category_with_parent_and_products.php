<?php

\Magento\TestFramework\Workaround\Override\Fixture\Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/categories.php');

/** @var $product \Magento\Catalog\Model\Product */
$product = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\Magento\Catalog\Model\Product::class);
$product->setTypeId(
    \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
)->setId(
    999
)->setAttributeSetId(
    4
)->setStoreId(
    1
)->setWebsiteIds(
    [1]
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
    [5]
)->setVisibility(
    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
)->setStatus(
    \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
)->save();

/** @var \Magento\Catalog\Model\CategoryRepository $categoryRepository */
$categoryRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(Magento\Catalog\Model\CategoryRepository::class);

/** @var \Magento\Catalog\Api\Data\CategoryInterface $category */
$category = $categoryRepository->get(5);
$category->setIsVirtualCategory(true);
$category->setVirtualCategoryRoot(2);
$category->setIsActive(true);
$category->setVirtualRule(
    json_encode('{"type":"Smile\\ElasticsuiteVirtualCategory\\Model\\Rule\\Condition\\Combine","attribute":null,"operator":null,"value":"1","is_value_processed":null,"aggregator":"all"})')
);
$category->setIsChangedProductList(true);

$categoryRepository->save($category);

/** @var $product \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer */
$indexer = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::class);
$indexer->executeRow(5);
