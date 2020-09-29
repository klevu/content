<?php

namespace Klevu\Content\Console\Command;

use Klevu\Content\Model\ContentInterface as KlevuContentSync;
use Klevu\Content\Model\MagentoContentActions as MagentoContentActions;
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

    const LOCK_FILE = 'cms_klevu_running_index.lock';
    const AREA_CODE_LOCK_FILE = 'klevu_cms_areacode.lock';

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
     * @param KlevuContent $contentSync
     */
    public function __construct(
        State $state,
        StoreManagerInterface $storeInterface,
        DirectoryList $directoryList,
        LoggerInterface $logger
    )
    {
        $this->state = $state;
        $this->directoryList = $directoryList;
        $this->storeInterface = $storeInterface;
        $this->_logger = $logger;
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
            }

        } catch (\Exception $e) {
            $output->writeln('<error>Error thrown in store wise CMS data sync: ' . $e->getMessage() . '</error>');
            if (isset($storeLockFile)) {
                if (file_exists($storeLockFile)) {
                    unlink($storeLockFile);
                }
            }
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
}

