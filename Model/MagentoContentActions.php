<?php
/**
 * Class \Klevu\Content\Model\MagentoContentActions
 */

namespace Klevu\Content\Model;

use Klevu\Content\Model\KlevuContentActions as Klevu_Content_Actions;
use \Magento\Framework\Model\AbstractModel as AbstractModel;
use \Magento\Eav\Model\Config as Eav_Config;
use Klevu\Content\Model\LoadAttribute as Klevu_LoadContentAttribute;
use Klevu\Search\Model\Context as Klevu_Search_Context;
use Klevu\Content\Helper\Data as Klevu_Content_Helper;


class MagentoContentActions extends AbstractModel
{

    public function __construct(
        Klevu_Search_Context $context,
        Eav_Config $eavConfig,
        Klevu_Content_Actions $klevuContentAction,
		Klevu_LoadContentAttribute $loadAttribute,
        Klevu_Content_Helper $contentHelperData
    )
    {

        $this->_ProductMetadataInterface = $context->getKlevuProductMeta();
        $this->_storeModelStoreManagerInterface = $context->getStoreManagerInterface();
        $this->_searchModelSession = $context->getBackendSession();
        $this->_apiActionDeleterecords = $context->getKlevuProductDelete();
        $this->_apiActionUpdaterecords = $context->getKlevuProductUpdate();
        $this->_apiActionAddrecords = $context->getKlevuProductAdd();
        $this->_frameworkModelResource = $context->getResourceConnection();
        $this->_loadAttribute = $loadAttribute;
        $this->_searchHelperConfig = $context->getHelperManager()->getConfigHelper();
        $this->_searchHelperCompat = $context->getHelperManager()->getCompatHelper();
		$this->_klevuContentActions = $klevuContentAction;
        $this->_contentHelperData = $contentHelperData;

        if (in_array($this->_ProductMetadataInterface->getEdition(),array("Enterprise","B2B")) && version_compare($this->_ProductMetadataInterface->getVersion(), '2.1.0', '>=')===true) {
            $this->_page_value = "row_id";
        } else {
            $this->_page_value = "page_id";
        }
    }


