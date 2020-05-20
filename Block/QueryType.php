<?php
namespace Klevu\Content\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Klevu\Content\Helper\Data as Klevu_ContentHelper;
use Klevu\Search\Helper\Config as Klevu_SearchHelperConfig;

class QueryType extends Template
{
	/**
     * @param Context $context     
	 * @param Klevu_ContentHelper $contentHelperData
     * @param Klevu_SearchHelperConfig $searchHelperConfig	
     * @param array $data
     */
	public function __construct(
        Context $context,
        Klevu_ContentHelper $contentHelperData,     
		Klevu_SearchHelperConfig $searchHelperConfig,
		array $data = []
    ) {
		
        parent::__construct($context, $data);
        $this->_contentHelperData = $contentHelperData;    
		$this->_searchHelperConfig = $searchHelperConfig;
    }
	    
    /**
     * Check whether the CMS enabled or not
     * @return bool
     */
	public function isCmsSyncEnabledOnFront() 
	{
		return $this->_contentHelperData->isCmsSyncEnabledOnFront();
	}
	
	/**
     * Check whether the content module configured
     * @return bool
     */
	public function isExtensionConfigured() 
	{
		return $this->_searchHelperConfig->isExtensionConfigured();
	}
	
}
