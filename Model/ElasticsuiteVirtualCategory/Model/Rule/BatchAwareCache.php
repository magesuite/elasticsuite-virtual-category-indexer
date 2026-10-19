<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule;

class BatchAwareCache implements \Magento\Framework\App\CacheInterface
{
    public function __construct(
        protected \Magento\Framework\App\CacheInterface $cache,
        protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule\CategorySearchQueryCache $categorySearchQueryCache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFrontend()
    {
        return $this->cache->getFrontend();
    }

    /**
     * @inheritDoc
     */
    public function load($identifier)
    {
        return $this->cache->load($identifier);
    }

    /**
     * @inheritDoc
     */
    public function save($data, $identifier, $tags = [], $lifeTime = null) // phpcs:ignore MageSuite.TooMany.MethodArguments.Found
    {
        if ($this->categorySearchQueryCache->isBatchInProgress()) {
            return false;
        }

        return $this->cache->save($data, $identifier, $tags, $lifeTime);
    }

    /**
     * @inheritDoc
     */
    public function remove($identifier)
    {
        return $this->cache->remove($identifier);
    }

    /**
     * @inheritDoc
     */
    public function clean($tags = [])
    {
        return $this->cache->clean($tags);
    }
}
