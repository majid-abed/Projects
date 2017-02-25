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

class PersianMagento_Enpayment_Model_Enpayment extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'enpayment';
	protected $_formBlockType = 'enpayment/form';
	protected $_infoBlockType = 'enpayment/info';
	protected $_isGateway               = false;
	protected $_canAuthorize            = false;
	protected $_canCapture              = false;
	protected $_canCapturePartial       = false;
	protected $_canRefund               = false;
	protected $_canVoid                 = false;
	protected $_canUseInternal          = false;
	protected $_canUseCheckout          = true;
	protected $_canUseForMultishipping  = false;
	protected $_order;
	
	public function getOrder() {
		if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($paymentInfo->getOrder()->getRealOrderId());
        }
        return $this->_order;
    }
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('enpayment/processing/redirect', array('_secure'=>true));
	}

	public function capture(Varien_Object $payment, $amount) {
		$payment->setStatus(self::STATUS_APPROVED)->setLastTransId($this->getTransactionId());
		return $this;
    }

	public function getPaymentMethodType() {
		return $this->_paymentMethod;
	}
	
	public function getUrl() {
		// get callback path
    	$urlPath = $this->_getCallbackPath();

    	// compile url parameters
    	$urlPath .= (strpos($urlPath, '?') === false) ? '?' : '&';
    	foreach ($this->getFormFields() as $key => $val) {
    	    $urlPath .= $key . '=' . urlencode($val) . '&';
    	}
    	$urlPath = substr($urlPath, 0, -1);  // remove last "&"

        // add optional MD5 encryption
        if ($md5Key = $this->getConfigData('md5_encryption_key')) {
            $urlPath .= '&fgkey=' . md5($md5Key . $urlPath);
        }
        return $this->getPremiumLink() . $urlPath;
    }

    /**
     * prepare params array to send it to gateway page via POST
     *
     * @return array
     */
    public function getFormFields()
    {
    	if ($this->getConfigData('use_store_currency')) {
        	$price      = number_format($this->getOrder()->getGrandTotal()*100,0,'.','');
        	$currency   = $this->getOrder()->getOrderCurrencyCode();
    	} else {
        	$price      = number_format($this->getOrder()->getBaseGrandTotal()*100,0,'.','');
        	$currency   = $this->getOrder()->getBaseCurrencyCode();
    	}

        $params = array(
			'price'					=>	$price,
			'cb_currency'			=>	$currency,
			'cb_content_name_utf'	=>	Mage::helper('enpayment')->__('Your purchase at') . ' ' . Mage::app()->getStore()->getName(),
			'externalBDRID'			=>	$this->getOrder()->getRealOrderId() . '-' . $this->getOrder()->getQuoteId(),
		);

        return $params;
    }
	
	/**
     * Get premium link from configuration
     *
     * @return string Premium Link
     */
    public function getPremiumLink() {
        // get premium link
        $premiumLink = $this->getConfigData('premium_link');
        if (empty($premiumLink)) {
            return;
        }

        // do some manual processing for backwards compatibility
        if (substr($premiumLink, -1) != '/') {
            $premiumLink .= '/';
        }
        $pStartPos = strpos($premiumLink, '://premium');
        $pEndPos = strpos($premiumLink, '/', $pStartPos+3);
        $premiumPart = substr($premiumLink, 0, $pEndPos+1);

        // remove ending slash again
        if (substr($premiumLink, -1) == '/') {
            $premiumLink = substr($premiumLink, 0, -1);
        }

        return $premiumLink;
    }
    /**
     * Get callback url without domain
     *
     * @return string Callback URL path /enpayment/processing/response/
     */
    protected function _getCallbackPath() {
        $callbackUrl = '';

        $url = Mage::getUrl('enpayment/processing/response', array('_secure'=>true));
        $urlParts = parse_url($url);

        $callbackUrl = $urlParts['path'];
        if (!empty($urlParts['query'])) {
            $callbackUrl .= '?'.$urlParts['query'];
        }

        return $callbackUrl;
    }
}
