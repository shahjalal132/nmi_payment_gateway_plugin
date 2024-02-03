<?php

add_action( 'woocommerce_checkout_order_processed', 'send_customer_information_to_api', 10, 1 );
function send_customer_information_to_api( $order_id ) {
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL            => 'https://propelr.transactiongateway.com/api/transact.php?customer_vault=add_customer&security_key=H24zBu3uC7rn3JR7uY86NqhQH6TZCzkc&ccnumber=4111111111111111&ccexp=10%2F25&currency=USD&payment=creditcard&orderid=5089&merchant_defined_field_=merchant_defined_field_1%3DValue&first_name=firstJalal&last_name=lastShah&address1=47-010%20Okana%20Pl&city=Kaneohe&state=HI&zip=96744&country=US&phone=8083819361&email=jalal%40gmail.com&company=xpay&address2=line%202&shipping_firstname=Shipping%20first%20name&shipping_lastname=Shipping%20last%20name&shipping_company=Shipping%20company&shipping_address1=Shipping%20address&shipping_address2=Shipping%20address%2C%20line%202&shipping_city=Shipping%20city&shipping_state=Shipping%20state&shipping_zip=Shipping%20postal%20code&shipping_country=Shipping%20country%20code&shipping_phone=Shipping%20phone%20number&acu_enabled=true',
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


add_action( 'woocommerce_checkout_order_processed', 'send_subscription_information_to_api', 10, 1 );
function send_subscription_information_to_api( $order_id ) {
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL            => 'https://secure.nmi.com/api/transact.php?recurring=add_subscription&plan_payments=0&plan_amount=111&security_key=H24zBu3uC7rn3JR7uY86NqhQH6TZCzkc&ccnumber=4111111111111111&ccexp=10%2F25&payment=creditcard&checkname=firstnew&checkaccount=24413815&checkaba=490000018&account_type=savings&currency=USD&account_holder_type=personal&sec_code=PPD&first_name=FirstJalal&last_name=lasJalal&address1=New%20York%20City&city=New%20York%20City&state=New%20York%20City&zip=53014&country=us&phone=%2B18143511255&email=test%40gmail.com&company=imjol&address2=Card%20billing%20address%2C%20line%202&fax=123&orderid=5089&order_description=Order%20Description&day_of_month=31&customer_receipt=true&paused_subscription=true&acu_enabled=true&month_frequency=11',
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

add_action( 'woocommerce_checkout_order_processed', 'send_plane_information_to_api', 10, 1 );
function send_plane_information_to_api( $order_id ) {
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL            => 'https://secure.nmi.com/api/transact.php?security_key=H24zBu3uC7rn3JR7uY86NqhQH6TZCzkc&recurring=add_plan&plan_payments=0&plan_amount=15.99&plan_name=jalal2&plan_id=5089&month_frequency=20&day_of_month=31',
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

