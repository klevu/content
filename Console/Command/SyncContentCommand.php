<?php

namespace Klevu\Content\Console\Command;

use Klevu\Content\Model\ContentInterface as KlevuContentSync;
use Klevu\Content\Model\MagentoContentActions as MagentoContentActions;
use Klevu\Logger\Api\StoreScopeResolverInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList as DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterface;
use Psr\Log\LoggerInterface as LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncContentCommand
 * @package Klevu\Content\Console\Command
 */
class SyncContentCommand extends Command
{
    const ALLDATA_CMS_DESC = 'Send all CMS pages to Klevu.';
    const UPDATESONLY_CMS_DESC = 'Only send those CMS pages which have been modified since the last sync with Klevu.';

    const LOCK_FILE = 'klcmsentity_klevu_running_index.lock';
    const AREA_CODE_LOCK_FILE = 'klevu_cmsentity_areacode.lock';

    /**
     * @var State
     */
    protected $state;

    /**
     * @var StoreManagerInterface
     */
    protected $storeInterface;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var StoreScopeResolverInterface
     */
    private $storeScopeResolver;

    /**
     * @var string|null
     */
    private $klevuLoggerFQCN;

    /**
     * @var KlevuContent
     */
    protected $contentSync;

    /**
     * @var \Klevu\Content\Model\MagentoContentActions
     */
    protected $magentoContentActions;

    /**
     * @param State $state
     * @param StoreManagerInterface $storeInterface
     * @param DirectoryList $directoryList
     * @param LoggerInterface $logger
     * @param StoreScopeResolverInterface|null $storeScopeResolver
     * @param string|null $klevuLoggerFQCN
     */
    public function __construct(
        State $state,
        StoreManagerInterface $storeInterface,
        DirectoryList $directoryList,
        LoggerInterface $logger,
        StoreScopeResolverInterface $storeScopeResolver = null,
        $klevuLoggerFQCN = null
    ) {
        $this->state = $state;
        $this->storeInterface = $storeInterface;
        $this->directoryList = $directoryList;
        $this->_logger = $logger;
        $this->storeScopeResolver = $storeScopeResolver;
        if (is_string($klevuLoggerFQCN)) {
            $this->klevuLoggerFQCN = $klevuLoggerFQCN;
        }

        parent::__construct();
    }

