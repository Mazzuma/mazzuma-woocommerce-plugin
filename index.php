<?php
/*
Plugin Name: WooCommerce Mazzuma Payment Gateway
Plugin URI: https:///mazzuma.teamcyst.com
Description: Mazzuma Payment gateway for woocommerce
Version: 1.1
Author: CYST Company Limited
Author URI: https://mazzuma.teamcyst.com
*/
add_action('plugins_loaded', 'woocommerce_cyst_mazzuma_init', 0);
function woocommerce_cyst_mazzuma_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;
    
    class WC_Cyst_Mazzuma extends WC_Payment_Gateway
    {
        
        public function __construct()
        {
            $this->id            = 'mazzuma';
            $this->medthod_title = 'Mazzuma';
            $this->has_fields    = false;
            $this->icon          = plugins_url('mazzuma-pay.png' ,__FILE__ );
            
            $this->init_form_fields();
            $this->init_settings();
            $this->title            = sanitize_text_field($this->get_option('title') );
            $this->description      = sanitize_text_field($this->get_option('description') );
            $this->api_key          = sanitize_text_field($this->get_option('api_key') );
            $this->redirect_page_id = sanitize_text_field($this->get_option('redirect_page_id') );
            $this->liveURL          = 'https://secure.teamcyst.com/api_call.php';
            $this->payURL           = 'https://secure.teamcyst.com/index.php?token=';
			$this->merchant_id		= 'Merchant 11';
            
            $this->msg['message'] = "";
            $this->msg['class']   = "";

            /*$this->method_description = "Pay with moble money using Mazzuma";*/
            
            add_action('init', array(
                &$this,
                'check_mazzuma_response'
            ));
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    &$this,
                    'process_admin_options'
                ));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(
                    &$this,
                    'process_admin_options'
                ));
            }
            add_action('woocommerce_receipt_mazzuma', array(
                &$this,
                'receipt_page'
            ));
        }
        
        function init_form_fields()
        {
            
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'cyst'),
                    'type' => 'checkbox',
                    'label' => __('Enable Mazzuma Payment Module.', 'cyst'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title:', 'cyst'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'cyst'),
                    'default' => __('Mazzuma', 'cyst')
                ),
                'description' => array(
                    'title' => __('Description:', 'cyst'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'cyst'),
                    'default' => __('Pay online using your mobile money account.', 'cyst')
                ),
                'api_key' => array(
                    'title' => __('API Key', 'cyst'),
                    'type' => 'text',
                    'description' => __('This is the API Key generated at the Mazzuma Dashboard. Visit https://dashboard.mazzuma.com to view your API Key"')
                ),
                
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => "URL of success page"
                )
            );
        }
        
        public function admin_options()
        {
            echo '<h3>' . __('Mazzuma Payment Gateway', 'cyst') . '</h3>';
            echo '<p>' . __('Receive payments mobile money payments online using Mazzuma') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
            
        }
        
        
        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }
        
        function receipt_page($order)
        {
            echo '<p>' . __('Thank you for your order, please click the button below to pay with Mazzuma.', 'cyst') . '</p>';
			
            echo $this->generate_mazzuma_form($order);
        }
        
        
        function genPayURL($orderParams){
             

            $payload = array(
                "success_url" => ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id),
                "price" => $orderParams->get_total(),
                "orderID" => $orderParams->get_id(),
                "apikey" => $this->api_key
            );

            
            error_log('Order payload: '.print_r($payload, true));

            $payload = json_encode($payload);
            

            $crypt_key = $this->api_key;
            
            
            $publicHash  = substr($crypt_key, 0, strlen($crypt_key) / 2);
            $privateHash = substr($crypt_key, strlen($crypt_key) / 2, strlen($crypt_key));
            $hash        = hash_hmac('sha256', $payload, $privateHash);
            
            $headers = array(
                'Api-Auth-Token' => $publicHash,
                'Data-Token' => $hash
            );
             
            $args = array(
                'body' => http_build_query(array("data" => $payload)),
                'timeout' => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => $headers,
                'cookies' => array()
            );

            error_log('Request payload: '.print_r($args, true));
             
            $response = wp_remote_post( $this->liveURL, $args );

            error_log('Response: '.print_r($response, true));

            $responseBody = $response["body"];
            $responseCode =  wp_remote_retrieve_response_code( $response );
            error_log('Response Body: '.print_r($responseBody, true));

            if($responseCode != 200 || $responseBody === false){
                return false;
            }else{
                $responseBody = json_decode($responseBody, true);
                $responseString = $responseBody["url"];
                $payURL = $this->payURL.$responseString;
                return $payURL;
            }

           /*  $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $this->liveURL,
                CURLOPT_POST  => true,
                CURLOPT_POSTFIELDS => http_build_query(array("data" => $payload)),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FAILONERROR => true
            ));
            
            $responseString = curl_exec($curl);
            error_log($responseString);

            if($responseString === false){
                curl_close($curl);
                return false;
            }else{
                $responseString = json_decode($responseString, true);
                $responseString = $responseString["url"];
                $payURL = $this->payURL.$responseString;
                curl_close($curl);
                return $payURL;
            } */
            


        }
        
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {
            error_log('----------------Processing Order----------------');
			
            global $woocommerce;
            $order = new WC_Order($order_id);
            $result = $this->genPayURL($order);
            if($result === false){
                error_log("There was an error while getting the payurl");
                return array(
                    'result' => 'failure',
                    'redirect' =>  $woocommerce->cart->get_checkout_url()
                );

            }else{
                //empty cart
                $woocommerce->cart->empty_cart();
                return array(
                    'result' => 'success',
                    'redirect' => $result
                );  
            }
            error_log('----------------Order Processing Ended----------------');
			

        }



        
        /**
         * Check for valid mazzuma server callback
         **/
        function check_mazzuma_response()
        {
            global $woocommerce;
            if (isset($_REQUEST['txnid']) && isset($_REQUEST['mihpayid'])) {
                $order_id_time = $_REQUEST['txnid'];
                $order_id      = explode('_', $_REQUEST['txnid']);
                $order_id      = (int) $order_id[0];
                if ($order_id != '') {
                    try {
                        $order       = new WC_Order($order_id);
                        $merchant_id = sanitize_text_field( $_REQUEST['key'] );
                        $amount      = sanitize_text_field($_REQUEST['Amount'] );
                        $hash        = sanitize_text_field($_REQUEST['hash'] );
                        
                        $status      = $_REQUEST['status'];
                        $productinfo = "Order $order_id";
                        echo $hash;
                        echo "{$this->salt}|$status|||||||||||{$order->get_billing_email()}|{$order->get_billing_first_name()}|$productinfo|{$order->get_order_total()}|$order_id_time|{$this->merchant_id}";
                        $checkhash       = hash('sha512', "{$this->salt}|$status|||||||||||{$order->get_billing_email()}|{$order->get_billing_first_name()}|$productinfo|{$order->get_order_total()}|$order_id_time|{$this->merchant_id}");
                        $transauthorised = false;
                        if ($order->get_status() !== 'completed') {
                            if ($hash == $checkhash) {
                                
                                $status = strtolower($status);
                                
                                if ($status == "success") {
                                    $transauthorised      = true;
                                    $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                    $this->msg['class']   = 'woocommerce_message';
                                    if ($order->get_status() == 'processing') {
                                        
                                    } else {
                                        $order->payment_complete();
                                        $order->add_order_note('Mazzuma payment successful<br/>Unnique Id from Mazzuma: ' . $_REQUEST['mihpayid']);
                                        $order->add_order_note($this->msg['message']);
                                        $woocommerce->cart->empty_cart();
                                    }
                                } else if ($status == "pending") {
                                    $this->msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
                                    $this->msg['class']   = 'woocommerce_message woocommerce_message_info';
                                    $order->add_order_note('Mazzuma payment status is pending<br/>Unnique Id from Mazzuma: ' . $_REQUEST['mihpayid']);
                                    $order->add_order_note($this->msg['message']);
                                    $order->update_status('on-hold');
                                    $woocommerce->cart->empty_cart();
                                } else {
                                    $this->msg['class']   = 'woocommerce_error';
                                    $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                    $order->add_order_note('Transaction Declined: ' . $_REQUEST['Error']);
                                    //Here you need to put in the routines for a failed
                                    //transaction such as sending an email to customer
                                    //setting database status etc etc
                                }
                            } else {
                                $this->msg['class']   = 'error';
                                $this->msg['message'] = "Security Error. Illegal access detected";
                                
                                //Here you need to simply ignore this and dont need
                                //to perform any operation in this condition
                            }
                            if ($transauthorised == false) {
                                $order->update_status('failed');
                                $order->add_order_note('Failed');
                                $order->add_order_note($this->msg['message']);
                            }
                            add_action('the_content', array(
                                &$this,
                                'showMessage'
                            ));
                        }
                    }
                    catch (Exception $e) {
                        // $errorOccurred = true;
                        $msg = "Error";
                    }
                    
                }
                
                
                
            }
            
        }
        
        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }
        
        // get all pages
        function get_pages($title = false, $indent = true)
        {
            $wp_pages  = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page  = get_page($has_parent);
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
    function woocommerce_add_cyst_mazzuma_gateway($methods)
    {
        $methods[] = 'WC_Cyst_Mazzuma';
        return $methods;
    }
    
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_cyst_mazzuma_gateway');
}