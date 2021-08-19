<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Service;

class VirtualCategoryIndexer implements \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface
{
    /**
     * @var array
     */
    protected $categoryIds;

    /**
     * @var string
     */
    protected $strategy;

    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer
     */
    protected $indexer;

    /**
     * @var array
     */
    protected $strategies;

    public function __construct(
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer $indexer,
        $strategies = []
    ) {
        $this->indexer = $indexer;
        $this->strategies = $strategies;
    }

    /**
     * @return array
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * @param string $categoryIds
     * @return VirtualCategoryIndexer
     */
    public function setCategoryIds(?array $categoryIds)
    {
        if ($this->strategy == 'executeRow') {
            $categoryIds = current($categoryIds);
        }

        $this->categoryIds = $categoryIds;

        return $this;
    }

    /**
     * @param string $strategy
     * @return VirtualCategoryIndexer
     */
    public function setStrategy(string $strategy)
    {
        if (!isset($this->strategies[$strategy])) {
            throw new \InvalidArgumentException(__('Unknown strategy model: %s', $strategy));
        }

        $this->strategy = $this->strategies[$strategy];

        return $this;
    }

    /**
     * @return bool
     */
    public function execute()
    {
        return $this->indexer->{$this->strategy}($this->categoryIds);
    }
}
