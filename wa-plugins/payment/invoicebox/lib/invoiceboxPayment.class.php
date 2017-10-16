<?php

class invoiceboxPayment extends waPayment implements waIPayment
{
    private $order_id;
    
    public function allowedCurrency()
    {
        return array('RUB');
    }

    /**
     * @see waIPayment::payment()
     */
    public function payment($payment_form_data, $order_data,  $auto_submit = false)
    {
		if ($auto_submit && $this->testmode) {
            $auto_submit = false;
        }
        $order = waOrder::factory($order_data);
		
		
        $hidden_fields = array();
		$invoicebox_api_key = $this->invoicebox_api_key;
        $hidden_fields['itransfer_participant_id'] = $this->invoicebox_participant_id;
        $hidden_fields['itransfer_participant_ident'] = $this->invoicebox_participant_ident;
		if (!empty($this->testmode))	$hidden_fields['itransfer_testmode'] =  '1';
	    $hidden_fields['itransfer_order_id'] = $this->app_id . '_' . $this->merchant_id . '_' . $order->id;
		$hidden_fields['itransfer_order_currency_ident'] = $order->currency;
		$hidden_fields['itransfer_order_amount'] = number_format($order->total, 2, '.', '');
		$contact = new waContact((int) $order->contact_id);
        $hidden_fields['itransfer_person_email'] = $contact->get('email')[0]['value'];
        $hidden_fields['itransfer_person_phone'] = $contact->get('phone')[0]['value'];
        $hidden_fields['itransfer_person_name'] = $contact->get('firstname').' '.$contact->get('lastname');
        $hidden_fields['itransfer_body_type'] = "PRIVATE";
        $hidden_fields['itransfer_order_description'] = $order->description;
        $transaction_data = array(
            'order_id' => $order->id,
        );
		
        $hidden_fields['itransfer_url_return'] = $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, $transaction_data);
        $hidden_fields['itransfer_url_notify'] = 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').'/payments.php/invoicebox/';
		$hidden_fields['itransfer_cms_name'] = 'WebAssist';
		$signatureValue = md5(
			$this->invoicebox_participant_id.
			$order->id.
			$order->total.
			$order->currency.
			$invoicebox_api_key
			); 
        $hidden_fields['itransfer_participant_sign'] = $signatureValue;
		$i=0;

		foreach ($order->items as $product) { 
			$i++;
			$hidden_fields['itransfer_item'.$i.'_name'] = $product['name'];
			$hidden_fields['itransfer_item'.$i.'_quantity'] = $product['quantity'];
			$hidden_fields['itransfer_item'.$i.'_price'] = number_format($product['price'] - ifset($product['discount'], 0.0), 2, '.', '');
			$hidden_fields['itransfer_item'.$i.'_vatrate'] = $this->getTaxId($product);
			$hidden_fields['itransfer_item'.$i.'_measure'] = "шт.";
		}
		
