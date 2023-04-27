<?php
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Generic.Files.LineLength.TooLong

namespace Klevu\Content\Test\Integration\Controller\Adminhtml\System\Config\Edit\SearchConfiguration;

use Klevu\Search\Api\Service\Account\GetFeaturesInterface;
use Klevu\Search\Api\Service\Account\Model\AccountFeaturesInterface;
use Klevu\Search\Service\Account\GetFeatures;
use Klevu\Search\Service\Account\Model\AccountFeatures;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\Config\Storage\Writer as ScopeConfigWriter;
use Magento\Framework\App\Config\Storage\WriterInterface as ScopeConfigWriterInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractBackendController as AbstractBackendControllerTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class RenderPluginTest extends AbstractBackendControllerTestCase
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var MockObject&LoggerInterface
     */
    private $loggerMock;

    /**
     * @var MockObject&AccountFeaturesInterface
     */
    private $accountFeaturesMock;

    /**
     * @var MockObject&GetFeaturesInterface
     */
    private $getFeaturesMock;

    /**
     * @var MockObject&ScopeConfigWriterInterface
     */
    private $scopeConfigWriterMock;

    /**
     * @var string
     */
    protected $resource = 'Klevu_Search::config_search';

    /**
     * @var int
     */
    protected $expectedNoAccessResponseCode = 302;

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/admin/url/use_custom 1
     * @magentoConfigFixture default_store admin/url/use_custom 1
     * @magentoConfigFixture default/admin/url/custom http://localhost/
     * @magentoConfigFixture default_store admin/url/custom http://localhost/
     * @magentoConfigFixture default/klevu_search/add_to_cart/enabledaddtocartfront 0
     * @magentoConfigFixture default_store klevu_search/add_to_cart/enabledaddtocartfront 0
     */
    public function testRender_DefaultScope()
    {
        $this->setupPhp5();

        $this->scopeConfigWriterMock->expects($this->never())
            ->method('save');

        $request = $this->getRequest();
        $request->setParam('section', 'klevu_search');
        $request->setMethod('GET');

        $this->dispatch($this->getAdminFrontName() . '/admin/system_config/edit');

        $response = $this->getResponse();
        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('<div id="system_config_tabs"', $responseBody);
        } else {
            $this->assertContains('<div id="system_config_tabs"', $responseBody);
        }
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        } else {
            $this->assertRegExp('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        }

        $matches = [];
        preg_match('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody, $matches);
        $this->assertCount(1, $matches);
        $cmsContentRow = current($matches);
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('Switch to Store View scope to manage', $cmsContentRow);
        } else {
            $this->assertContains('Switch to Store View scope to manage', $cmsContentRow);
        }
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront".*?</tr>#s', $responseBody);
            $this->assertDoesNotMatchRegularExpression('#<(input|select).*?id="klevu_search_cmscontent_enabledcmsfront"#s', $responseBody);
        } else {
            $this->assertNotRegExp('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront".*?</tr>#s', $responseBody);
            $this->assertNotRegExp('#<(input|select).*?id="klevu_search_cmscontent_enabledcmsfront"#s', $responseBody);
        }
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/admin/url/use_custom 1
     * @magentoConfigFixture default_store admin/url/use_custom 1
     * @magentoConfigFixture default/admin/url/custom http://localhost/
     * @magentoConfigFixture default_store admin/url/custom http://localhost/
     * @magentoConfigFixture default/klevu_search/add_to_cart/enabledaddtocartfront 0
     * @magentoConfigFixture default_store klevu_search/add_to_cart/enabledaddtocartfront 0
     */
    public function testRender_WebsiteScope()
    {
        $this->setupPhp5();
        $defaultStore = $this->storeManager->getDefaultStoreView();

        $this->scopeConfigWriterMock->expects($this->never())
            ->method('save');

        $request = $this->getRequest();
        $request->setParam('website', $defaultStore->getWebsiteId());
        $request->setParam('section', 'klevu_search');
        $request->setMethod('GET');

        $this->dispatch($this->getAdminFrontName() . '/admin/system_config/edit');

        $response = $this->getResponse();
        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        } else {
            $this->assertRegExp('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        }

        $matches = [];
        preg_match('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody, $matches);
        $this->assertCount(1, $matches);
        $cmsContentRow = current($matches);
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('Switch to Store View scope to manage', $cmsContentRow);
        } else {
            $this->assertContains('Switch to Store View scope to manage', $cmsContentRow);
        }
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront".*?</tr>#s', $responseBody);
            $this->assertDoesNotMatchRegularExpression('#<(input|select).*?id="klevu_search_cmscontent_enabledcmsfront"#s', $responseBody);
        } else {
            $this->assertNotRegExp('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront".*?</tr>#s', $responseBody);
            $this->assertNotRegExp('#<(input|select).*?id="klevu_search_cmscontent_enabledcmsfront"#s', $responseBody);
        }
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/admin/url/use_custom 1
     * @magentoConfigFixture default_store admin/url/use_custom 1
     * @magentoConfigFixture default/admin/url/custom http://localhost/
     * @magentoConfigFixture default_store admin/url/custom http://localhost/
     * @magentoConfigFixture default/klevu_search/add_to_cart/enabledaddtocartfront 0
     * @magentoConfigFixture default_store klevu_search/add_to_cart/enabledaddtocartfront 0
     */
    public function testRender_StoreScope_CmsContentAvailable_DefaultConfig()
    {
        $this->setupPhp5();
        $defaultStore = $this->storeManager->getDefaultStoreView();

        $this->scopeConfigWriterMock->expects($this->never())
            ->method('save');

        $this->accountFeaturesMock->method('isFeatureAvailable')->willReturnCallback(
            static function ($feature, $strict = false) {
                switch ($feature) {
                    case AccountFeatures::PM_FEATUREFLAG_CMS_FRONT:
                        $return = true;
                        break;

                    default:
                        $return = false;
                        break;
                }

                return $return;
            }
        );

        $request = $this->getRequest();
        $request->setParam('store', $defaultStore->getId());
        $request->setParam('section', 'klevu_search');
        $request->setMethod('GET');

        $this->dispatch($this->getAdminFrontName() . '/admin/system_config/edit');

        $response = $this->getResponse();
        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();

        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        } else {
            $this->assertRegExp('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        }
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody);
        } else {
            $this->assertNotRegExp('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody);
        }

        $matches = [];
        preg_match('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront".*?</tr>#s', $responseBody, $matches);
        $this->assertCount(1, $matches, 'CMS Content row');
        $cmsContentRow = current($matches);
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString('Switch to Store View scope to manage', $cmsContentRow);
            $this->assertStringNotContainsString('<div class="klevu-upgrade-block">TEST UPGRADE MESSAGE</div>', $cmsContentRow);
        } else {
            $this->assertNotContains('Switch to Store View scope to manage', $cmsContentRow);
            $this->assertNotContains('<div class="klevu-upgrade-block">TEST UPGRADE MESSAGE</div>', $cmsContentRow);
        }

        $matches = [];
        preg_match('#<select id="klevu_search_cmscontent_enabledcmsfront".*?>.*?</select>#s', $responseBody, $matches);
        $this->assertCount(1, $matches, 'CMS Content Field');
        $cmsContentField = current($matches);
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString('disabled', $cmsContentField);
        } else {
            $this->assertNotContains('disabled', $cmsContentField);
        }
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<option[^>]+value="1".*?>\s*Yes\s*</option>#s', $cmsContentField);
            $this->assertMatchesRegularExpression('#<option[^>]+value="0"[^>]+selected.*?>\s*No\s*</option>#s', $cmsContentField);
        } else {
            $this->assertRegExp('#<option[^>]+value="1".*?>\s*Yes\s*</option>#s', $cmsContentField);
            $this->assertRegExp('#<option[^>]+value="0"[^>]+selected.*?>\s*No\s*</option>#s', $cmsContentField);
        }
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/admin/url/use_custom 1
     * @magentoConfigFixture default_store admin/url/use_custom 1
     * @magentoConfigFixture default/admin/url/custom http://localhost/
     * @magentoConfigFixture default_store admin/url/custom http://localhost/
     * @magentoConfigFixture default/klevu_search/add_to_cart/enabledaddtocartfront 0
     * @magentoConfigFixture default_store klevu_search/add_to_cart/enabledaddtocartfront 0
     */
    public function testRender_StoreScope_CmsContentUnavailable_DefaultConfig()
    {
        $this->setupPhp5();
        $defaultStore = $this->storeManager->getDefaultStoreView();

        $this->scopeConfigWriterMock->expects($this->never())
            ->method('save');

        $this->accountFeaturesMock->method('isFeatureAvailable')->willReturnCallback(
            static function ($feature, $strict = false) {
                switch ($feature) {
                    case AccountFeatures::PM_FEATUREFLAG_CMS_FRONT:
                    default:
                        $return = false;
                        break;
                }

                return $return;
            }
        );

        $request = $this->getRequest();
        $request->setParam('store', $defaultStore->getId());
        $request->setParam('section', 'klevu_search');
        $request->setMethod('GET');

        $this->dispatch($this->getAdminFrontName() . '/admin/system_config/edit');

        $response = $this->getResponse();
        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();

        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        } else {
            $this->assertRegExp('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        }
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody);
        } else {
            $this->assertNotRegExp('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody);
        }

        $matches = [];
        preg_match('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront".*?</tr>#s', $responseBody, $matches);
        $this->assertCount(1, $matches, 'CMS Content row');
        $cmsContentRow = current($matches);
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString('Switch to Store View scope to manage', $cmsContentRow);
        } else {
            $this->assertNotContains('Switch to Store View scope to manage', $cmsContentRow);
        }
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('<div class="klevu-upgrade-block">TEST UPGRADE MESSAGE</div>', $cmsContentRow);
        } else {
            $this->assertContains('<div class="klevu-upgrade-block">TEST UPGRADE MESSAGE</div>', $cmsContentRow);
        }

        $matches = [];
        preg_match('#<select id="klevu_search_cmscontent_enabledcmsfront".*?>.*?</select>#s', $responseBody, $matches);
        $this->assertCount(1, $matches, 'CMS Content Field');
        $cmsContentField = current($matches);
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringContainsString('disabled', $cmsContentField);
        } else {
            $this->assertContains('disabled', $cmsContentField);
        }
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<option[^>]+value="1".*?>\s*Yes\s*</option>#s', $cmsContentField);
            $this->assertMatchesRegularExpression('#<option[^>]+value="0"[^>]+selected.*?>\s*No\s*</option>#s', $cmsContentField);
        } else {
            $this->assertRegExp('#<option[^>]+value="1".*?>\s*Yes\s*</option>#s', $cmsContentField);
            $this->assertRegExp('#<option[^>]+value="0"[^>]+selected.*?>\s*No\s*</option>#s', $cmsContentField);
        }
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/admin/url/use_custom 1
     * @magentoConfigFixture default_store admin/url/use_custom 1
     * @magentoConfigFixture default/admin/url/custom http://localhost/
     * @magentoConfigFixture default_store admin/url/custom http://localhost/
     * @magentoConfigFixture default/klevu_search/cmscontent/enabledcmsfront 1
     * @magentoConfigFixture default_store klevu_search/cmscontent/enabledcmsfront 1
     * @magentoConfigFixture default/klevu_search/add_to_cart/enabledaddtocartfront 0
     * @magentoConfigFixture default_store klevu_search/add_to_cart/enabledaddtocartfront 0
     */
    public function testRender_StoreScope_CmsContentAvailable_EnabledInConfig()
    {
        $this->setupPhp5();
        $defaultStore = $this->storeManager->getDefaultStoreView();

        $this->scopeConfigWriterMock->expects($this->never())
            ->method('save');

        $this->accountFeaturesMock->method('isFeatureAvailable')->willReturnCallback(
            static function ($feature, $strict = false) {
                switch ($feature) {
                    case AccountFeatures::PM_FEATUREFLAG_CMS_FRONT:
                        $return = true;
                        break;

                    default:
                        $return = false;
                        break;
                }

                return $return;
            }
        );

        $request = $this->getRequest();
        $request->setParam('store', $defaultStore->getId());
        $request->setParam('section', 'klevu_search');
        $request->setMethod('GET');

        $this->dispatch($this->getAdminFrontName() . '/admin/system_config/edit');

        $response = $this->getResponse();
        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();

        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        } else {
            $this->assertRegExp('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        }
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody);
        } else {
            $this->assertNotRegExp('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody);
        }

        $matches = [];
        preg_match('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront".*?</tr>#s', $responseBody, $matches);
        $this->assertCount(1, $matches, 'CMS Content row');
        $cmsContentRow = current($matches);
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString('Switch to Store View scope to manage', $cmsContentRow);
            $this->assertStringNotContainsString('<div class="klevu-upgrade-block">TEST UPGRADE MESSAGE</div>', $cmsContentRow);
        } else {
            $this->assertNotContains('Switch to Store View scope to manage', $cmsContentRow);
            $this->assertNotContains('<div class="klevu-upgrade-block">TEST UPGRADE MESSAGE</div>', $cmsContentRow);
        }

        $matches = [];
        preg_match('#<select id="klevu_search_cmscontent_enabledcmsfront".*?>.*?</select>#s', $responseBody, $matches);
        $this->assertCount(1, $matches, 'CMS Content Field');
        $cmsContentField = current($matches);
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString('disabled', $cmsContentField);
        } else {
            $this->assertNotContains('disabled', $cmsContentField);
        }
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<option[^>]+value="1"[^>]+selected.*?>\s*Yes\s*</option>#s', $cmsContentField);
            $this->assertMatchesRegularExpression('#<option[^>]+value="0".*?>\s*No\s*</option>#s', $cmsContentField);
        } else {
            $this->assertRegExp('#<option[^>]+value="1"[^>]+selected.*?>\s*Yes\s*</option>#s', $cmsContentField);
            $this->assertRegExp('#<option[^>]+value="0".*?>\s*No\s*</option>#s', $cmsContentField);
        }
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/admin/url/use_custom 1
     * @magentoConfigFixture default_store admin/url/use_custom 1
     * @magentoConfigFixture default/admin/url/custom http://localhost/
     * @magentoConfigFixture default_store admin/url/custom http://localhost/
     * @magentoConfigFixture default/klevu_search/cmscontent/enabledcmsfront 1
     * @magentoConfigFixture default_store klevu_search/cmscontent/enabledcmsfront 1
     * @magentoConfigFixture default/klevu_search/add_to_cart/enabledaddtocartfront 0
     * @magentoConfigFixture default_store klevu_search/add_to_cart/enabledaddtocartfront 0
     */
    public function testRender_StoreScope_CmsContentUnavailable_EnabledInConfig()
    {
        $this->setupPhp5();
        $defaultStore = $this->storeManager->getDefaultStoreView();

        $this->scopeConfigWriterMock->expects($this->atLeastOnce())
            ->method('save')
            ->with(
                'klevu_search/cmscontent/enabledcmsfront',
                0,
                'stores',
                (int)$defaultStore->getId()
            );
        $this->loggerMock->expects($this->atLeastOnce())
            ->method('debug')
            ->with('Automatically updated config value for "klevu_search/cmscontent/enabledcmsfront" following feature check');

        $this->accountFeaturesMock->method('isFeatureAvailable')->willReturnCallback(
            static function ($feature, $strict = false) {
                switch ($feature) {
                    case AccountFeatures::PM_FEATUREFLAG_CMS_FRONT:
                    default:
                        $return = false;
                        break;
                }

                return $return;
            }
        );

        $request = $this->getRequest();
        $request->setParam('store', $defaultStore->getId());
        $request->setParam('section', 'klevu_search');
        $request->setMethod('GET');

        $this->dispatch($this->getAdminFrontName() . '/admin/system_config/edit');

        $response = $this->getResponse();
        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();

        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        } else {
            $this->assertRegExp('#<fieldset[^>]+id="klevu_search_cmscontent"#', $responseBody);
        }
        if (method_exists($this, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody);
        } else {
            $this->assertNotRegExp('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront_info".*?</tr>#s', $responseBody);
        }

        $matches = [];
        preg_match('#<tr[^>]+id="row_klevu_search_cmscontent_enabledcmsfront".*?</tr>#s', $responseBody, $matches);
        $this->assertCount(1, $matches, 'CMS Content row');
        $cmsContentRow = current($matches);
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString('Switch to Store View scope to manage', $cmsContentRow);
        } else {
            $this->assertNotContains('Switch to Store View scope to manage', $cmsContentRow);
        }
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('<div class="klevu-upgrade-block">TEST UPGRADE MESSAGE</div>', $cmsContentRow);
        } else {
            $this->assertContains('<div class="klevu-upgrade-block">TEST UPGRADE MESSAGE</div>', $cmsContentRow);
        }

        $matches = [];
        preg_match('#<select id="klevu_search_cmscontent_enabledcmsfront".*?>.*?</select>#s', $responseBody, $matches);
        $this->assertCount(1, $matches, 'CMS Content Field');
        $cmsContentField = current($matches);
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString('disabled', $cmsContentField);
        } else {
            $this->assertContains('disabled', $cmsContentField);
        }
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression('#<option[^>]+value="1".*?>\s*Yes\s*</option>#s', $cmsContentField);
            $this->assertMatchesRegularExpression('#<option[^>]+value="0"[^>]+selected.*?>\s*No\s*</option>#s', $cmsContentField);
        } else {
            $this->assertRegExp('#<option[^>]+value="1".*?>\s*Yes\s*</option>#s', $cmsContentField);
            $this->assertRegExp('#<option[^>]+value="0"[^>]+selected.*?>\s*No\s*</option>#s', $cmsContentField);
        }
    }

    /**
     * @inheritdoc
     */
    public function testAclHasAccess()
    {
        $this->setupPhp5();

        if ($this->uri === null) {
            $this->markTestIncomplete('AclHasAccess test is not complete');
        }
        if ($this->httpMethod) {
            $this->getRequest()->setMethod($this->httpMethod);
        }
        $this->dispatch($this->uri);
        $this->assertNotSame(404, $this->getResponse()->getHttpResponseCode());
        $this->assertNotSame($this->expectedNoAccessResponseCode, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @inheritdoc
     */
    public function testAclNoAccess()
    {
        $this->setupPhp5();
        if ($this->resource === null || $this->uri === null) {
            $this->markTestIncomplete('Acl test is not complete');
        }
        if ($this->httpMethod) {
            $this->getRequest()->setMethod($this->httpMethod);
        }
        $this->_objectManager->get(\Magento\Framework\Acl\Builder::class)
            ->getAcl()
            ->deny($this->_auth->getUser()->getRoles(), $this->resource);
        $this->dispatch($this->uri);
        $this->assertSame($this->expectedNoAccessResponseCode, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * Alternative setup method to accommodate lack of return type casting in PHP5.6,
     *  given setUp() requires a void return type
     *
     * @return void
     * @throws AuthenticationException
     * @todo Move to setUp when PHP 5.x is no longer supported
     */
    private function setupPhp5()
    {
        $this->setUp();

        $this->storeManager = $this->_objectManager->get(StoreManagerInterface::class);

        $this->loggerMock = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->_objectManager->addSharedInstance($this->loggerMock, 'Klevu\Search\Logger\Logger\Search');

        $this->scopeConfigWriterMock = $this->getMockBuilder(ScopeConfigWriter::class)
            ->disableOriginalConstructor()
            ->setMethods(['save'])
            ->getMock();
        $this->_objectManager->addSharedInstance($this->scopeConfigWriterMock, ScopeConfigWriterInterface::class);
        $this->_objectManager->addSharedInstance($this->scopeConfigWriterMock, ScopeConfigWriter::class);

        $this->accountFeaturesMock = $this->getMockBuilder(AccountFeatures::class)
            ->disableOriginalConstructor()
            ->setMethods(['isFeatureAvailable', 'getUpgradeMessage'])
            ->getMock();
        $this->accountFeaturesMock->method('getUpgradeMessage')
            ->willReturn('TEST UPGRADE MESSAGE');

        $this->getFeaturesMock = $this->getMockBuilder(GetFeaturesInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMock();
        $this->getFeaturesMock->method('execute')->willReturn($this->accountFeaturesMock);

        $this->_objectManager->addSharedInstance($this->getFeaturesMock, GetFeaturesInterface::class);
        $this->_objectManager->addSharedInstance($this->getFeaturesMock, GetFeatures::class);

        $this->uri = $this->getAdminFrontName() . '/admin/system_config/edit/section/klevu_search';
    }

    /**
     * Returns configured admin front name for use in dispatching controller requests
     *
     * @return string
     */
    private function getAdminFrontName()
    {
        /** @var AreaList $areaList */
        $areaList = $this->_objectManager->get(AreaList::class);
        $adminFrontName = $areaList->getFrontName('adminhtml');
        if (!$adminFrontName) {
            /** @var FrontNameResolver $backendFrontNameResolver */
            $backendFrontNameResolver = $this->_objectManager->get(FrontNameResolver::class);
            $adminFrontName = $backendFrontNameResolver->getFrontName(true);
        }

        return (string)$adminFrontName;
    }
}
