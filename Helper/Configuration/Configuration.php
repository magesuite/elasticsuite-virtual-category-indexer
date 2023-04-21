<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration;

class Configuration
{
    public const XML_PATH_VIRTUAL_CATEGORY_INDEXER_ENABLED = 'virtual_category_indexer/general/enabled';
    public const XML_PATH_VIRTUAL_CATEGORY_INDEXER_SCHEDULE = 'virtual_category_indexer/general/schedule';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $isEnabled = null;

    protected $schedule = null;

    protected $assignToParentCategories = null;

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface)
    {
        $this->scopeConfig = $scopeConfigInterface;
    }

    /**
     * @return string
     */
    public function isEnabled(): bool
    {
        if ($this->isEnabled === null) {
            $this->isEnabled = $this->scopeConfig->isSetFlag(self::XML_PATH_VIRTUAL_CATEGORY_INDEXER_ENABLED);
        }

        return $this->isEnabled;
    }

    /**
     * @return string
     */
    public function getSchedule(): string
    {
        if ($this->schedule === null) {
            $this->schedule = $this->scopeConfig->getValue(self::XML_PATH_VIRTUAL_CATEGORY_INDEXER_SCHEDULE);
        }

        return $this->schedule;
    }

    /**
     * @return bool
     */
    public function shouldAssignProductsToParentCategories(): bool
    {
        if ($this->assignToParentCategories === null) {
            $this->assignToParentCategories = $this->scopeConfig->getValue(self::XML_PATH_VIRTUAL_CATEGORY_INDEXER_SCHEDULE);
        }

        return (bool) $this->assignToParentCategories;
    }
}
