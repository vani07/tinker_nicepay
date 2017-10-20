<!-- by Tinkerlust -->

<?php 

/**
* 
*/
class Nicepay_VirtualAccount_Model_Observer 
{
	public function includes(){
		$libDir = Mage::getBaseDir('lib')."/";
		require_once($libDir."Nicepay_VirtualAccount/NicepayLib.php");
	}
	
	public function checkTransaction()
	{
		$this->includes();

		$nicepay = new NicepayLib();

		//add 7 jam
		$todayDate = date('Y-m-d H:i:s');
		$todayDate = date('Y-m-d H:i:s',strtotime($todayDate."+7hours"));
		

		$yesterdayDate =  date('Y-m-d H:i:s', strtotime($todayDate." -2 days"));

		
		$datean = date('Y-m-d',strtotime($yesterdayDate)).' 00:00:00';
		
		
		$order_collection = Mage::getModel('sales/order')->getCollection()
			->addAttributeToFilter('created_at', array('from'=> $datean, 'to'=> $todayDate))
            ->addAttributeToFilter('status', array('eq' => 'pending'));



	    foreach ($order_collection as $order) {
	    	$payment_code = $order->getPayment()->getMethodInstance()->getCode();
	    	if ($payment_code == 'virtualaccount') {

	    		$tXid = $order->getNicepayTransactionId();
	    		$amt = $order->getGrandTotal();
	    		$referenceNo = $order->getIncrementId();
	    		
	    		$paymentStatus = $nicepay->checkPaymentStatus($tXid, $referenceNo, $amt);
	    		
	    		//status expired
	    		if ($paymentStatus->status == '3') {
	    			
	    			$time = $paymentStatus->vacctValidDt.' '.$paymentStatus->vacctValidTm;
	    			$time = strtotime($time);
	    			$today_int = strtotime($todayDate);
	    			
	    			if ($time <= $today_int) {
	    				
		    			$order->setData('state', "canceled");
						$order->setStatus("canceled");
		    			$history = $order->addStatusHistoryComment('Order cancelled by cronjob.', false);
	    				$history->setIsCustomerNotified(true);
	    				
	    				try {
	    					
	    					$order->save();
							Mage::log('status:success success order_id:'.$referenceNo.' trans_id: '.$tXid,null,'cron.log');
	    					
	    				} catch (Exception $e) {
	    					
							Mage::log('status:error order_id:'.$referenceNo.' trans_id: '.$tXid,null,'cron.log');
	    				}
	    			}
	    			
							
	    		}

	    		//kalau statusnya cancel
	    		if ($paymentStatus->status == '4') {
		    			$order->setData('state', "canceled");
						$order->setStatus("canceled");
		    			$history = $order->addStatusHistoryComment('Order cancelled by cronjob.', false);
	    				$history->setIsCustomerNotified(true);
	    				
	    				try {
	    					
	    					$order->save();
							Mage::log('status:success success order_id:'.$referenceNo.' trans_id: '.$tXid,null,'cron.log');
	    					
	    				} catch (Exception $e) {
	    					
							Mage::log('status:error order_id:'.$referenceNo.' trans_id: '.$tXid,null,'cron.log');
	    				}
	    		}
	    		
	    		
	    	}
	    
	    }
	}
}


 ?>