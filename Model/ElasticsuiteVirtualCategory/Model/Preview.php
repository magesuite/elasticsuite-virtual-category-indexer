<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ElasticsuiteVirtualCategory\Model;

class Preview extends \Smile\ElasticsuiteVirtualCategory\Model\Preview
{
    /**
     * @var string
     */
    protected $search;

    public function __construct(
        \Magento\Catalog\Api\Data\CategoryInterface $category,
        \Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Fulltext\CollectionFactory $productCollectionFactory,
        \Smile\ElasticsuiteCatalog\Model\ProductSorter\ItemDataFactory $previewItemFactory,
        \Smile\ElasticsuiteCore\Search\Request\Query\QueryFactory $queryFactory,
        \Smile\ElasticsuiteCore\Api\Search\ContextInterface $searchContext,
        $size = 10,
        $search = ''
    ) {
        $this->search = $search;

        parent:: __construct(
            $category,
            $productCollectionFactory,
            $previewItemFactory,
            $queryFactory,
            $searchContext,
            $size,
            $search
        );
    }

    /**
     * @return array
     */
    public function getRawData()
    {
        $productCollection = $this->getProductCollection()->setPageSize($this->size);

        if (!in_array($this->search, [null, ''], true)) {
            $productCollection->setSearchQuery($this->search);
        }

        return ['products' => $productCollection->getItems(), 'size' => $productCollection->getSize()];
    }
}
