<?php
/*
NICEPay Credit Card Controller
By: PT. NICEPay
www.nicepay.co.id
*/

class Nicepay_VirtualAccount_PaymentController extends Mage_Core_Controller_Front_Action{
	// The redirect action is triggered when someone places an order
	public function redirectAction() {
		$this->includes();

		$js_base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS);
		Mage::register('js_base_url',$js_base_url);

		$orderid = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderid);

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

		$email = $billing['email'];
		$billingEmail = $this->checkingAddrRule("email", $email);

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

		$email = $shipping['email'];
		$deliveryEmail = $this->checkingAddrRule("email", $email);

		$phone = $shipping['telephone'];
		$deliveryPhone = $this->checkingAddrRule("phone", $phone);

		$postCd = $shipping['postcode'];
		$deliveryPostCd = $this->checkingAddrRule("postCd", $postCd);

		$bankCd = $order->getPayment()->getData()["additional_information"]["bank_cd"];

		$nicepay = new NicepayLib();

		// Populate Mandatory parameters to send
		$dateNow        = date('Ymd');
  	$vaExpiryDate   = date('Ymd', strtotime($dateNow . ' +1 day')); // Set VA expiry date +1 day (optional)

		$nicepay->set('payMethod', '02');
		$nicepay->set('currency', $orderCurrency);
		$nicepay->set('cartData', json_encode($cartData));
		$nicepay->set('amt', $grandTotal); // Total gross amount //
		$nicepay->set('referenceNo', $orderid);
		$nicepay->set('description', 'Payment of invoice No '.$orderid); // Transaction description
		$nicepay->set('bankCd', $bankCd);

		//$nicepay->callBackUrl = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));
		$nicepay->dbProcessUrl = Mage::getUrl('virtualaccount/payment/callback', array('_secure' => true));
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

		$nicepay->set('vacctValidDt', $vaExpiryDate); // Set VA expiry date example: +1 day
    $nicepay->set('vacctValidTm', date('His')); // Set VA Expiry Time

		//running debug
		$nicepay_log["isi"] = $nicepay;
		$this->sent_log(json_encode($nicepay_log));

		// Send Data
		$response = $nicepay->requestVA();

		//running debug
		$nicepay_log["isi"] = $response;
		$this->sent_log(json_encode($nicepay_log));

		// Response from NICEPAY
		if(isset($response->resultCd) && $response->resultCd == "0000"){
			// please save tXid in your database
		    // echo "<pre>";
		    // echo "tXid              : $response->tXid\n";
		    // echo "API Type          : $response->apiType\n";
		    // echo "Request Date      : $response->requestDate\n";
		    // echo "Response Date     : $response->requestDate\n";
		    // echo "</pre>";

			$variable = array(
				"desc" => "Payment of invoice No ".$response->referenceNo,
				"amount" => "Rp. ".$grandTotal,
				"bank"=> $this->bank_info($bankCd)["label"],
				"bankContent" => $this->bank_info($bankCd)["content"],
				"va" => $response->bankVacctNo,
				"exp" => $vaExpiryDate,

			);
			
			// $this->sentManualPaymentEmail($order, $variable);

			Mage::register('desc', "Payment of invoice No ".$response->referenceNo);
			Mage::register('amount', $grandTotal);
			Mage::register('bank', $this->bank_info($bankCd)["label"]);
			Mage::register('bankCd', $bankCd);
			Mage::register('va', $response->bankVacctNo);
			Mage::register('expDate', $vaExpiryDate);
			$returnUrl = Mage::getUrl('virtualaccount/payment/success', array('_secure' => true));
			Mage::register('returnUrl', $returnUrl);
			
			//send email
			
			$bank_code_email = array('va'=> $response->bankVacctNo, 'bank_code' => $bankCd, 'bank_label' => $this->bank_info($bankCd)["label"]);
			$this->sentNewOrderEmail($order, $bank_code_email);

			$template = 'virtualaccount/redirect.phtml';
		}elseif(isset($response->resultCd)){
			// API data not correct or error happened in bank system, you can redirect back to checkout page or echo error message.
			// In this sample, we echo error message
			// header("Location: "."http://example.com/checkout.php");
			Mage::register('result_code', $response->resultCd);
			Mage::register('result_msg', $response->resultMsg);
			$template = 'virtualaccount/error.phtml';
		}else{
			// Timeout, you can redirect back to checkout page or echo error message.
			// In this sample, we echo error message
			// header("Location: "."http://example.com/checkout.php");
			Mage::register('connect_timeout', "Connection Timeout. Please Try again.");
			$template = 'virtualaccount/connect.phtml';
		}

		//running debug
		$nicepay_log["template"] = $template;
		$this->sent_log(json_encode($nicepay_log));

		$this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','virtualaccount',array('template' => $template));

		//running debug
		$nicepay_log["block"] = $block;
		$this->sent_log(json_encode($nicepay_log));

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
				$status = Mage::getConfig()->getNode('default/payment/virtualaccount')->payment_success_status;
				$order = Mage::getModel('sales/order')->loadByIncrementId($referenceNo);

				if($order->getStatus() != $status){
					$order->setData('state', $status);
					$order->setStatus($status);

					$history = $order->addStatusHistoryComment('Order was set to '.$status.' by NICEPay Bank Transfer Payment.', false);
					$history->setIsCustomerNotified(false);
					$order->save();

					$this->sentUpdateOrderEmail($order);
				}
			}else{
				// Flag the order as 'cancelled' and save it
				$order = Mage::getModel('sales/order')->loadByIncrementId($referenceNo);
				$order->setData('state', "canceled");
				$order->setStatus("canceled");

				$history = $order->addStatusHistoryComment('Order was set to Canceled by NICEPay Bank Transfer Payment.', false);
				$history->setIsCustomerNotified(false);
				$order->save();

				$this->sentUpdateOrderEmail($order);
			}
		}
	}

	public function successAction(){
		if(!isset($_REQUEST)){
			exit;
		}

		Mage::register('desc', addslashes($_REQUEST["desc"]));
		Mage::register('amount', addslashes($_REQUEST["amount"]));
		Mage::register('bank', addslashes($_REQUEST["bank"]));
		Mage::register('bankContent', $this->bank_info(addslashes($_REQUEST["bankCd"]))["content"]);
		Mage::register('va', addslashes($_REQUEST["va"]));
		Mage::register('expDate', addslashes($_REQUEST["expDate"]));
		

		$template = 'virtualaccount/success.phtml';


		
		$this->loadLayout();
		$orderid = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		$orderid = Mage::getModel('sales/order')->loadByIncrementId($orderid)->getId();
		
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','virtualaccount',array('template' => $template));
		Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($orderid)));

        //diubah menjadi 1 column
        $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');

		$this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
	}

	//tambahan info
	public function infoAction(){
			$this->loadLayout();
			$block = $this->getLayout()->createBlock('Mage_Core_Block_Template','virtualaccount',array('template' => 'virtualaccount/info.phtml'));
			$this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
			$this->getLayout()->getBlock('content')->append($block);
			$this->renderLayout();
	}

	public function includes(){
		$libDir = Mage::getBaseDir('lib')."/";
		require_once($libDir."Nicepay_VirtualAccount/NicepayLib.php");
	}

	public function bank_info($bankCd){
		$bank = array(
		  "BMRI" => array(
				  "label" => "Mandiri",
				  "content" => '<b>ATM Mandiri</b>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Pilih menu Bayar/Beli</li>
								<li>Pilih menu Lainnya</li>
								<li>Pilih menu Multi Payment</li>
								<li>Masukkan "70014" sebagai Kode Perusahaan / Institusi, kemudian pilih Benar</li>
								<li>Masukkan Transferpay Kode Bayar dengan Virtual Account yang sudah didapatkan</li>
								<li>Pilih YA setelah muncul konfirmasi pembayaran</li>
								<li>Periksa kembali Nominal Pembayaran Anda pada halaman Konfirmasi Pembayaran, kemudian pilih YA</li>
								<li>Ambil bukti pembayaran Anda dan Transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<b>Mobile Banking</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Mobile Banking</li>
								<li>Pilih menu Bayar</li>
								<li>Pilih menu Lainnya</li>
								<li>Input Transferpay sebagai penyedia jasa</li>
								<li>Input Nomor Virtual Account, misal : 70014XXXXXXXXXXX</li>
								<li>Pilih Lanjut</li>
								<li>Input OTP dan PIN</li>
								<li>Pilih OK</li>
								<li>Ambil bukti pembayaran Anda dan Transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<b>Internet Banking</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Internet Banking</li>
								<li>Pilih menu Bayar</li>
								<li>Pilih menu Multi Payment</li>
								<li>Input Transferpay sebagai penyedia jasa</li>
								<li>Input Nomor Virtual Account, misal : 70014XXXXXXXXXXX sebagai Kode Bayar</li>
								<li>Ceklis IDR</li>
								<li>Klik Lanjutkan</li>
								<li>Bukti bayar ditampilkan</li>
								<li>Transaksi selesai</li>
						  </ul>
						</div>
						<small>*1 (Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
			),
		  "BBBA" => array(
				  "label" => "Permata Bank",
				  "content" => '<b>ATM Permata Bank</b>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Masukkan Nomor PIN</li>
								<li>Pilih menu TRANSAKSI LAINNYA</li>
								<li>Pilih menu PEMBAYARAN</li>
								<li>Pilih Pembayaran Lain-Lain</li>
								<li>Pilih VIRTUAL ACCOUNT</li>
								<li>Masukkan 16 digit kode bayar (virtual account)<br/>Contoh : 8625xxxxx</li>
								<li>Pada layar akan tampil konfirmasi pembayaran</li>
								<li>Pilih BENAR untuk konfirmasi pembayaran</li>
								<li>Pilih YA agar struk / bukti transaksi keluar</li>
								<li>Transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<b>Mobile Banking / Internet Banking</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Mobile / Internet Banking</li>
								<li>Pilih menu "Pembayaran Tagihan"</li>
								<li>Pilih menu "Virtual Account"</li>
								<li>Masukkan 16 digit kode bayar (virtual account)<br/>Misal : 8625xxxxx</li>
								<li>Masukkan Nominal Pembayaran</li>
								<li>Klik Kirim dan Input otentikasi transaksi / Token</li>
								<li>Klik Kirim</li>
								<li>Bukti Pembayaran Anda akan ditampilkan dan transaksi selesai</li>
						  </ul>
						</div>
						<small>*1 (Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
				),
		"IBBK" => array(
			  "label" => "Maybank BII",
			  "content" => '<b>ATM</b>
					<div style="border:1px solid #cccccc; padding:10px 20px;">
					  <ul style="list-style-type: disc">
							<li>Pilih menu "Pembayaran / TOP UP Pulsa"</li>
							<li>Pilih jenis transaksi "Virtual Account"</li>
							<li>Masukkan Nomor Virtual Account misal : 7812XXXXXXXXXXXX, kemudian pilih "BENAR"</li>
							<li>Periksa kembali Nominal Pembayaran Anda pada halaman Konfirmasi Pembayaran</li>
							<li>Kemudian Pilih "YA"</li>
							<li>Ambil bukti pembayaran Anda dan Transaksi selesai</li>
					  </ul>
					</div>
					<br />
					<b>SMS Banking</b>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						<ul style="list-style-type: disc">
							<li>SMS ke 69811</li>
							<li>Ketik TRANSFER (Nomor Virtual Account) (Nominal), Contoh : TRANSFER 7812XXXXXX 10000</li>
							<li>Kirim SMS</li>
							<li>Anda akan mendapat balasan <br> Transfer dr rek (nomor rekening anda) ke rek (nomor virtual account) sebesar Rp 10.000 <br> Ketik (karakter acak)</li>
							<li>Balas SMS tersebut, ketik (karakter acak)</li>
							<li>Kirim SMS</li>
							<li>Bukti pembayaran Anda akan ditampilkan dan Transaksi selesai</li>
						</ul>
					</div>
					<br />
					<b>Internet Banking</b>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						<ul style="list-style-type: disc">
							<li>Login Internet Banking</li>
							<li>Pilih Menu Rekening dan Transaksi</li>
							<li>Pilih menu Maybank Virtual Account</li>
							<li>Pilih sumber tabungan</li>
							<li>Masukkan nomor Virtual Account, misal : 7812XXXXXX</li>
							<li>Masukkan jumlah uang yang akan ditransfer, misal : 10000</li>
							<li>Klik Submit</li>
							<li>Layar konfirmasi akan muncul, dan masukkan sms token</li>
							<li>Bukti Pembayaran Anda akan ditampilkan dan transaksi selesai</li>
						</ul>
					</div>
					<small>*1(Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
			),
		"BNIN" => array(
				  "label" => "BNI & Bank Lainnya",
				  "content" => '<b>via ATM BNI</b>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Pilih menu TRANSFER</li>
								<li>Pilih menu Lain</li>
								<li>Pilih KE REKENING BNI</li>
								<li>Masukkan nominal yang akan ditransfer, misal : 10000</li>
								<li>Masukkan nomor virtual account, misal : 8848XXXXXXXXXXXX</li>
								<li>Pilih "YA" untuk konfirmasi pembayaran</li>
								<li>Ambil bukti pembayaran Anda dan transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<b>via ATM Lainnya</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Pilih Menu Transfer pada masing - masing ATM sesuai bank yang digunakan</li>
								<li>Pilih Bank Lainnya</li>
								<li>Input Kode Bank, <b>009</b></li>
								<li>Input nomor virtual account Number, misal. 8848XXXXXXXXXXXXX</li>
								<li>Input nominal pembayaran, misal. 10000</li>
								<li>Pilih Benar</li>
								<li>Pilih Ya</li>
								<li>Ambil bukti pembayaran Anda dan Transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<b>via Mobile Banking BNI</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Mobile Banking</li>
								<li>Pilih Transfer</li>
								<li>Pilih Antar Rekening BNI</li>
								<li>Pilih Rekening Tujuan</li>
								<li>Pilih Input Rekening Baru</li>
								<li>Masukkan Nomor Virtual Account sebagai Nomor Rekening misal, 8848XXXXXXXXXXXX</li>
								<li>Klik Lanjut</li>
								<li>Klik Lanjut Kembali</li>
								<li>Masukkan Nominal Tagihan. misal, 10000</li>
								<li>Klik Lanjut</li>
								<li>Periksa Detail Konfirmasi. Pastikan Data Sudah Benar</li>
								<li>Jika Sudah Benar, Masukkan Password Transaksi</li>
								<li>Klik Lanjut</li>
								<li>Bukti Pembayaran Anda akan ditampilkan dan transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<b>via Internet Banking BNI</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Internet Banking</li>
								<li>Pilih Transaksi</li>
								<li>Pilih Info dan Administrasi</li>
								<li>Pilih Atur Rekening Tujuan</li>
								<li>Pilih Tambah Rekening Tujuan</li>
								<li>Klik Ok</li>
								<li>Masukkan Nomor Order Sebagai Nama Singkat, misal : Invoice-1234</li>
								<li>Masukkan Nomor Virtual Account Sebagai Nomor Rekening, misal : 8848XXXXXXXXXXXX</li>
								<li>Lengkapi Semua Data Yang Diperlukan</li>
								<li>Klik Lanjutkan</li>
								<li>Masukkan Kode Otentikasi Token lalu Proses Rekening Tujuan Berhasil Ditambahkan</li>
								<li>Pilih menu Transfer</li>
								<li>Pilih Transfer Antar Rek. BNI</li>
								<li>Pilih Rekening Tujuan dengan Nama Singkat Yang Sudah Anda Tambahkan. misal : Invoice-1234</li>
								<li>Masukkan Nominal. misal : 10000</li>
								<li>Masukkan Kode Otentikasi Token</li>
								<li>Bukti Pembayaran Anda akan ditampilkan dan transaksi selesai</li>
						  </ul>
						</div>
						<br/>
						<b>via Mobile Banking, SMS Banking & Internet Banking Bank Lain</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
							<ul style="list-style-type: disc">
								<li>Lakukan seperti anda mentransfer ke Bank lain pada umumnya</li>
								<li>Input <b>009</b> sebagai Kode Bank</li>
								<li>Input Nomor Virtual Account sebagai Nomor Rekening</li>
								<li>Input Nominal yang ditagihkan sebagai Nominal Transfer, kemudian klik OK</li>
								<li>Bukti pembayaran Anda akan ditampilkan dan Transaksi selesai</li>
							</ul>
						</div>
						<small>*1(Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
				),
		  "CENA" => array(
				  "label" => "BCA",
				  "content" => '<b>ATM</b>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Pilih Menu Transaksi Lainnya</li>
								<li>Pilih Transfer</li>
								<li>Pilih Ke rekening BCA Virtual Account</li>
								<li>Input Nomor Virtual Account, misal. 123456789012XXXX, kemudian Pilih BENAR</li>
								<li>Ambil bukti pembayaran Anda dan Transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<b>Mobile Banking</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Mobile Banking</li>
								<li>Pilih m-Transfer</li>
								<li>Pilih BCA Virtual Account</li>
								<li>Input Nomor Virtual Account, misal. 123456789012XXXX sebagai No. Virtual Account</li>
								<li>Klik Send</li>
								<li>Informasi VA akan ditampilkan, kemudian Klik OK</li>
								<li>Input PIN Mobile Banking</li>
								<li>Bukti pembayaran Anda akan ditampilkan dan Transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<b>Internet Banking</b>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Internet Banking</li>
								<li>Pilih Transaksi Dana</li>
								<li>Pilih Transfer Ke BCA Virtual Account</li>
								<li>Input Nomor Virtual Account, misal. 123456789012XXXX sebagai No. Virtual Account</li>
								<li>Klik Lanjutkan</li>
								<li>Input Respon KeyBCA Appli 1, kemudian Klik Kirim</li>
								<li>Bukti pembayaran Anda akan ditampilkan dan Transaksi selesai</li>
						  </ul>
						</div>
						<small>*1(Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
				),
			"BNIA" => array(
					"label" => "CIMB Niaga",
					"content" => '<strong id="h4thanks">ATM CIMB Niaga</strong>
						<div style="border:1px solid #cccccc;padding:10px 20px 0;">
							<ul style="list-style-type: disc">
							<li>Pilih Menu Pembayaran</li>
							<li>Pilih Lanjut</li>
							<li>Pilih Virtual Account</li>
							<li>Masukkan Nomor Virtual Account, misal. 5919XXXXXXXXXXX, kemudian Pilih Proses</li>
							<li>Data Virtual Account akan ditampilkan, kemudian Pilih Proses</li>
							<li>Ambil bukti pembayaran Anda dan transaksi selesai</li>
							</ul>
						</div>
						<br><br><strong id="h4thanks">Mobile Banking</strong>
						<div style="border:1px solid #cccccc;padding:10px 20px 0;">
							<ul style="list-style-type: disc">
							<li>Login Go Mobile</li>
							<li>Pilih Menu Transfer</li>
							<li>Pilih Menu Rekening Ponsel/CIMB Niaga lain</li>
							<li>Pilih Sumber dana yang akan digunakan</li>
							<li>Pilih Casa</li>
							<li>Masukkan Nomor Virtual Account, misal. 5919XXXXXXXXXXX</li>
							<li>Masukkan Nominal misal. Rp10.000,00.-, Klik Lanjut</li>
							<li>Data Virtual Account akan ditampilkan</li>
							<li>Masukkan PIN Mobile, Klik Konfirmasi</li>
							<li>Bukti pembayaran akan dikirimkan melalui sms dan transaksi selesai</li>
							</ul>
						</div>
						<br><br><strong id="h4thanks">Internet Banking</strong>
						<div style="border:1px solid #cccccc;padding:10px 20px 0;">
							<ul style="list-style-type: disc">
							<li>Login Internet Banking</li>
							<li>Rekening Sumber – Pilih yang akan anda gunakan</li>
							<li>Jenis Pembayaran – Pilih Virtual Account</li>
							<li>Untuk Pembayaran – Pilih Masukkan Nomor Virtual Account</li>
							<li>Nomor Rekening Virtual, misal. 5919XXXXXXXXXXX</li>
							<li>Isi Remark jika diperlukan, kemudian klik Lanjut</li>
							<li>Data Virtual Account akan ditampilkan</li>
							<li>Masukkan mPIN, Klik Kirim</li>
							<li>Bukti pembayaran akan ditampilkan dan transaksi selesai</li>
							</ul>
						</div>
						<small>*1(Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
				),
		  "HNBN" => array(
				  "label" => "KEB Hana Bank",
				  "content" => '<b>ATM KEB Hana</b>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Pilih menu "Pembayaran"</li>
								<li>Pilih menu "Lainnya"</li>
								<li>Masukkan Nomor Virtual Account, misal 9772XXXXXXXXXXXX</li>
								<li>Pilih "BENAR"</li>
								<li>Pilih "YA" untuk konfirmasi pembayaran</li>
								<li>Ambil bukti pembayaran Anda dan transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<p>Internet Banking</p>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Internet Banking</li>
								<li>Pilih menu "Transfer"</li>
								<li>Pilih "Withdrawal Account Information"</li>
								<li>Pilih Account Number Anda</li>
								<li>Masukkan nomor virtual account <br/>Misal : 9772XXXXXXXXXXXX</li>
								<li>Masukkan Nominal Pembayaran, misal : 10000</li>
								<li>Klik Submit</li>
								<li>Input SMS Pin</li>
								<li>Bukti transaksi akan ditampilkan</li>
								<li>Transaksi selesai</li>
						  </ul>
						</div>
						<small>*1(Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
				),
		  "BRIN" => array(
				  "label" => "BRI",
				  "content" => '<p>ATM</p>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
							<li>Pilih Menu Transaksi Lain</li>
							<li>Pilih Menu Pembayaran</li>
							<li>Pilih Menu Lain-lain</li>
							<li>Pilih Menu BRIVA</li>
							<li>Masukkan Nomor Virtual Account, misal. 88788XXXXXXXXXXX, kemudian Pilih YA</li>
							<li>Ambil bukti pembayaran Anda dan transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<p>Mobile Banking</p>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login BRI Mobile</li>
								<li>Pilih Mobile Banking BRI</li>
								<li>Pilih Menu Info</li>
								<li>Pilih Menu BRIVA</li>
								<li>Masukkan Nomor Virtual Account, misal. 88788XXXXXXXXXXX</li>
								<li>Masukkan Nominal misal. 10000, Klik Kirim</li>
								<li>Masukkan PIN Mobile, Klik Kirim</li>
								<li>Bukti pembayaran akan dikirimkan melalui sms dan transaksi selesai</li>
						  </ul>
						</div>
						<br />
						<p>Internet Banking</p>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login Internet Banking</li>
								<li>Pilih Pembayaran</li>
								<li>Pilih BRIVA</li>
								<li>Masukkan Nomor Virtual Account, misal. 88788XXXXXXXXXXX, Klik Kirim</li>
								<li>Masukkan Password dan mToken, Klik Kirim</li>
								<li>Bukti pembayaran akan ditampilkan dan transaksi selesai</li>
						  </ul>
						</div>
						<small>*1(Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
				),
		  "BDIN" => array(
				  "label" => "Danamon",
				  "content" => '<p>ATM</p>
						<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Pilih Menu Pembayaran</li>
								<li>Pilih Lain-lain</li>
								<li>Pilih Menu Virtual Account</li>
								<li>Masukkan Nomor Virtual Account, misal. 7915XXXXXXXXXXXX, kemudian Pilih Benar</li>
								<li>Data Virtual Account akan ditampilkan, kemudian Pilih Ya</li>
								<li>Ambil bukti pembayaran Anda dan transaksi selesai</li>
						  </ul>
						</div>
						<br /><p>Mobile Banking</p>
							<div style="border:1px solid #cccccc; padding:10px 20px;">
						  <ul style="list-style-type: disc">
								<li>Login D-Mobile</li>
								<li>Pilih Menu Pembayaran</li>
								<li>Pilih Menu Virtual Account</li>
								<li>Pilih Tambah Biller Baru Pembayaran</li>
								<li>Tekan Lanjut</li>
								<li>Masukkan Nomor Virtual Account, misal. 7915XXXXXXXXXXXX</li>
								<li>Tekan Ajukan</li>
								<li>Data Virtual Account akan ditampilkan</li>
								<li>Masukkan mPIN, Pilih Konfirmasi</li>
								<li>Bukti pembayaran akan dikirimkan melalui sms dan transaksi selesai</li>
						  </ul>
						</div>
						<small>*1(Satu) Nomor Virtual Account hanya berlaku untuk 1 (Satu) Nomor Pesanan</small>'
				)
		);

		return $bank[$bankCd];
	}

	public function sentNewOrderEmail($order,$extVariable){
		// This is the template name from your etc/config.xml
		// $template_id = 'nicepay_order_new_email';

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

		//custom sent email

		$storeId = Mage::app()->getStore()->getId();
		$templateId = Mage::getStoreConfig('sales_email/order/template',$storeId);
		
		$sender_name = Mage::getStoreConfig('trans_email/ident_general/name', $storeId);
		$sender_email = Mage::getStoreConfig('trans_email/ident_general/email', $storeId);
		$method = $order->getPayment()->getMethod();
		$payment = $order->getPayment();
		$payment->getData($method);
		$payment_name = $payment->getMethodInstance()->getTitle();

	
		$vars = array(
			'order' => $order,
			'store_name' => $sender_name,
			'store_email' => $sender_email,
			'payment_html' => $payment_name,
			'nicepay_req' => $extVariable,
		);

		$receiveEmail = $order->getCustomerEmail();

		$receiveName = ucfirst($order->getCustomerFirstname());

		$emailTemplate = Mage::getModel('core/email_template')->load($templateId);
			$emailTemplate->getProcessedTemplate($vars);

		$emailTemplate->setSenderEmail($sender_email);

		$emailTemplate->setSenderName($sender_name);

		try {
			
			$emailTemplate->send($receiveEmail,$receiveName, $vars);
			
		} catch (Exception $e) {
			
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

	// public function sentManualPaymentEmail($order, $extVariable){
		// This is the template name from your etc/config.xml

		//***********************************************
		//              || WARNING ||
		//***********************************************
		// settingan email manual ada pada new order by guest di config

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
		$receiveName  = $billingNm;

		$storeId = Mage::app()->getStore()->getId();
		$templateId = Mage::getStoreConfig('sales_email/order/guest_template',$storeId);
		
		$sender_name = Mage::getStoreConfig('trans_email/ident_general/name', $storeId);
		$sender_email = Mage::getStoreConfig('trans_email/ident_general/email', $storeId);

		// Load our template by template_id
		$emailTemplate = Mage::getModel('core/email_template')->load($templateId);

		$orderId = $order->getIncrementId();
		// Here is where we can define custom variables to go in our email template!
		
		$storeId = Mage::app()->getStore()->getId();
		$sender_name = Mage::getStoreConfig('trans_email/ident_general/name', $storeId);
		$sender_email = Mage::getStoreConfig('trans_email/ident_general/email', $storeId);
		$vars = array(
			'order' => $order,
			'store_name' => $sender_name,
			'store_email' => $sender_email,
		);

		if(is_array($extVariable)){
			$vars = array_merge($vars, $extVariable);
		}

		$emailTemplate->getProcessedTemplate($vars);

		$emailTemplate->setSenderEmail($sender_email);

		$emailTemplate->setSenderName($sender_name);
		
		$emailTemplate->setSubject('Manual Payment #'.$orderId);
		

		try {
			
			$emailTemplate->send($receiveEmail,$receiveName, $vars);
			
		} catch (Exception $e) {
			
			// Mage::logException($e);
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
			"email" => (object) array(
				"type" => "string",
				"length" => 40,
				"defaultValue" => "dummy"
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
		$debugMode = Mage::getConfig()->getNode('default/payment/virtualaccount')->nicepay_debug;
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
