<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_GeoIP
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\GeoIP\Helper;

use Magento\Customer\Helper\Address as CustomerAddressHelper;
use Magento\Directory\Model\Region;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Address
 * @package Mageplaza\GeoIP\Helper
 */
class Address extends Data
{
    /**
     * @type \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $_directoryList;

    /**
     * @type \Magento\Framework\Locale\Resolver
     */
    protected $_localeResolver;

    /**
     * @type \Magento\Directory\Model\Region
     */
    protected $_regionModel;

    /**
     * @var CustomerAddressHelper
     */
    protected $addressHelper;

    /**
     * Address constructor.
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param DirectoryList $directoryList
     * @param Resolver $localeResolver
     * @param Region $regionModel
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        Resolver $localeResolver,
        Region $regionModel
    )
    {
        $this->_directoryList  = $directoryList;
        $this->_localeResolver = $localeResolver;
        $this->_regionModel    = $regionModel;

        parent::__construct($context, $objectManager, $storeManager);
    }

    /***************************************** Maxmind Db GeoIp ******************************************************/
    /**
     * Check has library at path var/Mageplaza/GeoIp/GeoIp/
     * @return bool|string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function checkHasLibrary()
    {
        $path = $this->_directoryList->getPath('var') . '/Mageplaza/GeoIp/GeoIp';
        if (!file_exists($path)) {
            return false;
        }

        $folder   = scandir($path, true);
        $pathFile = $path . '/' . $folder[0] . '/GeoLite2-City.mmdb';
        if (!file_exists($pathFile)) {
            return false;
        }

        return $pathFile;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getGeoIpData()
    {
        $libPath = $this->checkHasLibrary();

        if ($this->getConfigValue('geoip/general/enable') && $libPath && class_exists('GeoIp2\Database\Reader')) {
            try {
                $geoIp  = new \GeoIp2\Database\Reader($libPath, $this->getLocales());
                $record = $geoIp->city($this->_request->getParam('fakeIp', null) ?: $this->_remoteAddress->getRemoteAddress());

                $geoIpData = [
                    'city'       => $record->city->name,
                    'country_id' => $record->country->isoCode,
                    'postcode'   => $record->postal->code
                ];

                if ($record->mostSpecificSubdivision) {
                    $code = $record->mostSpecificSubdivision->isoCode;
                    if ($regionId = $this->_regionModel->loadByCode($code, $record->country->isoCode)->getId()) {
                        $geoIpData['region_id'] = $regionId;
                    } else {
                        $geoIpData['region'] = $record->mostSpecificSubdivision->name;
                    }
                }
                $allowedCountries = $this->getConfigValue('general/country/allow');
                $allowedCountries = explode(',', $allowedCountries);
                if (!in_array($geoIpData['country_id'], $allowedCountries)) {
                    $geoIpData = [];
                }
            } catch (\Exception $e) {
                $geoIpData = [];
            }

            return $geoIpData;
        }

        return [];
    }

    /**
     * @return array
     */
    protected function getLocales()
    {
        $locale   = $this->_localeResolver->getLocale();
        $language = substr($locale, 0, 2) ? substr($locale, 0, 2) : 'en';

        $locales = [$language];
        if ($language != 'en') {
            $locales[] = 'en';
        }

        return $locales;
    }
}
