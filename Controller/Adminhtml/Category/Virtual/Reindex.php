<?php

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Controller\Adminhtml\Category\Virtual;

class Reindex extends \Magento\Backend\App\Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageSuite_ElasticsuiteVirtualCategoryIndexer::config_virtual_category_indexer';

    /**
     * @var \Magento\Catalog\Model\CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category
     */
    protected $categoryResourceModel;

    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration
     */
    protected $configuration;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var \Magento\Backend\Model\Url
     */
    protected $urlBuilder;

    public function __construct(
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Catalog\ResourceModel\Category $categoryResourceModel,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Backend\Model\Url $urlBuilder,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->configuration = $configuration;
        $this->jsonFactory = $jsonFactory;
        $this->urlBuilder = $urlBuilder;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {

        if ($this->configuration->isEnabled()) {
            $responseData = $this->forceReindexRequiredStatus();
        } else {
            $responseData = [
                'message' => __('Virtual category indexer is disabled. Enable it in <a href="%1" target="_blank">configuration</a>.', $this->getConfigurationUrl())
            ];
        }

        $resultJson = $this->jsonFactory->create();

        return $resultJson->setData($responseData);
    }

    /**
     * @return array
     */
    protected function forceReindexRequiredStatus(): array
    {
        if (!$this->configuration->isEnabled()) {
            $responseData = [
                'message' => __('Forcing reindex was not successful.')
            ];
        } else {
            $responseData = $this->forceReindex();
        }

        return $responseData;
    }

    /**
     * @return array
     */
    protected function forceReindex(): array
    {
        try {
            $categoryId = $this->getRequest()->getParam('id');
            $storeId = $this->getRequest()->getParam('store', 0);

            $category = $this->categoryRepository->get($categoryId, $storeId);

            $this->categoryResourceModel->setReindexRequired($category);

            $category->save();

            $responseData = [
                'message' => __('Product assignments will be reindexed in the next few minutes.')
            ];
        } catch (\Exception $e) {
            $responseData = [
                'message' => __('Forcing reindex was not successful.')
            ];
        }
        return $responseData;
    }

    public function getConfigurationUrl()
    {
        return $this->urlBuilder->getUrl(
            'adminhtml/system_config/edit/section/virtual_category_indexer',
            [
                '_secure' => true
            ]
        );
    }
}
