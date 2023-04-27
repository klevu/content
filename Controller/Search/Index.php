<?php

namespace Klevu\Content\Controller\Search;

use Magento\CatalogSearch\Helper\Data as CatalogSearchHelper;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\StateInterface as CacheStateInterface;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeListInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    /**
     * @var UrlInterface
     * @deprecated not in use
     * @see nothing
     */
    protected $_magentoFrameworkUrlInterface;
    /**
     * @var CacheTypeListInterface
     * @deprecated not in use
     * @see nothing
     */
    protected $_cacheTypeList;
    /**
     * @var CacheStateInterface
     * @deprecated not in use
     * @see nothing
     */
    protected $_cacheState;
    /**
     * @var CacheFrontendPool
     * @deprecated not in use
     * @see nothing
     */
    protected $_cacheFrontendPool;
    /**
     * @var PageFactory
     * @deprecated not in use
     * @see nothing
     */
    protected $resultPageFactory;
    /**
     * @var CatalogSearchHelper
     */
    protected $_catalogSearchHelper;

    /**
     * @param Context $context
     * @param CacheTypeListInterface $cacheTypeList
     * @param CacheStateInterface $cacheState
     * @param CacheFrontendPool $cacheFrontendPool
     * @param PageFactory $resultPageFactory
     * @param CatalogSearchHelper $catalogSearchHelper
     */
    public function __construct(
        Context $context,
        CacheTypeListInterface $cacheTypeList,
        CacheStateInterface $cacheState,
        CacheFrontendPool $cacheFrontendPool,
        PageFactory $resultPageFactory,
        CatalogSearchHelper $catalogSearchHelper
    ) {
        parent::__construct($context);
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheState = $cacheState;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->resultPageFactory = $resultPageFactory;
        $this->_catalogSearchHelper = $catalogSearchHelper;
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $query = $this->_catalogSearchHelper->getEscapedQueryText();
        $this->_view->loadLayout();
        $this->_view->getPage()->getConfig()->getTitle()->set(__("Content Search for: '%1'", $query));
        $this->_view->renderLayout();
    }
}
