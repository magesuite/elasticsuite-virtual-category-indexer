<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Service;

class VirtualCategoryIndexer implements \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface
{
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer $indexer;

    protected array|int $categoryIds;
    protected array $strategies;
    protected string $strategy;

    public function __construct(
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer $indexer,
        array $strategies = []
    ) {
        $this->indexer = $indexer;
        $this->strategies = $strategies;
    }

    public function getStrategies(): array
    {
        return $this->strategies;
    }

    public function execute(): void
    {
        $this->indexer->{$this->strategy}($this->categoryIds);
    }

    public function setCategoryIds(?array $categoryIds): \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface
    {
        if ($this->strategy == 'executeRow') {
            $categoryIds = current($categoryIds);
        }

        $this->categoryIds = $categoryIds;
        return $this;
    }

    public function setStrategy(string $strategy): \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface
    {
        if (!isset($this->strategies[$strategy])) {
            throw new \InvalidArgumentException(__('Unknown strategy model: %s', $strategy));
        }

        $this->strategy = $this->strategies[$strategy];

        return $this;
    }
}