		if($order->shipping>0){
			$i++;
			$hidden_fields['itransfer_item'.$i.'_name'] = mb_substr($order->shipping_name, 0, 128);
			$hidden_fields['itransfer_item'.$i.'_quantity'] = 1;
			$hidden_fields['itransfer_item'.$i.'_price'] = number_format($order->shipping, 2, '.', '');
			$hidden_fields['itransfer_item'.$i.'_measure'] = "шт.";
		}
        $view = wa()->getView();
        $view->assign('form_url', $this->getEndpointUrl());
        $view->assign('hidden_fields', $hidden_fields);
		$view->assign('auto_submit', $auto_submit);
        return $view->fetch($this->path.'/templates/payment.html');
    }

    private function getTaxId($item)
    {
        $id = 1;
        switch ($this->taxes) {
            case 'no':
                # 1 — без НДС;
                $id = 1;
                break;
            case 'map':
                $rate = ifset($item['tax_rate']);
                if (in_array($rate, array(null, false, ''), true)) {
                    $rate = -1;
                }
                switch ($rate) {
                    case 18: # 4 — НДС чека по ставке 18%;
                        $id = 4;
                        break;
                    case 10: # 3 — НДС чека по ставке 10%;
                        $id = 3;
                        break;
                    case 0: # 2 — НДС по ставке 0%;
                        $id = 2;
                        break;
                    default: # 1 — без НДС;
                        $id = 1;
                        break;
                }
                break;
        }
        return $id;
    }


    private function getEndpointUrl()
    {
        return 'https://go.invoicebox.ru/module_inbox_auto.u';
    }

    protected function callbackInit($request)
    {
        $pattern = '/^([a-z]+)_(\d+)_(.+)$/';
        if (!empty($request['participantOrderId']) && preg_match($pattern, $request['participantOrderId'], $match)) {
            $this->app_id = $match[1];
            $this->merchant_id = $match[2];
            $this->order_id = $match[3];
        }
        return parent::callbackInit($request);

    }

    protected function callbackHandler($request)
    {

	$participantId=trim($request["participantId"]);
    $participantOrderId=trim($request["participantOrderId"]);
    $ucode 		= trim($request["ucode"]);
	$timetype 	= trim($request["timetype"]);
	$time 		= str_replace(' ','+',trim($request["time"]));
	$amount 	= trim($request["amount"]);
	$currency 	= trim($request["currency"]);
	$agentName 	= trim(html_entity_decode($request["agentName"], ENT_QUOTES, 'UTF-8'));
	$agentPointName = trim(html_entity_decode($request["agentPointName"], ENT_QUOTES, 'UTF-8'));
	$testMode 	= trim($request["testMode"]);
	$sign	 	= trim($request["sign"]);
	$invoicebox_api_key = $this->invoicebox_api_key;
	$sign_strA = 
			$participantId .
			$participantOrderId .
			$ucode .
			$timetype .
			$time .
			$amount .
			$currency .
			$agentName .
			$agentPointName .
			$testMode .
			$invoicebox_api_key;
		$hash = md5( $sign_strA ); 
	
       require_once __DIR__.'/../../../../wa-apps/shop/lib/model/shopOrder.model.php';
        $order_model = new shopOrderModel();
        $order_id = $this->order_id;
        $order = $order_model->getById( $order_id );
        if (empty($request['sign']) || empty($hash) || ($request['sign'] != $hash)) {
            $result = array('error' =>
                array('message' => 'Invalid request sign')
            );
        }elseif (is_null($order_id)){
            $result = array('error' =>
                array('message' => 'заказа не существует')
            );
        }elseif ((float)round($order['total'],2) != (float)$amount) {
            $result = array('error' =>
                array('message' => 'не совпадает сумма заказа')
            );
        }
        else{
            $update_order = [];
            $update_order['state_id'] = 'paid';
            $update_order = array_merge($update_order, [
                'paid_date' => date('Y-m-d'),
                'paid_year' => date('Y'),
                'paid_quarter' => floor((date('m') - 1) / 3) + 1,
                'paid_month' => (int)date('m'),
            ]);
            $order_model->updateById($order_id, $update_order);
            $logs[] = array(
                'order_id'        => $order_id,
                'action_id'       => 'pay',
                'before_state_id' => $order['state_id'],
                'after_state_id'  => $update_order['state_id'],
                'text'            => '',
//                'params'          => array('merged_order_id' => $master_id),
            );
            #add log records
            require_once __DIR__.'/../../../../wa-apps/shop/lib/model/shopOrderLog.model.php';
            $log_model = new shopOrderLogModel();
            foreach ($logs as $log) {
                $log_model->add($log);
            }
            $result = array('result' =>
                array('message' => 'Запрос успешно обработан')
            );
        }
        

		$this->hardReturnJson($result);
		return;
		
        
    }
	
	protected function hardReturnJson( $arr )
    {
        header('Content-Type: application/json');
        $result = json_encode($arr);
        die($result);
    }
}
