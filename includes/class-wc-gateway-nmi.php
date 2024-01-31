<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_NMI class.
 *
 * @extends WC_Payment_Gateway_CC
 */

class WC_Gateway_NMI extends WC_Payment_Gateway_CC {

    public $testmode;
	public $api_keys;
	public $capture;
	public $saved_cards;
	public $new_card_default;
	public $add_customer_method;
	public $private_key;
	public $public_key;
	public $enable_3ds;
	public $checkout_key;
	public $username;
	public $password;
	public $googlepay_enable;
	public $applepay_enable;
	public $googlepay_billing_shipping;
	public $logging;
	public $debugging;
	public $line_items;
	public $allowed_card_types;
	public $customer_receipt;

    const NMI_REQUEST_URL_LOGIN = 'https://secure.networkmerchants.com/api/transact.php';
    const NMI_REQUEST_URL_API_KEYS = 'https://secure.nmi.com/api/transact.php';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'nmi';
		$this->method_title         = __( 'NMI', 'wc-nmi' );
		$this->method_description   = __( 'NMI works by adding credit card fields on the checkout and then sending the details to NMI for verification. It fully supports WooCommerce Subscriptions and WooCommerce Pre-Orders plugins.', 'wc-nmi' );
		$this->has_fields           = true;
		$this->supports             = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change', // Subs 1.n compatibility
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'multiple_subscriptions',
			'pre-orders',
            'add_payment_method',
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                      = $this->get_option( 'title' );
		$this->description                = $this->get_option( 'description' );
		$this->enabled                    = $this->get_option( 'enabled' );
		$this->testmode                   = $this->get_option( 'testmode' ) === 'yes';
		$this->api_keys                   = $this->get_option( 'api_keys' ) === 'yes';
		$this->capture                    = $this->get_option( 'capture', 'yes' ) === 'yes';
		$this->saved_cards                = $this->get_option( 'saved_cards' ) === 'yes';
		$this->new_card_default           = $this->saved_cards && $this->get_option( 'new_card_default' ) === 'yes';
		$this->add_customer_method        = $this->get_option( 'add_customer_method' );
		$this->private_key                = $this->get_option( 'private_key' );
		$this->public_key                 = $this->get_option( 'public_key' );
		$this->enable_3ds                 = $this->get_option( 'enable_3ds' ) === 'yes';
		$this->checkout_key               = $this->get_option( 'checkout_key' );
		$this->username                   = $this->get_option( 'username' );
		$this->password                   = $this->get_option( 'password' );
		$this->googlepay_enable           = $this->get_option( 'googlepay_enable' ) === 'yes';
		$this->applepay_enable            = $this->get_option( 'applepay_enable' ) === 'yes';
		$this->googlepay_billing_shipping = $this->get_option( 'googlepay_billing_shipping' ) === 'yes';
		$this->logging                    = $this->get_option( 'logging' ) === 'yes';
		$this->debugging                  = $this->get_option( 'debugging' ) === 'yes';
		$this->line_items                 = $this->get_option( 'line_items' ) === 'yes';
		$this->allowed_card_types         = $this->get_option( 'allowed_card_types', array() );
		$this->customer_receipt           = $this->get_option( 'customer_receipt' ) === 'yes';

		if ( $this->testmode ) {
			$this->description .= ' ' . sprintf( __( '<br /><br /><strong>TEST MODE ENABLED</strong><br /> In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date or check the documentation "<a href="%s">NMI Direct Post API</a>" for more card numbers.', 'wc-nmi' ), 'https://secure.nmi.com/merchants/resources/integration/download.php?document=directpost' );
			$this->description  = trim( $this->description );
		}

		// Hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );
		//if( $this->googlepay_billing_shipping ) {
		//	add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'display_payment_request_button_html' ), 1 );
		//} else {
		//	add_action( 'woocommerce_review_order_after_submit', array( $this, 'display_payment_request_button_html' ), 1 );
		//}

	}

	/**
	 * get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon = '';
		if( in_array( 'visa', $this->allowed_card_types ) ) {
			$icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa.svg' ) . '" alt="Visa" width="32" />';
		}
		if( in_array( 'mastercard', $this->allowed_card_types ) ) {
			$icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard.svg' ) . '" alt="Mastercard" width="32" />';
		}
		if( in_array( 'amex', $this->allowed_card_types ) ) {
			$icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex.svg' ) . '" alt="Amex" width="32" />';
		}
		if( in_array( 'discover', $this->allowed_card_types ) ) {
			$icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover.svg' ) . '" alt="Discover" width="32" />';
		}
		if( in_array( 'diners-club', $this->allowed_card_types ) ) {
			$icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners.svg' ) . '" alt="Diners Club" width="32" />';
		}
		if( in_array( 'jcb', $this->allowed_card_types ) ) {
			$icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb.svg' ) . '" alt="JCB" width="32" />';
		}
		if( in_array( 'maestro', $this->allowed_card_types ) ) {
			$icon .= '<img style="margin-left: 0.3em" src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/maestro.svg' ) . '" alt="Maestro" width="32" />';
		}
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( $this->enabled == 'no' ) {
			return;
		}

		// Check required fields
		if ( ! $this->api_keys && ! $this->username ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your Username <a href="%s">here</a>', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi' ) ) . '</p></div>';
			return;

		} elseif ( ! $this->api_keys && ! $this->password ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your Password <a href="%s">here</a>', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi' ) ) . '</p></div>';
			return;
		}

        if ( $this->api_keys && ! $this->private_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your Private Key <a href="%s">here</a>', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi' ) ) . '</p></div>';
			return;
		}

		if ( $this->enable_3ds && ! $this->checkout_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your Public Checkout Key <a href="%s">here</a>', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi' ) ) . '</p></div>';
			return;
		}

		// Simple check for duplicate keys
		if ( ! $this->api_keys && $this->username == $this->password ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Your Username and Password match. Please check and re-enter.', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi' ) ) . '</p></div>';
			return;
		}

		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
		if ( ! wc_checkout_is_https() ) {
			echo '<div class="notice notice-warning"><p>' . sprintf( __( 'NMI is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'wc-nmi' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>';
 		}

	}

    public function admin_options() { ?>
		<script>
            //alert(123);
            jQuery( function( $ ) {
                'use strict';

                /**
                 * Object to handle NMI admin functions.
                 */
                let wc_nmi_admin = {
                    isAPIKey: function() {
                        return $( '#woocommerce_nmi_api_keys' ).is( ':checked' );
                    },

                    /**
                     * Initialize.
                     */
                    init: function() {
						$( document.body ).on( 'change', '#woocommerce_nmi_api_keys', function() {
							let field_username = $( '#woocommerce_nmi_username' ).parents( 'tr' ).eq( 0 ),
					            field_password = $( '#woocommerce_nmi_password' ).parents( 'tr' ).eq( 0 ),
					            field_private_key = $( '#woocommerce_nmi_private_key' ).parents( 'tr' ).eq( 0 ),
					            field_public_key = $( '#woocommerce_nmi_public_key' ).parents( 'tr' ).eq( 0 ),
					            field_enable_3ds = $( '#woocommerce_nmi_enable_3ds' ).parents( 'tr' ).eq( 0 ),
					            field_checkout_key = $( '#woocommerce_nmi_checkout_key' ).parents( 'tr' ).eq( 0 );
					            //field_googlepay_enable = $( '#woocommerce_nmi_googlepay_enable' ).parents( 'tr' ).eq( 0 ),
					            //field_applepay_enable = $( '#woocommerce_nmi_applepay_enable' ).parents( 'tr' ).eq( 0 ),
								//field_googlepay_billing_shipping = $( '#woocommerce_nmi_googlepay_billing_shipping' ).parents( 'tr' ).eq( 0 );

							if ( $( this ).is( ':checked' ) ) {
								field_private_key.show();
                                field_public_key.show();
                                field_enable_3ds.show();
								if ( $( '#woocommerce_nmi_enable_3ds' ).is( ':checked' ) ) {
									field_checkout_key.show();
								}
                                //field_googlepay_enable.show();
                                //field_applepay_enable.show();
								/*if ( $( '#woocommerce_nmi_googlepay_enable' ).is( ':checked' ) ) {
                                    field_googlepay_billing_shipping.show();
								}*/
                               field_username.hide();
                               field_password.hide();
							} else {
								field_private_key.hide();
								field_public_key.hide();
								field_username.show();
								field_password.show();
								field_enable_3ds.hide();
								field_checkout_key.hide();
                                //field_googlepay_enable.hide();
                                //field_applepay_enable.hide();
                                //field_googlepay_billing_shipping.hide();
                            }
                        } );

						$( document.body ).on( 'change', '#woocommerce_nmi_enable_3ds', function() {
							let field_checkout_key = $( '#woocommerce_nmi_checkout_key' ).parents( 'tr' ).eq( 0 );
							if ( $( this ).is( ':checked' ) ) {
								field_checkout_key.show();
							} else {
								field_checkout_key.hide();
                            }
						});

						/*$( document.body ).on( 'change', '#woocommerce_nmi_googlepay_enable', function() {
							let field_googlepay_billing_shipping = $( '#woocommerce_nmi_googlepay_billing_shipping' ).parents( 'tr' ).eq( 0 );
							if ( $( this ).is( ':checked' ) ) {
								field_googlepay_billing_shipping.show();
							} else {
								field_googlepay_billing_shipping.hide();
                            }
						});*/

                        $( document.body ).on( 'change', '#woocommerce_nmi_saved_cards', function() {
                            let field_new_card_default = $( '#woocommerce_nmi_new_card_default' ).parents( 'tr' ).eq( 0 );
                            if ( $( this ).is( ':checked' ) ) {
                                field_new_card_default.show();
                            } else {
                                field_new_card_default.hide();
                            }
                        });

                        $( '#woocommerce_nmi_saved_cards' ).change();
                        $( '#woocommerce_nmi_enable_3ds' ).change();
                        //$( '#woocommerce_nmi_googlepay_enable' ).change();
                        //$( '#woocommerce_nmi_applepay_enable' ).change();
						$( '#woocommerce_nmi_api_keys' ).change();
                    }
                };

                wc_nmi_admin.init();
            } );
        </script>
		<?php parent::admin_options();
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( $this->enabled == "yes" ) {
			if ( is_add_payment_method_page() && ! $this->saved_cards ) {
				return false;
			}
			// Required fields check
			if ( ! $this->api_keys && ( ! $this->username || ! $this->password ) ) {
				return false;
			}

            if ( $this->api_keys && ! $this->private_key ) {
				return false;
			}
			return true;
		}
		return parent::is_available();
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters( 'wc_nmi_settings', array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'wc-nmi' ),
				'label'       => __( 'Enable NMI', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'wc-nmi' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-nmi' ),
				'default'     => __( 'Credit card - NMI', 'wc-nmi' )
			),
			'description' => array(
				'title'       => __( 'Description', 'wc-nmi' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'wc-nmi' ),
				'default'     => __( 'Pay with your credit card via NMI.', 'wc-nmi' )
			),
			'testmode' => array(
				'title'       => __( 'Test mode', 'wc-nmi' ),
				'label'       => __( 'Enable Test Mode', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode. This will display test information on the checkout page.', 'wc-nmi' ),
				'default'     => 'yes'
			),
			'api_keys' => array(
				'title'       => __( 'API Keys', 'wc-nmi' ),
				'label'       => __( 'Enable Authentication via API keys instead of login credentials.', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'RECOMMENDED! This ensures you are using the most updated API method. If you disable this, the plugin will process via a legacy method and will need you to enter your login username and password.', 'wc-nmi' ),
				'default'     => 'yes'
			),
            'private_key' => array(
				'title'       => __( 'Private Key', 'wc-nmi' ),
				'type'        => 'password',
				'description' => __( 'Used for authenticating transactions. Make sure the private key you enter here has "API" permission enabled.', 'wc-nmi' ),
				'default'     => ''
			),
            'public_key' => array(
				'title'       => __( 'Public Tokenization Key', 'wc-nmi' ),
				'type'        => 'text',
				'description' => __( 'Used for Collect.js tokenization for PCI compliance. Leave it empty ONLY if you are facing Javascript issues at checkout and the plugin will default to Direct Post method.', 'wc-nmi' ),
				'default'     => ''
			),
			'enable_3ds' => array(
				'title'       => __( '3D Secure 2.0', 'wc-nmi' ),
				'label'       => __( 'Enable 3D Secure 2.0.', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( '3D Secure 2.0 can help you avoid fraudulent transactions by authenticating transactions before submitting them to the gateway for processing.', 'wc-nmi' ),
				'default'     => ''
			),
			'checkout_key' => array(
				'title'       => __( 'Public Checkout Key', 'wc-nmi' ),
				'type'        => 'text',
				'description' => __( 'Used for 3D Secure 2.0 authentication.', 'wc-nmi' ),
				'default'     => ''
			),
			'username' => array(
				'title'       => __( 'Gateway Username', 'wc-nmi' ),
				'type'        => 'text',
				'description' => __( 'Legacy API method. Enter your NMI account username.', 'wc-nmi' ),
				'default'     => ''
			),
			'password' => array(
				'title'       => __( 'Gateway Password', 'wc-nmi' ),
				'type'        => 'password',
				'description' => __( 'Legacy API method. Enter your NMI account password.', 'wc-nmi' ),
				'default'     => ''
			),
			'capture' => array(
				'title'       => __( 'Capture', 'wc-nmi' ),
				'label'       => __( 'Capture charge immediately', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later.', 'wc-nmi' ),
				'default'     => 'yes'
			),
			'saved_cards' => array(
				'title'       => __( 'Saved Cards', 'wc-nmi' ),
				'label'       => __( 'Enable Payment via Saved Cards', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on NMI servers, not on your store.', 'wc-nmi' ),
				'default'     => 'no'
			),
			'new_card_default' => array(
				'title'       => '',
				'label'       => __( 'Set New Card as the Default Payment Method', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'The default payment method is automatically selected at checkout so this can help prevent customers from entering the card details they previously saved.', 'wc-nmi' ),
				'default'     => 'no'
			),
			'add_customer_method' => array(
				'title'       => __( 'Add Customer Method', 'wc-nmi' ),
				'type'		  => 'select',
				'description' => sprintf( __( 'Choose the API Method to use when adding customers to the gateway vault. The "auth" method will authorize the customer\'s card for %s 1.00 and will quickly void it when it is added to the vault so you must only select it in case your processor does not support "validate". If you have trouble signing up for subscriptions, adding or saving payment methods using "validate" then give "auth" a try.', 'wc-nmi' ), get_woocommerce_currency() ),
				'options' => array(
					'validate'	=> __( 'validate', 'wc-nmi' ),
					'auth'      => __( 'auth', 'wc-nmi' ),
				),
				'default'	  => 'validate',
				'css'    	  => 'min-width:100px;',
			),
			/*'googlepay_enable' => array(
				'title'       => __( 'Google Pay', 'wc-nmi' ),
				'label'       => __( 'Enable Payment via Google Pay', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay via Google Pay when available during checkout. Does not support subscriptions or saving payment method to vault.', 'wc-nmi' ),
				'default'     => 'yes'
			),
			'applepay_enable' => array(
				'title'       => __( 'Apple Pay', 'wc-nmi' ),
				'label'       => __( 'Enable Payment via Apple Pay', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay via Apple Pay when available during checkout. Does not support subscriptions or saving payment method to vault.', 'wc-nmi' ),
				'default'     => 'yes'
			),
			'googlepay_billing_shipping' => array(
				'title'       => '',
				'label'       => __( 'Get billing and shipping address from Google Pay', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, Google Pay option will display on top of the checkout page and allow skipping the rest of the checkout page. If not the Google Pay button will display at the bottom, and the customer will need to fill billing and shipping details in the checkout form.', 'wc-nmi' ),
				'default'     => 'yes'
			),*/
			'logging' => array(
				'title'       => __( 'Logging', 'wc-nmi' ),
				'label'       => __( 'Log debug messages', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( 'Save debug messages to the WooCommerce System Status log file <code>%s</code>.', 'wc-nmi' ), WC_Log_Handler_File::get_log_file_path( 'wc-nmi' ) ),
				'default'     => 'no'
			),
			'debugging' => array(
				'title'       => __( 'Gateway Debug', 'wc-nmi' ),
				'label'       => __( 'Log gateway requests and response to the WooCommerce System Status log.', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( '<strong>CAUTION! Enabling this option will write gateway requests including card numbers and CVV to the logs.</strong> Do not turn this on unless you have a problem processing credit cards. You must only ever enable it temporarily for troubleshooting or to send requested information to the plugin author. It must be disabled straight away after the issues are resolved and the plugin logs should be deleted.', 'wc-nmi' ) . ' ' . sprintf( __( '<a href="%s">Click here</a> to check and delete the full log file.', 'wc-nmi' ), admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . WC_Log_Handler_File::get_log_file_name( 'woocommerce-gateway-nmi' ) ) ),
				'default'     => 'no'
			),
			'line_items' => array(
				'title'       => __( 'Line Items', 'wc-nmi' ),
				'label'       => __( 'Enable Line Items', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'Add line item data to description sent to the gateway (eg. Item x qty).', 'wc-nmi' ),
				'default'     => 'no'
			),
			'allowed_card_types' => array(
				'title'       => __( 'Allowed Card types', 'wc-nmi' ),
				'class'       => 'wc-enhanced-select',
				'type'        => 'multiselect',
				'description' => __( 'Select the card types you want to allow payments from.', 'wc-nmi' ),
				'default'     => array( 'visa','mastercard','discover','amex' ),
				'options'	  => array(
					'visa' 			=> __( 'Visa', 'wc-nmi' ),
					'mastercard' 	=> __( 'MasterCard', 'wc-nmi' ),
					'discover' 		=> __( 'Discover', 'wc-nmi' ),
					'amex' 			=> __( 'American Express', 'wc-nmi' ),
					'diners-club' 	=> __( 'Diners Club', 'wc-nmi' ),
					'jcb' 			=> __( 'JCB', 'wc-nmi' ),
					'maestro' 		=> __( 'Maestro', 'wc-nmi' ),
				),
			),
			'customer_receipt' => array(
				'title'       => __( 'Receipt', 'wc-nmi' ),
				'label'       => __( 'Send Gateway Receipt', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, the customer will be sent an email receipt from NMI.', 'wc-nmi' ),
				'default'     => 'no'
			),
		) );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$display_tokenization = is_checkout() && $this->saved_cards;

		$total = WC()->cart->total;
		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$total = $order->get_total();
		}

        echo '<div class="nmi_new_card" id="nmi-payment-data"
			data-amount="' . esc_attr( $total ) . '">';

		if ( $this->description ) {
			echo apply_filters( 'wc_nmi_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		if ( $display_tokenization ) {
            $this->tokenization_script();
			$this->saved_payment_methods();
		}
        if( $this->api_keys && $this->public_key ) {
            $this->collect_js_form();
        } else {
            $this->form();
        }

        if ( $display_tokenization ) {
            $this->save_payment_method_checkbox();
        }

		echo '</div>';
	}

    public function collect_js_form() {
		?>
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>

			<!-- Used to display form errors -->
            <div class="nmi-source-errors" role="alert"></div>

            <div class="form-row form-row-wide"><div id="nmi-three-ds-mount-point"></div></div>

            <div class="form-row form-row-wide">
                <label for="nmi-card-number-element"><?php esc_html_e( 'Card Number', 'wc-nmi' ); ?> <span class="required">*</span></label>
                <div class="nmi-card-group">
                    <div id="nmi-card-number-element" class="wc-nmi-elements-field">
                    <!-- a NMI Element will be inserted here. -->
                    </div>

                    <i class="nmi-credit-card-brand nmi-card-brand" alt="Credit Card"></i>
                </div>
            </div>

            <div class="form-row form-row-first">
                <label for="nmi-card-expiry-element"><?php esc_html_e( 'Expiry Date', 'wc-nmi' ); ?> <span class="required">*</span></label>

                <div id="nmi-card-expiry-element" class="wc-nmi-elements-field">
                <!-- a NMI Element will be inserted here. -->
                </div>
            </div>

            <div class="form-row form-row-last">
                <label for="nmi-card-cvc-element"><?php esc_html_e( 'Card Code (CVC)', 'wc-nmi' ); ?> <span class="required">*</span></label>
            <div id="nmi-card-cvc-element" class="wc-nmi-elements-field">
            <!-- a NMI Element will be inserted here. -->
            </div>
            </div>
            <div class="clear"></div>

			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

    public function payment_scripts() {
		if ( ! $this->api_keys || ! $this->public_key || ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) ) {
			return;
		}

        add_filter( 'script_loader_tag', array( $this, 'add_public_key_to_js' ), 10, 2 );

		wp_enqueue_script( 'nmi-collect-js', 'https://secure.nmi.com/token/Collect.js', '', null, true );
        if( $this->enable_3ds ) {
	        wp_enqueue_script( 'nmi-gateway-js', 'https://secure.networkmerchants.com/js/v1/Gateway.js', '', null, true );
        }
		wp_enqueue_script( 'woocommerce_nmi', plugins_url( 'assets/js/nmi.js', WC_NMI_MAIN_FILE ), array( 'jquery-payment', 'nmi-collect-js' ), WC_NMI_VERSION . '.15', true );

		$nmi_params = array(
			'public_key'                  => $this->public_key,
			'enable_3ds'                  => $this->enable_3ds,
			'checkout_key'                => $this->checkout_key,
			'allowed_card_types'          => $this->allowed_card_types,
			'i18n_terms'                  => __( 'Please accept the terms and conditions first', 'wc-nmi' ),
			'i18n_required_fields'        => __( 'Please fill in required checkout fields first', 'wc-nmi' ),
			'card_disallowed_error'       => __( 'Card Type Not Accepted.', 'wc-nmi' ),
			'placeholder_cvc'             => __( 'CVC', 'woocommerce' ),
			'placeholder_expiry'          => __( 'MM / YY', 'woocommerce' ),
			'card_number_error'           => __( 'Invalid card number.', 'wc-nmi' ),
			'card_expiry_error'           => __( 'Invalid card expiry date.', 'wc-nmi' ),
			'card_cvc_error'              => __( 'Invalid card CVC.', 'wc-nmi' ),
			'card_3ds_challenge_message'  => __( 'Please complete the challenge and submit.', 'wc-nmi' ),
			'echeck_account_number_error' => __( 'Invalid account number.', 'wc-nmi' ),
			'echeck_account_name_error'   => __( 'Invalid account name.', 'wc-nmi' ),
			'needs_shipping_address'      => WC()->cart->needs_shipping_address(),
			'shipping_countries'          => WC()->cart->needs_shipping_address() ? array_keys( WC()->countries->get_shipping_countries() ) : array(),
			//'googlepay_enable'            => $this->should_show_express_payment_button() && $this->googlepay_enable,
			//'googlepay_billing_shipping'  => $this->should_show_express_payment_button() && $this->googlepay_billing_shipping,
			//'applepay_enable'             => $this->should_show_express_payment_button() && $this->applepay_enable,
			'echeck_routing_number_error' => __( 'Invalid routing number.', 'wc-nmi' ),
			'error_ref'                   => __( '(Ref: [ref])', 'wc-nmi' ),
			'timeout_error'               => __( 'The tokenization did not respond in the expected timeframe. Please make sure the fields are correctly filled in and submit the form again.', 'wc-nmi' ),
		);
        $nmi_params['is_checkout'] = ( is_checkout() && empty( $_GET['pay_for_order'] ) ) ? 'yes' : 'no'; // wpcs: csrf ok.
        $nmi_params['currency'] = get_woocommerce_currency(); // wpcs: csrf ok.

	    // If we're on the pay page we need to pass stripe.js the address of the order.
	    if ( $this->enable_3ds && isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) { // wpcs: csrf ok.
            global $wp;
		    $order_id = wc_clean( $wp->query_vars['order-pay'] ); // wpcs: csrf ok, sanitization ok, xss ok.
		    $order    = wc_get_order( $order_id );

		    if ( is_a( $order, 'WC_Order' ) ) {
			    $nmi_params['billing_email'] 	  = $order->get_billing_email();
			    $nmi_params['billing_first_name'] = $order->get_billing_first_name();
			    $nmi_params['billing_last_name']  = $order->get_billing_last_name();
			    $nmi_params['billing_address_1']  = $order->get_billing_address_1();
			    $nmi_params['billing_address_2']  = $order->get_billing_address_2();
			    $nmi_params['billing_state']      = $order->get_billing_state();
			    $nmi_params['billing_city']       = $order->get_billing_city();
			    $nmi_params['billing_postcode']   = $order->get_billing_postcode();
			    $nmi_params['billing_country']    = $order->get_billing_country();
			    $nmi_params['billing_phone']      = $order->get_billing_phone();
			    $nmi_params['currency'] 		  = $order->get_currency();
		    }
	    }

		wp_localize_script( 'woocommerce_nmi', 'wc_nmi_params', apply_filters( 'wc_nmi_params', $nmi_params ) );
	}

    public function add_public_key_to_js( $tag, $handle ) {
       if ( 'nmi-collect-js' !== $handle ) return $tag;

       return str_replace( ' src', ' data-tokenization-key="' . $this->public_key . '" src', $tag );
    }

    /**
	 * Returns a users saved tokens for this gateway.
	 * @since 1.1.0
	 * @return array
	 */
	public function get_tokens() {
		if ( sizeof( $this->tokens ) > 0 ) {
			return $this->tokens;
		}

		if ( is_user_logged_in() ) {
			$this->tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), $this->id );
		}

		return $this->tokens;
	}

    public function get_nmi_js_response() {
        if( !isset( $_POST['nmi_js_response'] ) ) {
            return false;
        }
		$response = json_decode( stripslashes( $_POST['nmi_js_response'] ), 1 );
        //print_r($response);
		return $response;
	}

	/**
	 * Process the payment
	 */
	public function process_payment( $order_id, $retry = true ) {

        $order       = wc_get_order( $order_id );
        $token_id 	 = isset( $_POST['wc-nmi-payment-token'] ) ? wc_clean( $_POST['wc-nmi-payment-token'] ) : '';
		$customer_id = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_nmi_customer_id', true ) : 0;

		if ( ! $customer_id || ! is_string( $customer_id ) ) {
			$customer_id = 0;
		}

		$this->log( "Info: Beginning processing payment for order $order_id for the amount of {$order->get_total()}" );

		$response = false;

		// Use NMI CURL API for payment
		try {
			$post_data = $three_ds_args = $billing_shipping = array();

			if ( $token_id !== 'new' && $token_id && $customer_id ) {
                $token = WC_Payment_Tokens::get( $token_id );

                if ( ! $token || $token->get_user_id() !== get_current_user_id() ) {
                    WC()->session->set( 'refresh_totals', true );
                    throw new Exception( __( 'Invalid payment method. Please input a new card number.', 'wc-nmi' ) );
                }

                $card_id = $token->get_token();
                if( $token->get_meta( 'initial_transaction_id' ) ) {
	                $three_ds_args = array(
		                'initial_transaction_id'      => $token->get_meta( 'initial_transaction_id' ),
		                'stored_credential_indicator' => 'used',
		                'initiated_by'                => 'customer',
		                'billing_method'              => 'recurring'
	                );
                } elseif( $token->get_meta( 'three_ds_args' ) ) {
					$three_ds_args = $token->get_meta( 'three_ds_args' );
				}

				$card_last4 = $token->get_last4();
				$card_type  = $token->get_card_type();
			}
			// Use token
			else {
				$maybe_saved_card = isset( $_POST['wc-nmi-new-payment-method'] ) && ! empty( $_POST['wc-nmi-new-payment-method'] );
				$card_id = 0;

				if( $js_response = $this->get_nmi_js_response() ) {
					//WC_NMI_Logger::log( sprintf( "JS response: %s", print_r( $js_response, 1 ) ) );

					$card_last4 = substr( $js_response['card']['number'], -4 );
					$card_type  = $this->get_card_type( str_replace( 'diners', 'diners-club', $js_response['card']['type'] ), 'name' );

					/*if( $this->googlepay_enable && $this->googlepay_billing_shipping && strtolower( $js_response['tokenType'] ) == 'googlepay' && ! empty( $js_response['wallet']['cardNetwork'] ) ) {
						$billing_shipping = array(
							'first_name'		=> $js_response['wallet']['billingInfo']['firstName'],
							'last_name'			=> $js_response['wallet']['billingInfo']['lastName'],
							'address1'			=> $js_response['wallet']['billingInfo']['address1'],
							'address2'			=> $js_response['wallet']['billingInfo']['address2'],
							'city'				=> $js_response['wallet']['billingInfo']['city'],
							'state'				=> $js_response['wallet']['billingInfo']['state'],
							'country'			=> $js_response['wallet']['billingInfo']['country'],
							'zip'				=> $js_response['wallet']['billingInfo']['postalCode'],
							'email' 			=> $js_response['wallet']['email'],
							'phone'				=> $js_response['wallet']['billingInfo']['phone'],
							'shipping_firstname' => $js_response['wallet']['shippingInfo']['firstName'],
							'shipping_lastname' => $js_response['wallet']['shippingInfo']['lastName'],
							'shipping_company' 	=> $js_response['wallet']['shippingInfo']['firstName'],
							'shipping_address1' => $js_response['wallet']['shippingInfo']['address1'],
							'shipping_address2' => $js_response['wallet']['shippingInfo']['address2'],
							'shipping_city' 	=> $js_response['wallet']['shippingInfo']['city'],
							'shipping_state' 	=> $js_response['wallet']['shippingInfo']['state'],
							'shipping_country'	=> $js_response['wallet']['shippingInfo']['country'],
							'shipping_zip' 		=> $js_response['wallet']['shippingInfo']['postalCode'],
						);
					} else*/
					if( isset( $js_response['three_ds_version'] ) ) {
						$three_ds_args['cavv']                = $js_response['cavv'];
						$three_ds_args['xid']                 = $js_response['xid'];
						$three_ds_args['eci']                 = $js_response['eci'];
						$three_ds_args['cardholder_auth']     = $js_response['cardholder_auth'];
						$three_ds_args['three_ds_version']    = $js_response['three_ds_version'];
						$three_ds_args['directory_server_id'] = $js_response['directory_server_id'];
					}
				} else {

					$card_last4 = substr( wc_clean( $_POST['nmi-card-number'] ), -4 );
					$card_type  = $this->get_card_type( wc_clean( $_POST['nmi-card-number'] ), 'pattern', 'name' );

                    // Check for CC details filled or not
                    if( empty( $_POST['nmi-card-number'] ) || empty( $_POST['nmi-card-expiry'] ) || empty( $_POST['nmi-card-cvc'] ) ) {
                        throw new Exception( __( 'Credit card details cannot be left incomplete.', 'wc-nmi' ) );
                    }

                    // Check for card type supported or not
                    if( ! in_array( $card_type, $this->allowed_card_types ) ) {
                        $this->log( sprintf( __( 'Card type being used is not one of supported types in plugin settings: %s', 'wc-nmi' ), $card_type ) );
                        throw new Exception( __( 'Card Type Not Accepted', 'wc-nmi' ) );
                    }
                }

				// Save token if logged in
				if ( apply_filters( 'wc_nmi_force_saved_card', ( is_user_logged_in() && $this->saved_cards && $maybe_saved_card ), $order_id ) ) {
					$customer_id = $this->add_customer( $order_id );
					if ( is_wp_error( $customer_id ) ) {
						throw new Exception( $customer_id->get_error_message() );
					} else {
						$this->add_card( $customer_id );
						if( strpos( $customer_id, ':' ) !== false ) {
							$vault_array = explode( ':', $customer_id );
							list( $customer_id, $initial_transaction_id ) = $vault_array;
							$three_ds_args = array(
								'initial_transaction_id'      => $initial_transaction_id,
								'stored_credential_indicator' => 'used',
								'initiated_by'                => 'customer',
                                'billing_method'              => 'recurring'
							);
						}
						$card_id = $customer_id;
					}
				} else {
                    if( isset( $js_response['token'] ) ) {
                        $post_data['payment_token'] = $js_response['token'];
                    } else {
                        $expiry = explode( ' / ', wc_clean( $_POST['nmi-card-expiry'] ) );
                        $expiry[1] = substr( $expiry[1], -2 );
                        $post_data['ccnumber']	= wc_clean( $_POST['nmi-card-number'] );
                        $post_data['ccexp']		= $expiry[0] . $expiry[1];
                        $post_data['cvv']		= wc_clean( $_POST['nmi-card-cvc'] );
                    }

					$customer_id = 0;
				}
			}
			// Store the ID in the order
			if ( $customer_id ) {
				$order->update_meta_data( '_nmi_customer_id', $customer_id );
			}
			if ( $card_id ) {
				$order->update_meta_data( '_nmi_card_id', $card_id );
			}
			if ( $three_ds_args ) {
				$order->update_meta_data( '_nmi_three_ds_args', $three_ds_args );
			}

			$description = sprintf( __( '%s - Order %s', 'wc-nmi' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );

			if( $this->line_items ) {
				$description .= ' (' . $this->get_line_items( $order ) . ')';
			}

			$payment_args = array(
				'orderid'	 		=> $order->get_order_number(),
				'order_description'	=> $description,
				'amount'			=> $order->get_total(),
				'shipping'			=> $order->get_shipping_total(),
				'tax'				=> $order->get_total_tax(),
				'transactionid'		=> $order->get_transaction_id(),
				'type'				=> $this->capture ? 'sale' : 'auth',
				'customer_vault_id' => $card_id ? $card_id : $customer_id,
				'currency'			=> $this->get_payment_currency( $order_id ),
			);

			if( empty( $billing_shipping ) ) {
				$billing_shipping = array(
					'first_name'		=> $order->get_billing_first_name(),
					'last_name'			=> $order->get_billing_last_name(),
					'address1'			=> $order->get_billing_address_1(),
					'address2'			=> $order->get_billing_address_2(),
					'city'				=> $order->get_billing_city(),
					'state'				=> $order->get_billing_state(),
					'country'			=> $order->get_billing_country(),
					'zip'				=> $order->get_billing_postcode(),
					'email' 			=> $order->get_billing_email(),
					'phone'				=> $order->get_billing_phone(),
					'company'			=> $order->get_billing_company(),
					'shipping_firstname' => $order->get_shipping_first_name(),
					'shipping_lastname' => $order->get_shipping_last_name(),
					'shipping_company' 	=> $order->get_shipping_company(),
					'shipping_address1' => $order->get_shipping_address_1(),
					'shipping_address2' => $order->get_shipping_address_2(),
					'shipping_city' 	=> $order->get_shipping_city(),
					'shipping_state' 	=> $order->get_shipping_state(),
					'shipping_country'	=> $order->get_shipping_country(),
					'shipping_zip' 		=> $order->get_shipping_postcode(),
				);
			}

			$payment_args = array_merge( $payment_args, $post_data, $three_ds_args, $billing_shipping );

			$payment_args = apply_filters( 'wc_nmi_request_args', $payment_args, $order );

			$response = $this->nmi_request( $payment_args );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			// Store charge ID
			$order->update_meta_data( '_nmi_charge_id', $response['transactionid'] );
			$order->update_meta_data( '_nmi_authorization_code', $response['authcode'] );
			$order->update_meta_data( '_nmi_card_last4', $card_last4 );
			$order->update_meta_data( '_nmi_card_type', $card_type );
            //$this->set_payment_method_title( $order, $card_last4, $card_type );

			if ( $response['response'] == 1 ) {
				$order->set_transaction_id( $response['transactionid'] );

				if( $payment_args['type'] == 'sale' ) {

					// Store captured value
					$order->update_meta_data( '_nmi_charge_captured', 'yes' );
					$order->update_meta_data( 'NMI Payment ID', $response['transactionid'] );

					// Payment complete
					$order->payment_complete( $response['transactionid'] );

					// Add order note
					$complete_message = trim( sprintf( __( "NMI charge complete (Charge ID: %s) %s %s", 'wc-nmi' ), $response['transactionid'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) );
					$order->add_order_note( $complete_message );
					$this->log( "Success: $complete_message" );

				} else {

					// Store captured value
					$order->update_meta_data( '_nmi_charge_captured', 'no' );

					if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
						wc_reduce_stock_levels( $order_id );
					}

					// Mark as on-hold
					$authorized_message = trim( sprintf( __( "NMI charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization. %s %s", 'wc-nmi' ), $response['transactionid'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) );
					$order->update_status( 'on-hold', $authorized_message . "\n" );
					$this->log( "Success: $authorized_message" );

				}

				$order->save();

			}

			// Remove cart
			WC()->cart->empty_cart();

			do_action( 'wc_gateway_nmi_process_payment', $response, $order );

			// Return thank you page redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);

		} catch ( Exception $e ) {
			wc_add_notice( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $e->getMessage() ), 'error' );
			$this->log( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $e->getMessage() ) );

			if( is_wp_error( $response ) && $response = $response->get_error_data() ) {
				$order->add_order_note( trim( sprintf( __( "NMI failure reason: %s %s %s", 'wc-nmi' ), $response['response_code'] . ' - ' . $response['responsetext'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) ) );
            }

			do_action( 'wc_gateway_nmi_process_payment_error', $e, $order );

			$order->update_status( 'failed' );

			return array(
				'result'   => 'fail',
				'redirect' => ''
			);

		}
	}

	public function get_line_items( $order ) {
		$line_items = array();
		// order line items
		foreach ( $order->get_items() as $item ) {
			$line_items[] = $item->get_name() . ' x ' .$item->get_quantity();
		}
		return implode( ', ', $line_items );
	}

	/**
	 * Refund a charge
	 * @param  int $order_id
	 * @param  float $amount
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() || $amount <= 0 ) {
			return false;
		}

		$this->log( "Info: Beginning refund for order $order_id for the amount of {$amount}" );

		$args = array(
			'amount'  			=> $amount,
			'transactionid'		=> $order->get_transaction_id(),
			'email' 			=> $order->get_billing_email(),
			'type'		 		=> 'refund',
			'order_description' => $reason,
			'currency'			=> $this->get_payment_currency( $order_id ),
		);

		$args = apply_filters( 'wc_nmi_request_args', $args, $order );

		$response = $this->nmi_request( $args );

		if ( is_wp_error( $response ) ) {
			$this->log( "Gateway Error: " . $response->get_error_message() );
			return $response;
		} elseif ( ! empty( $response['transactionid'] ) ) {
			$refund_message = sprintf( __( 'Refunded %s - Refund ID: %s - Reason: %s', 'wc-nmi' ), $amount, $response['transactionid'], $reason );
			$order->add_order_note( $refund_message );
			$order->save();
			$this->log( "Success: " . html_entity_decode( strip_tags( $refund_message ) ) );
			return true;
		}
	}

    /**
	 * Add a customer to NMI via the API.
	 *
	 * @param int $order_id
	 * @return int|WP_ERROR
	 */
	public function add_customer( $order_id = false ) {
		$order = wc_get_order( $order_id );
		$user_id = get_current_user_id();

        if( $js_response = $this->get_nmi_js_response() ) {
            $card_args = array(
                'payment_token' => $js_response['token'],
            );
	        if( isset( $js_response['three_ds_version'] ) ) {
		        $card_args['cavv']                        = $js_response['cavv'];
		        $card_args['xid']                         = $js_response['xid'];
		        $card_args['eci']                         = $js_response['eci'];
		        $card_args['cardholder_auth']             = $js_response['cardholder_auth'];
		        $card_args['three_ds_version']            = $js_response['three_ds_version'];
		        $card_args['directory_server_id']         = $js_response['directory_server_id'];
		        $card_args['stored_credential_indicator'] = 'stored';
		        $card_args['billing_method']	          = 'recurring';
		        $card_args['initiated_by']	              = 'customer';
	        }
        } else {
            $expiry = explode( ' / ', wc_clean( $_POST['nmi-card-expiry'] ) );
            $expiry[1] = substr( $expiry[1], -2 );
            $card_args = array(
                'ccnumber'	=> wc_clean( $_POST['nmi-card-number'] ),
                'ccexp'		=> $expiry[0] . $expiry[1],
                'cvv'		=> wc_clean( $_POST['nmi-card-cvc'] ),
            );
        }

		$customer_name = sprintf( __( 'Customer: %s %s', 'wc-nmi' ), get_user_meta( $user_id, 'billing_first_name', true ), get_user_meta( $user_id, 'billing_last_name', true ) );

		$args = array(
			'order_description' => $customer_name,
			'first_name'		=> get_user_meta( $user_id, 'billing_first_name', true ),
			'last_name'			=> get_user_meta( $user_id, 'billing_last_name', true ),
			'address1'			=> get_user_meta( $user_id, 'billing_address_1', true ),
			'address2'			=> get_user_meta( $user_id, 'billing_address_2', true ),
			'city'				=> get_user_meta( $user_id, 'billing_city', true ),
			'state'				=> get_user_meta( $user_id, 'billing_state', true ),
			'country'			=> get_user_meta( $user_id, 'billing_country', true ),
			'zip'				=> get_user_meta( $user_id, 'billing_postcode', true ),
			'email' 			=> get_user_meta( $user_id, 'billing_email', true ),
			'phone'				=> get_user_meta( $user_id, 'billing_phone', true ),
			'company'			=> get_user_meta( $user_id, 'billing_company', true ),
			'customer_vault' 	=> 'add_customer',
			'customer_vault_id'	=> '',
			'currency'			=> $this->get_payment_currency( $order_id ),
		);

		if ( $this->add_customer_method == 'validate' ) {
			$customer_method = array( 'type' => 'validate' );
		} else {
			$customer_method = array( 'type' => 'auth', 'amount' => 1.00 );
		}

		$args = array_merge( $card_args, $args, $customer_method );

		$args = apply_filters( 'wc_nmi_request_args', $args, $order );

		$response = $this->nmi_request( $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( ! empty( $response['customer_vault_id'] ) ) {

			// Store the ID on the user account if logged in
			if ( is_user_logged_in() ) {
				update_user_meta( get_current_user_id(), '_nmi_customer_id', $response['customer_vault_id'] );
			}

			// Voiding add_customer auth transaction
			if ( $customer_method['type'] == 'auth' ) {
				$args = array(
					'amount'		=> 1.00,
					'transactionid'	=> $response['transactionid'],
					'type' 			=> 'void',
				);
				$args = apply_filters( 'wc_nmi_request_args', $args, $order );

				$this->nmi_request( $args );
			}
			return ! empty( $card_args['stored_credential_indicator'] ) ? $response['customer_vault_id'] . ':' . $response['transactionid'] : $response['customer_vault_id'];
		}

		$error_message = __( 'Unable to add customer', 'wc-nmi' );
		$this->log( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $error_message ) );
		return new WP_Error( 'error', $error_message );
	}

	/**
	 * Add a card to a customer via the API.
	 *
	 * @param int $customer_id
	 * @param bool $skip
	 * @return object
	 */
	public function add_card( $customer_id, $skip = false ) {
		$three_ds_args = array();
		$initial_transaction_id = false;

        if( strpos( $customer_id, ':' ) !== false ) {
	        $vault_array = explode( ':', $customer_id );
            list( $customer_id, $initial_transaction_id ) = $vault_array;
        }

        if( $js_response = $this->get_nmi_js_response() ) {
            $card = array(
                'id' => $customer_id,
                'ccexp'	=> $js_response['card']['exp'],
                'last4'	=> substr( $js_response['card']['number'], -4 ),
                'brand'	=> $this->get_card_type( str_replace( 'diners', 'diners-club', $js_response['card']['type'] ), 'name' ),
            );
            $expiry = array(
                substr( $js_response['card']['exp'], 0, 2 ),
                substr( $js_response['card']['exp'], -2 ),
            );
	        if( isset( $js_response['three_ds_version'] ) ) {
		        $three_ds_args = array(
			        'cavv'                => $js_response['cavv'],
			        'xid'                 => $js_response['xid'],
			        'eci'                 => $js_response['eci'],
			        'cardholder_auth'     => $js_response['cardholder_auth'],
			        'three_ds_version'    => $js_response['three_ds_version'],
			        'directory_server_id' => $js_response['directory_server_id'],
		        );
	        }
        } else {
            $card_no = wc_clean( $_POST['nmi-card-number'] );
            $expiry = explode( ' / ', wc_clean( $_POST['nmi-card-expiry'] ) );
            $expiry[1] = substr( $expiry[1], -2 );
            $card = array(
                'id' => $customer_id,
                'ccexp'	=> $expiry[0] . $expiry[1],
                'last4'	=> substr( $card_no, -4 ),
                'brand'	=> $this->get_card_type( $card_no ),
            );
        }
		$card = (object) $card;

		if( !$skip ) {
            $token = new WC_Payment_Token_CC();
            $token->set_token( $card->id );
			$token->set_gateway_id( 'nmi' );
			$token->set_card_type( strtolower( $card->brand ) );
			$token->set_last4( $card->last4 );
			$token->set_expiry_month( $expiry[0] );
			$token->set_expiry_year( '20' . $expiry[1] );
			$token->set_default( $this->new_card_default );
			$token->set_user_id( get_current_user_id() );
			if( $three_ds_args ) {
				$token->update_meta_data( 'three_ds_args', $three_ds_args );
			}
			if( $initial_transaction_id ) {
				$token->update_meta_data( 'initial_transaction_id', $initial_transaction_id );
			}
			$token->save();

			// Make sure all other tokens are not set to default.
			if ( $this->new_card_default && $token->get_user_id() > 0 ) {
				WC_Payment_Tokens::set_users_default( $token->get_user_id(), $token->get_id() );
			}
		}
		return $card;
	}

    /**
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the NMI API.
	 * @since 1.1.0
	 */
	public function add_payment_method() {
		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'There was a problem adding the card.', 'wc-nmi' ), 'error' );
			return;
		}

        $customer_id = $this->add_customer();
        if ( is_wp_error( $customer_id ) ) {
			wc_add_notice( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $customer_id->get_error_message() ), 'error' );
			$this->log( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $customer_id->get_error_message() ) );
			return;
        }

        $this->add_card( $customer_id );

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
	}

	function nmi_request( $args ) {

		$gateway_debug = ( $this->logging && $this->debugging );

        $request_url = $this->api_keys ? self::NMI_REQUEST_URL_API_KEYS : self::NMI_REQUEST_URL_LOGIN;
		$request_url = apply_filters( 'wc_nmi_request_url', $request_url );

        $auth_params = $this->api_keys ? array( 'security_key' => $this->private_key ) : array(
            'username' => $this->username,
            'password' => $this->password,
        );

		$args['customer_receipt'] = isset( $args['customer_receipt'] ) ? $args['customer_receipt'] : $this->customer_receipt;
		$args['ipaddress'] = isset( $args['ipaddress'] ) ? $args['ipaddress'] : WC_Geolocation::get_ip_address();

        if( isset( $args['customer_vault_id'] ) && empty( $args['customer_vault_id'] ) ) {
            unset( $args['customer_vault_id'] );
        }

        if( isset( $args['transactionid'] ) && empty( $args['transactionid'] ) ) {
            unset( $args['transactionid'] );
        }

        if( isset( $args['currency'] ) && empty( $args['currency'] ) ) {
            $args['currency'] = get_woocommerce_currency();
        }

        if( isset( $args['state'] ) && empty( $args['state'] ) && ! in_array( $args['type'], array( 'capture', 'void', 'refund' ) ) ) {
            $args['state'] = 'NA';
        }
        if( isset( $args['shipping_state'] ) && empty( $args['shipping_state'] ) && ! in_array( $args['type'], array( 'capture', 'void', 'refund' ) ) ) {
            $args['shipping_state'] = 'NA';
        }

        $args = array_merge( $args, $auth_params );

        // Setting custom timeout for the HTTP request
		add_filter( 'http_request_timeout', array( $this, 'http_request_timeout' ), 9999 );

        //$headers = array( 'Content-Type' => 'application/json' );
        $headers = array();
        $response = wp_remote_post( $request_url, array( 'body' => $args , 'headers' => $headers ) );

		$result = is_wp_error( $response ) ? $response : wp_remote_retrieve_body( $response );

        // Saving to Log here
		if( $gateway_debug ) {
			$message = sprintf( "\nPosting to: \n%s\nRequest: \n%sResponse: \n%s", $request_url, print_r( $args, 1 ), print_r( $result, 1 ) );
			WC_NMI_Logger::log( $message );
		}

		remove_filter( 'http_request_timeout', array( $this, 'http_request_timeout' ), 9999 );

		if ( is_wp_error( $result ) ) {
			return $result;
		} elseif( empty( $result ) ) {
			$error_message = __( 'There was an error with the gateway response.', 'wc-nmi' );
			return new WP_Error( 'invalid_response', apply_filters( 'woocommerce_nmi_error_message', $error_message, $result ) );
		}

        parse_str( $result, $result );

        if( count( $result ) < 8 ) {
			$error_message = sprintf( __( 'Unrecognized response from the gateway: %s', 'wc-nmi' ), $response );
			return new WP_Error( 'invalid_response', apply_filters( 'woocommerce_nmi_error_message', $error_message, $result ) );
        }

        if( !isset( $result['response'] ) || !in_array( $result['response'], array( 1, 2, 3 ) ) ) {
			$error_message = __( 'There was an error with the gateway response.', 'wc-nmi' );
			return new WP_Error( 'invalid_response', apply_filters( 'woocommerce_nmi_error_message', $error_message, $result ) );
        }

        if( $result['response'] == 2 ) {
			$error_message = '<!-- Error: ' . $result['response_code'] . ' --> ' . __( 'Your card has been declined.', 'wc-nmi' );
			return new WP_Error( 'decline_response', apply_filters( 'woocommerce_nmi_error_message', $error_message, $result ), $result );
		}

        if( $result['response'] == 3 ) {
			$error_message = '<!-- Error: ' . $result['response_code'] . ' --> ' . $result['responsetext'];
			return new WP_Error( 'error_response', apply_filters( 'woocommerce_nmi_error_message', $error_message, $result ), $result );
		}

        return $result;

	}

    public function http_request_timeout( $timeout_value ) {
		return 45; // 45 seconds. Too much for production, only for testing.
	}

	function get_card_type( $value, $field = 'pattern', $return = 'label' ) {
		$card_types = array(
			array(
				'label' => 'American Express',
				'name' => 'amex',
				'pattern' => '/^3[47]/',
				'valid_length' => '[15]'
			),
			array(
				'label' => 'JCB',
				'name' => 'jcb',
				'pattern' => '/^35(2[89]|[3-8][0-9])/',
				'valid_length' => '[16]'
			),
			array(
				'label' => 'Discover',
				'name' => 'discover',
				'pattern' => '/^(6011|622(12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5]|64[4-9])|65)/',
				'valid_length' => '[16]'
			),
			array(
				'label' => 'MasterCard',
				'name' => 'mastercard',
				'pattern' => '/^5[1-5]/',
				'valid_length' => '[16]'
			),
			array(
				'label' => 'Visa',
				'name' => 'visa',
				'pattern' => '/^4/',
				'valid_length' => '[16]'
			),
			array(
				'label' => 'Maestro',
				'name' => 'maestro',
				'pattern' => '/^(5018|5020|5038|6304|6759|676[1-3])/',
				'valid_length' => '[12, 13, 14, 15, 16, 17, 18, 19]'
			),
			array(
				'label' => 'Diners Club',
				'name' => 'diners-club',
				'pattern' => '/^3[0689]/',
				'valid_length' => '[14]'
			),
		);

		foreach( $card_types as $type ) {
			$compare = $type[$field];
			if ( ( $field == 'pattern' && preg_match( $compare, $value, $match ) ) || $compare == $value ) {
				return $type[$return];
			}
		}

		return false;

	}

	/**
	 * Get payment currency, either from current order or WC settings
	 *
	 * @since 4.1.0
	 * @return string three-letter currency code
	 */
	function get_payment_currency( $order_id = false ) {
 		$currency = get_woocommerce_currency();
		$order_id = ! $order_id ? $this->get_checkout_pay_page_order_id() : $order_id;

 		// Gets currency for the current order, that is about to be paid for
 		if ( $order_id ) {
 			$order    = wc_get_order( $order_id );
 			$currency = $order->get_currency();
 		}
 		return $currency;
 	}

	/**
	 * Returns the order_id if on the checkout pay page
	 *
	 * @since 3.0.0
	 * @return int order identifier
	 */
	public function get_checkout_pay_page_order_id() {
		global $wp;
		return isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
	}

	public function display_payment_request_button_html() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways[$this->id] ) ) {
			return;
		}

		if ( ! $this->is_available() ) {
			return;
		}

		if ( ! $this->should_show_express_payment_button() ) {
			return;
		}

		?>
		<div id="wc-nmi-upe-wrapper" style="clear:both;padding-top:1.5em;">
			<?php if( $this->googlepay_enable ) { ?>
				<div id="wc-nmi-googlepay">
					<!-- An NMI express payment method will be inserted here. -->
				</div>
			<?php } ?>
			<?php if( $this->applepay_enable ) { ?>
                <div id="wc-nmi-applepay">
                    <!-- An NMI express payment method will be inserted here. -->
                </div>
			<?php } ?>
		</div>
		<?php
	}

	public function should_show_express_payment_button() {

		// Don't show if it's not the checkout page.
		if ( ! is_checkout() ) {
			return false;
		}

		if( ! $this->api_keys || ! $this->public_key ) {
			return false;
		}

		// Don't show if cart contains subscription product.
		if( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}

		// Don't show if cart contains pre-order product.
		if( class_exists( 'WC_Pre_Orders_Cart' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() ) {
			return false;
		}

		return true;
	}

	/**
	 * Add needed order meta
	 *
	 * @param integer $order_id    The order ID.
	 * @param array   $posted_data The posted data from checkout form.
	 *
	 * @since   3.3.6
	 * @return  void
	 */
	public function set_payment_method_title( &$order, $card_last4, $card_type ) {
		if ( ! isset( $_POST['payment_method'] ) || 'nmi' !== $_POST['payment_method'] ) {
			return;
		}

		$order->set_payment_method_title( $order->get_payment_method_title() . ' ' . sprintf( '(%s - **** %d)', wc_get_credit_card_type_label( $card_type ), $card_last4 ) );
	}

	/**
	 * Filters the gateway title to reflect Payment Request type
	 */
	public function filter_gateway_title( $title, $id ) {
		global $post;

		if( ! is_object( $post ) || $id != 'nmi' ) {
			return $title;
		}

		$order = wc_get_order( $post->ID );

		if( ! is_object( $order ) ) {
			return $title;
		}

		if( $order->get_meta( '_nmi_card_last4' ) && $order->get_meta( '_nmi_card_type' ) && strpos( $title, $order->get_meta( '_nmi_card_last4' ) ) === false ) {
			$title .= ' ' . sprintf( '(%s - **** %d)', wc_get_credit_card_type_label( $order->get_meta( '_nmi_card_type' ) ), $order->get_meta( '_nmi_card_last4' ) );
			remove_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );
		}

		return $title;
	}

	/**
	 * get_avs_message function.
	 *
	 * @access public
	 * @param string $code
	 * @return string
	 */
	public function get_avs_message( $code ) {
		$avs_messages = array(
			'A' => __( 'Address match only', 'wc-nmi' ),
			'B' => __( 'Address match only', 'wc-nmi' ),
			'C' => __( 'No address or ZIP match only', 'wc-nmi' ),
			'D' => __( 'Exact match, 5-character numeric ZIP', 'wc-nmi' ),
			'E' => __( 'Not a mail/phone order', 'wc-nmi' ),
			'G' => __( 'Non-U.S. issuer does not participate', 'wc-nmi' ),
			'I' => __( 'Non-U.S. issuer does not participate', 'wc-nmi' ),
			'L' => __( '5-character ZIP match only', 'wc-nmi' ),
			'M' => __( 'Exact match, 5-character numeric ZIP', 'wc-nmi' ),
			'N' => __( 'No address or ZIP match only', 'wc-nmi' ),
			'O' => __( 'AVS not available', 'wc-nmi' ),
			'P' => __( '5-character ZIP match only', 'wc-nmi' ),
			'R' => __( 'Issuer system unavailable', 'wc-nmi' ),
			'S' => __( 'Service not supported', 'wc-nmi' ),
			'U' => __( 'Address unavailable', 'wc-nmi' ),
			'W' => __( '9-character numeric ZIP match only', 'wc-nmi' ),
			'X' => __( 'Exact match, 9-character numeric ZIP', 'wc-nmi' ),
			'Y' => __( 'Exact match, 5-character numeric ZIP', 'wc-nmi' ),
			'Z' => __( '5-character ZIP match only', 'wc-nmi' ),
			'0' => __( 'AVS not available', 'wc-nmi' ),
			'1' => __( '5-character ZIP, customer name match only', 'wc-nmi' ),
			'2' => __( 'Exact match, 5-character numeric ZIP, customer name', 'wc-nmi' ),
			'3' => __( 'Address, customer name match only', 'wc-nmi' ),
			'4' => __( 'No address or ZIP or customer name match only', 'wc-nmi' ),
			'5' => __( '5-character ZIP, customer name match only', 'wc-nmi' ),
			'6' => __( 'Exact match, 5-character numeric ZIP, customer name', 'wc-nmi' ),
			'7' => __( 'Address, customer name match only', 'wc-nmi' ),
			'8' => __( 'No address or ZIP or customer name match only', 'wc-nmi' ),
		);

		if ( array_key_exists( $code, $avs_messages ) ) {
			return "\n" . sprintf( 'AVS Response: %s', $code . ' - ' . $avs_messages[$code] );
		} else {
			return '';
		}
	}

	/**
	 * get_cvv_message function.
	 *
	 * @access public
	 * @param string $code
	 * @return string
	 */
	public function get_cvv_message( $code ) {
		$cvv_messages = array(
			'M' => __( 'CVV2/CVC2 Match', 'wc-nmi' ),
			'N' => __( 'CVV2 / CVC2 No Match', 'wc-nmi' ),
			'P' => __( 'Not Processed', 'wc-nmi' ),
			'S' => __( 'Merchant Has Indicated that CVV2 / CVC2 is not present on card', 'wc-nmi' ),
			'U' => __( 'Issuer is not certified and/or has not provided visa encryption keys', 'wc-nmi' ),
		);

		if ( array_key_exists( $code, $cvv_messages ) ) {
			return "\n" . sprintf( 'CVV2 Response: %s', $code . ' - ' . $cvv_messages[$code] );
		} else {
			return '';
		}
	}

	/**
	 * Send the request to NMI's API
	 *
	 * @since 2.6.10
	 *
	 * @param string $message
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WC_NMI_Logger::log( $message );
		}
	}

}
