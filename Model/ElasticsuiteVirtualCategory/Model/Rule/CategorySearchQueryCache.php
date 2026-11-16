<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule;

class CategorySearchQueryCache
{
    protected bool $isBatchInProgress = false;

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

    public function resetLocalCache(): void
    {
        $localCacheProperty = new \ReflectionProperty(\Smile\ElasticsuiteVirtualCategory\Model\Rule::class, 'localCache');
        $localCacheProperty->setValue(null, []);
    }
}
