<?php

namespace Klevu\Content\Model;

use Klevu\Logger\Constants as LoggerConstants;
use Klevu\Search\Model\Klevu\KlevuFactory as Klevu_Factory;
use Klevu\Search\Model\Product\KlevuProductActionsInterface as Klevu_Product_Actions;
use Klevu\Search\Model\Sync as KlevuSync;

class Content extends \Klevu\Search\Model\Product\Sync implements ContentInterface
{
    /**
     * @var \Magento\Framework\Model\Resource
     */
    protected $_frameworkModelResource;
    /**
     * @var \Klevu\Search\Model\Session
     */
    protected $_searchModelSession;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeModelStoreManagerInterface;
    /**
     * @var \Klevu\Content\Helper\Data
     */
    protected $_contentHelperData;
    /**
     * @var \Klevu\Search\Model\Api\Action\Deleterecords
     */
    protected $_apiActionDeleterecords;
    /**
     * @var \Klevu\Search\Model\Api\Action\Addrecords
     */
    protected $_apiActionAddrecords;
    /**
     * @var \Klevu\Search\Helper\Compat
     */
    protected $_searchHelperCompat;
    /**
     * @var \Magento\Cms\Model\Page
     */
    protected $_cmsModelPage;
    /**
     * @var \Klevu\Search\Model\Api\Action\Updaterecords
     */
    protected $_apiActionUpdaterecords;
    /**
     * @var \Klevu\Search\Helper\Data
     */
    protected $_searchHelperData;

    /**
     * @var \Klevu\Search\Helper\Config
     */
    protected $_searchHelperConfig;


    /**
     * @var \Klevu\Search\Model\Api\Action\Startsession
     */
    protected $_apiActionStartsession;

