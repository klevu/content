<?php

namespace Klevu\Content\Model;

use Klevu\Content\Helper\Data as ContentHelper;
use Klevu\Logger\Constants as LoggerConstants;
use Klevu\Search\Helper\Compat as CompatHelper;
use Klevu\Search\Helper\Config as ConfigHelper;
use Klevu\Search\Helper\Data as SearchHelper;
use Klevu\Search\Helper\Stock as StockHelper;
use Klevu\Search\Model\Context;
use Klevu\Search\Model\Sync;
use Magento\Catalog\Model\Category;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class LoadAttribute extends AbstractModel
{
    /**
     * @var StoreManagerInterface
     */
    protected $_storeModelStoreManagerInterface;
    /**
     * @var ResourceConnection
     */
    protected $_frameworkModelResource;
    /**
     * @var ConfigHelper
     */
    protected $_searchHelperConfig;
    /**
     * @var CompatHelper
     */
    protected $_searchHelperCompat;
    /**
     * @var SearchHelper
     */
    protected $_searchHelperData;
    /**
     * @var Sync
     */
    protected $_klevuSync;
    /**
     * @var StockHelper
     */
    protected $_stockHelper;
    /**
     * @var Category
     */
    protected $_catalogModelCategory;
    /**
     * @var ContentHelper
     */
    private $_contentHelperData;
    /**
     * @var CmsPage
     */
    private $_cmsModelPage;

    /**
     * @param Context $context
     * @param Category $catalogModelCategory
     * @param ContentHelper|null $contentHelper
     * @param CmsPage|null $cmsPage
     */
    public function __construct(
        Context $context,
        Category $catalogModelCategory,
        ContentHelper $contentHelper = null,
        CmsPage $cmsPage = null
    ) {
        $helperManager = $context->getHelperManager();
        $this->_storeModelStoreManagerInterface = $context->getStoreManagerInterface();
        $this->_frameworkModelResource = $context->getResourceConnection();
        $this->_searchHelperConfig = $helperManager->getConfigHelper();
        $this->_searchHelperCompat = $helperManager->getCompatHelper();
        $this->_searchHelperData = $helperManager->getDataHelper();
        $this->_klevuSync = $context->getSync();
        $this->_stockHelper = $helperManager->getStockHelper();
        $this->_catalogModelCategory = $catalogModelCategory;
        $this->_contentHelperData = $contentHelper ?:
            ObjectManager::getInstance()->create(ContentHelper::class);
        $this->_cmsModelPage = $cmsPage ?:
            ObjectManager::getInstance()->create(CmsPage::class);
    }

    /**
     * Add the page Sync data to each page in the given list. Updates the given
     * list directly to save memory.
     *
     * @param array $pages An array of pages. Each element should be an array with
     *                        containing an element with "id" as the key and the Page
     *                        ID as the value.
     *
     * @return array
     */
    public function addCmsData(&$pages)
    {
        $page_ids = [];
        $cmsDataNew = [];
        foreach ($pages as $value) {
            $page_ids[] = $value["page_id"];
        }
        try {
            $store = $this->_storeModelStoreManagerInterface->getStore();
        } catch (NoSuchEntityException $e) {
            $this->_searchHelperData->log(
                LoggerConstants::ZEND_LOG_ERR,
                sprintf('%s: %s', __METHOD__, $e->getMessage())
            );

            return $cmsDataNew;
        }
        $base_url = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $store->isFrontUrlSecure());

        $cmsData = [];
        $pageCollection = $this->_cmsModelPage->getCollection();
        if ($pageCollection) {
            $pageCollection->addStoreFilter($store->getId());
            $pageCollection->addFieldToSelect("*");
            $pageCollection->addFieldToFilter('page_id', [
                'in' => $page_ids,
            ]);
            $cmsData = $pageCollection->load()->getData();
        }
        foreach ($cmsData as $value) {
            $value["name"] = $value["title"];
            $value["id"] = "pageid_" . $value["page_id"];
            $value["url"] = $base_url . $value["identifier"];
            $desc = preg_replace(
                "/<script\b[^>]*>(.*?)<\/script>/is",
                "",
                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                html_entity_decode($value["content"])
            );
            $value["desc"] = preg_replace(
                '#\{{.*?\}}#s',
                '',
                strip_tags((string)$this->_contentHelperData->ripTags($desc))
            );
            $value["metaDesc"] = $value["meta_description"] . $value["meta_keywords"];
            $value["shortDesc"] = substr(
                preg_replace(
                    '#\{{.*?\}}#s',
                    '',
                    strip_tags((string)$this->_contentHelperData->ripTags($desc))
                ),
                0,
                200
            );
            $value["listCategory"] = "KLEVU_CMS";
            $value["category"] = "pages";
            $value["salePrice"] = 0;
            $value["currency"] = "USD";
            $value["inStock"] = "yes";
            $value["visibility"] = "search";
            $cmsDataNew[] = $value;
        }

        return $cmsDataNew;
    }
}
