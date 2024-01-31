<?php

function display_customer_information_callback() {
    // Make sure WooCommerce is active
    if ( class_exists( 'WooCommerce' ) ) {

        // Get the order ID (replace 123 with the actual order ID)
        $order_id = 2511;

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

            // Display customer and order information
            // echo 'Customer Name: ' . $customer_data->display_name . '<br>';
            echo 'Customer Email: ' . $customer_email . '<br>';
            echo 'First Name: ' . $first_name . '<br>';
            echo 'Last Name: ' . $last_name . '<br>';
            echo 'Customer Phone: ' . $customer_phone . '<br>';
            echo 'Billing City: ' . $billing_city . '<br>';
            echo 'Shipping State: ' . $billing_state . '<br>';
            echo 'Shipping Postcode: ' . $billing_postcode . '<br>';
            echo 'Shipping Country: ' . $billing_country . '<br>';
            echo 'Order Amount: ' . wc_price( $order_total ) . '<br>';
            echo 'Billing Address 1 : ' . $billing_address_1 . '<br>';
            echo 'Billing Address 2 : ' . $billing_address_2 . '<br>';

        } else {
            echo 'Order not found.';
        }

    } else {
        echo 'WooCommerce is not active.';
    }

}
add_shortcode( 'display_customer_information', 'display_customer_information_callback' );

?>