    /**
     * Configure this command
     */
    protected function configure()
    {
        $this->setName('klevu:sync:cmspages')
            ->setDescription('
            Sync CMS Pages with Klevu for all stores.
            You can specify whether to process all cmspages or just those that have changed via an option detailed below.
            If no option is specified, --updatesonly will be used.')
            ->setDefinition($this->getInputList())
            ->setHelp(
                <<<HELP

Only send CMS pages which have been modified since the last sync with Klevu:
    <comment>%command.full_name% --updatesonly</comment>

Send all CMS pages to Klevu:
    <comment>%command.full_name% --alldata</comment>

HELP
            );
        parent::configure();
    }


    /**
     * Run the content sync command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // See comments against methods for background. Ref: KS-7853
        $this->initLogger();
        $this->initStoreScopeResolver();

        $this->storeScopeResolver->setCurrentStoreById(0);
        $logDir = $this->directoryList->getPath(DirectoryList::VAR_DIR);
        $storeLockFile = '';
        $areaCodeFile = $logDir . "/" . self::AREA_CODE_LOCK_FILE;
        try {
            if (file_exists($areaCodeFile)) {
                unlink($areaCodeFile);
            }
            $this->state->setAreaCode(Area::AREA_FRONTEND);
        } catch (LocalizedException $e) {
            fopen($areaCodeFile, 'w');
            if ($this->state->getAreaCode() != Area::AREA_FRONTEND) {
                $output->writeln(__(
                    sprintf('CMS sync running in an unexpected state AreaCode : (%s)', $this->state->getAreaCode())
                )->getText());
            }
        }

        $storeList = $this->storeInterface->getStores();
        $syncFailed = $syncSuccess = array();
        $storeCodesForCMS = array();
        foreach ($storeList as $store) {
            if (!isset($this->websiteList[$store->getWebsiteId()])) $this->websiteList[$store->getWebsiteId()] = array();
            $this->websiteList[$store->getWebsiteId()] = array_unique(array_merge($this->websiteList[$store->getWebsiteId()], array($store->getCode())));
            $storeCodesForCMS[] = $store->getCode();
        }
        $this->magentoContentActions = ObjectManager::getInstance()->get(MagentoContentActions::class);
        $this->contentSync = ObjectManager::getInstance()->get(KlevuContentSync::class);
        try {
            $output->writeln("=== Starting storewise CMS data sync ===");
            $output->writeln('');

            if ($input->hasParameterOption('--alldata')) {
                $output->writeln('<info>CMS Synchronization started using --alldata option.</info>');
                $this->magentoContentActions->markCMSRecordIntoQueue();
            } elseif ($input->hasParameterOption('--updatesonly')) {
                $output->writeln('<info>CMS Synchronization started using --updatesonly option.</info>');
            } else {
                $output->writeln('<info>No option provided. CMS Synchronization started using updatesonly option.</info>');
            }

            if (count($storeCodesForCMS) > 0) {
                foreach ($storeCodesForCMS as $rowStoreCode) {
                    $this->storeScopeResolver->setCurrentStoreByCode($rowStoreCode);

                    $storeLockFile = $logDir . "/" . $rowStoreCode . "_" . self::LOCK_FILE;
                    if (file_exists($storeLockFile)) {
                        $output->writeln('<error>Klevu CMS sync process cannot start because a lock file exists for store code: ' . $rowStoreCode . ', skipping this store.</error>');
                        $output->writeln("");
                        $syncFailed[] = $rowStoreCode;
                        continue;
                    }
                    fopen($storeLockFile, 'w');
                    $rowStoreObject = $this->storeInterface->getStore($rowStoreCode);
                    if (!is_object($rowStoreObject)) {
                        $output->writeln('<error>Store object found invalid for store code : ' . $rowStoreCode . ', skipping this store.</error>');
                        $output->writeln("");
                        $syncFailed[] = $rowStoreCode;
                        continue;
                    }
                    $output->writeln('');
                    $output->writeln("<info>CMS Sync started for store code : " . $rowStoreObject->getCode() . "</info>");
                    $msg = $this->contentSync->syncCmsData($rowStoreObject);
                    if (!empty($msg)) {
                        $output->writeln("<comment>" . $msg . "</comment>");
                    }
                    $output->writeln("<info>CMS Sync completed for store code : " . $rowStoreObject->getCode() . "</info>");

                    $syncSuccess[] = $rowStoreObject->getCode();

                    if (file_exists($storeLockFile)) {
                        unlink($storeLockFile);
                    }
                    $output->writeln("<info>********************************</info>");
                }
                $this->storeScopeResolver->setCurrentStoreById(0);
            }

        } catch (\Exception $e) {
            $output->writeln('<error>Error thrown in store wise CMS data sync: ' . $e->getMessage() . '</error>');
            if (isset($storeLockFile)) {
                if (file_exists($storeLockFile)) {
                    unlink($storeLockFile);
                }
            }
            $this->storeScopeResolver->setCurrentStoreById(0);

            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
        $output->writeln('');
        if (!empty($syncSuccess)) {
            $output->writeln('<info>CMS Sync successfully completed for store code(s): ' . implode(",", $syncSuccess) . '</info>');
        }
        if (!empty($syncFailed)) {
            $output->writeln('<error>CMS Sync did not complete for store code(s): ' . implode(",", $syncFailed) . '</error>');
        }

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }


    public function getInputList()
    {
        $inputList = [];

        $inputList[] = new InputOption(
            'updatesonly',
            null,
            InputOption::VALUE_OPTIONAL,
            self::UPDATESONLY_CMS_DESC
        );

        $inputList[] = new InputOption(
            'alldata',
            null,
            InputOption::VALUE_OPTIONAL,
            self::ALLDATA_CMS_DESC
        );

        return $inputList;
    }

    /**
     * Check that the logger property is of the expected class and, if not, create using OM
     *
     * In order to support updates from 2.3.x to 2.4.x, which introduced the logger module,
     *  we can't inject the actual logger through DI as all CLI commands are instantiated
     *  by bin/magento. This prevents setup:upgrade running and enabling the logger module
     *  because the logger module isn't already enabled.
     * As such, we pass an FQCN for the desired logger class and then check that it matches
     *  at the start of any method utilising it
     * We avoid temporal coupling by falling back to the standard LoggerInterface in the
     *  constructor
     *
     * @return void
     */
    private function initLogger()
    {
        if (!($this->_logger instanceof LoggerInterface)) {
            $objectManager = ObjectManager::getInstance();
            if ($this->klevuLoggerFQCN && !($this->_logger instanceof $this->klevuLoggerFQCN)) {
                $this->_logger = $objectManager->get($this->klevuLoggerFQCN);
            } elseif (!$this->_logger) {
                $this->_logger = $objectManager->get(LoggerInterface::class);
            }
        }
    }

    /**
     * Instantiate the StoreScopeResolver property
     *
     * For the same reasons as initLogger is required, we can't inject a class from a new
     *  module into a CLI command. Unlike initLogger, however, this is a new property so
     *  the usual $this->>storeScopeResolver = $storeScopeResolver ?: ObjectManager::getInstance()->get(StoreScopeResolverInterface::class)
     *  logic can effectively be used without checking for a class mismatch
     *
     * @return void
     */
    private function initStoreScopeResolver()
    {
        if (null === $this->storeScopeResolver) {
            $this->storeScopeResolver = ObjectManager::getInstance()->get(StoreScopeResolverInterface::class);
        }
    }
}