    /**
     * @var \Magento\Cron\Model\Schedule
     */
    protected $_cronModelSchedule;
    protected $_klevuSyncModel;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $frameworkModelResource,
        \Magento\Backend\Model\Session $searchModelSession,
        \Magento\Store\Model\StoreManagerInterface $storeModelStoreManagerInterface,
        \Klevu\Content\Helper\Data $contentHelperData,
        \Klevu\Search\Model\Api\Action\Deleterecords $apiActionDeleterecords,
        \Klevu\Search\Model\Api\Action\Addrecords $apiActionAddrecords,
        \Klevu\Search\Helper\Compat $searchHelperCompat,
        \Magento\Cms\Model\Page $cmsModelPage,
        \Klevu\Search\Model\Api\Action\Updaterecords $apiActionUpdaterecords,
        \Klevu\Search\Helper\Data $searchHelperData,
        \Klevu\Search\Helper\Config $searchHelperConfig,
        \Klevu\Search\Model\Api\Action\Startsession $apiActionStartsession,
        \Magento\Cron\Model\Schedule $cronModelSchedule,
        \Magento\Framework\App\ProductMetadataInterface $productMetadataInterface,
        Klevu_Factory $klevuFactory,
        KlevuSync $klevuSyncModel,
        Klevu_Product_Actions $klevuProductActions


    )
    {
        $this->_klevuSyncModel = $klevuSyncModel;
        $this->_klevuSyncModel->setJobCode($this->getJobCode());
        $this->_klevuFactory = $klevuFactory;
        $this->_frameworkModelResource = $frameworkModelResource;
        $this->_searchModelSession = $searchModelSession;
        $this->_storeModelStoreManagerInterface = $storeModelStoreManagerInterface;
        $this->_contentHelperData = $contentHelperData;
        $this->_apiActionDeleterecords = $apiActionDeleterecords;
        $this->_apiActionAddrecords = $apiActionAddrecords;
        $this->_searchHelperCompat = $searchHelperCompat;
        $this->_cmsModelPage = $cmsModelPage;
        $this->_apiActionUpdaterecords = $apiActionUpdaterecords;
        $this->_searchHelperData = $searchHelperData;
        $this->_searchHelperConfig = $searchHelperConfig;
        $this->_apiActionStartsession = $apiActionStartsession;
        $this->_cronModelSchedule = $cronModelSchedule;
        $this->_ProductMetadataInterface = $productMetadataInterface;
        $this->_klevuProductActions = $klevuProductActions;
        if (in_array($this->_ProductMetadataInterface->getEdition(), array("Enterprise", "B2B")) && version_compare($this->_ProductMetadataInterface->getVersion(), '2.1.0', '>=') === true) {
            $this->_page_value = "row_id";
        } else {
            $this->_page_value = "page_id";
        }
    }

    public function _construct()
    {
        parent::_construct();
        $this->addData([
            "connection" => $this->_frameworkModelResource->getConnection("core_write")
        ]);
    }

    public function getJobCode()
    {
        return "klevu_search_content_sync";
    }

    /**
     * Perform Content Sync on any configured stores, adding new content, updating modified and
     * deleting removed content since last sync.
     */
    public function run()
    {

        // Sync Data only for selected store from config wizard
        $session = $this->_searchModelSession;
        $firstSync = $session->getFirstSync();
        if (!empty($firstSync)) {
            $onestore = $this->_storeModelStoreManagerInterface->getStore($firstSync);
            $this->reset();
            if (!$this->_contentHelperData->isCmsSyncEnabled($onestore->getId())) {
                return;
            }
            if (!$this->_klevuProductActions->setupSession($onestore)) {
                return;
            }
            $this->syncCmsData($onestore);
            return;
        }

        if ($this->isRunning(2)) {
            // Stop if another copy is already running
            $this->log(LoggerConstants::ZEND_LOG_INFO, "Stopping because another copy is already running.");
            return;
        }

        // Sync all store cms Data
        $stores = $this->_storeModelStoreManagerInterface->getStores();
        foreach ($stores as $store) {
            /** @var \Magento\Framework\Model\Store $store */
            $this->reset();
            if (!$this->_contentHelperData->isCmsSyncEnabled($store->getId())) {
                continue;
            }
            if (!$this->_klevuProductActions->setupSession($store)) {
                continue;
            }
            $this->syncCmsData($store);
        }
    }
    
    /**
     * @param $store
     * @return mixed|void
     */
    public function syncCmsData($store)
    {
        $this->reset();
        if (!$this->_contentHelperData->isCmsSyncEnabled($store->getId())) {
            $msg = sprintf("CMS Sync found disabled for %s (%s).", $store->getWebsite()->getName(), $store->getName());
            $this->log(LoggerConstants::ZEND_LOG_INFO, $msg);
            return $msg;
        }
        if (!$this->_klevuProductActions->setupSession($store)) {
            return;
        }
        if ($this->rescheduleIfOutOfMemory()) {
            return;
        }
        $page_ids = [];

        $this->_storeModelStoreManagerInterface->setCurrentStore($store->getId());
        $this->log(LoggerConstants::ZEND_LOG_INFO, sprintf("Starting Cms sync for %s (%s).", $store->getWebsite()->getName(), $store->getName()));

        $actions = array('delete', 'update', 'add');
        $errors = 0;
        foreach ($actions as $key => $action) {
            if ($this->rescheduleIfOutOfMemory()) {
                return;
            }
            if ($action == 'add') {
                $page_ids = $this->addPagesCollection($store);
            }

            if ($action == 'update') {
                $page_ids = $this->updatePagesCollection($store);
            }

            if ($action == 'delete') {
                $page_ids = $this->deletePagesCollection($store);
            }
            $method = $action . "cms";
            $cms_pages = $page_ids;
            $total = count($cms_pages);
            $this->log(LoggerConstants::ZEND_LOG_INFO, sprintf("Found %d Cms Pages to %s.", $total, $action));
            $pages = ceil($total / static ::RECORDS_PER_PAGE);
            for ($page = 1; $page <= $pages; $page++) {
                if ($this->rescheduleIfOutOfMemory()) {
                    return;
                }
                $offset = ($page - 1) * static ::RECORDS_PER_PAGE;
                $result = $this->$method(array_slice($cms_pages, $offset, static ::RECORDS_PER_PAGE));
                if ($result !== true) {
                    $errors++;
                    $this->log(LoggerConstants::ZEND_LOG_ERR, sprintf("Errors occurred while attempting to %s cms pages %d - %d: %s", $action, $offset + 1, ($offset + static ::RECORDS_PER_PAGE <= $total) ? $offset + static ::RECORDS_PER_PAGE : $total, $result));
                }
            }
        }
        $this->log(LoggerConstants::ZEND_LOG_INFO, sprintf("Finished cms page sync for %s (%s).", $store->getWebsite()->getName(), $store->getName()));
    }


    public function deletePagesCollection($store)
    {
        $k_pages = $this->getKlevuProductCollection($store);
        $send_exluded_to_delete = array();
        $k_page_ids = array();
        $m_page_ids = array();
        $to_detete = array();
        $cms_ids = $this->_cmsModelPage->getCollection()->addFieldToSelect('page_id')->addStoreFilter($store->getId())->addFieldToFilter('is_active', 1)->getData();
        foreach ($cms_ids as $key => $value) {
            $m_page_ids[] = $value['page_id'];
        }
        foreach ($k_pages as $key => $value) {
            $k_page_ids[] = $value['product_id'];
        }
        $pageids = $this->excludedPageIds($store);
        // send exluded pages to delete if it is already added
        $send_exludedpages_to_delete = array_intersect($k_page_ids, $pageids);
        // ids exits in Klevu but not exits/or disabled in magento
        $pages_to_delete = array_diff($k_page_ids, $m_page_ids);
        $to_detete = array_merge($pages_to_delete, $send_exludedpages_to_delete);
        return $to_detete;

    }

    protected function excludedPageIds($store)
    {
        $pageids = array();
        $cPgaes = $this->_contentHelperData->getExcludedPages($store);
        if (!empty($cPgaes)) {
            foreach ($cPgaes as $key => $cvalue) {
                $pageids[] = (int)$cvalue['cmspages'];
            }
        }
        return $pageids;
    }

    public function addPagesCollection($store)
    {
        $pageids = $this->excludedPageIds($store);
        $pages_to_add = array();
        $k_page_ids = array();
        $m_page_ids = array();
        $k_pages = $this->getKlevuProductCollection($store);
        $cms_ids = $this->_cmsModelPage->getCollection()->addFieldToSelect('page_id')->addStoreFilter($store->getId())->addFieldToFilter('is_active', 1)->getData();
        foreach ($cms_ids as $key => $value) {
            $m_page_ids[] = $value['page_id'];
        }

        $m_page_ids = array_diff($m_page_ids, $pageids);

        foreach ($k_pages as $key => $value) {
            $k_page_ids[] = $value['product_id'];
        }
        $pages_to_add = array_diff($m_page_ids, $k_page_ids);
        return $pages_to_add;
    }

    public function updatePagesCollection($store)
    {
        $klevuToUpdate = array();
        $klevu = $this->_klevuFactory->create();
        $klevuCollection = $klevu->getCollection()
            ->addFieldToFilter($klevu->getKlevuField('type'), $klevu->getKlevuType('page'))
            ->addFieldToFilter($klevu->getKlevuField('store_id'), $store->getid())
            ->join(
                ['cms_page' => $this->_frameworkModelResource->getTableName('cms_page')],
                "main_table." . $klevu->getKlevuField('product_id') . " = cms_page.page_id AND cms_page.update_time > main_table.last_synced_at",
                ""
            );
        $klevuCollection->load();

        if ($klevuCollection->count() > 0) {
            foreach ($klevuCollection as $klevuItem) {
                $klevuToUpdate[$klevuItem->getData($klevu->getKlevuField('product_id'))]["product_id"] = $klevuItem->getData($klevu->getKlevuField('product_id'));
                $klevuToUpdate[$klevuItem->getData($klevu->getKlevuField('product_id'))]["parent_id"] = $klevuItem->getData($klevu->getKlevuField('parent_id'));

            }
        }

        return $klevuToUpdate;
    }


    protected function getKlevuProductCollection($store)
    {
        $limit = $this->_klevuSyncModel->getSessionVariable("limit");
        $klevu = $this->_klevuFactory->create();
        $klevuCollection = $klevu->getCollection()
            ->addFieldToSelect($klevu->getKlevuField('product_id'))
            ->addFieldToSelect($klevu->getKlevuField('store_id'))
            ->addFieldToFilter($klevu->getKlevuField('type'), $klevu->getKlevuType('page'))
            ->addFieldToFilter($klevu->getKlevuField('store_id'), $store->getId());
        return $klevuCollection->getData();
    }

    /**
     * Delete the given pages from Klevu Search. Returns true if the operation was
     * successful, or the error message if the operation failed.
     *
     * @param array $data List of pages to delete. Each element should be an array
     *                    containing an element with "page_id" as the key and page id as
     *                    the value.
     *
     * @return bool|string
     */
    protected function deletecms(array $data)
    {
        $format_data = [];
        foreach ($data as $key => $value) {
            $format_data[]['page_id'] = $value;
        }
        $total = count($format_data);
        $response = $this->_apiActionDeleterecords->setStore($this->_storeModelStoreManagerInterface->getStore())->execute([
            'sessionId' => $this->_searchModelSession->getKlevuSessionId(),
            'records' => array_map(function ($v) {
                return [
                    'id' => "pageid_" . $v['page_id']
                ];
            }, $format_data)
        ]);
        if ($response->isSuccess()) {
            $connection = $this->_frameworkModelResource->getConnection("core_write");
            $select = $connection->select()->from([
                'k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")
            ])->where("k.store_id = ?", $this->_storeModelStoreManagerInterface->getStore()->getId())->where("k.type = ?", "pages");
            $skipped_record_ids = [];
            if ($skipped_records = $response->getSkippedRecords()) {
                $skipped_record_ids = array_flip($skipped_records["index"]);
            }
            $or_where = [];
            for ($i = 0; $i < count($format_data); $i++) {
                if (isset($skipped_record_ids[$i])) {
                    continue;
                }
                $or_where[] = sprintf("(%s)", $connection->quoteInto("k.product_id = ?", $format_data[$i]['page_id']));
            }
            $select->where(implode(" OR ", $or_where));
            $connection->query($select->deleteFromSelect("k"));
            $skipped_count = count($skipped_record_ids);
            if ($skipped_count > 0) {
                return sprintf("%d cms%s failed (%s)", $skipped_count, ($skipped_count > 1) ? "s" : "", implode(", ", $skipped_records["messages"]));
            } else {
                return true;
            }
        } else {
            $this->_searchModelSession->setKlevuFailedFlag(1);
            return sprintf("%d cms%s failed (%s)", $total, ($total > 1) ? "s" : "", $response->getMessage());
        }
    }

    /**
     * Add the given pages to Klevu Search. Returns true if the operation was successful,
     * or the error message if it failed.
     *
     * @param array $data List of pages to add. Each element should be an array
     *                    containing an element with "page_id" as the key and page id as
     *                    the value.
     *
     * @return bool|string
     */
    protected function addCms(array $data)
    {

        $total = count($data);
        $data = $this->addCmsData($data);
        $response = $this->_apiActionAddrecords->setStore($this->_storeModelStoreManagerInterface->getStore())->execute([
            'sessionId' => $this->_searchModelSession->getKlevuSessionId(),
            'records' => $data
        ]);
        if ($response->isSuccess()) {
            $skipped_record_ids = [];
            if ($skipped_records = $response->getSkippedRecords()) {
                $skipped_record_ids = array_flip($skipped_records["index"]);
            }
            $sync_time = $this->_searchHelperCompat->now();
            foreach ($data as $i => & $record) {
                if (isset($skipped_record_ids[$i])) {
                    unset($data[$i]);
                    continue;
                }
                $ids[$i] = explode("_", $data[$i]['id']);
                $record = [
                    $ids[$i][1],
                    0,
                    $this->_storeModelStoreManagerInterface->getStore()->getId(),
                    $sync_time,
                    "pages"
                ];
            }

            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $write = $this->_frameworkModelResource->getConnection("core_write");
                    $query = "replace into " . $this->_frameworkModelResource->getTableName('klevu_product_sync')
                        . "(product_id, parent_id, store_id, last_synced_at, type) values "
                        . "(:product_id, :parent_id, :store_id, :last_synced_at, :type)";
                    $binds = [
                        'product_id' => $value[0],
                        'parent_id' => $value[1],
                        'store_id' => $value[2],
                        'last_synced_at' => $value[3],
                        'type' => 'pages'
                    ];
                    $write->query($query, $binds);
                }
            }

            $skipped_count = count($skipped_record_ids);
            if ($skipped_count > 0) {
                return sprintf("%d cms%s failed (%s)", $skipped_count, ($skipped_count > 1) ? "s" : "", implode(", ", $skipped_records["messages"]));
            } else {
                return true;
            }
        } else {
            $this->_searchModelSession->setKlevuFailedFlag(1);
            return sprintf("%d cms%s failed (%s)", $total, ($total > 1) ? "s" : "", $response->getMessage());
        }
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
        $page_ids = $pages;
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
            $value["desc"] = preg_replace('#\{{.*?\}}#s', '', strip_tags($this->_contentHelperData->ripTags($desc)));
            $value["metaDesc"] = $value["meta_description"] . $value["meta_keywords"];
            $value["shortDesc"] = substr(preg_replace('#\{{.*?\}}#s', '', strip_tags($this->_contentHelperData->ripTags($desc))), 0, 200);
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

    /**
     * Update the given pages on Klevu Search. Returns true if the operation was successful,
     * or the error message if it failed.
     *
     * @param array $data List of Pages to update. Each element should be an array
     *                    containing an element with "page_id" as the key and page id as
     *                    the value
     *
     * @return bool|string
     */
    protected function updateCms(array $data)
    {
        $total = count($data);
        $data = $this->addCmsData($data);
        $response = $this->_apiActionUpdaterecords->setStore($this->_storeModelStoreManagerInterface->getStore())->execute([
            'sessionId' => $this->_searchModelSession->getKlevuSessionId(),
            'records' => $data
        ]);
        if ($response->isSuccess()) {
            $helper = $this->_searchHelperData;
            $connection = $this->_frameworkModelResource->getConnection("core_write");
            $skipped_record_ids = [];
            if ($skipped_records = $response->getSkippedRecords()) {
                $skipped_record_ids = array_flip($skipped_records["index"]);
            }
            $where = [];
            for ($i = 0; $i < count($data); $i++) {
                if (isset($skipped_record_ids[$i])) {
                    continue;
                }
                $ids[$i] = explode("_", $data[$i]['id']);
                $where[] = sprintf("(%s AND %s AND %s)", $connection->quoteInto("product_id = ?", $ids[$i][1]), $connection->quoteInto("parent_id = ?", 0), $connection->quoteInto("type = ?", "pages"));
            }
            $where = sprintf("(%s) AND (%s)", $connection->quoteInto("store_id = ?", $this->_storeModelStoreManagerInterface->getStore()->getId()), implode(" OR ", $where));
            $connection->update($this->_frameworkModelResource->getTableName('klevu_product_sync'), [
                'last_synced_at' => $this->_searchHelperCompat->now()
            ], $where);
            $skipped_count = count($skipped_record_ids);
            if ($skipped_count > 0) {
                return sprintf("%d cms%s failed (%s)", $skipped_count, ($skipped_count > 1) ? "s" : "", implode(", ", $skipped_records["messages"]));
            } else {
                return true;
            }
        } else {
            $this->_searchModelSession->setKlevuFailedFlag(1);
            return sprintf("%d cms%s failed (%s)", $total, ($total > 1) ? "s" : "", $response->getMessage());
        }
    }
}
