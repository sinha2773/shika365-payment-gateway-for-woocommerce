<?php
/*
Plugin Name: Shika365 Payment Gateway for WooCommerce
Plugin URI: https://wordpress.org/plugins/shika365-payment-gateway-for-woocommerce/
Description: Shika365 Payment Gateway for Woocommerce is Secure Online Payment Gateway Based on Shika365, Mobile Money, Visa Card and MasterCard. Powered by https://shika365.com
Version: 1.1
Author: Shika365 Team
Author URI: https://shika365.com
License: GPL2 or Later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: shika365
*/


if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}


add_action('plugins_loaded', 'woo_shika365_init', 0);

function woo_shika365_init()
{

    if (!class_exists('WC_Payment_Gateway'))
        return;


    class WC_Shika365 extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'shika365';
            $this->medthod_title = 'Shika365 Payment Gateway';
            $this->icon = apply_filters('woocommerce_shika365_icon', plugins_url('assets/images/logo.png', __FILE__));
            $this->desc_icon = apply_filters('woocommerce_shika365_desc_icon', plugins_url('assets/images/pay_via_icon.png', __FILE__));
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'] . ' <br><img height="50" src="' . $this->desc_icon . '" alt="">';
            $this->merchant_name = $this->settings['merchant_name'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->merchant_api_token = $this->settings['merchant_api_token'];
            $this->go_live = 'yes'; //$this->settings['go_live']; // no test version yet
            $this->currency = $this->settings['currency'];

            //Checking for live environment..
            if ($this->go_live == "yes") {
                $this->api_base_url = 'https://shika365.com/merchantPay/getToken';
            } else {
                $this->api_base_url = 'https://shika365.com/merchantPayTest/getToken';
            }


            $this->msg['message'] = "";
            $this->msg['class'] = "";

            if (isset($_REQUEST["shika365-response-notice"])) {
                wc_add_notice(sanitize_text_field($_REQUEST["shika365-response-notice"]), "error");
            }


            if (isset($_REQUEST["shika365-error-notice"])) {
                wc_add_notice(sanitize_text_field($_REQUEST["shika365-error-notice"]), "error");
            }


            if (isset($_REQUEST["order_id"]) && (int)sanitize_text_field($_REQUEST["order_id"])>0 && isset($_REQUEST["shika365_response"])) {
                //Check Shika365 API Response...
                $this->check_shika365_response();
            }


            //check for at least Woocommerce 2.0...
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

        }


        //Iniatialization of config form...
        function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'shika365'),
                    'type' => 'checkbox',
                    'label' => __('Enable Shika365 Payment Gateway as a payment option on the checkout page.', 'shika365'),
                    'default' => 'no'
                ),
                'go_live' => array(
                    'title' => __('Environment', 'shika365'),
                    'label' => __('check to live environment', 'client'),
                    'type' => 'select',
                    'description' => __('ensure that you have all your credentials details set.', 'client'),
                    'options' => array('LIVE'),
                    // 'desc_tip' => true
                ),

//                'go_live' => array(
//                    'title' => __('Go Live', 'shika365'),
//                    'label' => __('Check to live environment', 'client'),
//                    'type' => 'checkbox',
//                    'description' => __('Ensure that you have all your credentials details set.', 'client'),
//                    'default' => 'no',
//                    'desc_tip' => true
//                ),

                'title' => array(
                    'title' => __('Title', 'shika365'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'shika365'),
                    'placeholder' => 'Shika365',
                    'default' => __('Shika365', 'shika365')
                ),
                'description' => array(
                    'title' => __('Description', 'shika365'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'client'),
                    'placeholder' => 'Pay securely by Credit , Debit card or Mobile Money through Shika365 Checkout',
                    'default' => __('Pay securely by Credit , Debit card or Mobile Money through Shika365 Checkout.', 'client')
                ),
                'currency' => array(
                    'title' => __('Currency', 'shika365'),
                    'type' => 'select',
                    'options' => array('GHS', 'USD', 'EURO', 'GBP'),
                    'description' => __('Select your currency. Default is : GHS', 'client')
                ),

