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
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->categoryRepository = $categoryRepository;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        try {
            $categoryId = $this->getRequest()->getParam('id');
            $category = $this->categoryRepository->get($categoryId);

            $category->setData(
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE,
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED
            );

            $this->categoryRepository->save($category);

            $responseData = [
                'message' => __('Product assignments will be reindexed in the next few minutes.')
            ];
        } catch (\Exception $e) {
            $responseData = [
                'message' => __('Forcing reindex was not successful.')
            ];
        }

        $resultJson = $this->jsonFactory->create();

        return $resultJson->setData($responseData);
    }
}
