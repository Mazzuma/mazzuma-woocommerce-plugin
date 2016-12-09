<?php
/*
Plugin Name: WooCommerce Mazzuma Payment Gateway
Plugin URI: https:///mazzuma.teamcyst.com
Description: Mazzuma Payment gateway for woocommerce
Version: 1.0
Author: CYST Company Limited
Author URI: https://mazzuma.teamcyst.com
*/
add_action('plugins_loaded', 'woocommerce_cyst_mazzuma_init', 0);
function woocommerce_cyst_mazzuma_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_Cyst_Mazzuma extends WC_Payment_Gateway{
    public function __construct(){
      $this -> id = 'mazzuma';
      $this -> medthod_title = 'Mazzuma';
      $this -> has_fields = false;
      $this -> icon = plugins_url()."/mazzuma/mazzuma-pay.png";

      $this -> init_form_fields();
      $this -> init_settings();

      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      $this -> api_key = $this -> settings['api_key'];
      $this -> salt = $this -> settings['salt'];
      $this -> redirect_page_id = $this -> settings['redirect_page_id'];
      $this -> liveurl = 'https://secure.teamcyst.com/api_call.php';

      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

      add_action('init', array(&$this, 'check_mazzuma_response'));
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
      add_action('woocommerce_receipt_mazzuma', array(&$this, 'receipt_page'));
   }
    function init_form_fields(){

       $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'cyst'),
                    'type' => 'checkbox',
                    'label' => __('Enable Mazzuma Payment Module.', 'cyst'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'cyst'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'cyst'),
                    'default' => __('Mazzuma', 'cyst')),
                'description' => array(
                    'title' => __('Description:', 'cyst'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'cyst'),
                    'default' => __('Pay online using your mobile money account.', 'cyst')),
                'api_key' => array(
                    'title' => __('API Key', 'cyst'),
                    'type' => 'text',
                    'description' => __('This is the API Key generated at the Mazzuma Dashboard."')),

                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of success page"
                )
            );
    }

       public function admin_options(){
        echo '<h3>'.__('Mazzuma Payment Gateway', 'cyst').'</h3>';
        echo '<p>'.__('Receive payments mobile money payments online using Mazzuma').'</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this -> generate_settings_html();
        echo '</table>';

    }


    function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }

    function receipt_page($order){
        echo '<p>'.__('Thank you for your order, please click the button below to pay with Mazzuma.', 'cyst').'</p>';
        echo $this -> generate_mazzuma_form($order);
    }

    public function generate_mazzuma_form($order_id){

       global $woocommerce;
    	$order = new WC_Order( $order_id );
        $txnid = $order_id.'_'.date("ymds");

        $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);

        $productinfo = "Order $order_id";

        $str = "$this->merchant_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->salt";
        $hash = hash('sha512', $str);

        $mazzuma_args = array(
          'key' => $this -> api_key,
          'txnid' => $txnid,
          'price' => $order -> order_total,
          'productinfo' => $productinfo,
          'firstname' => $order -> billing_first_name,
          'lastname' => $order -> billing_last_name,
          'address1' => $order -> billing_address_1,
          'address2' => $order -> billing_address_2,
          'city' => $order -> billing_city,
          'state' => $order -> billing_state,
          'country' => $order -> billing_country,
          'zipcode' => $order -> billing_zip,
          'email' => $order -> billing_email,
          'phone' => $order -> billing_phone,
          'surl' => $redirect_url,
          'furl' => $redirect_url,
          'curl' => $redirect_url,
          'hash' => $hash,
          'pg' => 'NB',
          'orderID' => $order_id,
          'success_url' => $redirect_url
          );


          $options['orderID']          = $order_id;
          $options['price']            = $total;
          $options['success_url']       =$redirect_url;

        $mazzuma_args_array = array();

        foreach($mazzuma_args as $o) {
          if (array_key_exists($o, $options))
            $post[$o] = $options[$o];
        }

        $crypt_key = $this -> api_key;

        if(function_exists('json_encode'))
          $post = json_encode($post);
        else
          $post = rmJSONencode($post);


          $publicHash = substr($crypt_key, 0, strlen($crypt_key)/2);
          $privateHash = substr($crypt_key,strlen($crypt_key)/2, strlen($crypt_key));
          $hash = hash_hmac('sha256', $post, $privateHash);

          $headers = array(
              'Api-Auth-Token: '.$publicHash,
              'Data-Token: '.$hash
          );

          $ch = curl_init('https://secure.teamcyst.com/api_call.php');
          curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
          curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
          curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query(array("data"=>$post)));

          $responseString = curl_exec($ch);

          if(!$responseString) {
            $response = curl_error($ch);

            die(Tools::displayError("Error: no data returned from API server!"));

          } else {

            if(function_exists('json_decode'))
              $response = json_decode($responseString, true);
            else
              $response = rmJSONdecode($responseString);
          }

          curl_close($ch);

          if(isset($response['error'])) {
            mzlog($response['error']);
            die(Tools::displayError("Error occurred! (" . $response['error']['type'] . " - " . $response['error']['message'] . ")"));
          } else if(!$response['url']) {
            die(Tools::displayError("Error: Response did not include invoice url!"));
          } else {
            header('Location: https://secure.teamcyst.com/index.php?token=' . $response['url']);
          }

