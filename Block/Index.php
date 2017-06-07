<?php
namespace Klevu\Content\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\RequestInterface;

class Index extends \Magento\Framework\View\Element\Template
{
    
    /**
     * Get the Klevu other content
     * @return array
     */
    public function getCmsContent()
    {
        $collection = \Magento\Framework\App\ObjectManager::getInstance()->get('Klevu\Content\Helper\Data')->getCmsData();
        return $collection;
    }
    /**
     * Return the Klevu other content filters
     * @return array
     */
    public function getContentFilters()
    {
        $filters = \Magento\Framework\App\ObjectManager::getInstance()->get('Klevu\Content\Helper\Data')->getKlevuFilters();
        return $filters;
    }
}
