<?php
namespace Klevu\Content\Block\Adminhtml\Form;

use Magento\Backend\Block\Template\Context as Template_Context;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterface;


class Cmspages extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    
    /**
     * @var Customerpage
     */
    protected $_pageRenderer;

	/**
     * @var Context
     */
	private $_context;

	/**
     * @var storeManager
     */
    private $storeManager;



    public function __construct(
        Template_Context $context,

        array $data = []

    ) {

        parent::__construct($context,$data);
        $this->_context = $context;

    }
	
	/**
     * Retrieve HTML markup for given form element
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->storeManager = $this->_context->getStoreManager();
        $store_mode = $this->storeManager->isSingleStoreMode();
        if(!$store_mode && $element->getScope() != "stores" && $element->getHtmlId() == 'klevu_search_cmscontent_excludecms_pages')  {
            return;
        }

        return parent::render($element);
    }


    /**
     * Check if inheritance checkbox has to be rendered
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return bool
     */
    protected function _isInheritCheckboxRequired(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->storeManager = $this->_context->getStoreManager();
        $store_mode = $this->storeManager->isSingleStoreMode();

        if(!$store_mode && $element->getScope() == "stores" && $element->getHtmlId() == 'klevu_search_cmscontent_excludecms_pages')  {
            return;
        }

        return $element->getCanUseWebsiteValue()
            || $element->getCanUseDefaultValue()
            || $element->getCanRestoreToDefault();
    }
	
	
    /**
     * Retrieve page column renderer
     *
     * @return Customerpage
     */
    protected function _getpageRenderer()
    {
        $this->_cmsModelPage = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Cms\Model\Page');
        $cms_pages = $this->_cmsModelPage->getCollection()->addFieldToSelect(["page_id","title"])->addFieldToFilter('is_active', 1);
        $page_ids = $cms_pages->getData();
        $cmsOptions = array();
        foreach ($page_ids as $id) {
            $cmsOptions[$id['page_id']] = addslashes($id['title']);
        }
        if (!$this->_pageRenderer) {
            $this->_pageRenderer = $this->getLayout()->createBlock(
                'Klevu\Content\Block\Adminhtml\Form\System\Config\Field\Select',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->_pageRenderer->setClass('customer_page_select');
            
            $this->_pageRenderer->setOptions($cmsOptions);
            $this->_pageRenderer->setExtraParams('style="width:200px;"');
        }
        return $this->_pageRenderer;
    }

    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        
        $this->addColumn('cmspages', [
            'label' => __('CMS Pages'),
            'renderer'=> $this->_getpageRenderer(),
        ]);
        
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Exclude CMS Pages');
    }

    /**
     * Prepare existing row data object
     *
     * @param \Magento\Framework\DataObject $row
     * @return void
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $optionExtraAttr = [];
        $optionExtraAttr['option_' . $this->_getpageRenderer()->calcOptionHash($row->getCmspages())] =
            'selected="selected"';
        $row->setData(
            'option_extra_attrs',
            $optionExtraAttr
        );
    }
}