//

    }
    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
        global $woocommerce;
    	$order = new WC_Order( $order_id );
        return array('result' => 'success', 'redirect' => add_query_arg('order',
            $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
        );
    }

    /**
     * Check for valid mazzuma server callback
     **/
    function check_mazzuma_response(){
        global $woocommerce;
        if(isset($_REQUEST['txnid']) && isset($_REQUEST['mihpayid'])){
            $order_id_time = $_REQUEST['txnid'];
            $order_id = explode('_', $_REQUEST['txnid']);
            $order_id = (int)$order_id[0];
            if($order_id != ''){
                try{
                    $order = new WC_Order( $order_id );
                    $merchant_id = $_REQUEST['key'];
                    $amount = $_REQUEST['Amount'];
                    $hash = $_REQUEST['hash'];

                    $status = $_REQUEST['status'];
                    $productinfo = "Order $order_id";
                    echo $hash;
                    echo "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}";
                    $checkhash = hash('sha512', "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}");
                    $transauthorised = false;
                    if($order -> status !=='completed'){
                        if($hash == $checkhash)
                        {

                          $status = strtolower($status);

                            if($status=="success"){
                                $transauthorised = true;
                                $this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                $this -> msg['class'] = 'woocommerce_message';
                                if($order -> status == 'processing'){

                                }else{
                                    $order -> payment_complete();
                                    $order -> add_order_note('Mazzuma payment successful<br/>Unnique Id from Mazzuma: '.$_REQUEST['mihpayid']);
                                    $order -> add_order_note($this->msg['message']);
                                    $woocommerce -> cart -> empty_cart();
                                }
                            }else if($status=="pending"){
                                $this -> msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
                                $this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
                                $order -> add_order_note('Mazzuma payment status is pending<br/>Unnique Id from Mazzuma: '.$_REQUEST['mihpayid']);
                                $order -> add_order_note($this->msg['message']);
                                $order -> update_status('on-hold');
                                $woocommerce -> cart -> empty_cart();
                            }
                            else{
                                $this -> msg['class'] = 'woocommerce_error';
                                $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                $order -> add_order_note('Transaction Declined: '.$_REQUEST['Error']);
                                //Here you need to put in the routines for a failed
                                //transaction such as sending an email to customer
                                //setting database status etc etc
                            }
                        }else{
                            $this -> msg['class'] = 'error';
                            $this -> msg['message'] = "Security Error. Illegal access detected";

                            //Here you need to simply ignore this and dont need
                            //to perform any operation in this condition
                        }
                        if($transauthorised==false){
                            $order -> update_status('failed');
                            $order -> add_order_note('Failed');
                            $order -> add_order_note($this->msg['message']);
                        }
                        add_action('the_content', array(&$this, 'showMessage'));
                    }}catch(Exception $e){
                        // $errorOccurred = true;
                        $msg = "Error";
                    }

            }



        }

    }

    function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }
     // get all pages
    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while($has_parent) {
                    $prefix .=  ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
}
   /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_cyst_mazzuma_gateway($methods) {
        $methods[] = 'WC_Cyst_Mazzuma';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_cyst_mazzuma_gateway' );
}
