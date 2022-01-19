<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Setup\Patch\Data;

/** Patch installing virtual_category_indexing attribute. Value 1 informe that indexer is processing */
class VirtualCategoryIndexingAttribute implements \Magento\Framework\Setup\Patch\DataPatchInterface
{
    /**
     * @var \Magento\Eav\Setup\EavSetup
     */
    protected $eavSetup;

    /**
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    protected $moduleDataSetupInterface;

    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetupInterface
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->moduleDataSetupInterface = $moduleDataSetupInterface;
        $this->eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetupInterface]);
    }

    public function apply()
    {
        if (!$this->eavSetup->getAttributeId(
            \Magento\Catalog\Model\Category::ENTITY,
            \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE
        )) {
            $this->eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::VIRTUAL_CATEGORY_REINDEX_REQUIRED_ATTRIBUTE,
                [
                    'type' => 'int',
                    'label' => 'Virtual category requires reindexing',
                    'default' => '0',
                    'required' => false,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'group' => 'General Information'
                ]
            );
        }
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
