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
 
class PersianMagento_Enpayment_Block_Form extends Mage_Payment_Block_Form {
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('enpayment/form.phtml');
    }

    public function getPaymentImageSrc() {
    	return $this->getSkinUrl('images/persianmagento/enpayment.png');
    }
}