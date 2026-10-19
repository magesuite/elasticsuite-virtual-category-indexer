<?php

declare(strict_types=1);

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
$registry = $objectManager->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$productCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);
$productCollection->addAttributeToFilter('sku', 'virtual_root_source_product');
$productCollection->delete();

$categoryCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
$categoryCollection->addAttributeToFilter('entity_id', ['in' => [4443, 4442, 4441]]);
$categoryCollection->delete();

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
