<?php
class Nicepay_CreditCard_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'creditcard';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('creditcard/payment/redirect', array('_secure' => true));
	}
}
?>