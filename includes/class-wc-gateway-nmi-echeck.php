<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_NMI_ECheck class.
 *
 * @extends WC_Payment_Gateway_ECheck
 */

class WC_Gateway_NMI_ECheck extends WC_Payment_Gateway_ECheck {

	public $testmode;
	public $api_keys;
	public $capture;
	public $saved_account;
	public $new_account_default;
	public $private_key;
	public $public_key;
	public $username;
	public $password;
	public $logging;
	public $debugging;
	public $line_items;
	public $allowed_card_types;
	public $customer_receipt;
	public $card_tokenization_enabled;

	const NMI_REQUEST_URL_LOGIN = 'https://secure.networkmerchants.com/api/transact.php';
    const NMI_REQUEST_URL_API_KEYS = 'https://secure.nmi.com/api/transact.php';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'nmi-echeck';
		$this->method_title         = __( 'NMI - eCheck', 'wc-nmi' );
		$this->method_description   = __( 'NMI works by adding eCheck fields on the checkout and then sending the details to NMI for verification. It fully supports WooCommerce Subscriptions and WooCommerce Pre-Orders plugins.', 'wc-nmi' );
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

        $main_settings = get_option( 'woocommerce_nmi_settings' );
        $this->private_key	   		= ! empty( $main_settings['private_key'] ) ? $main_settings['private_key'] : '';
		$this->public_key	   		= ! empty( $main_settings['public_key'] ) ? $main_settings['public_key'] : '';
        $this->card_tokenization_enabled = $this->public_key && ! empty( $main_settings['enabled'] ) && 'yes' === $main_settings['enabled'] && ! empty( $main_settings['api_keys'] ) && 'yes' === $main_settings['api_keys'];

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title       		  	= $this->get_option( 'title' );
		$this->description 		  	= $this->get_option( 'description' );
		$this->enabled     		  	= $this->get_option( 'enabled' );
		$this->testmode    		  	= $this->get_option( 'testmode' ) === 'yes';
		$this->api_keys    		  	= $this->get_option( 'api_keys' ) === 'yes';
		$this->saved_account		= $this->get_option( 'saved_account' ) === 'yes';
		$this->new_account_default 	= $this->saved_account && $this->get_option( 'new_account_default' ) === 'yes';
		$this->username	   		  	= $this->get_option( 'username' );
		$this->password	   		  	= $this->get_option( 'password' );
		$this->logging     		  	= $this->get_option( 'logging' ) === 'yes';
		$this->debugging   		  	= $this->get_option( 'debugging' ) === 'yes';
		$this->line_items  		  	= $this->get_option( 'line_items' ) === 'yes';
		$this->customer_receipt   	= $this->get_option( 'customer_receipt' ) === 'yes';

		if ( $this->testmode ) {
			$this->description .= ' ' . sprintf( __( '<br /><br /><strong>TEST MODE ENABLED</strong><br /> In test mode, you can use the routing number and account number 123123123 or check the documentation "<a href="%s">NMI Direct Post API</a>".', 'wc-nmi' ), 'https://secure.nmi.com/merchants/resources/integration/download.php?document=directpost' );
			$this->description  = trim( $this->description );
		}

