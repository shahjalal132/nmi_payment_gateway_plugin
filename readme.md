=== NMI Payment Gateway For WooCommerce (Enterprise) ===  
Contributors: Pledged Plugins  
Tags: woocommerce Network Merchants (NMI), Network Merchants (NMI), payment gateway, woocommerce, woocommerce payment gateway, recurring payments, subscriptions, pre-orders  
Plugin URI: https://pledgedplugins.com/products/nmi-payment-gateway-woocommerce/  
Requires at least: 4.0  
Tested up to: 6.4  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

This Payment Gateway For WooCommerce extends the functionality of WooCommerce to accept payments from credit/debit cards using the NMI payment gateway. Since customers will be entering credit cards directly on your store you should sure that your checkout pages are protected by SSL.

== Description ==

`NMI Payment Gateway for WooCommerce` allows you to accept credit cards directly on your WooCommerce store by utilizing the NMI payment gateway.

= Features =

1. Accept Credit Cards directly on your website by using the NMI gateway.
2. No redirecting your customer back and forth.
3. Very easy to install and configure. Ready in Minutes!
4. Supports WooCommerce Subscriptions and WooCommerce Pre-Orders add-on from WooCommerce.com.
5. Safe and secure method to process credit cards using the NMI payment gateway.
6. Internally processes credit cards, safer, quicker, and more secure!

If you need any assistance with this or any of our other plugins, please visit our support portal:  
https://pledgedplugins.com/support

== Installation ==

Easy steps to install the plugin:

1. Upload `woocommerce-gateway-nmi` folder/directory to the `/wp-content/plugins/` directory.
2. Activate the plugin (WordPress -> Plugins).
3. Go to the WooCommerce settings page (WordPress -> WooCommerce -> Settings) and select the Payments tab.
4. Under the Payments tab, you will find all the available payment methods. Find the 'NMI' link in the list and click it.
5. On this page you will find all of the configuration options for this payment gateway.
6. Enable the method by using the checkbox.
7. Enter the NMI API keys (Private Key and Public Key).

That's it! You are ready to accept credit cards with your NMI payment gateway now connected to WooCommerce.

`Is SSL Required to use this plugin?`  
A valid SSL certificate is required to ensure your customer credit card details are safe and make your site PCI DSS compliant. This plugin does not store the customer credit card numbers or sensitive information on your website.

`Does the plugin support direct updates from the WP dashboard?`  
Yes. You can navigate to WordPress -> Tools -> WooCommerce NMI License page and activate the license key you received with your order. Once that is done you will be able to directly update the plugin to the latest version from the WordPress dashboard itself.

== Changelog ==

3.3.8  
Updated "WC tested up to" header to 8.3  
Updated compatibility info to WordPress 6.4  
Declared incompatibility with cart and checkout blocks  

3.3.7  
Fixed more 3DS processing issues  
Updated "WC tested up to" header to 8.2  

3.3.6  
Fixed issue with 3DS payment processing  
Saved card or account number last4 and card type to order meta  
Showed card or account details on edit order page  
Updated compatibility info to WordPress 6.3  
Updated "WC tested up to" header to 8.0  

3.3.5  
Made compatible with LaForat theme  
Added filter to force save card and accounts  
Sending address fields in 3DS request only if they exist  
Saved and passed 3DS variables in all requests for the card used  
Updated "WC tested up to" header to 7.7  

3.3.4  
Made compatible with WooCommerce HPOS  
Fixed captured payments being voided on cancelling orders  
Updated compatibility info to WordPress 6.2  
Updated "WC tested up to" header to 7.5  

3.3.3  
Added option to set new customer cards or account details on checkout as their default payment method  
Updated "WC tested up to" header to 7.3  

3.3.2  
Made compatible with Avada Checkout  
Made compatible with Woolentor plugin  
Fixed issues with 3DS transaction processing  
Removed state from 3DS request outside US and Canada  
Updated "WC tested up to" header to 7.0  
Updated compatibility info to WordPress 6.1  

