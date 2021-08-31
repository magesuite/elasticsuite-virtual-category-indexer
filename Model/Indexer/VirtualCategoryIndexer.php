<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer;

class VirtualCategoryIndexer implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    /**
     * Indexer ID in configuration
     */
    public const INDEXER_ID = 'elasticsuite_virtual_category';

    /**
     * @var \Magento\Catalog\Model\Category
     */
    protected $catalogCategoryModel;

    /**
     * @var \Magento\Catalog\Model\CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\CategoryProduct
     */
    protected $catalogCategoryProductResourceModel;

    /**
     * @var \Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Fulltext\CollectionFactory
     */
    protected $categoryCollectionFactory;

    protected $categoryIds = [];

    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration
     */
    protected $configuration;

    /**
     * @var \Magento\Indexer\Model\IndexerFactory
     */
    protected $indexerFactory;

    protected $productIds = [];

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\Category $catalogCategoryModel,
        \Magento\Catalog\Model\ResourceModel\CategoryProduct $catalogCategoryProductResourceModel,
        \Magento\Indexer\Model\IndexerFactory $indexerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->catalogCategoryModel = $catalogCategoryModel;
        $this->catalogCategoryProductResourceModel = $catalogCategoryProductResourceModel;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->configuration = $configuration;
        $this->indexerFactory = $indexerFactory;
        $this->storeManager = $storeManager;
    }

    /*
     * Used by mview, allows process indexer in the "Update on schedule" mode
     * @return void
     */
    public function execute($categoryIds)
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->executeList($categoryIds);
    }

    /*
     * Will take all of the data and reindex
     * Will run when reindex via command line
     * @return void
     */
    public function executeFull()
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $categoryIds =  $this->categoryCollectionFactory->create()->getAllVirtualCategoryIds();

        foreach ($categoryIds as $categoryId) {
            $this->reindex((int) $categoryId);
        }

    }

    /*
     * Works with a set of entity changed (may be massaction)
     * @return void
     */
    public function executeList(array $categoryIds)
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        foreach ($categoryIds as $categoryId) {
            $this->reindex((int) $categoryId);
        }

        $this->reindexCategoryProduct();
    }

    /*
     * Works in runtime for a single entity using plugins
     * @return null|array
     */
    public function executeRow($categoryId)
    {
        if (!$this->configuration->isEnabled()) {
            return;
        }

        $this->reindex($categoryId);
        $this->reindexCategoryProduct();
    }

    /**
     * @param int $categoryId
     * @return void
     */
    protected function reindex($categoryId)
    {
        try {
            $category = $this->getCategory($categoryId);

            $oldProductIds = $this->catalogCategoryProductResourceModel->getOldProductIds();
            $currentProductIds = $this->catalogCategoryProductResourceModel->reindexVirtualCategory($category);

            if ($oldProductIds && $currentProductIds) {
                $currentProductIds =  array_unique(array_merge($oldProductIds, $currentProductIds));
            }

            if ($currentProductIds) {
                $this->productIds = array_unique(array_merge($this->productIds, $currentProductIds));
                $this->categoryIds[] = $categoryId;
            }
        } finally {
            if (isset($category)) {
                $this->setVirtualCategoryReindexAttributeToFalse($category);
            }
        }
    }

    /**
     * Category initialization withoud load from database
     * @param int $categoryId
     * @return \Magento\Catalog\Api\Data\CategoryInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getCategory($categoryId)
    {
        $storeId = current($this->storeManager->getStores())->getId();
        $category = $this->categoryRepository->get($categoryId, $storeId);

        $category->setAddedProductIds($category->getAddedProductIds() ?? []);
        $category->setSortedProductIds($category->getSortedProductIds() ?? []);

        $extensionAttributes = $category->getExtensionAttributes();
        $extensionAttributes->setVirtualQuery(true);
        $category->setExtensionAttributes($extensionAttributes);

        return $category;
    }

    /**
     * @param int $categoryId
     * @param array $currentProductIds
     */
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

    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     */
    protected function setVirtualCategoryReindexAttributeToFalse(\Magento\Catalog\Api\Data\CategoryInterface $category): void
    {
        $category->setData(
            \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE,
            \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_NOT_REQUIRED
        )->setStoreId(0);
        $category->getResource()->saveAttribute(
            $category,
            \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE
        );
    }
}
