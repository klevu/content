<?php

namespace Klevu\Content\Block\Adminhtml\Form;

use Klevu\Content\Block\Adminhtml\Form\System\Config\Field\Select as FormFieldSelect;
use Magento\Backend\Block\Template\Context as Template_Context;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Store\Model\StoreManager;

class Cmspages extends AbstractFieldArray
{
    /**
     * @var BlockInterface
     */
    protected $_pageRenderer;
    /**
     * @var Template_Context
     */
    private $_context;
    /**
     * @var storeManager
     */
    private $storeManager;

    /**
     * @param Template_Context $context
     * @param array $data
     */
    public function __construct(
        Template_Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_context = $context;
    }

    /**
     * Retrieve HTML markup for given form element
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $this->storeManager = $this->_context->getStoreManager();
        $store_mode = $this->storeManager->isSingleStoreMode();
        if (!$store_mode && $element->getScope() !== "stores"
            && $element->getHtmlId() === 'klevu_search_cmscontent_excludecms_pages'
        ) {
            return '';
        }

        return parent::render($element);
    }

    /**
     * Check if inheritance checkbox has to be rendered
     *
     * @param AbstractElement $element
     *
     * @return bool
     */
    protected function _isInheritCheckboxRequired(AbstractElement $element)
    {
        $this->storeManager = $this->_context->getStoreManager();
        $store_mode = $this->storeManager->isSingleStoreMode();

        if (!$store_mode && $element->getScope() === "stores"
            && $element->getHtmlId() === 'klevu_search_cmscontent_excludecms_pages'
        ) {
            return;
        }

        return $element->getCanUseWebsiteValue()
            || $element->getCanUseDefaultValue()
            || $element->getCanRestoreToDefault();
    }

    /**
     * Retrieve page column renderer
     *
     * @return BlockInterface
     * @throws LocalizedException
     */
    protected function _getpageRenderer()
    {
        $this->_cmsModelPage = ObjectManager::getInstance()->get(CmsPage::class);
        $cms_pages = $this->_cmsModelPage->getCollection()->addFieldToSelect([
            "page_id",
            "title"
        ])->addFieldToFilter('is_active', 1);
        $page_ids = $cms_pages->getData();
        $cmsOptions = [];
        foreach ($page_ids as $id) {
            $title = isset($id['title']) ? $id['title'] : '';
            $cmsOptions[$id['page_id']] = $this->escapeHtml($title);
        }
        if (!$this->_pageRenderer) {
            $this->_pageRenderer = $this->getLayout()->createBlock(
                FormFieldSelect::class,
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
     * @throws LocalizedException
     */
    protected function _prepareToRender()
    {
        $this->addColumn('cmspages', [
            'label' => __('CMS Pages'),
            'renderer' => $this->_getpageRenderer(),
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Exclude CMS Pages');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $optionExtraAttr = [];
        $hash = $this->_getpageRenderer()->calcOptionHash($row->getCmspages());
        $optionExtraAttr['option_' . $hash] = 'selected="selected"';
        $row->setData(
            'option_extra_attrs',
            $optionExtraAttr
        );
    }
}
