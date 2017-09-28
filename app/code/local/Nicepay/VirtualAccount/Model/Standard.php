<?php
class Nicepay_VirtualAccount_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'virtualaccount';

	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	protected $_canSaveCc   						= true;
    protected $_formBlockType 				= 'virtualaccount/form';

	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('virtualaccount/payment/redirect', array('_secure' => true));
	}
}
?>
