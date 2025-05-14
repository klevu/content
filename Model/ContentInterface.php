<?php

namespace Klevu\Content\Model;

interface ContentInterface
{
    /**
     * @return mixed
     */
    public function _construct();

    /**
     * @return mixed
     */
    public function getJobCode();

    /**
     * Perform Content Sync on any configured stores, adding new content, updating modified and
     * deleting removed content since last sync.
     */
    public function run();

    /**
     * @param $store
     *
     * @return mixed
     */
    public function syncCmsData($store);

    /**
     * @param $store
     *
     * @return mixed
     */
    public function deletePagesCollection($store);

    /**
     * @param $store
     *
     * @return mixed
     */
    public function addPagesCollection($store);

    /**
     * @param $store
     *
     * @return mixed
     */
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
