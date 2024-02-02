<?php


add_action( 'woocommerce_checkout_order_processed', 'send_customer_information_to_api', 10, 1 );
function send_customer_information_to_api( $order_id ) {
    // Make sure WooCommerce is active
    if ( class_exists( 'WooCommerce' ) ) {

        // Get the order object
        $order = wc_get_order( $order_id );

        // Check if the order exists
        if ( $order ) {

            // Get customer ID
            $customer_id = $order->get_user_id();

            // Get customer data
            $customer_data = get_userdata( $customer_id );

            $payment_method      = null;
            $subscription_period = null;
            $product_type        = null;
            $billing_interval    = null;
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

                    // get the subscription data
                    $wc_data = $subscription->data;

                    $s_year  = 'year';
                    $s_month = 'month';

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

            // get security key
            $security_key = get_option( 'woocommerce_nmi_private_key' );


            // curl request for insert customer information's
            $curl     = curl_init();
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

add_action( 'woocommerce_checkout_order_processed', 'add_plane_api', 10, 1 );
function add_plane_api() {

    $curl = curl_init();
    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL            => 'https://secure.nmi.com/api/transact.php?security_key=H24zBu3uC7rn3JR7uY86NqhQH6TZCzkc&recurring=add_plan&plan_payments=0&plan_amount=20.99&plan_name=jalal&plan_id=2565&month_frequency=18&day_of_month=31',
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
}


class Xpay_Payment_Gateway {

    // Define gateway variables
    public $payment_method;
    public $subscription_period;
    public $product_type;
    public $billing_interval;
    public $day_of_month;
    public $plane_amount;
    public $currency;
    public $payment_type;

    // Define billing information variable
    public $billing_first_name;
    public $billing_last_name;
    public $billing_company;
    public $billing_address_1;
    public $billing_address_2;
    public $billing_city;
    public $billing_state;
    public $billing_postcode;
    public $billing_country;
    public $billing_customer_email;
    public $billing_customer_phone;

    // Define Shipping information variable
    public $shipping_first_name;
    public $shipping_last_name;
    public $shipping_company;
    public $shipping_address_1;
    public $shipping_address_2;
    public $shipping_phone;
    public $shipping_city;
    public $shipping_state;
    public $shipping_postcode;
    public $shipping_country;

    // Define security key variable
    public $security_key;

    // Define card number and exp date variable
    public $cc_number;
    public $cc_exp;


    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'send_customer_information_to_api' ], 10, 1 );
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'send_plane_information_to_api' ], 10, 1 );
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'send_subscription_information_to_api' ], 10, 1 );
    }

    public function send_customer_information_to_api( $order_id ) {

        // Make sure WooCommerce is active
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
                    $this->product_type = wc_get_product( $product_id )->get_type();
                }


                // Check if the order is a subscription order
                if ( wcs_order_contains_subscription( $order ) ) {
                    // Get the subscription objects associated with the order
                    $subscriptions = wcs_get_subscriptions_for_order( $order );

                    // Loop through each subscription (assuming there's only one subscription per order)
                    foreach ( $subscriptions as $subscription ) {

                        // get the subscription data
                        $wc_data = $subscription->data;

                        // define small year and month to convert Year and Month
                        $s_year  = 'year';
                        $s_month = 'month';

                        // get payment period and time
                        $billing_interval_inner = $wc_data['billing_interval'];
                        $billing_period_inner   = $wc_data['billing_period'];

                        $this->billing_interval = $billing_interval_inner;

                        // get payment method and total amount
                        $this->payment_method = $wc_data['payment_method'];
                        $this->plane_amount   = $wc_data['total'];

                        // concat the interval and period
                        $this->subscription_period = $billing_interval_inner . ' ' . $billing_period_inner;

                        if ( strpos( $this->subscription_period, $s_year ) ) {
                            $this->subscription_period = str_replace( $s_year, 'Years', $this->subscription_period );
                        } else if ( strpos( $this->subscription_period, $s_month ) ) {
                            $this->subscription_period = str_replace( $s_month, 'Months', $this->subscription_period );
                        }

                    }
                }

                // get billing information
                $this->billing_first_name     = $customer_data->billing_first_name;
                $this->billing_last_name      = $customer_data->billing_last_name;
                $this->billing_company        = $customer_data->billing_company;
                $this->billing_address_1      = $customer_data->billing_address_1;
                $this->billing_address_2      = $customer_data->billing_address_2;
                $this->billing_city           = $order->get_billing_city();
                $this->billing_state          = $order->get_billing_state();
                $this->billing_postcode       = $order->get_billing_postcode();
                $this->billing_country        = $order->get_billing_country();
                $this->billing_customer_email = $order->get_billing_email();
                $this->billing_customer_phone = $order->get_billing_phone();

                // Get shipping information
                $this->shipping_first_name = $customer_data->shipping_first_name;
                $this->shipping_last_name  = $customer_data->shipping_last_name;
                $this->shipping_company    = $customer_data->shipping_company;
                $this->shipping_address_1  = $customer_data->shipping_address_1;
                $this->shipping_address_2  = $customer_data->shipping_address_2;
                $this->shipping_phone      = $customer_data->shipping_phone;
                $this->shipping_city       = $order->get_shipping_city();
                $this->shipping_state      = $order->get_shipping_state();
                $this->shipping_postcode   = $order->get_shipping_postcode();
                $this->shipping_country    = $order->get_shipping_country();

                // get security key
                $this->security_key = get_option( 'woocommerce_nmi_private_key' );

                // curl request for insert customer information's
                $curl     = curl_init();
                $curl_url = 'https://propelr.transactiongateway.com/api/transact.php'
                    . '?customer_vault=add_customer'
                    . '&security_key=' . urlencode( string: $this->security_key )
                    . '&ccnumber=4111111111111111'
                    . '&ccexp=10%2F25'
                    . '&currency=USD'
                    . '&payment=creditcard'
                    . '&orderid=' . urlencode( $this->order_id )
                    . '&merchant_defined_field_=merchant_defined_field_1%3DValue'
                    . '&first_name=' . urlencode( $this->billing_first_name )
                    . '&last_name=' . urlencode( $this->billing_last_name )
                    . '&address1=' . urlencode( $this->billing_address_1 )
                    . '&address2=' . urlencode( $this->billing_address_2 )
                    . '&city=' . urlencode( $this->billing_city )
                    . '&state=' . urlencode( $this->billing_state )
                    . '&zip=' . urlencode( $this->billing_postcode )
                    . '&country=' . urlencode( $this->billing_country )
                    . '&phone=' . urlencode( $this->billing_customer_phone )
                    . '&email=' . urlencode( $this->billing_customer_email )
                    . '&company=' . urlencode( $this->billing_company )
                    . '&shipping_firstname=' . urlencode( $this->shipping_first_name )
                    . '&shipping_lastname=' . urlencode( $this->shipping_last_name )
                    . '&shipping_company=' . urlencode( $this->shipping_company )
                    . '&shipping_address1=' . urlencode( $this->shipping_address_1 )
                    . '&shipping_address2=' . urlencode( $this->shipping_address_2 )
                    . '&shipping_city=' . urlencode( $this->shipping_city )
                    . '&shipping_state=' . urlencode( $this->shipping_state )
                    . '&shipping_zip=' . urlencode( $this->shipping_postcode )
                    . '&shipping_country=' . urlencode( $this->shipping_country )
                    . '&shipping_phone=' . urlencode( $this->shipping_phone )
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
                echo '<h4>' . $response . '</h4>';

            } else {
                echo 'Order not found.';
            }
        } else {
            echo 'WooCommerce is not active.';
        }
    }

    public function send_plane_information_to_api( $order_id ) {

        // check condition for month_frequency
        if ( '1 Years' == $this->subscription_period ) {
            $this->billing_interval = 12;
        } else if ( '2 Years' == $this->subscription_period ) {
            $this->billing_interval = 24;
        } else if ( '3 Years' == $this->subscription_period ) {
            $this->billing_interval = 36;
        }


        $curl = curl_init();

        $curl_url = 'https://secure.nmi.com/api/transact.php'
            . '?security_key=' . urlencode( $this->security_key )
            . '&recurring=add_plan'
            . '&plan_payments=0'
            . '&plan_amount=' . urlencode( $this->plane_amount )
            . '&plan_name=' . urlencode( $this->subscription_period )
            . '&plan_id=' . urlencode( $order_id )
            . '&month_frequency=' . urlencode( $this->billing_interval )
            . '&day_of_month=31';


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
        echo '<h4>' . $response . '</h4>';
    }

    public function send_subscription_information_to_api( $order_id ) {
        // codeHere;
    }
}