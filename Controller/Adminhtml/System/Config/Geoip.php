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

namespace Mageplaza\GeoIP\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\JsonFactory;
use Mageplaza\GeoIP\Helper\Data as HelperData;

/**
 * Class Geoip
 * @package Mageplaza\GeoIP\Controller\Adminhtml\System\Config
 */
class Geoip extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var DirectoryList
     */
    protected $_directoryList;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Geoip constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param DirectoryList $directoryList
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        DirectoryList $directoryList,
        HelperData $helperData
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_directoryList    = $directoryList;
        $this->_helperData       = $helperData;

        parent::__construct($context);
    }

    /**
     * @return $this|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $status = false;
        try {
            $path = $this->_directoryList->getPath('var') . '/Mageplaza/GeoIp/GeoIp';
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            $folder   = scandir($path, true);
            $pathFile = $path . '/' . $folder[0] . '/GeoLite2-City.mmdb';
            if (file_exists($pathFile)) {
                foreach (scandir($path . '/' . $folder[0], true) as $filename) {
                    if ($filename == '..' || $filename == '.') {
                        continue;
                    }
                    @unlink($path . '/' . $folder[0] . '/' . $filename);
                }
                @rmdir($path . '/' . $folder[0]);
            }

            file_put_contents($path . '/GeoLite2-City.tar.gz', fopen($this->_helperData->getDownloadPath(), 'r'));
            $phar = new \PharData($path . '/GeoLite2-City.tar.gz');
            $phar->extractTo($path);
            $status  = true;
            $message = __("Download library success!");
        } catch (\Exception $e) {
            $message = __("Can't download file. Please try again! %1", $e->getMessage());
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        return $result->setData(['success' => $status, 'message' => $message]);
    }
}
