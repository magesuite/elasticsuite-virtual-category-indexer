<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Test\Integration\Model;

class RuleTest extends \PHPUnit\Framework\TestCase
{
    protected ?\Magento\Framework\App\ObjectManager $objectManager;
    protected ?\Magento\Framework\App\CacheInterface $cache;
    protected ?\Smile\ElasticsuiteVirtualCategory\Model\Rule $virtualCategoryRule;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();

        $this->cache = $this
            ->getMockBuilder(\Magento\Framework\App\CacheInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->virtualCategoryRule = $this->objectManager->create(
                \Smile\ElasticsuiteVirtualCategory\Model\Rule::class,
                ['cache' => $this->cache]
            );
    }

    public function testItGenerateProperCacheKey()
    {
        $expectedCacheKey = 'getCategorySearchQuery|1|1|0|1';

        $this->cache
            ->expects($this->once())
            ->method('load')
            ->with($expectedCacheKey)
            ->willReturn(null);

        $this->virtualCategoryRule->setStoreId(1);
        $this->virtualCategoryRule->getCategorySearchQuery(1);
    }
}
