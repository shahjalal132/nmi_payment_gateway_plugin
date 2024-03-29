<?php

add_action( 'woocommerce_checkout_order_processed', 'add_customer_to_api', 10, 1 );
function add_customer_to_api( $order_id ) {

    // add customer
    if ( class_exists( 'WooCommerce' ) ) {

        // Get the order object
        $order = wc_get_order( $order_id );

        // Check if the order exists
        if ( $order ) {

            // Get customer ID
            $customer_id = $order->get_user_id();

            // Get customer data
            $customer_data = get_userdata( $customer_id );

            foreach ( $order->get_items() as $item_id => $item ) {

                // get the product id
                $product_id = $item->get_product_id();

                // get product type
                $product_type = wc_get_product( $product_id )->get_type();
            }

            // Check if the order is a subscription order
            if ( wcs_order_contains_subscription( $order ) ) {

                // Get the subscription objects associated with the order
                $subscriptions = wcs_get_subscriptions_for_order( $order );

                // Loop through each subscription (assuming there's only one subscription per order)
                foreach ( $subscriptions as $subscription ) {

                    // get the subscription data
                    $wc_data = $subscription->data;

                    $s_year  = 'year';
                    $s_month = 'month';

                    // get card data
                    $meta_data = $wc_data['meta_data'][3];

                    // get ccexp and last4 and card_brand
                    $cc_exp        = $meta_data->value->ccexp;
                    $last_4_digits = $meta_data->value->last4;
                    $card_brand    = $meta_data->value->brand;

                    $currency            = $wc_data['currency'] ?? null;
                    $billing_interval    = $wc_data['billing_interval'];
                    $billing_period      = $wc_data['billing_period'];
                    $payment_method      = $wc_data['payment_method'];
                    $plane_amount        = $wc_data['total'];
                    $subscription_period = $billing_interval . ' ' . $billing_period;

                    if ( strpos( $subscription_period, $s_year ) ) {
                        $subscription_period = str_replace( $s_year, 'Years', $subscription_period );
                    } else if ( strpos( $subscription_period, $s_month ) ) {
                        $subscription_period = str_replace( $s_month, 'Months', $subscription_period );
                    }

                    // check condition for month_frequency
                    if ( '1 Years' == $subscription_period ) {
                        $billing_interval = 12;
                    } else if ( '2 Years' == $subscription_period ) {
                        $billing_interval = 24;
                    }

                }

                // get billing information
                $first_name        = $customer_data->billing_first_name;
                $last_name         = $customer_data->billing_last_name;
                $billing_company   = $customer_data->billing_company;
                $billing_address_1 = $customer_data->billing_address_1;
                $billing_address_2 = $customer_data->billing_address_2;
                $billing_city      = $order->get_billing_city();
                $billing_state     = $order->get_billing_state();
                $billing_postcode  = $order->get_billing_postcode();
                $billing_country   = $order->get_billing_country();
                $customer_email    = $order->get_billing_email();
                $customer_phone    = $order->get_billing_phone();

                // Get shipping information
                $shipping_first_name = $customer_data->shipping_first_name;
                $shipping_last_name  = $customer_data->shipping_last_name;
                $shipping_company    = $customer_data->shipping_company;
                $shipping_address_1  = $customer_data->shipping_address_1;
                $shipping_address_2  = $customer_data->shipping_address_2;
                $shipping_phone      = $customer_data->shipping_phone;
                $shipping_city       = $order->get_shipping_city();
                $shipping_state      = $order->get_shipping_state();
                $shipping_postcode   = $order->get_shipping_postcode();
                $shipping_country    = $order->get_shipping_country();

                // get nmi settings
                $nmi_settings = get_option( 'woocommerce_nmi_settings' );
                $security_key = $nmi_settings['private_key'];
                $public_key   = $nmi_settings['public_key'];

                // define plane id
                $plane_id = 2815; // replace plane id with dynamic id

                // define card number
                // $cc_number = null;
                $cc_number = 4111111111111111;

                /* if ( isset( $_POST['ccnumber'] ) ) {
                    $cc_number = sanitize_text_field( $_POST['ccnumber'] );
                } */

                $payment_type      = $payment_type ?? 'creditcard';
                $day_of_month      = date( 'j' );
                $order_description = $order_description ?? '';

                // Add a customer
                $curl     = curl_init();
                $curl_url = 'https://propelr.transactiongateway.com/api/transact.php'
                    . '?customer_vault=add_customer'
                    . '&security_key=' . urlencode( $security_key )
                    . '&ccnumber=' . urlencode( $cc_number )
                    . '&ccexp=' . urlencode( $cc_exp )
                    . '&currency=' . urlencode( $currency )
                    . '&payment=' . urlencode( $payment_type )
                    . '&orderid=' . urlencode( $order_id )
                    . '&merchant_defined_field_=merchant_defined_field_1%3DValue'
                    . '&first_name=' . urlencode( $first_name )
                    . '&last_name=' . urlencode( $last_name )
                    . '&address1=' . urlencode( $billing_address_1 )
                    . '&address2=' . urlencode( $billing_address_2 )
                    . '&city=' . urlencode( $billing_city )
                    . '&state=' . urlencode( $billing_state )
                    . '&zip=' . urlencode( $billing_postcode )
                    . '&country=' . urlencode( $billing_country )
                    . '&phone=' . urlencode( $customer_phone )
                    . '&email=' . urlencode( $customer_email )
                    . '&company=' . urlencode( $billing_company )
                    . '&shipping_firstname=' . urlencode( $shipping_first_name )
                    . '&shipping_lastname=' . urlencode( $shipping_last_name )
                    . '&shipping_company=' . urlencode( $shipping_company )
                    . '&shipping_address1=' . urlencode( $shipping_address_1 )
                    . '&shipping_address2=' . urlencode( $shipping_address_2 )
                    . '&shipping_city=' . urlencode( $shipping_city )
                    . '&shipping_state=' . urlencode( $shipping_state )
                    . '&shipping_zip=' . urlencode( $shipping_postcode )
                    . '&shipping_country=' . urlencode( $shipping_country )
                    . '&shipping_phone=' . urlencode( $shipping_phone )
                    . '&acu_enabled=true';

                curl_setopt_array(
                    $curl,
                    array(
                        CURLOPT_URL            => $curl_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING       => '',
                        CURLOPT_MAXREDIRS      => 10,
                        CURLOPT_TIMEOUT        => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST  => 'POST',
                    )
                );

                $response = curl_exec( $curl );

                curl_close( $curl );
                // end add customer

            }

            // Add a plane conditionally
            if ( 'nmi' == $payment_method && ( 'simple-subscription' == $product_type || 'variable-subscription' == $product_type ) ) {

                $curl     = curl_init();
                $curl_url = 'https://secure.nmi.com/api/transact.php'
                    . '?security_key=' . urlencode( $security_key )
                    . '&recurring=add_plan'
                    . '&plan_payments=0'
                    . '&plan_amount=' . urlencode( $plane_amount )
                    . '&plan_name=' . urlencode( $subscription_period )
                    . '&plan_id=' . urlencode( $order_id )
                    . '&month_frequency=' . urlencode( $billing_interval )
                    . '&day_of_month=' . urlencode( $day_of_month );

                curl_setopt_array(
                    $curl,
                    array(
                        CURLOPT_URL            => $curl_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING       => '',
                        CURLOPT_MAXREDIRS      => 10,
                        CURLOPT_TIMEOUT        => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST  => 'POST',
                    )
                );

                $response = curl_exec( $curl );

                curl_close( $curl );
            }
            // end add a plane

            // Add a subscription conditionally
            if ( 'nmi' == $payment_method && ( 'simple-subscription' == $product_type || 'variable-subscription' == $product_type ) ) {
                $curl     = curl_init();
                $curl_url = 'https://secure.nmi.com/api/transact.php?recurring=add_subscription'
                    . '&plan_id=' . urlencode( $plane_id )
                    . '&security_key=' . urlencode( $security_key )
                    . '&ccnumber=' . urlencode( $cc_number )
                    . '&ccexp=10%2F25'
                    . '&payment=' . urlencode( $payment_type )
                    . '&checkname=' . urlencode( $first_name )
                    // . '&checkaccount=24413815'
                    // . '&checkaba=490000018'
                    . '&account_type=savings'
                    . '&currency=' . urlencode( $currency )
                    . '&account_holder_type=personal'
                    . '&sec_code=PPD'
                    . '&first_name=' . urlencode( $first_name )
                    . '&last_name=' . urlencode( $last_name )
                    . '&address1=' . urlencode( $last_name )
                    . '&city=' . urlencode( $billing_city )
                    . '&state=' . urlencode( $billing_state )
                    . '&zip=' . urlencode( $billing_postcode )
                    . '&country=' . urlencode( $billing_country )
                    . '&phone=' . urlencode( $customer_phone )
                    . '&email=' . urlencode( $customer_email )
                    . '&company=' . urlencode( $billing_company )
                    . '&address2=' . urlencode( $billing_address_2 )
                    . '&orderid=' . urlencode( $order_id )
                    . '&order_description=' . urlencode( $order_description )
                    . '&ponumber=' . urlencode( $customer_phone )
                    . '&customer_receipt=true'
                    . '&acu_enabled=true';


                curl_setopt_array(
                    $curl,
                    array(
                        CURLOPT_URL            => $curl_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING       => '',
                        CURLOPT_MAXREDIRS      => 10,
                        CURLOPT_TIMEOUT        => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST  => 'POST',
                    )
                );

                $response = curl_exec( $curl );

                curl_close( $curl );
            }

            // end add subscription

        } else {
            echo 'Order not found.';
        }
    } else {
        echo 'WooCommerce is not active.';
    }
}