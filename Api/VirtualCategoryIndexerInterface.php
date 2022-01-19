<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Api;

interface VirtualCategoryIndexerInterface
{
    public const STRATEGY_FULL = 'full';
    public const STRATEGY_LIST = 'list';
    public const STRATEGY_CATEGORY = 'category';

    public const VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE = 'virtual_category_reindex_required';
    public const VIRTUAL_CATEGORY_ID = 'virtual_category_id';
    public const VIRTUAL_CATEGORY_REINDEX_REQUIRED = 1;
    public const VIRTUAL_CATEGORY_REINDEX_NOT_REQUIRED = 0;

    /**
     * @return bool
     */
    public function execute();

    /**
     * @param string $strategy
     * @return VirtualCategoryIndexerInterface
     */
    public function setStrategy(string $strategy);

    /**
     * @param string $strategy
     * @return VirtualCategoryIndexerInterface
     */
    public function setCategoryIds(?array $categoryIds);
}
