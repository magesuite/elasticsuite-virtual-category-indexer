<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer;

class VirtualCategoryIndexer implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    /**
     * Indexer ID in configuration
     */
    public const INDEXER_ID = 'elasticsuite_virtual_category';

    protected \Magento\Catalog\Model\CategoryRepository $categoryRepository;
    protected \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory;
    protected \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry;
    protected \Magento\Framework\App\CacheInterface $cache;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct $catalogCategoryProductResourceModel;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\VirtualCategoryIndexer $virtualCategoryIndexerResourceModel;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule\CategorySearchQueryCache $categorySearchQueryCache;
    protected \Psr\Log\LoggerInterface $logger;

    protected array $categoryIds = [];
    protected array $productIds = [];

    public function __construct(
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Magento\Framework\App\CacheInterface $cache,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct $catalogCategoryProductResourceModel,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\VirtualCategoryIndexer $virtualCategoryIndexerResourceModel,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model\Rule\CategorySearchQueryCache $categorySearchQueryCache,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->indexerRegistry = $indexerRegistry;
        $this->cache = $cache;
        $this->configuration = $configuration;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->catalogCategoryProductResourceModel = $catalogCategoryProductResourceModel;
        $this->virtualCategoryIndexerResourceModel = $virtualCategoryIndexerResourceModel;
        $this->categorySearchQueryCache = $categorySearchQueryCache;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute($categoryIds): void
    {
        $this->executeList($categoryIds);
    }

    public function executeFull(): void
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->reindexCategories($this->getAllVirtualCategoryIds());
    }

    public function executeList(array $categoryIds): void
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->reindexCategories($categoryIds);
    }

    /**
     * @inheritDoc
     */
    public function executeRow($categoryId): void
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->reindexCategories([$categoryId]);
    }

    protected function reindexCategories(array $categoryIds): void
    {
        $this->categorySearchQueryCache->startBatch();

        try {
            foreach ($categoryIds as $categoryId) {
                $this->reindex((int)$categoryId);
            }
        } finally {
            $this->categorySearchQueryCache->finishBatch();
        }

        $this->reindexCategoryProduct();
        $this->cleanCategoryCacheById($categoryIds);
    }

    protected function reindex(int $categoryId): void
    {
        try {
            $category = $this->getCategory($categoryId);
            $category->setData(
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE,
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED
            );

            $this->clearCategorySearchQueryCache($category);
            $currentProductIds = $this->catalogCategoryProductResourceModel->reindexVirtualCategory($category);

            if ($currentProductIds) {
                $this->productIds = array_unique(array_merge($this->productIds, $currentProductIds));
            }

            $this->categoryIds[] = $categoryId;

            if ($this->configuration->shouldAssignProductsToParentCategories()) {
                $isActive = $this->categoryResourceModel->isActiveInSomeStore($category);
                $this->catalogCategoryProductResourceModel->assignProductsToParentCategory($category, $isActive);
            }

            $category->setData(
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE,
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_NOT_REQUIRED
            );
        } catch (\Exception $e) {
            $this->logger->critical(sprintf('Error during virtual category reindex, categoryId %s, error %s', $categoryId, $e->getMessage()));

            if ($this->configuration->isRetryEnabled() && $this->virtualCategoryIndexerResourceModel->getIndexer()->isScheduled()) {
                $this->virtualCategoryIndexerResourceModel->scheduleReindex($categoryId);
            }
        }
    }

    protected function getCategory(int $categoryId): \Magento\Catalog\Api\Data\CategoryInterface
    {
        $storeId = $this->categoryResourceModel->getFirstStoreId($categoryId);
        $category = $this->categoryRepository->get($categoryId, $storeId);

        $category->setAddedProductIds($category->getAddedProductIds() ?? []);
        $category->setSortedProductIds($category->getSortedProductIds() ?? []);
        $category->setHasDraftVirtualRule(true);

        $extensionAttributes = $category->getExtensionAttributes();
        $extensionAttributes->setVirtualQuery(true);
        $category->setExtensionAttributes($extensionAttributes);

        return $category;
    }

    public function clearCategorySearchQueryCache(\Magento\Catalog\Api\Data\CategoryInterface $category): void
    {
        $this->categorySearchQueryCache->clean($category);
    }

    protected function getAllVirtualCategoryIds(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToFilter('is_virtual_category', ['eq' => 1]);

        $select = $collection->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns('e.' . $collection->getEntity()->getIdFieldName());
        $select->order(['e.level DESC', 'e.entity_id ASC']);

        return $collection->getConnection()->fetchCol($select);
    }

    protected function reindexCategoryProduct(): void
    {
        $indexer = $this->virtualCategoryIndexerResourceModel->getIndexer();
        if ($indexer->isScheduled()) {
            return;
        }

        $catalogCategoryProductIndexer = $this->indexerRegistry->get(\Magento\Catalog\Model\Indexer\Category\Product::INDEXER_ID);
        $catalogSearchFulltextIndexer = $this->indexerRegistry->get(\Magento\CatalogSearch\Model\Indexer\Fulltext::INDEXER_ID);
        $elasticSuiteCategoriesFulltextIndexer = $this->indexerRegistry->get(\Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext::INDEXER_ID);

        $catalogCategoryProductIndexer->reindexList($this->categoryIds);
        $catalogSearchFulltextIndexer->reindexList($this->productIds);
        $elasticSuiteCategoriesFulltextIndexer->reindexList($this->categoryIds);
    }

    protected function cleanCategoryCacheById(array $categoryIds): void
    {
        $tags = [];
        foreach ($categoryIds as $categoryId) {
            $tags[] = sprintf('cat_c_p_%s', $categoryId);
        }
        $this->cache->clean($tags);
    }
}