3.3.1  
Added additional error handling  
Updated "WC tested up to" header to 6.9  

3.3.0  
Added support for 3D Secure transactions  
Updated "WC tested up to" header to 6.7  
Fixed PHP notices  

3.2.0  
Made compatible with CheckoutWC plugin  
Added AVS and CVV responses to order notes  
Capture or void payment from any status if the order is authorized  
Saved "authorization_code" from transaction response to order meta  
Disabled amount 1.00 sale and refund for adding accounts to vault  
Updated "WC tested up to" header to 6.6  
Updated compatibility info to WordPress 6.0  
General code clean up  

3.1.5  
Fixed occasional fatal error during WooCommerce upgrade  

3.1.4  
Fixed issues with changing payment method on subscriptions  

3.1.3  
Added filter on error message displayed at checkout  
Added error code alongside the failed transaction response reason in order notes  
Added shipping and tax amounts to gateway requests  
Added shipping fields to gateway request  
Updated "WC tested up to" header to 4.9  
Updated minimum WC version to 3.3  
Tested with WordPress 5.6  

3.1.2  
Print failed transaction response reason for subscription and pre-order in order notes  
Updated "WC tested up to" header to 4.6  

3.1.1  
Sanitized user input in POST variable  
Print failed transaction response reason in order notes  
Updated "WC tested up to" header to 4.3  
Fixed order line items  

3.1.0  
Made PCI compliant by adding Collect.js tokenization  
Added functionality for processing transactions via API keys  
Set up environment check and global notices  

3.0.0  
Modified nmi_request() method to use wp_remote_post() to make it fully GPL compatible  
Removed NMI SDK files  
Added filters for NMI request parameters and transaction POST URL  
Updated "WC tested up to" header to 4.0  

2.1.7  
Updated "WC tested up to" header to 3.9  

2.1.6  
Made compatible with WooCommerce Sequential Order Numbers Pro  
Fixed order status not changing to Failed on decline  
Updated "WC tested up to" header to 3.8  

2.1.5  
Updated "WC tested up to" header to 3.7  

2.1.4  
Removed $_POST fields from being sent in gateway requests  
Removed currency restrictions  
Passed sec_code to gateway request for ACH transactions  

2.1.3  
Fixed subscription update payment method not working  
Replaced deprecated function "reduce_order_stock" with "wc_reduce_stock_levels"  

2.1.2  
Updated "WC tested up to" header to 3.6  
Fixed repeat failed transactions on renewal orders  

2.1.1  
Fixed conflicts with other echeck gateways  
Fixed log message and changed logging descriptions  

2.1.0  
Implemented full ACH support  
Fixed PHP notices  
Changed logging method  
Updated post meta saving method  
Removed deprecated script code  
Prevented the "state" parameter from being sent in "refund", "capture" or "void" transactions  

2.0.7  
Integrated auto-update API  

2.0.6  
Added GDPR retention setting and logic  
Fixed false negative on SSL warning notice in admin.  

2.0.5  
Added GDPR privacy support  
Added "minimum required" and "tested upto" headers  

2.0.4  
Added JCB, Maestro and Diners Club as options for allowed card types  
Made state field default to "NA" to support countries without a state  
Fixed tokenization issues when customer enters card expiry date in MM/YYYY format  
Removed deprecated code  

2.0.3  
Added option to choose the API method for adding customers to the gateway vault  
Passed billing details to "Pay for Order" page  

2.0.2  
Added option to restrict card types  
Made gateway receipt emails optional  

2.0.1  
Fixed issue with decline error handling for subscriptions and pre-orders  

2.0.0  
Made compatible with WooCommerce 3.0.0  

1.1.0  
Added subscription payment retry callbacks  
Implemented tokenization API, and removed legacy method of saving cards  

1.0.1  
Fixed an issue with PHP7  
Added validation for empty checkout form  
Added "Save to account" option to the checkout form for the customers  

1.0.0  
Initial release version