    public function getContentSyncDataActions($store)
    {

        $cPgaes = $this->_contentHelperData->getExcludedPages($store);
        if (!empty($cPgaes)) {
            foreach ($cPgaes as $key => $cvalue) {
                $pageids[]  = (int)$cvalue['cmspages'];
            }
        } else {
            $pageids = "";
        }

        if (!empty($pageids)) {
            $eids = implode("','", $pageids);
        } else {
            $eids = $pageids;
        }

        if (in_array($this->_ProductMetadataInterface->getEdition(),array("Enterprise","B2B")) && version_compare($this->_ProductMetadataInterface->getVersion(), '2.1.0', '>=')===true) {
		$actions = [
            'delete' => $this->_frameworkModelResource->getConnection("core_write")
                ->select()
                /*
                 * Select synced cms in the current store/mode that
                 * are no longer enabled
                 */
                ->from(
                    ['k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")],
                    ['page_id' => "k.product_id"]
                )
                ->joinLeft(
                    ['c' => $this->_frameworkModelResource->getTableName("cms_page")],
                    "k.product_id = c.page_id",
                    ""
                )
                ->joinLeft(
                    ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                    "v.".$this->_page_value." = c.".$this->_page_value,
                    ""
                )
                ->where(
                    "((k.store_id = :store_id AND v.store_id != 0) AND (k.type = :type) AND (k.product_id NOT IN ?)) OR ( (k.product_id IN ('".$eids."') OR (c.".$this->_page_value." IS NULL) OR (c.is_active = 0)) AND (k.type = :type) AND k.store_id = :store_id)",
                    $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        ->from(
                            ['i' => $this->_frameworkModelResource->getTableName("cms_page")],
                            ['page_id' => "i.page_id"]
                        )->join(
                            ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                            "i.".$this->_page_value." = v.".$this->_page_value." AND v.store_id = :store_id",
                            ""
                        )
                        ->where('i.page_id NOT IN (?)', $pageids)
                    // ->where("i.store_id = :store_id")
                )
                ->group(['k.product_id'])
                ->bind([
                    'store_id'=> $store->getId(),
                    'type' => "pages",
                ]),
            'update' =>
                    $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        /*
                         * Select pages for the current store/mode
                         * have been updated since last sync.
                         */
                         ->from(
                             ['k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")],
                             ['page_id' => "k.product_id"]
                         )
                        ->join(
                            ['c' => $this->_frameworkModelResource->getTableName("cms_page")],
                            "c.page_id = k.product_id",
                            ""
                        )
                        ->joinLeft(
                            ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                            "v.".$this->_page_value." = c.".$this->_page_value." AND v.store_id = :store_id",
                            ""
                        )
                        ->where("(c.is_active = 1) AND (k.type = :type) AND (k.store_id = :store_id) AND (c.update_time > k.last_synced_at)")
                        ->where('c.'.$this->_page_value.' NOT IN (?)', $pageids)
                ->bind([
                    'store_id' => $store->getId(),
                    'type'=> "pages",
                    ]),
                    'add' =>  $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        ->union([
                        $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        /*
                         * Select pages for the current store/mode
                         * have been updated since last sync.
                         */
                        ->from(
                            ['p' => $this->_frameworkModelResource->getTableName("cms_page")],
                            ['page_id' => "p.page_id"]
                        )
                        ->where('p.page_id NOT IN (?)', $pageids)
                        ->joinLeft(
                            ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                            "p.".$this->_page_value." = v.".$this->_page_value,
                            ""
                        )
                        ->joinLeft(
                            ['k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")],
                            "p.page_id = k.product_id AND k.store_id = :store_id AND k.type = :type",
                            ""
                        )
                        ->where("p.is_active = 1 AND k.product_id IS NULL AND v.store_id =0"),
                        $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        /*
                         * Select pages for the current store/mode
                         * have been updated since last sync.
                         */
                        ->from(
                            ['p' => $this->_frameworkModelResource->getTableName("cms_page")],
                            ['page_id' => "p.".$this->_page_value]
                        )
                        ->where('p.'.$this->_page_value.' NOT IN (?)', $pageids)
                        ->join(
                            ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                            "p.".$this->_page_value." = v.".$this->_page_value." AND v.store_id = :store_id",
                            ""
                        )
                        ->joinLeft(
                            ['k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")],
                            "v.".$this->_page_value." = k.product_id AND k.store_id = :store_id AND k.type = :type",
                            ""
                        )
                        ->where("p.is_active = 1 AND k.product_id IS NULL")
                        ])
                    ->bind([
                    'type' => "pages",
                    'store_id' => $store->getId(),
                    ]),
            ];
        } else {
            $actions = [
            'delete' => $this->_frameworkModelResource->getConnection("core_write")
                ->select()
                /*
                 * Select synced cms in the current store/mode that
                 * are no longer enabled
                 */
                ->from(
                    ['k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")],
                    ['page_id' => "k.product_id"]
                )
                ->joinLeft(
                    ['c' => $this->_frameworkModelResource->getTableName("cms_page")],
                    "k.product_id = c.".$this->_page_value,
                    ""
                )
                ->joinLeft(
                    ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                    "v.".$this->_page_value." = c.".$this->_page_value,
                    ""
                )
                ->where(
                    "((k.store_id = :store_id AND v.store_id != 0) AND (k.type = :type) AND (k.product_id NOT IN ?)) OR ( (k.product_id IN ('".$eids."') OR (c.".$this->_page_value." IS NULL) OR (c.is_active = 0)) AND (k.type = :type) AND k.store_id = :store_id)",
                    $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        ->from(
                            ['i' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                            ['page_id' => "i.".$this->_page_value]
                        )
                        ->where('i.'.$this->_page_value.' NOT IN (?)', $pageids)
                        ->where("i.store_id = :store_id OR i.store_id = 0")
                )
                ->group(['k.product_id'])
                ->bind([
                    'store_id'=> $store->getId(),
                    'type' => "pages",
                ]),
            'update' =>
                    $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        /*
                         * Select pages for the current store/mode
                         * have been updated since last sync.
                         */
                         ->from(
                             ['k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")],
                             ['page_id' => "k.product_id"]
                         )
                        ->join(
                            ['c' => $this->_frameworkModelResource->getTableName("cms_page")],
                            "c.".$this->_page_value." = k.product_id",
                            ""
                        )
                        ->joinLeft(
                            ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                            "v.".$this->_page_value." = c.".$this->_page_value." AND v.store_id = :store_id",
                            ""
                        )
                        ->where("(c.is_active = 1) AND (k.type = :type) AND (k.store_id = :store_id) AND (c.update_time > k.last_synced_at)")
                        ->where('c.'.$this->_page_value.' NOT IN (?)', $pageids)
                ->bind([
                    'store_id' => $store->getId(),
                    'type'=> "pages",
                    ]),
                    'add' =>  $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        ->union([
                        $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        /*
                         * Select pages for the current store/mode
                         * have been updated since last sync.
                         */
                        ->from(
                            ['p' => $this->_frameworkModelResource->getTableName("cms_page")],
                            ['page_id' => "p.".$this->_page_value]
                        )
                        ->where('p.'.$this->_page_value.' NOT IN (?)', $pageids)
                        ->joinLeft(
                            ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                            "p.".$this->_page_value." = v.".$this->_page_value,
                            ""
                        )
                        ->joinLeft(
                            ['k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")],
                            "p.".$this->_page_value." = k.product_id AND k.store_id = :store_id AND k.type = :type",
                            ""
                        )
                        ->where("p.is_active = 1 AND k.product_id IS NULL AND v.store_id =0"),
                        $this->_frameworkModelResource->getConnection("core_write")
                        ->select()
                        /*
                         * Select pages for the current store/mode
                         * have been updated since last sync.
                         */
                        ->from(
                            ['p' => $this->_frameworkModelResource->getTableName("cms_page")],
                            ['page_id' => "p.".$this->_page_value]
                        )
                        ->where('p.'.$this->_page_value.' NOT IN (?)', $pageids)
                        ->join(
                            ['v' => $this->_frameworkModelResource->getTableName("cms_page_store")],
                            "p.".$this->_page_value." = v.".$this->_page_value." AND v.store_id = :store_id",
                            ""
                        )
                        ->joinLeft(
                            ['k' => $this->_frameworkModelResource->getTableName("klevu_product_sync")],
                            "v.".$this->_page_value." = k.product_id AND k.store_id = :store_id AND k.type = :type",
                            ""
                        )
                        ->where("p.is_active = 1 AND k.product_id IS NULL")
                        ])
                    ->bind([
                    'type' => "pages",
                    'store_id' => $store->getId(),
                    ]),
            ];
        }
        return $actions;
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
    public function deletecms(array $data)
    {
        $total = count($data);
        $response = $this->_apiActionDeleterecords->setStore($this->_storeModelStoreManagerInterface->getStore())->execute([
            'sessionId' => $this->getSessionId() ,
            'records' => array_map(function ($v) {

                return [
                    'id' => "pageid_" . $v['page_id']
                ];
            }, $data)
        ]);
        if ($response->isSuccess()) {
            $this->_klevuContentActions->executeDeleteContentSuccess($data, $response);
        } else {
			$this->_searchModelSession->setKlevuFailedFlag(1);
            return sprintf("%d cms%s failed (%s)", $total, ($total > 1) ? "s" : "", $response->getMessage());
        }
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
    public function updateCms(array $data)
    {
        $total = count($data);
        $data = $this->_loadAttribute->addCmsData($data);
        $response = $this->_apiActionUpdaterecords->setStore($this->_storeModelStoreManagerInterface->getStore())->execute([
            'sessionId' => $this->getSessionId() ,
            'records' => $data
        ]);
        if ($response->isSuccess()) {
            $this->_klevuContentActions->executeUpdateContentSuccess($data, $response);
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
    public function addCms(array $data)
    {
        $total = count($data);
        $data = $this->_loadAttribute->addCmsData($data);
        $response = $this->_apiActionAddrecords->setStore($this->_storeModelStoreManagerInterface->getStore())->execute([
            'sessionId' => $this->getSessionId() ,
            'records' => $data
        ]);
        if ($response->isSuccess()) {
            $this->_klevuContentActions->executeAddContentSuccess($data, $response);
        } else {
			$this->_searchModelSession->setKlevuFailedFlag(1);
            return sprintf("%d cms%s failed (%s)", $total, ($total > 1) ? "s" : "", $response->getMessage());
        }
    }


}

