<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Cron;

class VirtualCategoryIndexer
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface
     */
    protected $virtualCategoryIndexerService;

    public function __construct(
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface $virtualCategoryIndexerService,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->virtualCategoryIndexerService = $virtualCategoryIndexerService;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        try {
            $this->virtualCategoryIndexerService->setStrategy(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::STRATEGY_FULL)
                ->execute();
        } catch (\InvalidArgumentException | \Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            return;
        }
    }
}
