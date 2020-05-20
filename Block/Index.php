<?php
namespace Klevu\Content\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Klevu\Content\Helper\Data as Klevu_ContentHelper;
use Magento\CatalogSearch\Helper\Data as Magento_CatalogSearchHelper;
use Klevu\Search\Helper\Config as Klevu_SearchHelperConfig;

class Index extends Template
{
	protected $_context;
	
	/**
     * @param Context $context     
	 * @param Klevu_ContentHelper $contentHelperData
     * @param Klevu_SearchHelperConfig $searchHelperConfig
	 * @param Magento_CatalogSearchHelper $catalogSearchHelper
     * @param array $data
     */
	public function __construct(
		Context $context,             
        Klevu_ContentHelper $contentHelperData,     
		Klevu_SearchHelperConfig $searchHelperConfig,       
		Magento_CatalogSearchHelper $catalogSearchHelper,
		array $data = []
    
	) {
		$this->_context = $context;
		parent::__construct($context, $data);        
        $this->_contentHelperData = $contentHelperData;    
		$this->_searchHelperConfig = $searchHelperConfig;		        
		$this->_catalogSearchHelper = $catalogSearchHelper;
    }
	
    
    /**
     * Get the Klevu other content
	 *
     * @return array
     */
    public function getCmsContent()
    {		
        return $this->_contentHelperData->getCmsData();
         
    }
	
    /**
     * Return the Klevu other content filters
	 *
     * @return array
     */
    public function getContentFilters()
    {
        return $this->_contentHelperData->getKlevuFilters();      
    }
	
	/**
     * Return query param from request
	 *
     * @return string
     */
	public function getQueryParam() 
	{		
		return $this->_context->getRequest()->getParam("q");
	}
	
	/**
     * Check whether the cms enabled or not
	 *
     * @return bool
     */
	public function isCmsSyncEnabledOnFront() 
	{
		return $this->_contentHelperData->isCmsSyncEnabledOnFront();
	}
	
	/**
     * Return the escaped query text
	 *
     * @return string
     */
	public function getEscapedQueryText() 
	{
		return $this->_catalogSearchHelper->getEscapedQueryText();
	}
	
	/**
     * Return the requested module name
	 *
     * @return string
     */
	public function getModuleName() 
	{		
		return $this->_context->getRequest()->getModuleName();
	}
	
	/**
     * Check whether the content module configured
	 *
     * @return bool
     */
	public function isExtensionConfigured() 
	{
		return $this->_searchHelperConfig->isExtensionConfigured();
	}
	
	/**
     * Return the catalog search URL
	 *
     * @return string
     */
	public function getCatalogSearchURL()
	{		
		return $this->getUrl('catalogsearch/result/')."?q=".$this->getEscapedQueryText();
	}	
	
	/**
     * Return the content search URL
	 *
     * @return string
     */
	public function getContentSearchURL()
	{		
		return $this->getUrl('content/search')."?q=".$this->getEscapedQueryText();
	}
	
}
