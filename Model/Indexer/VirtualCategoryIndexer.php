<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer;

class VirtualCategoryIndexer implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    /**
     * Indexer ID in configuration
     */
    public const INDEXER_ID = 'elasticsuite_virtual_category';

    protected \Magento\Catalog\Model\Category $catalogCategoryModel;
    protected \Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryProductResourceModel;
    protected \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory;
    protected \Magento\Catalog\Model\CategoryRepository $categoryRepository;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration;
    protected \Magento\Indexer\Model\IndexerFactory $indexerFactory;
    protected \Magento\Store\Model\StoreManagerInterface $storeManager;
    protected \Magento\Customer\Model\ResourceModel\Group\CollectionFactory $customerGroupCollectionFactory;
    protected \Magento\Framework\App\CacheInterface $cache;
    protected \Smile\ElasticsuiteVirtualCategory\Helper\Config $virtualCategoryConfig;
    protected \Psr\Log\LoggerInterface $logger;

    /** @var int[] */
    protected array $categoryIds = [];
    /** @var int[] */
    protected array $productIds = [];
    protected ?array $customerGroups = null;

    public function __construct(
        \Magento\Catalog\Model\Category $catalogCategoryModel,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryProductResourceModel,
        \Magento\Indexer\Model\IndexerFactory $indexerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel,
        \Magento\Customer\Model\ResourceModel\Group\CollectionFactory $customerGroupCollectionFactory,
        \Magento\Framework\App\CacheInterface $cache,
        \Smile\ElasticsuiteVirtualCategory\Helper\Config $virtualCategoryConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->catalogCategoryModel = $catalogCategoryModel;
        $this->catalogCategoryProductResourceModel = $catalogCategoryProductResourceModel;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->configuration = $configuration;
        $this->indexerFactory = $indexerFactory;
        $this->storeManager = $storeManager;
        $this->customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->cache = $cache;
        $this->virtualCategoryConfig = $virtualCategoryConfig;
        $this->logger = $logger;
    }

    public function execute($categoryIds)
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->executeList($categoryIds);
    }

    public function executeFull()
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $categoryIds = $this->categoryCollectionFactory->create()->getAllVirtualCategoryIds();

        foreach ($categoryIds as $categoryId) {
            $this->reindex((int)$categoryId);
        }
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
    }

    public function executeRow($categoryId)
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->reindex((int)$categoryId);
        $this->reindexCategoryProduct();
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
        if ($this->customerGroups !== null) {
            return $this->customerGroups;
        }

        $collection = $this->customerGroupCollectionFactory->create();
        $this->customerGroups = $collection->getItems();

        return $this->customerGroups;
    }

    protected function reindexCategoryProduct(): void
    {
        foreach ($this->categoryIds as $categoryId) {
            $indexer = $this->indexerFactory->create();
            $indexer->load(\Magento\Catalog\Model\Indexer\Category\Product::INDEXER_ID);
            $indexer->reindexRow($categoryId);
        }

        $indexer = $this->indexerFactory->create();
        $indexer->load(\Magento\CatalogSearch\Model\Indexer\Fulltext::INDEXER_ID);
        $indexer->reindexList($this->productIds);

        foreach ($this->categoryIds as $categoryId) {
            $indexer = $this->indexerFactory->create();
            $indexer->load(\Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext::INDEXER_ID);
            $indexer->reindexRow($categoryId);
        }
    }
}