		// Hooks
        if( !$this->card_tokenization_enabled ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        }
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'remove_api_key_values' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_echeck_form_fields', array( $this, 'form_fields' ), 10, 2 );
		add_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );

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
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your Username <a href="%s">here</a>', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi-echeck' ) ) . '</p></div>';
			return;

		} elseif ( ! $this->api_keys && ! $this->password ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your Password <a href="%s">here</a>', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi-echeck' ) ) . '</p></div>';
			return;
		}

		if ( $this->api_keys && ! $this->private_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your Private Key <a href="%s">here</a>', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi' ) ) . '</p></div>';
			return;
		}

		// Simple check for duplicate keys
		if ( ! $this->api_keys && $this->username == $this->password ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI error: Your Username and Password match. Please check and re-enter.', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi-echeck' ) ) . '</p></div>';
			return;
		}

		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
		if ( ! wc_checkout_is_https() ) {
			echo '<div class="notice notice-warning"><p>' . sprintf( __( 'NMI (eCheck) is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'wc-nmi' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>';
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
                        return $( '#woocommerce_nmi-echeck_api_keys' ).is( ':checked' );
                    },

                    /**
                     * Initialize.
                     */
                    init: function() {
                        $( document.body ).on( 'change', '#woocommerce_nmi-echeck_api_keys', function() {
                           let field_username = $( '#woocommerce_nmi-echeck_username' ).parents( 'tr' ).eq( 0 ),
					           field_password = $( '#woocommerce_nmi-echeck_password' ).parents( 'tr' ).eq( 0 ),
					           field_private_key = $( '#woocommerce_nmi-echeck_private_key' ).parents( 'tr' ).eq( 0 ),
					           field_public_key = $( '#woocommerce_nmi-echeck_public_key' ).parents( 'tr' ).eq( 0 );

                            if ( $( this ).is( ':checked' ) ) {
                                field_private_key.show();
                                field_public_key.show();
                                field_username.hide();
                                field_password.hide();
                            } else {
                                field_private_key.hide();
                                field_public_key.hide();
                                field_username.show();
                                field_password.show();
                            }
                        } );

						$( document.body ).on( 'change', '#woocommerce_nmi-echeck_saved_account', function() {
                            let field_new_account_default = $( '#woocommerce_nmi-echeck_new_account_default' ).parents( 'tr' ).eq( 0 );
                            if ( $( this ).is( ':checked' ) ) {
                                field_new_account_default.show();
                            } else {
                                field_new_account_default.hide();
                            }
                        });

						$( '#woocommerce_nmi-echeck_saved_account' ).change();
                        $( '#woocommerce_nmi-echeck_api_keys' ).change();
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
			if ( is_add_payment_method_page() && ! $this->saved_account ) {
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
				'label'       => __( 'Enable NMI - eCheck', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'wc-nmi' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-nmi' ),
				'default'     => __( 'eCheck (NMI)', 'wc-nmi' )
			),
			'description' => array(
				'title'       => __( 'Description', 'wc-nmi' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'wc-nmi' ),
				'default'     => __( 'Pay with your echeck details via NMI.', 'wc-nmi' )
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
				'description' => sprintf( __( 'Please enable API keys from the <a href="%s">card settings page</a> and enter it there.', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi' ) ),
				'default'     => $this->private_key,
                'disabled'    => true
			),
            'public_key' => array(
				'title'       => __( 'Public Key', 'wc-nmi' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Please enable API keys from the <a href="%s">card settings page</a> and enter it there.', 'wc-nmi' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nmi' ) ),
				'default'     => $this->public_key,
                'disabled'    => true
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
			'saved_account' => array(
				'title'       => __( 'Saved Account', 'wc-nmi' ),
				'label'       => __( 'Enable Payment via Saved Account', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved account during checkout. eCheck details are saved on NMI servers, not on your store.', 'wc-nmi' ),
				'default'     => 'no'
			),
			'new_account_default' => array(
				'title'       => '',
				'label'       => __( 'Set New Account as the Default Payment Method', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'The default payment method is automatically selected at checkout so this can help prevent customers from entering the account details they previously saved.', 'wc-nmi' ),
				'default'     => 'no'
			),
			'logging' => array(
				'title'       => __( 'Logging', 'wc-nmi' ),
				'label'       => __( 'Log debug messages', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( 'Save debug messages to the WooCommerce System Status log file <code>%s</code>.', 'wc-nmi' ), WC_Log_Handler_File::get_log_file_path( 'woocommerce-gateway-nmi' ) ),
				'default'     => 'no'
			),
			'debugging' => array(
				'title'       => __( 'Gateway Debug', 'wc-nmi' ),
				'label'       => __( 'Log gateway requests and response to the WooCommerce System Status log.', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( '<strong>CAUTION! Enabling this option will write gateway requests including eCheck details to the logs.</strong> Do not turn this on unless you have a problem processing eCheck. You must only ever enable it temporarily for troubleshooting or to send requested information to the plugin author. It must be disabled straight away after the issues are resolved and the plugin logs should be deleted.', 'wc-nmi' ) . ' ' . sprintf( __( '<a href="%s">Click here</a> to check and delete the full log file.', 'wc-nmi' ), admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . WC_Log_Handler_File::get_log_file_name( 'woocommerce-gateway-nmi' ) ) ),
				'default'     => 'no'
			),
			'line_items' => array(
				'title'       => __( 'Line Items', 'wc-nmi' ),
				'label'       => __( 'Enable Line Items', 'wc-nmi' ),
				'type'        => 'checkbox',
				'description' => __( 'Add line item data to description sent to the gateway (eg. Item x qty).', 'wc-nmi' ),
				'default'     => 'no'
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

    public function remove_api_key_values( $settings ) {
        unset( $settings['public_key'] );
        unset( $settings['private_key'] );
        return $settings;
    }

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$display_tokenization = is_checkout() && $this->saved_account;

        echo '<div class="nmi_new_account" id="nmi-payment-data">';

		if ( $this->description ) {
			echo apply_filters( 'wc_nmi_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		if ( $display_tokenization ) {
            $this->tokenization_script();
			$this->saved_payment_methods();
		}

        if ( $this->api_keys && $this->public_key ) {
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
		<fieldset id="<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-echeck-form wc-payment-form'>
			<?php do_action( 'woocommerce_echeck_form_start', $this->id ); ?>

            <!-- Used to display form errors -->
            <div class="nmi-source-errors" role="alert"></div>

            <div class="form-row form-row-wide">
                <label for="nmi-echeck-account-name-element"><?php esc_html_e( 'Account Holder Name', 'wc-nmi' ); ?> <span class="required">*</span></label>
                <div class="nmi-echeck-group">
                    <div id="nmi-echeck-account-name-element" class="wc-nmi-elements-field">
                    <!-- a NMI Element will be inserted here. -->
                    </div>
                </div>
            </div>

            <div class="form-row form-row-first">
                <label for="nmi-echeck-routing-number-element"><?php esc_html_e( 'Routing number', 'wc-nmi' ); ?> <span class="required">*</span></label>
                <div id="nmi-echeck-routing-number-element" class="wc-nmi-elements-field">
                <!-- a NMI Element will be inserted here. -->
                </div>
            </div>

            <div class="form-row form-row-last">
                <label for="nmi-echeck-account-number-element"><?php esc_html_e( 'Account number', 'wc-nmi' ); ?> <span class="required">*</span></label>
				<div id="nmi-echeck-account-number-element" class="wc-nmi-elements-field">
				<!-- a NMI Element will be inserted here. -->
				</div>
            </div>

			<div class="form-row form-row-first">
				<label for="nmi-echeck-account-type"><?php esc_html_e( 'Account Type', 'wc-nmi' ); ?><span class="required">*</span></label>
				<select name="nmi-echeck-account-type" class="input-text">
				  <option value="checking"><?php echo esc_html__( 'Checking', 'wc-nmi' ); ?></option>
				  <option value="savings"><?php echo esc_html__( 'Saving', 'wc-nmi' ); ?></option>
				</select>
            </div>

            <div class="form-row form-row-last">
				<label for="nmi-echeck-holder-type"><?php esc_html_e( 'Account Holder Type', 'wc-nmi' ); ?><span class="required">*</span></label>
				<select name="nmi-echeck-holder-type" class="input-text">
				  <option value="personal"><?php echo esc_html__( 'Personal', 'wc-nmi' ); ?></option>
				  <option value="business"><?php echo esc_html__( 'Business', 'wc-nmi' ); ?></option>
				</select>
				</div>
            <div class="clear"></div>

			<?php do_action( 'woocommerce_echeck_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

    public function payment_scripts() {
		if ( $this->card_tokenization_enabled || ! $this->api_keys || ! $this->public_key || ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) ) {
			return;
		}

        add_filter( 'script_loader_tag', array( $this, 'add_public_key_to_js' ), 10, 2 );

		wp_enqueue_script( 'nmi-collect-js', 'https://secure.nmi.com/token/Collect.js', '', null, true );
		wp_enqueue_script( 'woocommerce_nmi', plugins_url( 'assets/js/nmi.js', WC_NMI_MAIN_FILE ), array( 'jquery-payment', 'nmi-collect-js' ), WC_NMI_VERSION, true );

		$nmi_params = array(
			'public_key'           	=> $this->public_key,
			'enable_3ds'           	=> false,
			'checkout_key'          => '',
			'allowed_card_types'   	=> array(),
			'i18n_terms'           	=> __( 'Please accept the terms and conditions first', 'wc-nmi' ),
			'i18n_required_fields' 	=> __( 'Please fill in required checkout fields first', 'wc-nmi' ),
            'card_disallowed_error' => '',
            'placeholder_cvc' 		=> '',
            'placeholder_expiry' 	=> '',
            'card_number_error' 	=> '',
            'card_expiry_error' 	=> '',
            'card_cvc_error' 		=> '',
            'echeck_account_number_error' 	=> __( 'Invalid account number.', 'wc-nmi' ),
            'echeck_account_name_error' 	=> __( 'Invalid account name.', 'wc-nmi' ),
            'echeck_routing_number_error' 	=> __( 'Invalid routing number.', 'wc-nmi' ),
            'error_ref' 			=> __( '(Ref: [ref])', 'wc-nmi' ),
            'timeout_error' 		=> __( 'The tokenization did not respond in the expected timeframe. Please make sure the fields are correctly filled in and submit the form again.', 'wc-nmi' ),

		);
        $nmi_params['is_checkout'] = ( is_checkout() && empty( $_GET['pay_for_order'] ) ) ? 'yes' : 'no'; // wpcs: csrf ok.

		wp_localize_script( 'woocommerce_nmi', 'wc_nmi_params', apply_filters( 'wc_nmi_params', $nmi_params ) );
	}

    public function add_public_key_to_js( $tag, $handle ) {
       if ( 'nmi-collect-js' !== $handle ) return $tag;

       return str_replace( ' src', ' data-tokenization-key="' . $this->public_key . '" src', $tag );
    }

	public function form_fields( $fields, $gateway_id ) {
		if( $gateway_id == $this->id ) {
			$fields = array( 'account-name' => '<p class="form-row form-row-wide">
					<label for="' . esc_attr( $this->id ) . '-account-name">' . esc_html__( 'Account Holder Name', 'wc-nmi' ) . '&nbsp;<span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-account-name" class="input-text" type="text" autocomplete="off" name="' . esc_attr( $this->id ) . '-account-name" />
				</p>' ) + $fields;

			$fields['account-number'] = '<p class="form-row form-row-last">
					<label for="' . esc_attr( $this->id ) . '-account-number">' . esc_html__( 'Account number', 'wc-nmi' ) . '&nbsp;<span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '-account-number" class="input-text wc-echeck-form-account-number" type="text" autocomplete="off" name="' . esc_attr( $this->id ) . '-account-number" maxlength="17" />
				</p>';

			$fields['account-type'] = '<p class="form-row form-row-first">
					<label for="' . esc_attr( $this->id ) . '-account-type">' . esc_html__( 'Account Type', 'wc-nmi' ) . ' <span class="required">*</span></label>
					<select name="' . esc_attr( $this->id ) . '-account-type" class="input-text">
					  <option value="checking">' . esc_html__( 'Checking', 'wc-nmi' ) . '</option>
					  <option value="savings">' . esc_html__( 'Saving', 'wc-nmi' ) . '</option>
					</select>
				</p>';

			$fields['holder-type'] = '  <p class="form-row form-row-last">
					<label for="' . esc_attr( $this->id ) . '-holder-type">' . esc_html__( 'Account Holder Type', 'wc-nmi' ) . ' <span class="required">*</span></label>
					<select name="' . esc_attr( $this->id ) . '-holder-type" class="input-text">
					  <option value="personal">' . esc_html__( 'Personal', 'wc-nmi' ) . '</option>
					  <option value="business">' . esc_html__( 'Business', 'wc-nmi' ) . '</option>
					</select>
				</p>';
		}

		return $fields;
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
		return $response;
	}

	/**
	 * Process the payment
	 */
	public function process_payment( $order_id, $retry = true ) {

		$order       = wc_get_order( $order_id );
        $token_id 	 = isset( $_POST['wc-nmi-echeck-payment-token'] ) ? wc_clean( $_POST['wc-nmi-echeck-payment-token'] ) : '';
		$customer_id = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_nmi_customer_id', true ) : 0;

		if ( ! $customer_id || ! is_string( $customer_id ) ) {
			$customer_id = 0;
		}

		$this->log( "Info: Beginning processing payment for order $order_id for the amount of {$order->get_total()}" );

		$response = false;

		// Use NMI CURL API for payment
		try {
			$post_data = array();

			if ( $token_id !== 'new' && $token_id && $customer_id ) {
                $token = WC_Payment_Tokens::get( $token_id );

                if ( ! $token || $token->get_user_id() !== get_current_user_id() ) {
                    WC()->session->set( 'refresh_totals', true );
                    throw new Exception( __( 'Invalid payment method. Please input a new account number.', 'wc-nmi' ) );
                }

                $account_id = $token->get_token();
				$account_last4 = $token->get_last4();
			}
			// Use token
			else {
				$maybe_saved_account = isset( $_POST['wc-nmi-echeck-new-payment-method'] ) && ! empty( $_POST['wc-nmi-echeck-new-payment-method'] );
				$account_id = 0;

				if( $js_response = $this->get_nmi_js_response() ) {
					$account_last4 = substr( $js_response['account']['number'], -4 );
				} else {
					$account_last4 = substr( wc_clean( $_POST['nmi-echeck-account-number'] ), -4 );

					// Check for eCheck details filled or not
					if ( empty( $_POST['nmi-echeck-routing-number'] ) || empty( $_POST['nmi-echeck-account-number'] ) || empty( $_POST['nmi-echeck-account-name'] ) ) {
						throw new Exception( __( 'eCheck details cannot be left incomplete.', 'wc-nmi' ) );
					}
				}

				// Save token if logged in
				if ( apply_filters( 'wc_nmi_force_saved_account', ( is_user_logged_in() && $this->saved_account && $maybe_saved_account ), $order_id ) ) {
					$customer_id = $this->add_customer( $order_id );
					if ( is_wp_error( $customer_id ) ) {
						throw new Exception( $customer_id->get_error_message() );
					} else {
						$this->add_account( $customer_id );
						$account_id = $customer_id;
					}
				} else {
					if( $js_response = $this->get_nmi_js_response() ) {
                        $post_data['payment_token'] = $js_response['token'];
                    } else {
						$post_data['payment']				= 'check';
						$post_data['checkaba']				= wc_clean( $_POST['nmi-echeck-routing-number'] );
						$post_data['checkaccount']			= wc_clean( $_POST['nmi-echeck-account-number'] );
						$post_data['account_type']			= $_POST['nmi-echeck-account-type'];
						$post_data['checkname']				= $_POST['nmi-echeck-account-name'];
						$post_data['account_holder_type']	= $_POST['nmi-echeck-holder-type'];
					}
					$customer_id = 0;
				}
			}
			// Store the ID in the order
			if ( $customer_id ) {
				$order->update_meta_data( '_nmi_customer_id', $customer_id );
			}
			if ( $account_id ) {
				$order->update_meta_data( '_nmi_account_id', $account_id );
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
				'type'				=> 'sale',
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
				'customer_vault_id' => $account_id ? $account_id : $customer_id,
				'currency'			=> $this->get_payment_currency( $order_id ),
			);

			$payment_args = array_merge( $payment_args, $post_data );

			$payment_args = apply_filters( 'wc_nmi_request_args', $payment_args, $order );

			$response = $this->nmi_request( $payment_args );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			// Store charge ID
			$order->update_meta_data( '_nmi_charge_id', $response['transactionid'] );
			$order->update_meta_data( '_nmi_authorization_code', $response['authcode'] );
			$order->update_meta_data( '_nmi_account_last4', $account_last4 );

			if ( $response['response'] == 1 ) {
				$order->set_transaction_id( $response['transactionid'] );

				// Store captured value
				$order->update_meta_data( '_nmi_charge_captured', 'yes' );
				$order->update_meta_data( 'NMI Payment ID', $response['transactionid'] );

				// Payment complete
				$order->payment_complete( $response['transactionid'] );

				// Add order note
				$complete_message = trim( sprintf( __( "NMI charge complete (Charge ID: %s) %s", 'wc-nmi' ), $response['transactionid'], self::get_avs_message( $response['avsresponse'] ) ) );
				$order->add_order_note( $complete_message );
				$this->log( "Success: $complete_message" );

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
				$order->add_order_note( trim( sprintf( __( "NMI failure reason: %s %s", 'wc-nmi' ), $response['response_code'] . ' - ' . $response['responsetext'], self::get_avs_message( $response['avsresponse'] ) ) ) );
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
			'payment'			=> 'check',
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
			$account_args = array(
				'payment_token' => $js_response['token'],
            );
        } else {
            $account_args = array(
                'checkaba'				=> wc_clean( $_POST['nmi-echeck-routing-number'] ),
				'checkaccount'			=> wc_clean( $_POST['nmi-echeck-account-number'] ),
				'account_type'			=> $_POST['nmi-echeck-account-type'],
				'checkname'				=> $_POST['nmi-echeck-account-name'],
				'account_holder_type' 	=> $_POST['nmi-echeck-holder-type'],
            );
        }

		$customer_name = sprintf( __( 'Customer: %s %s', 'wc-nmi' ), get_user_meta( $user_id, 'billing_first_name', true ), get_user_meta( $user_id, 'billing_last_name', true ) );
		$args = array(
			'order_description'	 	=> $customer_name,
			'payment'				=> 'check',
			'first_name'			=> get_user_meta( $user_id, 'billing_first_name', true ),
			'last_name'				=> get_user_meta( $user_id, 'billing_last_name', true ),
			'address1'				=> get_user_meta( $user_id, 'billing_address_1', true ),
			'address2'				=> get_user_meta( $user_id, 'billing_address_2', true ),
			'city'					=> get_user_meta( $user_id, 'billing_city', true ),
			'state'					=> get_user_meta( $user_id, 'billing_state', true ),
			'country'				=> get_user_meta( $user_id, 'billing_country', true ),
			'zip'					=> get_user_meta( $user_id, 'billing_postcode', true ),
			'email' 				=> get_user_meta( $user_id, 'billing_email', true ),
			'phone'					=> get_user_meta( $user_id, 'billing_phone', true ),
			'company'				=> get_user_meta( $user_id, 'billing_company', true ),
			'customer_vault' 		=> 'add_customer',
			'customer_vault_id'		=> '',
			'currency'				=> $this->get_payment_currency( $order_id ),
		);

		$args = array_merge( $account_args, $args );

		$args = apply_filters( 'wc_nmi_request_args', $args, $order );

		$response = $this->nmi_request( $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( ! empty( $response['customer_vault_id'] ) ) {

			// Store the ID on the user account if logged in
			if ( is_user_logged_in() ) {
				update_user_meta( get_current_user_id(), '_nmi_customer_id', $response['customer_vault_id'] );
			}

			return $response['customer_vault_id'];
		}

		$error_message = __( 'Unable to add customer', 'wc-nmi' );
		$this->log( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $error_message ) );
		return new WP_Error( 'error', $error_message );
	}

	/**
	 * Add a echeck account to a customer via the API.
	 *
	 * @param int $customer_id
	 * @param bool $skip
	 * @return object
	 */
	public function add_account( $customer_id, $skip = false ) {

		if( $js_response = $this->get_nmi_js_response() ) {
			$account = array(
				'id' => $customer_id,
				'last4'	=> substr( $js_response['check']['account'], -4 ),
			);
		} else {
			$account_no = wc_clean( $_POST['nmi-echeck-account-number'] );
			$account = array(
				'id' => $customer_id,
				'last4'	=> substr( $account_no, -4 ),
			);
		}
		$account = (object) $account;

		if( !$skip ) {
            $token = new WC_Payment_Token_eCheck();
            $token->set_token( $account->id );
			$token->set_gateway_id( 'nmi-echeck' );
			$token->set_last4( $account->last4 );
			$token->set_default( $this->new_account_default );
			$token->set_user_id( get_current_user_id() );
			$token->save();

			// Make sure all other tokens are not set to default.
			if ( $this->new_account_default && $token->get_user_id() > 0 ) {
				WC_Payment_Tokens::set_users_default( $token->get_user_id(), $token->get_id() );
			}
		}
		return $account;
	}

    /**
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the NMI API.
	 * @since 1.1.0
	 */
	public function add_payment_method() {
		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'There was a problem adding the echeck account.', 'wc-nmi' ), 'error' );
			return;
		}

        $customer_id = $this->add_customer();

        if ( is_wp_error( $customer_id ) ) {
			wc_add_notice( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $customer_id->get_error_message() ), 'error' );
			$this->log( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $customer_id->get_error_message() ) );
			return;
        }

        $this->add_account( $customer_id );

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
		$args['sec_code'] = 'WEB';

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
			$error_message = '<!-- Error: ' . $result['response_code'] . ' --> ' . __( 'Transaction failed to process. Please try a different account or another mode of payment, if available.', 'wc-nmi' );
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

	/**
	 * Add needed order meta
	 *
	 * @param integer $order_id    The order ID.
	 * @param array   $posted_data The posted data from checkout form.
	 *
	 * @since   3.3.6
	 * @return  void
	 */
	public function set_payment_method_title( &$order, $account_last4 ) {
		if ( ! isset( $_POST['payment_method'] ) || 'nmi-echeck' !== $_POST['payment_method'] ) {
			return;
		}

		$order->set_payment_method_title( $order->get_payment_method_title() . ' ' . sprintf( '(Account - **** %d)', $account_last4 ) );

	}

	/**
	 * Filters the gateway title to reflect Payment Request type
	 */
	public function filter_gateway_title( $title, $id ) {
		global $post;

		if( ! is_object( $post ) || $id != 'nmi-echeck' ) {
			return $title;
		}

		$order = wc_get_order( $post->ID );

		if( ! is_object( $order ) ) {
			return $title;
		}

		if( $order->get_meta( '_nmi_account_last4' ) && strpos( $title, $order->get_meta( '_nmi_account_last4' ) ) === false ) {
			$title .= ' ' . sprintf( '(Account - **** %d)', $order->get_meta( '_nmi_account_last4' ) );
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