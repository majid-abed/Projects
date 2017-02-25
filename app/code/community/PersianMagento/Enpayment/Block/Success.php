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

class PersianMagento_Enpayment_Block_Success extends Mage_Core_Block_Template {
	protected function _construct() {
        parent::_construct();
        $this->setTemplate('enpayment/success.phtml');
    }
	
	public function getSuccessUrl() {
        return Mage::getUrl('*/*/success', array('_secure'=>true));
    }
}