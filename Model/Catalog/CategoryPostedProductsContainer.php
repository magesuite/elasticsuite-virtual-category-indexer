<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog;

class CategoryPostedProductsContainer
{
    protected array $postedProducts = [];

    public function setPostedProducts($postedProducts)
    {
        $this->postedProducts = $postedProducts;
    }

    public function getPostedProducts()
    {
        return $this->postedProducts;
    }

    public function reset()
    {
        $this->postedProducts = [];
    }
}
