<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration;

class Configuration
{
    public const XML_PATH_VIRTUAL_CATEGORY_INDEXER_ENABLED = 'virtual_category_indexer/general/enabled';
    public const XML_PATH_VIRTUAL_CATEGORY_INDEXER_SCHEDULE = 'virtual_category_indexer/general/schedule';
    public const XML_PATH_VIRTUAL_CATEGORY_INDEXER_RETRY_ENABLED = 'virtual_category_indexer/general/retry_enabled';
    public const XML_PATH_VIRTUAL_CATEGORY_ASSIGN_PRODUCTS_TO_PARENT_CATEGORIES = 'virtual_category_indexer/general/assign_products_to_parent_categories';

    protected \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface)
    {
        $this->scopeConfig = $scopeConfigInterface;
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_VIRTUAL_CATEGORY_INDEXER_ENABLED);
    }

    public function isRetryEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_VIRTUAL_CATEGORY_INDEXER_RETRY_ENABLED);
    }

    public function shouldAssignProductsToParentCategories(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_VIRTUAL_CATEGORY_ASSIGN_PRODUCTS_TO_PARENT_CATEGORIES);
    }
}