//                'channel' => array(
//                    'title' => __('Channel', 'shika365'),
//                    'type' => 'select',
//                    'options' => array('Card Only', 'Mobile Money Only', 'Both'),
//                    'description' => __('Select channel that you want to allow on the checkout page. Default is : Both ', 'client')),

                'merchant_name' => array(
                    'title' => __('Merchant Name / Shop name / Company Name ', 'shika365'),
                    'type' => 'text',
                    'description' => __('This will be use for the payment description. ')
                ),
                'merchant_id' => array(
                    'title' => __('Shika365 Merchant Number', 'shika365'),
                    'type' => 'text',
                    'description' => __('Shika365 Merchant Number given during registration or go to Business Settings in shika365.com.')
                ),
            );
        }


        public function admin_options()
        {

            echo '<h3>' . __('Shika365 Payment Gateway', 'shika365') . '</h3>';
            echo '<p>' . __('With a simple configuration, you can accept payments from cards to mobile money with Shika365.') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html($this->form_fields);
            echo '</table>';
        }


        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }


        //Sending Request to Shika365 API...
        function send_request_to_shika365_api($order_id)
        {

            global $woocommerce;
            //Getting settings...
            $merchantname = $this->merchant_name;
            $merchantid = $this->merchant_id;
            $api_base_url = $this->api_base_url;

            $order = new WC_Order($order_id);
            $amount = $order->total;
            $customer_email = $order->billing_email;
            $currency = $this->currency;
            $channel = $this->channel;

            $redirect_url = $woocommerce->cart->get_checkout_url() . '?order_id=' . $order_id . '&shika365_response';
            $cancel_url = $woocommerce->cart->get_checkout_url() . '?order_id=' . $order_id . '&shika365_response&status=cancel';

            //Generating 12 unique random transaction id...
            $transaction_id = '';
            $allowed_characters = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 0);
            for ($i = 1; $i <= 12; $i++) {
                $transaction_id .= $allowed_characters[rand(0, count($allowed_characters) - 1)];
                WC()->session->set('shika365_wc_transaction_id', $transaction_id);
            }


            //Hashing order details...
            $key_options = $merchantid . $transaction_id . $amount . $customer_email;
            $shika365_wc_hash_key = hash('sha512', $key_options);
            WC()->session->set('shika365_wc_hash_key', $shika365_wc_hash_key);

            //checking for currency GHS/USD/EUR...
            if ($currency == 0) {
                $currency = "GHS";
            } elseif ($currency == 1) {
                $currency = "USD";
            } elseif ($currency == 2) {
                $currency = "EUR";
            } elseif ($currency == 3) {
                $currency = "GBP";
            } else {
                $currency = "GHS";
            }


            //checking for channel card/momo/both...
            if ($channel == 0) {
                $channel = "card";
            } elseif ($channel == 1) {
                $channel = "momo";
            } elseif ($channel == 2) {
                $channel = "both";
            } else {
                $channel = "both";
            }


            $post_data = [
                'action' => 'get_token',
                "merchant_id" => $merchantid,
                'reference_id' => $transaction_id,
                'success_url' => $redirect_url,
                'cancel_url' => $cancel_url,
                'item_no[]' => $order_id,
                'item_name[]' => "Payment  to " . $merchantname . "",
                'item_quantity[]' => 1,
                'item_price[]' => $amount,
                'amount' => $amount, // total amount
                'email' => $customer_email,
                'currency' => $currency,
                'payment_method' => $channel
            ];
            // echo $api_base_url;exit;
            $response = wp_remote_post($api_base_url, array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(),
                    'body' => $post_data,
                    'cookies' => array()
                )
            );

            // print_r($response);
            // exit;


            //Decoding response...
            $response_data = json_decode($response['body'], true);
