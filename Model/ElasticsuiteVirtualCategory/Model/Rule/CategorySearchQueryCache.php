<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule;

class CategorySearchQueryCache
{
    protected array $customerGroupIds = [];
    protected bool $isBatchInProgress = false;

    public function __construct(
        protected \Magento\Framework\App\CacheInterface $cache,
        protected \Magento\Store\Model\StoreManagerInterface $storeManager,
        protected \Magento\Customer\Model\ResourceModel\Group\CollectionFactory $customerGroupCollectionFactory,
        protected \Smile\ElasticsuiteVirtualCategory\Helper\Config $virtualCategoryConfig
    ) {
    }

    public function startBatch(): void
    {
        $this->resetLocalCache();
        $this->isBatchInProgress = true;
    }

    public function finishBatch(): void
    {
        $this->resetLocalCache();
        $this->isBatchInProgress = false;
    }

    public function isBatchInProgress(): bool
    {
        return $this->isBatchInProgress;
    }

    public function clean(\Magento\Catalog\Api\Data\CategoryInterface $category): void
    {
        $this->removeSharedCacheEntries((int)$category->getId());
        $this->resetLocalCache();
    }

    protected function removeSharedCacheEntries(int $categoryId): void
    {
        foreach ($this->storeManager->getStores(true) as $store) {
            foreach ($this->getCustomerGroupIds() as $customerGroupId) {
                $this->cache->remove($this->getCacheIdentifier((int)$store->getId(), $categoryId, $customerGroupId));
            }
        }
    }

    protected function getCacheIdentifier(int $storeId, int $categoryId, int $customerGroupId): string
    {
        $isForceZeroResultsEnabled = (int)$this->virtualCategoryConfig->isForceZeroResultsForDisabledCategoriesEnabled($storeId);

        return sprintf('getCategorySearchQuery|%d|%d|%d|%d', $storeId, $categoryId, $customerGroupId, $isForceZeroResultsEnabled);
    }

    protected function resetLocalCache(): void
    {
        $localCacheProperty = new \ReflectionProperty(\Smile\ElasticsuiteVirtualCategory\Model\Rule::class, 'localCache');
        $localCacheProperty->setValue(null, []);
    }

    protected function getCustomerGroupIds(): array
    {
        if (!empty($this->customerGroupIds)) {
            return $this->customerGroupIds;
        }

        $this->customerGroupIds = array_map('intval', $this->customerGroupCollectionFactory->create()->getAllIds());

        return $this->customerGroupIds;
    }
}
