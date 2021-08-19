<?php

declare(strict_types=1);

namespace MageSuite\ElasticsuiteVirtualCategoryIndexer\Console\Command;

class VirtualCategoryIndexer extends \Symfony\Component\Console\Command\Command
{
    protected const OPTION_INDEXER_STRATEGY = 'strategy';
    protected const OPTION_INDEXER_CATEGORY_IDS = 'Category ids';
    protected const STRATEGY_QUESTION_MESSAGE = 'Choose correct strategy [%s]';
    protected const IDS_QUESTION_MESSAGE = 'Provide correct category ids separated by comma';
    protected const ID_QUESTION_MESSAGE = 'Provide correct category id';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface
     */
    protected $virtualCategoryIndexerService;

    /**
     * @var \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterfaceFactory
     */
    protected $virtualCategoryIndexerServiceFactory;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    public function __construct(
        \Magento\Framework\App\State $state,
        \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterfaceFactory $virtualCategoryIndexerServiceFactory,
        \Psr\Log\LoggerInterface $logger,
        string $name = null
    ) {
        parent::__construct($name);

        $this->logger = $logger;
        $this->state = $state;
        $this->virtualCategoryIndexerServiceFactory = $virtualCategoryIndexerServiceFactory;
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $options = [
            new \Symfony\Component\Console\Input\InputOption(
                self::OPTION_INDEXER_STRATEGY,
                's',
                \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
                'Indexer strategy: full (default), list, category'
            ),

            new \Symfony\Component\Console\Input\InputOption(
                self::OPTION_INDEXER_CATEGORY_IDS,
                'c',
                \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
                'Category ids separated by comma'
            )
        ];

        $this->setName('indexer:reindex:virtual-category')
            ->setDescription('Copy product ids connected to category ids into catalog_category_product table')
            ->setDefinition($options);

        parent::configure();
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $this->virtualCategoryIndexerService = $this->virtualCategoryIndexerServiceFactory->create();

        if (!$this->virtualCategoryIndexerService->isEnabled()) {
            $output->writeln("Module is disabled in store configuration");
            return;
        }

        try {
            $this->state->emulateAreaCode(
                \Magento\Framework\App\Area::AREA_ADMINHTML,
                [$this, 'runIndexer'],
                [$input, $output]
            );
        } catch (\InvalidArgumentException | \Exception $e) {
            $output->writeln($e->getMessage());
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function runIndexer(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $strategy = $this->getStrategy($input, $output);
        $categoryIds = (array) $this->getCategoryIds($strategy, $input, $output);

        $this->virtualCategoryIndexerService->setStrategy($strategy)
            ->setCategoryIds($categoryIds)
            ->execute();
    }

    /**
     * @return string
     */
    protected function getStrategy(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $strategy = $input->getOption(static::OPTION_INDEXER_STRATEGY);

        if (!$strategy) {
            return \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::STRATEGY_FULL;
        }

        $strategies = $this->virtualCategoryIndexerService->getStrategies();

        if (!isset($strategies[$strategy])) {
            $strategyKeys = array_keys($strategies);
            $strategyKeys = implode('/', $strategyKeys);
            $message = sprintf(self::STRATEGY_QUESTION_MESSAGE, $strategyKeys);

            $this->callQuestion($message, self::OPTION_INDEXER_STRATEGY, $input, $output);

            return $this->getStrategy($input, $output);
        }

        return $strategy;
    }

    /**
     * @param string|null $strategy
     * @return array|null
     */
    protected function getCategoryIds(
        ?string $strategy,
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ): ?array {
        if (!$strategy || $strategy === \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::STRATEGY_FULL) {
            return null;
        }

        $categoryIds = $input->getOption(self::OPTION_INDEXER_CATEGORY_IDS);

        if (!$categoryIds || preg_match('/[^0-9,]/i', $categoryIds)) {
            $message = $strategy == \MageSuite\ElasticsuiteVirtualCategoryIndexer\Api\VirtualCategoryIndexerInterface::STRATEGY_CATEGORY
                ? self::ID_QUESTION_MESSAGE
                : self::IDS_QUESTION_MESSAGE;

            $this->callQuestion($message, self::OPTION_INDEXER_CATEGORY_IDS, $input, $output);

            return $this->getCategoryIds($strategy, $input, $output);
        }

        return explode(',', $categoryIds);
    }

    /**
     * @param string $message
     * @param string $param
     */
    protected function callQuestion(
        string $message,
        string $param,
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ): void {
        $question = new \Symfony\Component\Console\Question\Question(
            sprintf('<question>%s:</question> ', $message),
            ''
        );

        $questionHelper = $this->getHelper('question');

        $input->setOption(
            $param,
            $questionHelper->ask($input, $output, $question)
        );
    }
}
