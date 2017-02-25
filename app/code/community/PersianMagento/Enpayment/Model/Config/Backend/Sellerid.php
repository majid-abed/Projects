<?php
/**
 * NOTICE OF LICENSE
 *
 * You may not sell, sub-license, rent or lease
 * any portion of the Software or Documentation to anyone.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade to newer
 * versions in the future.
 *
 * @category   PersianMagento
 * @package    PersianMagento_Enpayment
 * @copyright  Copyright (c) 2013-1392 Persian-Magento.ir
 * @contacts   support@persian-magento.ir
 */

class PersianMagento_Enpayment_Model_Config_Backend_Sellerid extends Mage_Core_Model_Config_Data {
    /**
     * Verify seller id in Enpayment registration system to reduce configuration failures (experimental)
     *
     * @return PersianMagento_Enpayment_Model_Enpayment_Config_Backend_Sellerid
     */
    protected function _beforeSave() {
    	try {
    	    if ($this->getValue()) {
    			$client = new Varien_Http_Client();
    			$client->setUri((string)Mage::getConfig()->getNode('enpayment/verify_url'))
    				->setConfig(array('timeout'=>10,))
    				->setHeaders('accept-encoding', '')
    				->setParameterPost('seller_id', $this->getValue())
    				->setMethod(Zend_Http_Client::POST);
    			$response = $client->request();
    	    }
		} catch (Exception $e) {
			// verification system unavailable. no further action.
		}

        return $this;
    }
}
