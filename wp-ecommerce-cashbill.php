<?php
/*
 * Plugin Name: WP eCommerce Payment Gateway - CashBill
 * Plugin URL: http://cashbill.pl
 * Description: CashBill is easy to use electronic payment system. You can integrate our payment package with your website and offer customers secure payments.
 * Version: 1.0
 * Author: Łukasz Firek
 */
$nzshpcrt_gateways[$num]['name'] = 'CashBill';
$nzshpcrt_gateways[$num]['internalname'] = 'CashBill';
$nzshpcrt_gateways[$num]['function'] = 'cashbill_gateway';
$nzshpcrt_gateways[$num]['form'] = 'cashbill_form';
$nzshpcrt_gateways[$num]['submit_function'] = 'cashbill_form_submit';
$nzshpcrt_gateways[$num]['display_name'] = 'Płatności CashBill';

if (! get_option('cashbill_id') != '')
    update_option('cashbill_id', ' ');

if (! get_option('cashbill_key') != '')
    update_option('cashbill_key', ' ');

if (! get_option('cashbill_test') != '')
    update_option('cashbill_test', '1');

function cashbill_form()
{
    $id = get_option('cashbill_id');
    $key = get_option('cashbill_key');
    $test = get_option('cashbill_test');
    $logo = plugins_url('img/cashbill_100x39.png', __FILE__);
    
    $select = array(
        '0'=>'Produkcyjny',
        '1'=>'Testowy'
    );
    
    
    $output = '<tr><td colspan="2" style="text-align:center;"><br/><a href="http://cashbill.pl/" target="_blank"><img src="'.$logo.'"></a><br/><br/></td></tr>';
    
    $output .= '<tr><td colspan="2"><strong>Ustawienia Użytkownika</strong></td></tr>';
    $output .= '<tr><td><label for="cashbill_id">Identyfikator Punktu Płatności</label></td>';
    $output .= '<td><input name="cashbill_id" id="cashbill_id" type="text" value="' . $id . '"/><br/>';
    $output .= '<p class="description">Identyfikator nadany przy zakładaniu punktu płatności</p>';
    $output .= '</td></tr>';
    
    $output .= '<tr><td><label for="cashbill_id">Klucz Punktu Płatności</label></td>';
    $output .= '<td><input name="cashbill_key" id="cashbill_key" type="text" value="' . $key . '"/><br/>';
    $output .= '<p class="description">Klucz nadany przy zakładaniu punktu płatności</p>';
    $output .= '</td></tr>';
    $output .= '<tr><td><label for="cashbill_test">Tryb</label></td>';
    $output .= '<td>';
    $output .= '<select name="cashbill_test">';
    foreach ($select as $key => $value) {
        $output .= '<option value="' . $key . '"';
        if ($key == $test) {
            $output .= ' selected="selected"';
        }
        $output .= '>' . $value . '</option>';
    }
    
    $output .= '</select>';
    $output .= '<br/>';
    $output .= '<p class="description">Wybierz tryb działania punktu płatności</p>';
    $output .= '</td></tr>';
    
    $output .= '<td><a href="'.plugins_url( 'pdf/Instrukcja instalacji.pdf', __FILE__ ).'" target="_blank"><img src="'.plugins_url( 'img/pdf-icon.png', __FILE__ ).'" /> Instrukcja Instalacji</a><br/>';
    $output .= '</td>';

    return $output;  
}

function cashbill_form_submit()
{
    if ($_POST['cashbill_id'] != null) {
        update_option('cashbill_id', $_POST['cashbill_id']);
    }
    
    if ($_POST['cashbill_key'] != null) {
        update_option('cashbill_key', $_POST['cashbill_key']);
    }
    
    if ($_POST['cashbill_test'] == '1') {
        update_option('cashbill_test', '1');
    } else {
        update_option('cashbill_test', '0');
    }
    
    return true;
}


function cashbill_get_cancelurl($transaction_id, $session_id)
{
    $cancelurl = get_option('shopping_cart_url');

    $params = array('cashbill_cancel' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $cancelurl);
}

function cashbill_get_accepturl($transaction_id, $session_id)
{
    $accepturl = get_option('transact_url');

    $params = array('cashbill_accept' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $accepturl);
}

function cashbill_get_callbackurl($transaction_id, $session_id)
{
    $callbackurl = get_option('siteurl');

    $params = array('cashbill_callback' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $callbackurl);
}

