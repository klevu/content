<?php
/**
 * Class \Klevu\Search\Model\Product\MagentoProductActions
 */
namespace Klevu\Content\Model;
use \Magento\Framework\Model\AbstractModel as AbstractModel;
use \Magento\Catalog\Model\Category as Category;

class LoadAttribute extends  AbstractModel
{

    public function __construct(
        \Klevu\Search\Model\Context $context,
		Category $catalogModelCategory
    ){
        $this->_storeModelStoreManagerInterface = $context->getStoreManagerInterface();
        $this->_frameworkModelResource = $context->getResourceConnection();
        $this->_searchHelperConfig = $context->getHelperManager()->getConfigHelper();
        $this->_searchHelperCompat = $context->getHelperManager()->getCompatHelper();
        $this->_searchHelperData = $context->getHelperManager()->getDataHelper();
        $this->_klevuSync = $context->getSync();
        $this->_stockHelper = $context->getHelperManager()->getStockHelper();
		$this->_catalogModelCategory = $catalogModelCategory;

    }

    /**
     * Add the page Sync data to each page in the given list. Updates the given
     * list directly to save memory.
     *
     * @param array $pages An array of pages. Each element should be an array with
     *                        containing an element with "id" as the key and the Page
     *                        ID as the value.
     *
     * @return $this
     */
	public function addCmsData(&$pages)
    {
        $page_ids = [];
        $cms_data_new = [];
        foreach ($pages as $key => $value) {
            $page_ids[] = $value["page_id"];
        }
        if ($this->_storeModelStoreManagerInterface->getStore()->isFrontUrlSecure()) {
            $base_url = $this->_storeModelStoreManagerInterface->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK, true);
        } else {
            $base_url = $this->_storeModelStoreManagerInterface->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        }
        $data = $this->_cmsModelPage->getCollection()->addStoreFilter($this->_storeModelStoreManagerInterface->getStore()->getId())->addFieldToSelect("*")->addFieldToFilter('page_id', [
            'in' => $page_ids
        ]);
        $cms_data = $data->load()->getData();
        foreach ($cms_data as $key => $value) {
            $value["name"] = $value["title"];
            $value["desc"] = $value["content"];
            $value["id"] = "pageid_" . $value["page_id"];
            $value["url"] = $base_url . $value["identifier"];
            $desc = preg_replace("/<script\b[^>]*>(.*?)<\/script>/is", "", html_entity_decode($value["content"]));
            $value["desc"] = preg_replace('#\{{.*?\}}#s', '', strip_tags((string)$this->_contentHelperData->ripTags($desc)));
            $value["metaDesc"] = $value["meta_description"] . $value["meta_keywords"];
            $value["shortDesc"] = substr(preg_replace('#\{{.*?\}}#s', '', strip_tags((string)$this->_contentHelperData->ripTags($desc))), 0, 200);
            $value["listCategory"] = "KLEVU_CMS";
            $value["category"] = "pages";
            $value["salePrice"] = 0;
            $value["currency"] = "USD";
            $value["inStock"] = "yes";
			$value["visibility"] = "search";
            $cms_data_new[] = $value;
        }
        return $cms_data_new;
    }
}
