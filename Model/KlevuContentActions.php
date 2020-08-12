<?php
/**
 * Class \Klevu\Search\Model\Product\Sync
 * @method \Magento\Framework\Db\Adapter\Interface getConnection()
 * @method \Magento\Store\Model\Store getStore()
 * @method string getKlevuSessionId()
 */

namespace Klevu\Content\Model;

use Klevu\Search\Model\Context as Klevu_Search_Context;
use Magento\Framework\DataObject;

class KlevuContentActions extends DataObject
{

    public function __construct(
        Klevu_Search_Context $context
    )
    {
        $this->_searchHelperConfig = $context->getHelperManager()->getConfigHelper();
        $this->_searchHelperData = $context->getHelperManager()->getDataHelper();
        $this->_apiActionStartsession = $context->getStartSession();
        $this->_searchModelSession = $context->getBackendSession();
        $this->_klevuSyncModel = $context->getSync();
        $this->_frameworkModelResource = $context->getResourceConnection();
        $this->_searchHelperCompat = $context->getHelperManager()->getCompatHelper();
        $this->_storeModelStoreManagerInterface = $context->getStoreManagerInterface();

    }

    /**
     * Delete success processing , separated for easier override
     */
    public function executeDeleteContentSuccess(array $data, $response)
    {
			$connection = $this->_frameworkModelResource->getConnection("core_write");
            $select = $connection->select()->from([
                'k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")
            ])->where("k.store_id = ?", $this->getStore()->getId())->where("k.type = ?", "pages");
            $skipped_record_ids = [];
            if ($skipped_records = $response->getSkippedRecords()) {
                $skipped_record_ids = array_flip($skipped_records["index"]);
            }
            $or_where = [];
        $iMaxCount = count($data);
            for ($i = 0; $i < $iMaxCount; $i++) {
                if (isset($skipped_record_ids[$i])) {
                    continue;
                }
                $or_where[] = sprintf("(%s)", $connection->quoteInto("k.product_id = ?", $data[$i]['page_id']));
            }
            $select->where(implode(" OR ", $or_where));
            $connection->query($select->deleteFromSelect("k"));
            $skipped_count = count($skipped_record_ids);
            if ($skipped_count > 0) {
                return sprintf("%d cms%s failed (%s)", $skipped_count, ($skipped_count > 1) ? "s" : "", implode(", ", $skipped_records["messages"]));
            } else {
                return true;
            }
    }

    /**
     * Update success processing , separated for easier override
     */
    public function executeUpdateContentSuccess(array $data, $response)
    {
			$helper = $this->_searchHelperData;
            $connection = $this->_frameworkModelResource->getConnection("core_write");
            $skipped_record_ids = [];
            if ($skipped_records = $response->getSkippedRecords()) {
                $skipped_record_ids = array_flip($skipped_records["index"]);
            }
            $where = [];
        $iMaxCount = count($data);
            for ($i = 0; $i < $iMaxCount; $i++) {
                if (isset($skipped_record_ids[$i])) {
                    continue;
                }
                $ids[$i] = explode("_", $data[$i]['id']);
                $where[] = sprintf("(%s AND %s AND %s)", $connection->quoteInto("product_id = ?", $ids[$i][1]), $connection->quoteInto("parent_id = ?", 0), $connection->quoteInto("type = ?", "pages"));
            }
            $where = sprintf("(%s) AND (%s)", $connection->quoteInto("store_id = ?", $this->getStore()->getId()), implode(" OR ", $where));
            $connection->update($this->_frameworkModelResource->getTableName('klevu_product_sync'), [
                'last_synced_at' => $this->_searchHelperCompat->now()
            ], $where);
            $skipped_count = count($skipped_record_ids);
            if ($skipped_count > 0) {
                return sprintf("%d cms%s failed (%s)", $skipped_count, ($skipped_count > 1) ? "s" : "", implode(", ", $skipped_records["messages"]));
            } else {
                return true;
            }
    }

    /**
     * Add success processing , separated for easier override
     */
    public function executeAddContentSuccess(array $data, $response)
    {
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
                    $this->getStore()->getId() ,
                    $sync_time,
                    "pages"
                ];
            }
           
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $write =  $this->_frameworkModelResource->getConnection("core_write");
                    $query = "replace into ".$this->_frameworkModelResource->getTableName('klevu_product_sync')
                           . "(product_id, parent_id, store_id, last_synced_at, type) values "
                           . "(:product_id, :parent_id, :store_id, :last_synced_at, :type)";
                    $binds = [
                        'product_id' => $value[0],
                        'parent_id' => $value[1],
                        'store_id' => $value[2],
                        'last_synced_at'  => $value[3],
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
    }
}