//             print_r( $response_data);

            //Checking if error
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_message = empty($error_message) ? 'Error' : $error_message;
                return $redirect_url . "&shika365-response-notice=" . $error_message;
            }


            //Getting Response...
            if (!isset($response_data['status'])) {
                $status = null;
            } else {
                $status = $response_data['status'];
            }

            if (!isset($response_data['description'])) {
                $description = null;
            } else {
                $description = $response_data['description'];
            }

            if (!isset($response_data['message'])) {
                $message = null;
            } else {
                $message = $response_data['message'];
            }


            if (!isset($response_data['checkout_url'])) {
                $checkout_url = null;
            } else {
                $checkout_url = $response_data['checkout_url'];
            }


            if ($status == "success") {
                //Redirect to checkout page...
                return $checkout_url;
                exit;
            } else {
                return $redirect_url . "&shika365-response-notice=" . $message . ' ' . $description;
            }
        }//end of send_request_to_shika365_api()...


        //Processing payment...
        function process_payment($order_id)
        {
            WC()->session->set('shika365_wc_oder_id', $order_id);
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => $this->send_request_to_shika365_api($order_id)
            );
        }


        //show message either error or success...
        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }


        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
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
                        $next_page = get_post($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array

                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }


        //Getting Shika365 Api response...
        function check_shika365_response()
        {
            global $woocommerce;
            $shika365 = isset($_REQUEST["shika365_response"]) ? sanitize_text_field($_REQUEST["shika365_response"]) : "";
            $order_id = isset($_REQUEST["order_id"]) ? sanitize_text_field($_REQUEST["order_id"]) : "";
            $code = isset($_REQUEST["code"]) ? sanitize_text_field($_REQUEST["code"]) : "";
            $status = isset($_REQUEST["status"]) ? sanitize_text_field($_REQUEST["status"]) : "";
            $transaction_id = isset($_REQUEST["transaction_id"]) ? sanitize_text_field($_REQUEST["transaction_id"]) : "";
            $reference_id = isset($_REQUEST["reference_id"]) ? sanitize_text_field($_REQUEST["reference_id"]) : "";
            $response_message = isset($_REQUEST["message"]) ? sanitize_text_field($_REQUEST["message"]) : "";

            if ($shika365 != "") {
                die("<h2 style=color:red>Not a valid request !</h2>");
            }


            $wc_order_id = WC()->session->get('shika365_wc_oder_id');
            $order = new WC_Order($wc_order_id);
            if ($order->status == 'pending' || $order->status == 'processing') {

                if ($order_id != '' && $transaction_id != '' && $reference_id != '') {

                    $wc_transaction_id = WC()->session->get('shika365_wc_transaction_id');
                    $shika365_wc_hash_key = WC()->session->get('shika365_wc_hash_key');

                    if (empty($shika365_wc_hash_key)) {
                        die("<h2 style=color:red>Ooups ! something went wrong </h2>");
                    }


                    if ($order_id != $wc_order_id) {

                        $message = "Code 0001 : Data has been tampered . 
                            Order ID is " . $wc_order_id . "";
                        $message_type = "error";
                        $order->add_order_note($message);
                        $redirect_url = $order->get_cancel_order_url();
                        wp_redirect($redirect_url);
                        exit;
                    }


                    if ($reference_id != $wc_transaction_id) {
                        $message = "Code 0002 : Data has been tampered . 
                            Order ID is " . $wc_order_id . "";
                        $message_type = "error";
                        $order->add_order_note($message);
                        $redirect_url = $order->get_cancel_order_url();
                        wp_redirect($redirect_url);
                        exit;

                    }


                    if ($wc_order_id != '' && $wc_transaction_id != '') {
                        try {
                            if ($status == "success") {

                                $message = "Thank you for shopping with us. 
                                Your transaction was succssful, payment has been received. 
                                You order is currently being processed. 
                                Your Order ID is " . $wc_order_id . "";
                                $message_type = "success";

                                $order->payment_complete();
                                $order->update_status('completed');
                                $order->add_order_note('Shika365 Status : ' . $status . '<br/>Transaction ID  ' . $wc_transaction_id . ' <br/>Reference ID ' . $reference_id . ' <br /> Message: ' . $response_message . '');

                                //empty cart redirect to success page...
                                $woocommerce->cart->empty_cart();
                                $redirect_url = $this->get_return_url($order);
                                $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                                WC()->session->__unset('shika365_wc_hash_key');
                                WC()->session->__unset('shika365_wc_order_id');
                                WC()->session->__unset('shika365_wc_transaction_id');
                                wp_redirect($redirect_url);
                                exit;
                            } else if ($status == "failed") {
                                //$order->payment_complete();
                                $order->update_status('failed');
                                $order->add_order_note('Shika365 Status : ' . $status . '<br/>Transaction ID  ' . $wc_transaction_id . ' <br/>Reference ID ' . $reference_id . ' <br /> Message: Transaction declined');
                                $redirect_url = $woocommerce->cart->get_checkout_url();
                                $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                                WC()->session->__unset('shika365_wc_hash_key');
                                WC()->session->__unset('shika365_wc_order_id');
                                WC()->session->__unset('shika365_wc_transaction_id');
                                wp_redirect($redirect_url);
                                exit;
                            } else {
                                $message = "Thank you for shopping with us. However, the transaction failed.";
                                $message_type = "error";
                                // $order->payment_complete();
                                $order->update_status('failed');
                                $order->add_order_note('Shika365 Status : ' . $status . '<br/>Transaction ID  ' . $wc_transaction_id . ' <br/>Reference ID ' . $reference_id . ' <br /> Message: ' . $response_message . '');
                                $woocommerce->cart->empty_cart();
                                $redirect_url = $this->get_return_url($order);
                                $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                                WC()->session->__unset('shika365_wc_hash_key');
                                WC()->session->__unset('shika365_wc_order_id');
                                WC()->session->__unset('shika365_wc_transaction_id');
                                wp_redirect($redirect_url);
                                exit;
                            }


                            $notification_message = array(
                                'message' => $message,
                                'message_type' => $message_type
                            );
                            if (version_compare(WOOCOMMERCE_VERSION, "2.2") >= 0) {
                                add_post_meta($wc_order_id, '_shika365_hash', $shika365_wc_hash_key, true);
                            }
                            update_post_meta($wc_order_id, '_shika365_wc_message', $notification_message);

                        } catch (Exception $e) {
                            $order->add_order_note('Error: ' . $e->getMessage());
                            $redirect_url = $order->get_cancel_order_url();
                            wp_redirect($redirect_url);
                            exit;
                        }

                    }
                } else if ($status == 'cancel') {
                    $order->update_status('cancelled');
                    $order->add_order_note('Shika365 Status : cancelled<br/> Message: Transaction Cancelled');
                    $redirect_url = $order->get_cancel_order_url();
                    $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                    WC()->session->__unset('shika365_wc_hash_key');
                    WC()->session->__unset('shika365_wc_order_id');
                    WC()->session->__unset('shika365_wc_transaction_id');
                    wp_redirect($redirect_url);
                    exit;
                }

            }

        }


        static function woocommerce_add_shika365_gateway($methods)
        {
            $methods[] = 'WC_Shika365';
            return $methods;
        }


        static function woocommerce_add_shika365_settings_link($links)
        {
            $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_shika365">Settings</a>';
            array_unshift($links, $settings_link);
            return $links;
        }


    }


    $plugin = plugin_basename(__FILE__);
    add_filter("plugin_action_links_$plugin", array('WC_Shika365', 'woocommerce_add_shika365_settings_link'));
    add_filter('woocommerce_payment_gateways', array('WC_Shika365', 'woocommerce_add_shika365_gateway'));
}

