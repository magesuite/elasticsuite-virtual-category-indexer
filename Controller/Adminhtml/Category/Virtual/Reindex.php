<?php

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Controller\Adminhtml\Category\Virtual;

class Reindex extends \Magento\Backend\App\Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageSuite_ElasticsuiteVirtualCategoryIndexer::config_virtual_category_indexer';

    protected \Magento\Backend\Model\Url $urlBuilder;
    protected \Magento\Framework\Controller\Result\JsonFactory $jsonFactory;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\VirtualCategoryIndexer $virtualCategoryIndexerResourceModel;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Backend\Model\Url $urlBuilder,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\ResourceModel\VirtualCategoryIndexer $virtualCategoryIndexerResourceModel,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->jsonFactory = $jsonFactory;
        $this->virtualCategoryIndexerResourceModel = $virtualCategoryIndexerResourceModel;
        $this->configuration = $configuration;

        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->configuration->isEnabled()) {
            $responseData = $this->forceReindex();
        } else {
            $responseData = [
                'message' => __('Virtual category indexer is disabled. Enable it in <a href="%1" target="_blank">configuration</a>.', $this->getConfigurationUrl())
            ];
        }

        $resultJson = $this->jsonFactory->create();

        return $resultJson->setData($responseData);
    }

    protected function forceReindex(): array
    {
        try {
            $categoryId = $this->getRequest()->getParam('id');
            $indexer = $this->virtualCategoryIndexerResourceModel->getIndexer();

            if ($indexer->isScheduled()) {
                $this->virtualCategoryIndexerResourceModel->scheduleReindex((int)$categoryId);
            } else {
                $indexer->reindexRow($categoryId);
            }

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

    public function getConfigurationUrl(): string
    {
        return $this->urlBuilder->getUrl(
            'adminhtml/system_config/edit/section/virtual_category_indexer',
            [
                '_secure' => true
            ]
        );
    }
}
