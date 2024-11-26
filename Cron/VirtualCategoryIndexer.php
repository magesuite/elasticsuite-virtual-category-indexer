<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Cron;

class VirtualCategoryIndexer
{
    protected \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry;
    protected \Psr\Log\LoggerInterface $logger;

    public function __construct(
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->indexerRegistry = $indexerRegistry;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            $indexer = $this->indexerRegistry->get(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::INDEXER_ID);

            if ($indexer->isScheduled()) {
                $indexer->invalidate();
            } else {
                $indexer->reindexAll();
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
        }
    }
}
