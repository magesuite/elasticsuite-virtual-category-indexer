<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Cron;

class VirtualCategoryIndexer
{
    protected \Psr\Log\LoggerInterface $logger;
    protected \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface $virtualCategoryIndexerService;

    public function __construct(
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface $virtualCategoryIndexerService,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->virtualCategoryIndexerService = $virtualCategoryIndexerService;
    }

    public function execute()
    {
        try {
            $this->virtualCategoryIndexerService->setStrategy(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::STRATEGY_FULL)
                ->execute();
        } catch (\InvalidArgumentException|\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            return;
        }
    }
}
