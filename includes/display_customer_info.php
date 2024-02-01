<?php

function display_customer_information_callback() {
    // Make sure WooCommerce is active
    if ( class_exists( 'WooCommerce' ) ) {

        // Get the order ID (replace 123 with the actual order ID)
        $order_id = 2511;

        // Get the order object
        $order = wc_get_order( $order_id );

        /* echo '<pre>';
        print_r( $order );
        die(); */

        // Check if the order exists
        if ( $order ) {

            // Get customer ID
            $customer_id = $order->get_user_id();

            // Get customer data
            $customer_data = get_userdata( $customer_id );

            $payment_method      = null;
            $subscription_period = null;
            $product_type        = null;
            $plane_amount        = null;

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

                    /* echo '<pre>';
                    print_r( $subscription ); */

                    $wc_data = $subscription->data;

                    $s_year  = 'year';
                    $s_month = 'month';

                    /* echo '<pre>';
                    print_r( $wc_data );
                    wp_die(); */

                    $billing_interval = $wc_data['billing_interval'];
                    $billing_period   = $wc_data['billing_period'];
                    $payment_method   = $wc_data['payment_method'];
                    $plane_amount     = $wc_data['total'];

                    // concat the interval and period
                    $subscription_period = $billing_interval . ' ' . $billing_period;

                    if ( strpos( $subscription_period, $s_year ) ) {
                        $subscription_period = str_replace( $s_year, 'Years', $subscription_period );
                    } else if ( strpos( $subscription_period, $s_month ) ) {
                        $subscription_period = str_replace( $s_month, 'Months', $subscription_period );
                    }

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

            // Display customer Billing information
            echo '<h2>Billing Information</h2>';
            echo 'Billing First Name: ' . $first_name . '<br>';
            echo 'Billing Last Name: ' . $last_name . '<br>';
            echo 'Billing Customer Phone: ' . $customer_phone . '<br>';
            echo 'Billing Company: ' . $billing_company . '<br>';
            echo 'Billing City: ' . $billing_city . '<br>';
            echo 'Billing State: ' . $billing_state . '<br>';
            echo 'Billing Postcode: ' . $billing_postcode . '<br>';
            echo 'Billing Country: ' . $billing_country . '<br>';
            echo 'Billing Address 1 : ' . $billing_address_1 . '<br>';
            echo 'Billing Address 2 : ' . $billing_address_2 . '<br>';

            echo '<br>';

            // Display customer Shipping information
            echo '<h2>Shipping Information</h2>';
            echo 'Shipping First Name: ' . $shipping_first_name . '<br>';
            echo 'Shipping Last Name: ' . $shipping_last_name . '<br>';
            echo 'Shipping Phone: ' . $shipping_phone . '<br>';
            echo 'Shipping Company: ' . $shipping_company . '<br>';
            echo 'Shipping City: ' . $shipping_city . '<br>';
            echo 'Shipping State: ' . $shipping_state . '<br>';
            echo 'Shipping Postcode: ' . $shipping_postcode . '<br>';
            echo 'Shipping Country: ' . $shipping_country . '<br>';
            echo 'Shipping Address 1 : ' . $shipping_address_1 . '<br>';
            echo 'Shipping Address 2 : ' . $shipping_address_2 . '<br>';

            echo '<br>';

            echo '<h2>Payment Method Information</h2>';
            echo 'Payment Method: ' . $payment_method . '<br>';
            echo 'Subscription: ' . $subscription_period . '<br>';
            echo 'Product Type: ' . $product_type . '<br>';
            echo 'Plane Amount: ' . $plane_amount . '<br>';

            echo '<br>';

            die( '<h2>not send curl request just testing</h2>' );

            $curl = curl_init();

            // cURL URL with placeholders replaced by variables
            $curl_url = 'https://propelr.transactiongateway.com/api/transact.php'
                . '?customer_vault=add_customer'
                . '&security_key=H24zBu3uC7rn3JR7uY86NqhQH6TZCzkc'
                . '&ccnumber=4111111111111111'
                . '&ccexp=10%2F25'
                . '&checkname=testcustomer1'
                . '&checkaba=490000018'
                . '&checkaccount=24413815'
                . '&account_holder_type=personal'
                . '&account_type=savings'
                . '&sec_code=PPD'
                . '&currency=USD'
                . '&payment=creditcard'
                . '&orderid=2014'
                . '&order_description=orderdescription'
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
                . '&fax=1234'
                . '&shipping_id=2015'
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
                . '&shipping_fax=Shipping%20fax%20number'
                . '&shipping_email=Shipping%20email%20address'
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
add_shortcode( 'display_customer_information', 'display_customer_information_callback' );

?>