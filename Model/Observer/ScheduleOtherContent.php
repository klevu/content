<?php
namespace Klevu\Content\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Layout\Interceptor;

class ScheduleOtherContent implements ObserverInterface
{

    /**
     * @var \Klevu\Content\Helper\Data
     */
    protected $_contentHelperData;

    /**
     * @var \Klevu\Content\Model\ContentInterface
     */
    protected $_contentModelContent;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $_backendModelSession;

    public function __construct(
        \Klevu\Content\Helper\Data $contentHelperData,
	    \Klevu\Search\Helper\Config $contentHelperConfig,
        \Klevu\Content\Model\ContentInterface $contentModelContent,
        \Magento\Backend\Model\Session $backendModelSession
    ) {
    
        $this->_contentHelperData = $contentHelperData;
        $this->_contentModelContent = $contentModelContent;
        $this->_backendModelSession = $backendModelSession;
		$this->_contentHelperConfig = $contentHelperConfig;
		
    }

    /**
     * Run Other content based on event call.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
		if($this->_contentHelperConfig->isExternalCronEnabled()) {
			$this->_contentModelContent->schedule();
		}
    }
}