function cashbill_gateway($seperator, $sessionid)
{
    global $wpdb, $wpsc_cart;
    
    if (get_option('cashbill_test')) {
        $restUrl = 'https://pay.cashbill.pl/testws/rest/';
    } else {
        $restUrl = 'https://pay.cashbill.pl/ws/rest/';
    }
    
    $ordernumber = 'WPEC' . $wpdb->get_var("SELECT id FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid = '$sessionid' LIMIT 1;");
    	
    if(strlen($ordernumber) > 20)
    {
        $ordernumber = time();
    }
    
    $amount	= round($wpsc_cart->total_price, 2) * 100;
    $transaction_id = uniqid(md5(rand(1, 666)), true); // Set the transaction id to a unique value for reference in the system.

    $wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed = '1', transactid = '" . $transaction_id . "', date = '" . time() . "' WHERE sessionid = " . $sessionid . " LIMIT 1");

    $purchase_log = $wpdb->get_row($wpdb->prepare("SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`= %s LIMIT 1",  $sessionid), ARRAY_A);
    
    $usersql = $wpdb->prepare("SELECT `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.value,
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.`name`,
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.`unique_name` FROM
	`" . WPSC_TABLE_CHECKOUT_FORMS . "` LEFT JOIN
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "` ON
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.id =
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`form_id` WHERE
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`log_id`=%s
	ORDER BY `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`checkout_order`", $purchase_log['id']);

    $userinfo = $wpdb->get_results($usersql, ARRAY_A);
    
    foreach ($userinfo as $key => $value) {   
        if (($value['unique_name'] == 'billingemail') && $value['value'] != '') {
            $email = $value['value'];
        }
    }
    
    $QueryOrder = array(
        'title'=>"Zamówienie numer : ".$ordernumber,
        'amount.value'=>number_format(($amount/100),2),
        'amount.currencyCode'=>"PLN",
        'returnUrl'=>cashbill_get_accepturl($transaction_id, $sessionid),
        'description'=>"Zamówienie numer : ".$ordernumber,
        'additionalData'=>$ordernumber.':'.$sessionid,
        'referer'=>'wpecomerce',
        'personalData.email'=>$email,
    );
    
    $sign = SHA1(implode("",$QueryOrder).get_option('cashbill_key'));
    $QueryOrder['sign'] = $sign;    
    
    $response = wp_remote_post( $restUrl.'payment/'.get_option('cashbill_id'), array(
        'method'    => 'POST',
        'timeout'   => 90,
        'body' => $QueryOrder,
        'sslverify' => false,
    ) );
    
    $response = json_decode($response['body']);
      
    header("location: {$response->redirectUrl}"); 
    exit();
}


function cashbill_callback() {
    global $wpdb;
    if(isset($_GET['cmd']) && isset($_GET['args']) && isset($_GET['sign']))
    {

        if(md5($_GET['cmd'].$_GET['args'].get_option('cashbill_key')) == $_GET['sign'])
        {
            if (get_option('cashbill_test')) {
                $restUrl = 'https://pay.cashbill.pl/testws/rest/';
            } else {
                $restUrl = 'https://pay.cashbill.pl/ws/rest/';
            }

            $signature = SHA1($_GET['args'].get_option('cashbill_key'));
            $response = wp_remote_get( $restUrl.'payment/'.get_option('cashbill_id').'/'.$_GET['args'].'?sign='.$signature);
            $response = json_decode($response['body']);

            list($ordernumber,$sessionid) = explode(":",$response->additionalData);
            
            $purchase_log = new WPSC_Purchase_Log($sessionid, 'sessionid');
            if ($response->status == 'PositiveFinish') {
                $purchase_log->set('processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT);
                $purchase_log->set('transactid', $ordernumber);
                $purchase_log->set('notes', 'Płatność na kwotę ' . $response->amount->value . ' ' . $response->amount->currencyCode . ' została przyjęta przez CashBill.');
                $purchase_log->save();
            }
            if ($response->status == 'Abort' || $response->status == 'Fraud' || $response->status == 'NegativeFinish') {
                $purchase_log->set('processed', WPSC_Purchase_Log::PAYMENT_DECLINED);
                $purchase_log->set('transactid', $ordernumber);
                $purchase_log->set('notes', 'Płatność nie została przyjęta przez CashBill system zwrócił status ' . $response->status);
                $purchase_log->save();
            }
            echo 'OK';
        }else
        {
            echo "BLAD SYGNATURY";
        }
        exit();
    }
     
}

add_action('init', 'cashbill_callback');

function add_admin_menu(){
    add_menu_page( 'Płatności CashBill', 'Płatności CashBill', 'manage_options','options-general.php?page=wpsc-settings&tab=gateway&payment_gateway_id=CashBill', '', plugins_url( 'img/cashbill_50x50.png', __FILE__ ), 56 );
}

add_action( 'admin_menu', 'add_admin_menu' );