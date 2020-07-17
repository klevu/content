<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 9/19/2018
 * Time: 12:06 PM
 */

namespace Klevu\Content\Model;

/**
 * Interface ContentInterface
 * @package Klevu\Content\Model
 */
interface ContentInterface
{
    public function _construct();

    public function getJobCode();

    /**
     * Perform Content Sync on any configured stores, adding new content, updating modified and
     * deleting removed content since last sync.
     */
    public function run();

    /**
     * @param $store
     * @return mixed
     */
    public function syncCmsData($store);

    public function deletePagesCollection($store);

    public function addPagesCollection($store);

    public function updatePagesCollection($store);

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
    public function addcmsData(&$pages);
}
