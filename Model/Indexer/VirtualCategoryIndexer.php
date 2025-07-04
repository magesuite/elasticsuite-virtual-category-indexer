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
    protected \Magento\Customer\Model\ResourceModel\Group\CollectionFactory $customerGroupCollectionFactory;
    protected \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry;
    protected \Magento\Store\Model\StoreManagerInterface $storeManager;
    protected \Magento\Framework\App\CacheInterface $cache;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct $catalogCategoryProductResourceModel;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\VirtualCategoryIndexer $virtualCategoryIndexerResourceModel;
    protected \Smile\ElasticsuiteVirtualCategory\Helper\Config $virtualCategoryConfig;
    protected \Psr\Log\LoggerInterface $logger;

    protected array $categoryIds = [];
    protected array $productIds = [];
    protected array $customerGroups = [];

    public function __construct(
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Customer\Model\ResourceModel\Group\CollectionFactory $customerGroupCollectionFactory,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\CacheInterface $cache,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\CategoryProduct $catalogCategoryProductResourceModel,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\VirtualCategoryIndexer $virtualCategoryIndexerResourceModel,
        \Smile\ElasticsuiteVirtualCategory\Helper\Config $virtualCategoryConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->indexerRegistry = $indexerRegistry;
        $this->storeManager = $storeManager;
        $this->cache = $cache;
        $this->configuration = $configuration;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->catalogCategoryProductResourceModel = $catalogCategoryProductResourceModel;
        $this->virtualCategoryIndexerResourceModel = $virtualCategoryIndexerResourceModel;
        $this->virtualCategoryConfig = $virtualCategoryConfig;
        $this->logger = $logger;
    }

    public function execute($categoryIds)
    {
        $this->executeList($categoryIds);
    }

    public function executeFull()
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $categoryIds = $this->getAllVirtualCategoryIds();
        foreach ($categoryIds as $categoryId) {
            $this->reindex((int)$categoryId);
        }

        $this->reindexCategoryProduct();
        $this->cleanCategoryCacheById($categoryIds);
    }

    public function executeList(array $categoryIds)
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        foreach ($categoryIds as $categoryId) {
            $this->reindex((int)$categoryId);
        }

        $this->reindexCategoryProduct();
        $this->cleanCategoryCacheById($categoryIds);
    }

    public function executeRow($categoryId)
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->reindex((int)$categoryId);
        $this->reindexCategoryProduct();
        $this->cleanCategoryCacheById([$categoryId]);
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
                $isActive = $this->categoryResourceModel->getIsActiveInSomeStore($category);
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

        $extensionAttributes = $category->getExtensionAttributes();
        $extensionAttributes->setVirtualQuery(true);
        $category->setExtensionAttributes($extensionAttributes);

        return $category;
    }

    public function clearCategorySearchQueryCache(\Magento\Catalog\Api\Data\CategoryInterface $category): void
    {
        $stores = $this->storeManager->getStores(true);
        $customerGroups = $this->getCustomerGroups();

        foreach ($stores as $store) {
            foreach ($customerGroups as $customerGroup) {
                $cacheIdentifier = implode(
                    '|',
                    [
                        'getCategorySearchQuery',
                        $store->getId(),
                        $category->getId(),
                        $customerGroup->getId(),
                        $this->virtualCategoryConfig->isForceZeroResultsForDisabledCategoriesEnabled($store->getId())
                    ]);
                $this->cache->remove($cacheIdentifier);
            }
        }
    }

    protected function getCustomerGroups(): array
    {
        if (!empty($this->customerGroups)) {
            return $this->customerGroups;
        }

        $collection = $this->customerGroupCollectionFactory->create();
        $this->customerGroups = $collection->getItems();

        return $this->customerGroups;
    }

    protected function getAllVirtualCategoryIds(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToFilter('is_virtual_category', ['eq' => 1]);

        $select = $collection->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns('e.' . $collection->getEntity()->getIdFieldName());
        $select->order('level DESC');

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
