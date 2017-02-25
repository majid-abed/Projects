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
 
class PersianMagento_Enpayment_ProcessingController extends Mage_Core_Controller_Front_Action {

    protected $_successBlockType  = 'enpayment/success';
    protected $_failureBlockType  = 'enpayment/failure';
    protected $_order = NULL;
    protected $_paymentInst = NULL;


    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }
	
	public function mkzero($z) {
		$str = Mage::app()->getStore()->getId();
		while($z > 0) {
			$str .= "0";
			$z -= 1;
		}
		return $str;	
	}
	
	/**
     * when customer selects En payment method
     */
    public function redirectAction() {
        try {
            $session = $this->_getCheckout();

            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());
            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }
            if ($order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $this->_getPendingPaymentStatus(),
                    Mage::helper('enpayment')->__('Customer was redirected to EnBank.')
                )->save();
            }

            if ($session->getQuoteId() && $session->getLastSuccessQuoteId()) {
                $session->setEnpaymentQuoteId($session->getQuoteId());
                $session->setEnpaymentSuccessQuoteId($session->getLastSuccessQuoteId());
                $session->setEnpaymentRealOrderId($session->getLastRealOrderId());
                $session->getQuote()->setIsActive(false)->save();
                $session->clear();
            }

            $this->loadLayout();
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }
	
    /**
     * EnBank returns SERVER variables to this action
     * A redirect as response is awaited.
     */
    public function responseAction() {
		$state = $_POST["State"];
				
		if($state == "OK") {
			$length = strlen($_POST["ResNum"]);
			$zero = 8-$length;
			$orderNum = $this->mkzero($zero);
			$orderId = $orderNum.$_POST["ResNum"];
			$session = $this->_getCheckout();
			$orderid = $session->getEnpaymentRealOrderId();
			$this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderid);
			$this->_paymentInst = $this->_order->getPayment()->getMethodInstance();			
			$send_array = array($_POST["RefNum"],$this->_paymentInst->getConfigData('seller_id'),$this->_paymentInst->getConfigData('transactionmanager_password'),$_POST["ResNum"]);
			
			$client = new SoapClient('https://pna.shaparak.ir/ref-payment/ws/ReferencePayment?WSDL');
			$res = $client->__soapCall('VerifyTransaction',array($_POST["RefNum"],$this->_paymentInst->getConfigData('seller_id')));			
			
			if($res > 0) {
				$price = $res;				
				$this->_order->getPayment()->setTransactionId($_POST["RefNum"]);
				$this->_order->getPayment()->setLastTransId($_POST["RefNum"]);
				
				// create invoice
				if ($this->_order->canInvoice()) {
					$invoice = $this->_order->prepareInvoice();
					$invoice->register()->capture();
					Mage::getModel('core/resource_transaction')
						->addObject($invoice)
						->addObject($invoice->getOrder())
						->save();
				}
	
				// add order history comment
				$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('enpayment')->__('The amount has been authorized and captured by EnBank.'));
	
				// send email
				$this->_order->sendNewOrderEmail();
				$this->_order->setEmailSent(true);
				$this->_order->save();
	
				// redirect to success page
				$this->getResponse()->setBody(
					$this->getLayout()
						->createBlock($this->_successBlockType)
						->setOrder($this->_order)
						->toHtml());
			
			}else{
				$this->_redirect('enpayment/processing/caberror');
			}
		}else{				
				$this->_redirect('enpayment/processing/caberror');
		}        
    }

    /**
     * EnBank return action
     */
    public function successAction()
    {
        try {
			$session = $this->_getCheckout();
			$session->unsEnpaymentRealOrderId();
            $session->setQuoteId($session->getEnpaymentQuoteId(true));
            $session->setLastSuccessQuoteId($session->getEnpaymentSuccessQuoteId(true));
            $this->_redirect('checkout/onepage/success');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * EnBank sub error action
     */
    public function caberrorAction() {
	
		// load order
        if (!$this->_order || !$this->_order->getId()) {
            if ($orderId = $this->_getCheckout()->getEnpaymentRealOrderId()) {
                $this->_order = Mage::getModel('sales/order')->load($orderId);
            }
        }

        // cancel order
        if ($this->_order->canCancel()) {
            $this->_order->cancel();
            $this->_order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, Mage::helper('enpayment')->__('An error occured during the payment process. The order has been canceled.'));
            $this->_order->save();
        }
		
		// set quote to active
        $session = $this->_getCheckout();
        if ($quoteId = $session->getEnpaymentQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                $session->setQuoteId($quoteId);
            }
        }
		$session->addError(Mage::helper('enpayment')->__('An error occured during the payment process. The order has been canceled.'));
		$this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_failureBlockType)
                ->setOrder($this->_order)
                ->toHtml()
        );
    }

    /**
     * Enpayment sub success action
     */
    public function cabsuccessAction()
    {
        try {
            // get order reference
            $externalBDRID = $this->getRequest()->getParam('externalBDRID');

            // load order
            list($orderId) = explode('-', $externalBDRID, 2);
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
			if (!$this->_order->getId())
				throw new Exception('Order ID not found.');

			$this->_paymentInst = $this->_order->getPayment()->getMethodInstance();

			// get EnBank system domain
			preg_match('/http[s]?:\/\/[a-z0-9_-]*\.([a-z]{2})\.[a-z0-9]*\.[a-z]{2,6}/i', $this->_paymentInst->getPremiumLink(), $matches);
			// create client object
			$client = new SoapClient('https://pna.shaparak.ir/ref-payment/ws/ReferencePayment?WSDL',array('exceptions' => 0));

            // second confirmation data
            $secondconfirmation = array(
                'sellerID'			=>	$this->_paymentInst->getConfigData('seller_id'),
                'tmPassword'		=>	$this->_paymentInst->getConfigData('transactionmanager_password'),
                'slaveMerchantID'	=>	'0',
                'externalBDRID'		=>	$externalBDRID
            );

            // start soap request
			$result = $client->__soapCall('isExternalBDRIDCommitted',$secondconfirmation);
			if (is_soap_fault($result)) {
				throw new Exception('Second Confirmation failed. '.$result->detail->{'TransactionManager.Status.StatusException'}->message.'. Details: '.var_export($secondconfirmation,true));
			}
            if($result->isCommitted != 1) {
            	throw new Exception('Second Confirmation failed. Transaction not commited. Details: '.var_export($secondconfirmation,true), 10);
			}

            // save transaction ID
            $this->_order->getPayment()->setTransactionId($result->BDRID);
            $this->_order->getPayment()->setLastTransId($result->BDRID);

            // create invoice
            if ($this->_order->canInvoice()) {
                $invoice = $this->_order->prepareInvoice();
                $invoice->register()->capture();
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
            }

            // add order history comment
            $this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('enpayment')->__('The amount has been authorized and captured by EnBank.'));

            // send email
            $this->_order->sendNewOrderEmail();
            $this->_order->setEmailSent(true);
            $this->_order->save();

            // redirect to success page
            $this->getResponse()->setBody(
                $this->getLayout()
                    ->createBlock($this->_successBlockType)
                    ->setOrder($this->_order)
                    ->toHtml()
            );
        } catch (Exception $e) {
			Mage::log('Enpayment: '.$e->getMessage());
            $this->caberrorAction();
        }
    }

    /**
     * Checking GET and SERVER variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedParams()
    {
        // get request variables
        $externalBDRID = $this->getRequest()->getParam('externalBDRID');
        $request = $this->getRequest()->getServer();

        if (!isset($request['HTTP_X_USERID']) || !isset($request['HTTP_X_PRICE']) || !isset($request['HTTP_X_CURRENCY']) || !isset($request['HTTP_X_TRANSACTION']) || !isset($request['HTTP_X_CONTENTID']) || !isset($request['HTTP_X_USERIP']))
            throw new Exception('Request doesn\'t contain all required C&B elements.', 10);

        // validate request ip coming from Enpayment proxy
        $helper = Mage::helper('core/http');
        if (method_exists($helper, 'getRemoteAddr')) {
            $remoteAddr = $helper->getRemoteAddr();
        } else {
            $request = $this->getRequest()->getServer();
            $remoteAddr = $request['REMOTE_ADDR'];
        }
        if (substr($remoteAddr,0,11) != '217.22.128.') {
            throw new Exception('IP can\'t be validated as Enpayment-IP.', 20);
        }

        // validate Enpayment user id
        if (empty($request['HTTP_X_USERID']) || is_nan($request['HTTP_X_USERID']))
            throw new Exception('Invalid Enpayment-UID.', 30);

        // check order id
		list($orderId) = explode('-', $externalBDRID, 2);
        if (empty($orderId) || strlen($orderId) > 50)
            throw new Exception('Missing or invalid order ID', 30);

        // load order for further validation
        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
		if (!$this->_order->getId())
			throw new Exception('Order ID not found.', 35);

        // check transaction amount and currency
		if ($this->_order->getPayment()->getMethodInstance()->getConfigData('use_store_currency')) {
        	$price      = number_format($this->_order->getGrandTotal()*100,0,'.','');
        	$currency   = $this->_order->getOrderCurrencyCode();
    	} else {
        	$price      = number_format($this->_order->getBaseGrandTotal()*100,0,'.','');
        	$currency   = $this->_order->getBaseCurrencyCode();
    	}

		if (intval($price) != intval($request['HTTP_X_PRICE']/1000))
			throw new Exception('Transaction amount doesn\'t match.', 40);
		if ($currency != $request['HTTP_X_CURRENCY'])
			throw new Exception('Transaction currency doesn\'t match.', 50);

        return $externalBDRID;
    }

    protected function _getPendingPaymentStatus()
    {
        return Mage::helper('enpayment')->getPendingPaymentStatus();
    }
}
