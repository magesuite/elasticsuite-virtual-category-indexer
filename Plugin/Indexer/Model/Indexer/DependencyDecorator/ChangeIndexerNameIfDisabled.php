<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Plugin\Indexer\Model\Indexer\DependencyDecorator;

class ChangeIndexerNameIfDisabled
{
    protected const DISABLED_INDEXER_NAME = '(DISABLED) %s';
    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration
     */
    protected $configuration;

    public function __construct(\MageSuite\ElasticsuiteVirtualCategoryIndexer\Helper\Configuration\Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param \Magento\Indexer\Model\Indexer\DependencyDecorator $subject
     * @param string $result
     * @return mixed|string
     */
    public function afterGetTitle(\Magento\Indexer\Model\Indexer\DependencyDecorator $subject, $result)
    {
        if ($subject->getId() != \MageSuite\ElasticsuiteVirtualCategoryIndexer\Model\Indexer\VirtualCategoryIndexer::INDEXER_ID) {
            return $result;
        }

        if ($this->configuration->isEnabled()) {
            return $result;
        }

        return sprintf(self::DISABLED_INDEXER_NAME, $result);
    }
}
