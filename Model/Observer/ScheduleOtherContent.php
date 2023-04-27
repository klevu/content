<?php

namespace Klevu\Content\Model\Observer;

use Klevu\Content\Helper\Data as ContentHelper;
use Klevu\Content\Model\ContentInterface;
use Klevu\Search\Helper\Config as ConfigHelper;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ScheduleOtherContent implements ObserverInterface
{
    /**
     * @var ContentHelper
     */
    protected $_contentHelperData;
    /**
     * @var ContentInterface
     */
    protected $_contentModelContent;
    /**
     * @var BackendSession
     */
    protected $_backendModelSession;
    /**
     * @var ConfigHelper
     */
    protected $_contentHelperConfig;

    /**
     * @param ContentHelper $contentHelperData
     * @param ConfigHelper $contentHelperConfig
     * @param ContentInterface $contentModelContent
     * @param BackendSession $backendModelSession
     */
    public function __construct(
        ContentHelper $contentHelperData,
        ConfigHelper $contentHelperConfig,
        ContentInterface $contentModelContent,
        BackendSession $backendModelSession
    ) {
        $this->_contentHelperData = $contentHelperData;
        $this->_contentModelContent = $contentModelContent;
        $this->_backendModelSession = $backendModelSession;
        $this->_contentHelperConfig = $contentHelperConfig;
    }

    /**
     * Run Other content based on event call.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedIf
        if ($this->_contentHelperConfig->isExternalCronEnabled()) {
            //No need to schedule the Content CRON, will be removing in later versions
            //$this->_contentModelContent->schedule();
        }
    }
}
