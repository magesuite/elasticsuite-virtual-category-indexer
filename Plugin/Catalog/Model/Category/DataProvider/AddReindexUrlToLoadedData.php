<?php

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\Catalog\Model\Category\DataProvider;

class AddReindexUrlToLoadedData
{
    /**
     * @var Magento\Backend\Model\Url
     */
    protected $urlBuilder;

    public function __construct(\Magento\Backend\Model\Url $urlBuilder)
    {
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @param \Magento\Catalog\Model\Category\DataProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetData(\Magento\Catalog\Model\Category\DataProvider $subject, $result)
    {
        $category = $subject->getCurrentCategory();
        $result[$category->getId()]['reindex_url'] = $this->getReindexUrl($category->getId());

        return $result;
    }

    /**
     * @param string|null $categoryId
     * @return string|null
     */
    protected function getReindexUrl(?string $categoryId): ?string
    {
        return $this->urlBuilder->getUrl(
            'catalog/category_virtual/reindex',
            [
                'id' => $categoryId,
                '_current' => true,
                '_secure' => true
            ]
        );
    }
}
