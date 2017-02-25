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
 * @copyright  Copyright (c) 2016-1395 Persian-Magento.ir
 * @contacts   support@persian-magento.ir
 */
 
class PersianMagento_Enpayment_Block_Redirect extends Mage_Core_Block_Template {
    
	protected function _getCheckout() {
		return Mage::getSingleton('checkout/session');
	}

	protected function _getOrder() {
		if ($this->getOrder()) {
			return $this->getOrder();
		} elseif ($orderIncrementId = $this->_getCheckout()->getLastRealOrderId()) {
			return Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		} else {
			return null;
		}
	}

    /**
     * Get form data
     *
     * @return array
     */
	public function getFormData() {
		$order = $this->_getOrder()->_data;
		$array = $this->_getOrder()->getPayment()->getMethodInstance()->getFormFields();
		$price = $array["price"];
		$seller_id = $this->_getOrder()->getPayment()->getMethodInstance()->getConfigData('seller_id');
		$len = strlen($price);
		$len -= 2;
		$price = substr($price,0,$len);		
		$code = Mage::app()->getStore()->getCode();		
		$cburl = Mage::getUrl('enpayment/processing/response', array('_secure'=>true));
		
		$params = array(
				"Amount"	=> $price,
				"MID"		=> $seller_id,
				"ResNum"	=> $order["entity_id"],
				"RedirectURL"   => $cburl
			);
		return $params;
	}

    /**
     * Getting gateway url
     *
     * @return string
     */
  public function getFormAction() {
    return "https://pna.shaparak.ir/CardServices";
  }
}
