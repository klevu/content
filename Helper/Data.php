<?php

namespace Klevu\Content\Helper;

use Klevu\Logger\Constants as LoggerConstants;
use Klevu\Search\Helper\Config as ConfigHelper;
use Klevu\Search\Helper\Data as KlevuSearchHelper;
use Klevu\Search\Model\Api\Action\Idsearch;
use Klevu\Search\Model\Api\Action\Searchtermtracking;
use Klevu\Search\Model\Api\Response as KlevuApiResponse;
use Klevu\Search\Model\System\Config\Source\Yesnoforced;
use Magento\CatalogSearch\Helper\Data as MagentoSearchHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_CMS_SYNC_ENABLED = "klevu_search/product_sync/enabledcms";
    const XML_PATH_EXCLUDED_CMS_PAGES = "klevu_search/cmscontent/excludecms";
    const XML_PATH_EXCLUDEDCMS_PAGES = "klevu_search/cmscontent/excludecms_pages";
    const XML_PATH_CMS_ENABLED_ON_FRONT = "klevu_search/cmscontent/enabledcmsfront";

    /**
     * @var RequestInterface
     */
    protected $_frameworkAppRequestInterface;
    /**
     * @var RequestInterface
     */
    protected $_catalogSearchHelper;
    /**
     * @var ConfigHelper
     */
    protected $_searchHelperConfig;
    /**
     * @var Idsearch
     */
    protected $_apiActionIdsearch;
    /**
     * @var KlevuSearchHelper
     */
    protected $_searchHelperData;
    /**
     * @var Searchtermtracking
     */
    protected $_apiActionSearchtermtracking;
    /**
     * @var ScopeConfigInterface
     */
    protected $_appConfigScopeConfigInterface;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var array
     */
    protected $_klevu_Content_parameters;
    /**
     * @var KlevuApiResponse
     */
    protected $_klevu_Content_response;
    /**
     * @var array
     */
    protected $_klevu_Cms_Data;
    /**
     * @var array
     */
    protected $_klevu_tracking_parameters;

    public function __construct(
        RequestInterface $frameworkAppRequestInterface,
        ConfigHelper $searchHelperConfig,
        Idsearch $apiActionIdsearch,
        KlevuSearchHelper $searchHelperData,
        Searchtermtracking $apiActionSearchtermtracking,
        ScopeConfigInterface $appConfigScopeConfigInterface,
        MagentoSearchHelper $catalogSearchHelper,
        SerializerInterface $serializer = null
    ) {
        $this->_frameworkAppRequestInterface = $frameworkAppRequestInterface;
        $this->_searchHelperConfig = $searchHelperConfig;
        $this->_apiActionIdsearch = $apiActionIdsearch;
        $this->_searchHelperData = $searchHelperData;
        $this->_apiActionSearchtermtracking = $apiActionSearchtermtracking;
        $this->_appConfigScopeConfigInterface = $appConfigScopeConfigInterface;
        $this->_catalogSearchHelper = $catalogSearchHelper;
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(SerializerInterface::class);
    }

    /**
     * Return the Klevu api content filters
     * @return array
     */
    public function getContentSearchFilters()
    {
        if (empty($this->_klevu_Content_parameters)) {
            $query = $this->_catalogSearchHelper->getEscapedQueryText();
            $this->_klevu_Content_parameters = [
                'ticket' => $this->_searchHelperConfig->getJsApiKey(),
                'noOfResults' => 1000,
                'term' => $query,
                'klevuSort' => 'rel',
                'paginationStartsFrom' => 0,
                'enableFilters' => 'true',
                'category' => 'KLEVU_CMS',
                'fl' => 'name,shortDesc,url',
                'klevuShowOutOfStockProducts' => 'true',
                'filterResults' => $this->_getPreparedFilters(),
            ];
            $this->log(LoggerConstants::ZEND_LOG_DEBUG, sprintf("Starting search for term: %s", $query));
        }

        return $this->_klevu_Content_parameters;
    }

    /**
     * Send the API Request and return the API Response.
     * @return \Klevu\Search\Model\Api\Response
     */
    public function getKlevuResponse()
    {
        if (!$this->_klevu_Content_response) {
            $this->_klevu_Content_response = $this->_apiActionIdsearch->execute($this->getContentSearchFilters());
        }

        return $this->_klevu_Content_response;
    }

    /**
     * Return the Klevu api search filters
     * @return array
     */
    public function getContentSearchTracking($noOfTrackingResults, $queryType)
    {
        $query = $this->_catalogSearchHelper->getEscapedQueryText();
        $this->_klevu_tracking_parameters = [
            'klevu_apiKey' => $this->_searchHelperConfig->getJsApiKey(),
            'klevu_term' => $query,
            'klevu_totalResults' => $noOfTrackingResults,
            'klevu_shopperIP' => $this->_searchHelperData->getIp(),
            'klevu_typeOfQuery' => $queryType,
            'Klevu_typeOfRecord' => 'KLEVU_CMS'
        ];
        $this->log(LoggerConstants::ZEND_LOG_DEBUG, sprintf("Content Search tracking for term: %s", $query));

        return $this->_klevu_tracking_parameters;
    }

    /**
     * This method executes the the Klevu API request if it has not already been called, and takes the result
     * with the result
     * We then add all these values to our class variable $_klevu_\Cms\Data.
     *
     * @return array
     */
    public function getCmsData()
    {
        $cmsData = [];
        if (!empty($this->_klevu_Cms_Data)) {
            return $this->_klevu_Cms_Data;
        }
        // If no results, return an empty array
        if (!$this->getKlevuResponse()->hasData('result')) {
            return [];
        }
        $oneResult = [];
        foreach ($this->getKlevuResponse()->getData('result') as $key => $value) {
            if (isset($value['name'])) {
                if (!empty($value['shortDesc'])) {
                    $value["shortDesc"] = $value['shortDesc'];
                }
                $cmsData[] = $value;
            } else {
                switch ($key) {
                    case "name":
                        $oneResult['name'] = $value;
                        break;
                    case "url":
                        $oneResult['url'] = $value;
                        break;
                    case "shortDesc":
                        if (!empty($value)) {
                            $oneResult['shortDesc'] = $value;
                        }
                        break;
                }
            }
        }
        if (isset($oneResult['name']) && isset($oneResult['url'])) {
            $cmsData[] = $oneResult;
        }
        $this->_klevu_Cms_Data = $cmsData;

        $responseMeta = $this->getKlevuResponse()->getData('meta');
        $this->_apiActionSearchtermtracking->execute(
            $this->getContentSearchTracking(count($this->_klevu_Cms_Data), $responseMeta['typeOfQuery'])
        );
        $this->log(
            LoggerConstants::ZEND_LOG_DEBUG,
            sprintf("Cms count returned: %s", count($this->_klevu_Cms_Data))
        );

        return $this->_klevu_Cms_Data;
    }

    /**
     * Print Log in Klevu log file.
     *
     * @param int $level
     * @param string $message
     *
     * @retiurn void
     */
    protected function log($level, $message)
    {
        $this->_searchHelperData->log($level, $message);
    }

    /**
     * Get excluded cms page for store.
     *
     * @param StoreInterface|int $store
     *
     * @return string
     */
    public function getExcludedCmsPages($store = null)
    {
        return $this->_appConfigScopeConfigInterface->getValue(
            static::XML_PATH_EXCLUDED_CMS_PAGES,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Checks if the given value is json encoded
     *
     * @param  $sValue
     *
     * @return bool
     */
    public function isJson($sValue)
    {
        return is_string($sValue) &&
            is_array(json_decode($sValue, true)) &&
            (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Get excluded cms page for store.
     *
     * @param StoreInterface|int $store
     *
     * @return array
     */
    public function getExcludedPages($store = null)
    {
        $excludeCms = $this->_appConfigScopeConfigInterface->getValue(
            static::XML_PATH_EXCLUDEDCMS_PAGES,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        if (($excludeCms !== "[]") && !empty($excludeCms)) {
            if ($this->isJson($excludeCms)) {
                $values = json_decode($excludeCms, true);
            } else {
                $values = $this->serializer->unserialize($excludeCms);
            }
            if (is_array($values)) {
                return $values;
            }
        }

        return [];
    }

    /**
     * Get value of cms synchronize for the given store.
     *
     * @param StoreInterface|int $store
     *
     * @return int
     */
    public function getCmsSyncEnabledFlag($store = null)
    {
        return (int)$this->_appConfigScopeConfigInterface->getValue(
            static::XML_PATH_CMS_SYNC_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if Cms Sync is enabled for the given store.
     *
     * @param StoreInterface|int $store
     *
     * @return bool
     */
    public function isCmsSyncEnabled($store = null)
    {
        return $this->getCmsSyncEnabledFlag($store) === Yesnoforced::YES;
    }

    /**
     * Get value of cms synchronize for the given store.
     *
     * @param StoreInterface|int $store
     *
     * @return int
     */
    public function getCmsSyncEnabledOnFront($store = null)
    {
        return (int)$this->_appConfigScopeConfigInterface->getValue(
            static::XML_PATH_CMS_ENABLED_ON_FRONT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if Cms is enabled on frontend for the given store.
     *
     * @param StoreInterface|int $store
     *
     * @return bool
     */
    public function isCmsSyncEnabledOnFront($store = null)
    {
        return $this->getCmsSyncEnabledOnFront($store) === Yesnoforced::YES;
    }

    /**
     * Get the type filters for Content from Klevu .
     *
     * @return array
     */
    public function getKlevuFilters()
    {
        $attributes = [];
        $filters = $this->getKlevuResponse()->getData('filters');
        // If there are no filters, return empty array.
        if (empty($filters)) {
            return [];
        }

        foreach ($filters as $filter) {
            foreach ($filter as $filterData) {
                if (!isset($filterData['@attributes']['key'])) {
                    continue;
                }
                $key = (string)$filterData['@attributes']['key'];
                $attributes[$key] = [
                    'label' => (string)$filterData['@attributes']['label']
                ];
                $attributes[$key]['option'] = [];
                if (!$filterData['option']) {
                    continue;
                }
                foreach ($filterData['option'] as $option) {
                    if (isset($option['@attributes']['name'])) {
                        $attributes[$key]['options'][] = [
                            'label' => trim((string)$option['@attributes']['name']),
                            'count' => trim((string)$option['@attributes']['count']),
                            'selected' => trim((string)$option['@attributes']['selected'])
                        ];
                    }
                    if (isset($option['name'])) {
                        $attributes[$key]['options'][] = [
                            'label' => trim((string)$option['name']),
                            'count' => trim((string)$option['count']),
                            'selected' => trim((string)$option['selected'])
                        ];
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * Get the active filters, then prepare them for Klevu.
     *
     * @return string
     */
    protected function _getPreparedFilters()
    {
        $preparedFilters = [];
        $filterType = $this->_frameworkAppRequestInterface->getParam('cat');
        if (!empty($filterType)) {
            $preparedFilters['category'] = $filterType;

            $this->log(
                LoggerConstants::ZEND_LOG_DEBUG,
                sprintf('Active For Category Filters: %s', var_export($preparedFilters, true))
            );

            return implode(';;', array_map(function ($value, $key) {
                return sprintf('%s:%s', $key, $value);
            }, $preparedFilters, array_keys($preparedFilters)));
        }
    }

    /**
     * Return the Cms pages.
     *
     * @param int|StoreInterface $store
     *
     * @return array
     */
    public function getCmsPageMap($store = null)
    {
        $excludedCmsPages = $this->_appConfigScopeConfigInterface->getValue(
            static::XML_PATH_EXCLUDEDCMS_PAGES,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        $cmsMap = $this->serializer->unserialize($excludedCmsPages);

        return (is_array($cmsMap)) ? $cmsMap : [];
    }

    /**
     * set the Cms pages.
     *
     * @param int|StoreInterface $store
     *
     * @return $this
     */
    public function setCmsPageMap($map, $store = null)
    {
        unset($map["__empty"]);
        $this->_searchHelperConfig->setStoreConfig(
            static::XML_PATH_EXCLUDEDCMS_PAGES,
            $this->serializer->serialize($map),
            $store
        );

        return $this;
    }

    /**
     * Remove html tags and replace it with space.
     *
     * @param $string
     *
     * @return string
     */
    public function ripTags($string)
    {
        // ----- remove HTML TAGs -----
        $string = preg_replace('/<[^>]*>/', ' ', $string);

        // ----- remove control characters -----
        $string = str_replace(["\r", "\n", "\t"], ['', ' ', ' '], $string);

        // ----- remove multiple spaces -----
        return trim(preg_replace('/ {2,}/', ' ', $string));
    }
}
