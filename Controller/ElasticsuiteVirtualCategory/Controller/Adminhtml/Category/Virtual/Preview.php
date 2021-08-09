<?php

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Controller\ElasticsuiteVirtualCategory\Controller\Adminhtml\Category\Virtual;

/**
 * Due to the need to add extesion attribute (virtual_query=true) in the loadCategory() method
 * and private scopes of other methods in the class,
 * not only the loadCategory() method but the entire class was overwritten
 *
 * Prive scopes has been changed to protected according to CreativeStyle coding standards.
 *
 * @see \Smile\ElasticsuiteVirtualCategory\Model\Preview
 */
class Preview extends \Magento\Backend\App\Action
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::categories';

    /**
     * @var \Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory
     */
    protected $previewModelFactory;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Catalog\Model\CategoryRepository
     */
    protected $categoryRepository;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Smile\ElasticsuiteVirtualCategory\Model\PreviewFactory $previewModelFactory,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    ) {
        parent::__construct($context);

        $this->categoryRepository = $categoryRepository;
        $this->previewModelFactory = $previewModelFactory;
        $this->jsonHelper = $jsonHelper;
    }

    /**
     * {@inheritDoc}
     */
    public function execute()
    {
        $responseData = $this->getPreviewObject()->getData();
        $json = $this->jsonHelper->jsonEncode($responseData);

        $this->getResponse()->representJson($json);
    }

    /**
     * Load and initialize the preview model.
     *
     * @return \Smile\ElasticsuiteVirtualCategory\Model\Preview
     */
    protected function getPreviewObject()
    {
        $category = $this->getCategory();
        $pageSize = $this->getPageSize();
        $search = $this->getRequest()->getParam('search');

        return $this->previewModelFactory->create(['category' => $category, 'size' => $pageSize, 'search' => $search]);
    }

    /**
     * Load current category and apply admin current modifications (added and removed products, updated virtual rule, ...).
     *
     * @return \Magento\Catalog\Api\Data\CategoryInterface
     */
    protected function getCategory()
    {
        $category = $this->loadCategory();

        $this->addVirtualCategoryData($category)
            ->addSelectedProducts($category)
            ->setSortedProducts($category);

        return $category;
    }

    /**
     * Load current category using the request params.
     *
     * @return \Magento\Catalog\Api\Data\CategoryInterface
     */
    protected function loadCategory()
    {
        $storeId = $this->getRequest()->getParam('store');
        $categoryId = $this->getRequest()->getParam('entity_id');

        $category = $this->categoryRepository->get($categoryId, $storeId);

        $extensionAttributes = $category->getExtensionAttributes();
        $extensionAttributes->setVirtualQuery(true);
        $category->setExtensionAttributes($extensionAttributes);

        return $category;
    }

    /**
     * Append virtual rule params to the category.
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category Category.
     *
     * @return Preview
     */
    protected function addVirtualCategoryData(\Magento\Catalog\Api\Data\CategoryInterface $category)
    {
        $isVirtualCategory = (bool)$this->getRequest()->getParam('is_virtual_category');
        $category->setIsVirtualCategory($isVirtualCategory);

        if ($isVirtualCategory) {
            $category->getVirtualRule()->loadPost($this->getRequest()->getParam('virtual_rule', []));
            $category->setVirtualCategoryRoot($this->getRequest()->getParam('virtual_category_root', null));
        }

        return $this;
    }

    /**
     * Add user selected products.
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category Category.
     *
     * @return Preview
     */
    protected function addSelectedProducts(\Magento\Catalog\Api\Data\CategoryInterface $category)
    {
        $selectedProducts = $this->getRequest()->getParam('selected_products', []);

        $addedProducts = isset($selectedProducts['added_products']) ? $selectedProducts['added_products'] : [];
        $category->setAddedProductIds($addedProducts);

        $deletedProducts = isset($selectedProducts['deleted_products']) ? $selectedProducts['deleted_products'] : [];
        $category->setDeletedProductIds($deletedProducts);

        return $this;
    }

    /**
     * Append products sorted by the user to the category.
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category Category.
     *
     * @return Preview
     */
    protected function setSortedProducts(\Magento\Catalog\Api\Data\CategoryInterface $category)
    {
        $productPositions = $this->getRequest()->getParam('product_position', []);
        $category->setSortedProductIds(array_keys($productPositions));

        return $this;
    }

    /**
     * Return the preview page size.
     *
     * @return int
     */
    protected function getPageSize()
    {
        return (int)$this->getRequest()->getParam('page_size');
    }
}
