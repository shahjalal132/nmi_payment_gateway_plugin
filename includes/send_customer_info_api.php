<?php


add_action( 'woocommerce_checkout_order_processed', 'send_customer_information_to_api', 10, 1 );
function send_customer_information_to_api( $order_id ) {
    // Make sure WooCommerce is active
    if ( class_exists( 'WooCommerce' ) ) {

        // Get the order ID (replace 123 with the actual order ID)
        // $order_id = 2511;

        // Get the order object
        $order = wc_get_order( $order_id );

        // Check if the order exists
        if ( $order ) {

            // Get customer ID
            $customer_id = $order->get_user_id();

            // Get customer data
            $customer_data = get_userdata( $customer_id );

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

            // get payment method
            $payment_method = $order->get_payment_method();
            $security_key   = get_option( 'woocommerce_nmi_private_key' );

            $curl = curl_init();

            // cURL URL with placeholders replaced by variables
            $curl_url = 'https://propelr.transactiongateway.com/api/transact.php'
                . '?customer_vault=add_customer'
                . '&security_key=' . urlencode( string: $security_key )
                . '&ccnumber=4111111111111111'
                . '&ccexp=10%2F25'
                . '&currency=USD'
                . '&payment=creditcard'
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
            echo $response;

        } else {
            echo 'Order not found.';
        }

    } else {
        echo 'WooCommerce is not active.';
    }

}


?>