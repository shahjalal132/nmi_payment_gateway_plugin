<?php

// function display_customer_information_callback() {
//     // Make sure WooCommerce is active
//     if ( class_exists( 'WooCommerce' ) ) {

//         // Get the order ID (replace 123 with the actual order ID)
//         $order_id = 2511;

//         // Get the order object
//         $order = wc_get_order( $order_id );

//         // Check if the order exists
//         if ( $order ) {

//             // Get customer ID
//             $customer_id = $order->get_user_id();

//             // Get customer data
//             $customer_data = get_userdata( $customer_id );

//             // get billing information
//             $first_name        = $customer_data->billing_first_name;
//             $last_name         = $customer_data->billing_last_name;
//             $billing_company   = $customer_data->billing_company;
//             $billing_address_1 = $customer_data->billing_address_1;
//             $billing_address_2 = $customer_data->billing_address_2;
//             $billing_city      = $order->get_billing_city();
//             $billing_state     = $order->get_billing_state();
//             $billing_postcode  = $order->get_billing_postcode();
//             $billing_country   = $order->get_billing_country();
//             $customer_email    = $order->get_billing_email();
//             $customer_phone    = $order->get_billing_phone();

//             // Get shipping information
//             $shipping_first_name = $customer_data->shipping_first_name;
//             $shipping_last_name  = $customer_data->shipping_last_name;
//             $shipping_company    = $customer_data->shipping_company;
//             $shipping_address_1  = $customer_data->shipping_address_1;
//             $shipping_address_2  = $customer_data->shipping_address_2;
//             $shipping_phone      = $customer_data->shipping_phone;
//             $shipping_city       = $order->get_shipping_city();
//             $shipping_state      = $order->get_shipping_state();
//             $shipping_postcode   = $order->get_shipping_postcode();
//             $shipping_country    = $order->get_shipping_country();

//             // Display customer Billing information
//             echo 'First Name: ' . $first_name . '<br>';
//             echo 'Last Name: ' . $last_name . '<br>';
//             echo 'Customer Phone: ' . $customer_phone . '<br>';
//             echo 'Billing Company: ' . $billing_company . '<br>';
//             echo 'Billing City: ' . $billing_city . '<br>';
//             echo 'Shipping State: ' . $billing_state . '<br>';
//             echo 'Shipping Postcode: ' . $billing_postcode . '<br>';
//             echo 'Shipping Country: ' . $billing_country . '<br>';
//             echo 'Billing Address 1 : ' . $billing_address_1 . '<br>';
//             echo 'Billing Address 2 : ' . $billing_address_2 . '<br>';

//             echo '<br>';
//             echo '<br>';

//             // Display customer Shipping information
//             echo 'Shipping First Name: ' . $shipping_first_name . '<br>';
//             echo 'Shipping Last Name: ' . $shipping_last_name . '<br>';
//             echo 'Shipping Phone: ' . $shipping_phone . '<br>';
//             echo 'Shipping Company: ' . $shipping_company . '<br>';
//             echo 'Shipping City: ' . $shipping_city . '<br>';
//             echo 'Shipping State: ' . $shipping_state . '<br>';
//             echo 'Shipping Postcode: ' . $shipping_postcode . '<br>';
//             echo 'Shipping Country: ' . $shipping_country . '<br>';
//             echo 'Shipping Address 1 : ' . $shipping_address_1 . '<br>';
//             echo 'Shipping Address 2 : ' . $shipping_address_2 . '<br>';

//             // make curl post request

//             $curl = curl_init();

//             // cURL URL with placeholders replaced by variables
//             $curl_url = 'https://propelr.transactiongateway.com/api/transact.php'
//                 . '?customer_vault=add_customer'
//                 . '&security_key=H24zBu3uC7rn3JR7uY86NqhQH6TZCzkc'
//                 . '&ccnumber=4111111111111111'
//                 . '&ccexp=10%2F25'
//                 . '&checkname=testcustomer1'
//                 . '&checkaba=490000018'
//                 . '&checkaccount=24413815'
//                 . '&account_holder_type=personal'
//                 . '&account_type=savings'
//                 . '&sec_code=PPD'
//                 . '&currency=USD'
//                 . '&payment=creditcard'
//                 . '&orderid=2014'
//                 . '&order_description=orderdescription'
//                 . '&merchant_defined_field_=merchant_defined_field_1%3DValue'
//                 . '&first_name=' . urlencode( $first_name )
//                 . '&last_name=' . urlencode( $last_name )
//                 . '&address1=' . urlencode( $billing_address_1 )
//                 . '&address2=' . urlencode( $billing_address_2 )
//                 . '&city=' . urlencode( $billing_city )
//                 . '&state=' . urlencode( $billing_state )
//                 . '&zip=' . urlencode( $billing_postcode )
//                 . '&country=' . urlencode( $billing_country )
//                 . '&phone=' . urlencode( $customer_phone )
//                 . '&email=' . urlencode( $customer_email )
//                 . '&company=' . urlencode( $billing_company )
//                 . '&fax=1234'
//                 . '&shipping_id=2015'
//                 . '&shipping_firstname=' . urlencode( $shipping_first_name )
//                 . '&shipping_lastname=' . urlencode( $shipping_last_name )
//                 . '&shipping_company=' . urlencode( $shipping_company )
//                 . '&shipping_address1=' . urlencode( $shipping_address_1 )
//                 . '&shipping_address2=' . urlencode( $shipping_address_2 )
//                 . '&shipping_city=' . urlencode( $shipping_city )
//                 . '&shipping_state=' . urlencode( $shipping_state )
//                 . '&shipping_zip=' . urlencode( $shipping_postcode )
//                 . '&shipping_country=' . urlencode( $shipping_country )
//                 . '&shipping_phone=' . urlencode( $shipping_phone )
//                 . '&shipping_fax=Shipping%20fax%20number'
//                 . '&shipping_email=Shipping%20email%20address'
//                 . '&acu_enabled=true';

//             curl_setopt_array(
//                 $curl,
//                 array(
//                     CURLOPT_URL            => $curl_url,
//                     CURLOPT_RETURNTRANSFER => true,
//                     CURLOPT_ENCODING       => '',
//                     CURLOPT_MAXREDIRS      => 10,
//                     CURLOPT_TIMEOUT        => 0,
//                     CURLOPT_FOLLOWLOCATION => true,
//                     CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
//                     CURLOPT_CUSTOMREQUEST  => 'POST',
//                 )
//             );

//             $response = curl_exec( $curl );

//             curl_close( $curl );
//             echo $response;

//         } else {
//             echo 'Order not found.';
//         }

//     } else {
//         echo 'WooCommerce is not active.';
//     }

// }
// add_shortcode( 'display_customer_information', 'display_customer_information_callback' );


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