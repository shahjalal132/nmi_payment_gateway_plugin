<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( 'class-wc-gateway-nmi.php' );

/**
 * WC_Gateway_NMI_Addons class.
 *
 * @extends WC_Gateway_NMI
 */
class WC_Gateway_NMI_Addons extends WC_Gateway_NMI {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );

			add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'update_failing_payment_method' ), 10, 2 );

			// display the credit card used for a subscription in the "My Subscriptions" table
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

			// allow store managers to manually set NMI as the payment method on a subscription
			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ) );
		}
	}

	/**
	 * Process the subscription
	 *
	 * @param int $order_id
	 * @param bool $retry
	 *
	 * @return array
	 */
	public function process_subscription( $order_id, $retry = true ) {
		$order       = wc_get_order( $order_id );
		$token_id    = isset( $_POST['wc-nmi-payment-token'] ) ? wc_clean( $_POST['wc-nmi-payment-token'] ) : '';
		$customer_id = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_nmi_customer_id', true ) : 0;

		if ( ! $customer_id || ! is_string( $customer_id ) ) {
			$customer_id = 0;
		}

		$this->log( "Info: Beginning processing subscription payment for order $order_id for the amount of {$order->get_total()}" );

		// Use NMI CURL API for payment
		try {
			$three_ds_args = array();
			// Pay using a saved card!
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
				$card    = array(
					'id'    => $card_id,
					'ccexp' => $token->get_expiry_month() . substr( $token->get_expiry_year(), -2 ),
					'last4' => $token->get_last4(),
					'brand' => wc_get_credit_card_type_label( $token->get_card_type() ),
				);
				$card    = (object) $card;
			}

			// Save token
			if ( ! $customer_id || ! $token_id || $token_id === 'new' ) {

				if( $js_response = $this->get_nmi_js_response() ) {

					if( isset( $js_response['three_ds_version'] ) ) {
						$three_ds_args['cavv']                = $js_response['cavv'];
						$three_ds_args['xid']                 = $js_response['xid'];
						$three_ds_args['eci']                 = $js_response['eci'];
						$three_ds_args['cardholder_auth']     = $js_response['cardholder_auth'];
						$three_ds_args['three_ds_version']    = $js_response['three_ds_version'];
						$three_ds_args['directory_server_id'] = $js_response['directory_server_id'];
					}
				} else {
					$card_type = $this->get_card_type( wc_clean( $_POST['nmi-card-number'] ), 'pattern', 'name' );

					// Check for CC details filled or not
					if ( empty( $_POST['nmi-card-number'] ) || empty( $_POST['nmi-card-expiry'] ) || empty( $_POST['nmi-card-cvc'] ) ) {
						throw new Exception( __( 'Credit card details cannot be left incomplete.', 'wc-nmi' ) );
					}

					// Check for card type supported or not
					if ( ! in_array( $card_type, $this->allowed_card_types ) ) {
						$this->log( sprintf( __( 'Card type being used is not one of supported types in plugin settings: %s', 'wc-nmi' ), $card_type ) );
						throw new Exception( __( 'Card Type Not Accepted', 'wc-nmi' ) );
					}
				}

				$maybe_saved_card = isset( $_POST['wc-nmi-new-payment-method'] ) && ! empty( $_POST['wc-nmi-new-payment-method'] );
				$customer_id      = $this->add_customer( $order_id );

				if ( is_wp_error( $customer_id ) ) {
					$payment_response = $customer_id;
					throw new Exception( $customer_id->get_error_message() );
				} else {
					$skip = ! ( $this->saved_cards && $maybe_saved_card );
					$card = $this->add_card( $customer_id, $skip );
				}
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

			// Store the ID in the order
			$this->save_meta( $order_id, $customer_id, $card_id, $card, $three_ds_args );
			$order = wc_get_order( $order_id );

			if ( ! isset( $_GET['change_payment_method'] ) && $order->get_total() > 0 ) {
				$payment_response = $this->process_subscription_payment( $order, $order->get_total(), true );

				if ( is_wp_error( $payment_response ) ) {
					throw new Exception( $payment_response->get_error_message() );
				}

			} else {
				$order->payment_complete();
				$order->save();
			}

			WC()->cart->empty_cart();

			// Return thank you page redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);

		} catch ( Exception $e ) {

			wc_add_notice( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $e->getMessage() ), 'error' );
			$this->log( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $e->getMessage() ) );

			if ( is_wp_error( $payment_response ) && $response = $payment_response->get_error_data() ) {
				$order->add_order_note( trim( sprintf( __( "NMI failure reason: %s %s %s", 'wc-nmi' ), $response['response_code'] . ' - ' . $response['responsetext'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) ) );
			}

			do_action( 'wc_gateway_nmi_process_payment_error', $e, $order );

			if ( ! isset( $_GET['change_payment_method'] ) ) {
				$order->update_status( 'failed' );
			} else {
				$order->set_payment_method( $order->get_meta( '_old_payment_method' ) );
				$order->set_payment_method_title( $order->get_meta( '_old_payment_method_title' ) );
				$order->add_order_note( sprintf( __( 'Payment method changed back to "%1$s" since the new card was not accepted.', 'wc-nmi' ), $order->get_meta( '_old_payment_method_title' ) ) );
				$order->save();
			}
		}
	}

	/**
	 * Store the customer and card IDs on the order and subscriptions in the order
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	protected function save_meta( $order_id, $customer_id, $card_id, $card, $three_ds_args ) {
		$order = wc_get_order( $order_id );

		$order->update_meta_data( '_nmi_customer_id', $customer_id );
		$order->update_meta_data( '_nmi_card_id', $card_id );
		$order->update_meta_data( '_nmi_card', $card );
		$order->update_meta_data( '_nmi_card_last4', $card->last4 );
		$order->update_meta_data( '_nmi_card_type', sanitize_title( $card->brand ) );
		if( $three_ds_args ) {
			$order->update_meta_data( '_nmi_three_ds_args', $three_ds_args );
		}
		$order->save();

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = array();
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription->update_meta_data( '_nmi_customer_id', $customer_id );
			$subscription->update_meta_data( '_nmi_card_id', $card_id );
			$subscription->update_meta_data( '_nmi_card', $card );
			$subscription->update_meta_data( '_nmi_card_last4', $card->last4 );
			$subscription->update_meta_data( '_nmi_card_type', sanitize_title( $card->brand ) );
			if( $three_ds_args ) {
				$subscription->update_meta_data( '_nmi_three_ds_args', $three_ds_args );
			}
			$subscription->save();
		}
	}

	/**
	 * Don't transfer NMI customer/token meta to resubscribe orders.
	 *
	 * @access public
	 *
	 * @param object $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return void
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		$resubscribe_order->delete_meta_data( '_nmi_customer_id' );
		$resubscribe_order->delete_meta_data( '_nmi_card_id' );
		$resubscribe_order->delete_meta_data( '_nmi_card' );
		$resubscribe_order->delete_meta_data( '_nmi_card_last4' );
		$resubscribe_order->delete_meta_data( '_nmi_card_type' );
		$resubscribe_order->delete_meta_data( '_nmi_three_ds_args' );
		$this->delete_renewal_meta( $resubscribe_order );
		$resubscribe_order->save();
	}

	/**
	 * Don't transfer NMI fee/ID meta to renewal orders.
	 *
	 * @access public
	 *
	 * @param object $renewal_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return object
	 */
	public function delete_renewal_meta( $renewal_order ) {
		$renewal_order->delete_meta_data( 'NMI Payment ID' );

		return $renewal_order;
	}

	/**
	 * Process the pre-order
	 *
	 * @param int $order_id
	 *
	 * @return array|void
	 */
	public function process_pre_order( $order_id, $retry = true ) {

		if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) ) {

			$order       = wc_get_order( $order_id );
			$token_id    = isset( $_POST['wc-nmi-payment-token'] ) ? wc_clean( $_POST['wc-nmi-payment-token'] ) : '';
			$customer_id = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_nmi_customer_id', true ) : 0;

			if ( ! $customer_id || ! is_string( $customer_id ) ) {
				$customer_id = 0;
			}

			try {
				$three_ds_args = array();
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

				// Save token
				if ( ! $customer_id || ! $token_id || $token_id === 'new' ) {
					if( $js_response = $this->get_nmi_js_response() ) {
						$card_last4 = substr( $js_response['card']['number'], -4 );
						$card_type  = $this->get_card_type( str_replace( 'diners', 'diners-club', $js_response['card']['type'] ), 'name' );

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
						if ( empty( $_POST['nmi-card-number'] ) || empty( $_POST['nmi-card-expiry'] ) || empty( $_POST['nmi-card-cvc'] ) ) {
							throw new Exception( __( 'Credit card details cannot be left incomplete.', 'wc-nmi' ) );
						}

						// Check for card type supported or not
						if ( ! in_array( $card_type, $this->allowed_card_types ) ) {
							$this->log( sprintf( __( 'Card type being used is not one of supported types in plugin settings: %s', 'wc-nmi' ), $card_type ) );
							throw new Exception( __( 'Card Type Not Accepted', 'wc-nmi' ) );
						}
					}

					$maybe_saved_card = isset( $_POST['wc-nmi-new-payment-method'] ) && ! empty( $_POST['wc-nmi-new-payment-method'] );
					$customer_id      = $this->add_customer( $order_id );

					if ( is_wp_error( $customer_id ) ) {
						$payment_response = $customer_id;
						throw new Exception( $customer_id->get_error_message() );
					} elseif ( $this->saved_cards && $maybe_saved_card ) {
						$this->add_card( $customer_id );
					}
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

				// Store the ID in the order
				$order->update_meta_data( '_nmi_customer_id', $customer_id );
				$order->update_meta_data( '_nmi_card_id', $card_id );
				$order->update_meta_data( '_nmi_card_last4', $card_last4 );
				$order->update_meta_data( '_nmi_card_type', $card_type );
				if ( $three_ds_args ) {
					$order->update_meta_data( '_nmi_three_ds_args', $three_ds_args );
				}

				// Reduce stock levels
				wc_reduce_stock_levels( $order_id );

				// Remove cart
				WC()->cart->empty_cart();

				// Is pre ordered!
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

				$order->save();

				// Return thank you page redirect
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);

			} catch ( Exception $e ) {

				wc_add_notice( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $e->getMessage() ), 'error' );
				$this->log( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $e->getMessage() ) );

				if ( is_wp_error( $payment_response ) && $response = $payment_response->get_error_data() ) {
					$order->add_order_note( trim( sprintf( __( "NMI failure reason: %s %s %s", 'wc-nmi' ), $response['response_code'] . ' - ' . $response['responsetext'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) ) );
				}

				do_action( 'wc_gateway_nmi_process_payment_error', $e, $order );
				$order->update_status( 'failed' );

				return array(
					'result'   => 'success',
					'redirect' => $order->get_checkout_payment_url( true ),
				);
			}
		} else {
			return parent::process_payment( $order_id );
		}
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true ) {
		// Processing subscription
		if ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) ) {
			return $this->process_subscription( $order_id, $retry );

			// Processing pre-order
		} elseif ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order_id ) ) {
			return $this->process_pre_order( $order_id, $retry );

			// Processing regular product
		} else {
			return parent::process_payment( $order_id, $retry );
		}
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 *
	 * @access public
	 * @return void
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		// Define some callbacks if the first attempt fails.
		$retry_callbacks = array();

		if ( $renewal_order->get_meta( '_nmi_card_id' ) != get_user_meta( $renewal_order->get_user_id(), '_nmi_customer_id', true ) ) {
			$retry_callbacks = array(
				'remove_order_card_before_retry',
			);
		}

		while ( 1 ) {
			$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

			if ( is_wp_error( $response ) ) {
				if ( 0 === sizeof( $retry_callbacks ) ) {
					$renewal_order->update_status( 'failed', sprintf( __( 'NMI Transaction Failed (%s)', 'wc-nmi' ), $response->get_error_message() ) );
					break;
				} else {
					$retry_callback = array_shift( $retry_callbacks );
					call_user_func( array( $this, $retry_callback ), $renewal_order );
				}
			} else {
				// Successful
				break;
			}
		}
	}

	/**
	 * Remove order meta
	 *
	 * @param object $order
	 */
	public function remove_order_card_before_retry( $order ) {
		$order->delete_meta_data( '_nmi_card_id' );
		$order->delete_meta_data( '_nmi_three_ds_args' );
		$order->save();
	}

	/**
	 * process_subscription_payment function.
	 *
	 * @access public
	 *
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 * @param bool initial_payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0, $initial_payment = false ) {

		$user_id      = $order->get_user_id();
		$nmi_customer = get_user_meta( $user_id, '_nmi_customer_id', true );

		// If we couldn't find an NMI customer linked to the account, fallback to the order meta data.
		if ( ! $nmi_customer || ! is_string( $nmi_customer ) ) {
			$nmi_customer = $order->get_meta( '_nmi_customer_id' );
		}

		// Or fail :(
		if ( ! $nmi_customer ) {
			return new WP_Error( 'nmi_error', __( 'Customer not found', 'wc-nmi' ) );
		}

		$description = sprintf( __( '%s - Order %s', 'wc-nmi' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );

		if ( $this->line_items ) {
			$description .= ' (' . $this->get_line_items( $order ) . ')';
		}

		$args = array(
			'orderid'            => $order->get_order_number(),
			'order_description'  => $description,
			'amount'             => $amount,
			'shipping'           => $order->get_shipping_total(),
			'tax'                => $order->get_total_tax(),
			'transactionid'      => $order->get_transaction_id(),
			'type'               => $this->capture ? 'sale' : 'auth',
			'first_name'         => $order->get_billing_first_name(),
			'last_name'          => $order->get_billing_last_name(),
			'address1'           => $order->get_billing_address_1(),
			'address2'           => $order->get_billing_address_2(),
			'city'               => $order->get_billing_city(),
			'state'              => $order->get_billing_state(),
			'country'            => $order->get_billing_country(),
			'zip'                => $order->get_billing_postcode(),
			'email'              => $order->get_billing_email(),
			'phone'              => $order->get_billing_phone(),
			'company'            => $order->get_billing_company(),
			'shipping_firstname' => $order->get_shipping_first_name(),
			'shipping_lastname'  => $order->get_shipping_last_name(),
			'shipping_company'   => $order->get_shipping_company(),
			'shipping_address1'  => $order->get_shipping_address_1(),
			'shipping_address2'  => $order->get_shipping_address_2(),
			'shipping_city'      => $order->get_shipping_city(),
			'shipping_state'     => $order->get_shipping_state(),
			'shipping_country'   => $order->get_shipping_country(),
			'shipping_zip'       => $order->get_shipping_postcode(),
			'customer_vault_id'  => $nmi_customer,
			'currency'           => $this->get_payment_currency( $order->get_id() ),
		);

		// See if we're using a particular card
		if ( $card_id = $order->get_meta( '_nmi_card_id' ) ) {
			$args['customer_vault_id'] = $card_id;
			if ( $three_ds_args = $order->get_meta( '_nmi_three_ds_args' ) ) {
				if( ! empty( $three_ds_args['initiated_by'] ) ) {
					$three_ds_args['initiated_by']   = 'merchant';
					$three_ds_args['billing_method'] = 'recurring';
				}
				$args = array_merge( $args, $three_ds_args );
			}
		}

		$args = apply_filters( 'wc_nmi_request_args', $args, $order );

		// Charge the customer
		$response = $this->nmi_request( $args );

		if ( is_wp_error( $response ) ) {
			$this->log( sprintf( __( 'Gateway Error: %s', 'wc-nmi' ), $response->get_error_message() ) );

			return $response;
		}

		if ( $response['response'] == 1 ) {

			$order->update_meta_data( '_nmi_charge_id', $response['transactionid'] );
			$order->update_meta_data( '_nmi_authorization_code', $response['authcode'] );

			$order->set_transaction_id( $response['transactionid'] );

			if ( $args['type'] == 'sale' ) {

				$order->update_meta_data( '_nmi_charge_captured', 'yes' );

				$order->payment_complete( $response['transactionid'] );

				$complete_message = trim( sprintf( __( "NMI charge complete (Charge ID: %s) %s %s", 'wc-nmi' ), $response['transactionid'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) );
				$order->add_order_note( $complete_message );

				$this->log( "Success: $complete_message" );

			} else {

				$order->update_meta_data( '_nmi_charge_captured', 'no' );

				$authorized_message = trim( sprintf( __( "NMI charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization. %s %s", 'wc-nmi' ), $response['transactionid'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) );
				$order->update_status( 'on-hold', $authorized_message . "\n" );

				$this->log( "Success: $authorized_message" );

				wc_reduce_stock_levels( $order->get_id() );

			}

			$order->save();

		}

		return $response;
	}

	/**
	 * Update the customer_id for a subscription after using NMI to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 *
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		$subscription->update_meta_data( '_nmi_customer_id', $renewal_order->get_meta( '_nmi_customer_id' ) );
		$subscription->update_meta_data( '_nmi_card_id', $renewal_order->get_meta( '_nmi_card_id' ) );
		$subscription->update_meta_data( '_nmi_card', $renewal_order->get_meta( '_nmi_card' ) );
		$subscription->update_meta_data( '_nmi_card_last4', $renewal_order->get_meta( '_nmi_card_last4' ) );
		$subscription->update_meta_data( '_nmi_card_type', $renewal_order->get_meta( '_nmi_card_type' ) );
		if( $renewal_order->get_meta( '_nmi_three_ds_args' ) ) {
			$subscription->update_meta_data( '_nmi_three_ds_args', $renewal_order->get_meta( '_nmi_three_ds_args' ) );
		}
		$subscription->save();
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 *
	 * @return string the subscription payment method
	 * @since 1.7.5
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		// bail for other payment methods
		if ( $this->id !== $subscription->get_payment_method() || ! $subscription->get_user_id() ) {
			return $payment_method_to_display;
		}

		$nmi_card_object = $subscription->get_meta( '_nmi_card' );

		if ( $nmi_card_object && is_object( $nmi_card_object ) ) {
			return sprintf( __( 'Via %s card ending in %s', 'wc-nmi' ), ( isset( $nmi_card_object->type ) ? $nmi_card_object->type : $nmi_card_object->brand ), $nmi_card_object->last4 );
		}

		$nmi_customer = $subscription->get_meta( '_nmi_customer_id' );

		// If we couldn't find an NMI customer linked to the subscription, fallback to the user meta data.
		if ( ! $nmi_customer || ! is_string( $nmi_customer ) ) {
			$user_id      = $subscription->get_user_id();
			$nmi_customer = get_user_meta( $user_id, '_nmi_customer_id', true );
		}

		// If we couldn't find an NMI customer linked to the account, fallback to the order meta data.
		if ( ( ! $nmi_customer || ! is_string( $nmi_customer ) ) && false !== $subscription->get_parent() ) {
			$nmi_customer = $subscription->get_parent()->get_meta( '_nmi_customer_id' );
		}

		// Card specified?
		$nmi_card = $subscription->get_meta( '_nmi_card_id' );

		// If we couldn't find an NMI customer linked to the account, fallback to the order meta data.
		if ( ! $nmi_card && false !== $subscription->get_parent() ) {
			$nmi_card = $subscription->get_parent()->get_meta( '_nmi_card_id' );
		}

		// Get cards from API
		$cards = $this->get_tokens();

		if ( $cards && $this->saved_cards ) {
			foreach ( $cards as $card ) {
				if ( $card->get_token() === $nmi_card ) {
					$payment_method_to_display = sprintf( __( 'Via %s card ending in %s', 'wc-nmi' ), $this->get_card_type( $card->get_meta( 'card_type' ), 'pattern' ), $card->get_meta( 'last4' ) );
					break;
				}
			}
		}

		return $payment_method_to_display;
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 * @since 2.5
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {

		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_nmi_customer_id' => array(
					'value' => $subscription->get_meta( '_nmi_customer_id' ),
					'label' => 'NMI Customer ID',
				),
				'_nmi_card_id'     => array(
					'value' => $subscription->get_meta( '_nmi_card_id' ),
					'label' => 'NMI Card ID',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 *
	 * @return exception|void
	 * @since 2.5
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {

		if ( $this->id === $payment_method_id ) {

			if ( empty( $payment_meta['post_meta']['_nmi_customer_id']['value'] ) ) {
				throw new Exception( 'A "_nmi_customer_id" value is required.' );
			}

		}
	}

	/**
	 * Process a pre-order payment when the pre-order is released
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function process_pre_order_release_payment( $order ) {
		try {

			$nmi_customer = $order->get_meta( '_nmi_customer_id' );
			$card_id      = $order->get_meta( '_nmi_card_id' );

			$description = sprintf( __( '%s - Order %s', 'wc-nmi' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );

			if ( $this->line_items ) {
				$description .= ' (' . $this->get_line_items( $order ) . ')';
			}

			$args = array(
				'orderid'            => $order->get_order_number(),
				'order_description'  => $description,
				'amount'             => $order->get_total(),
				'shipping'           => $order->get_shipping_total(),
				'tax'                => $order->get_total_tax(),
				'transactionid'      => $order->get_transaction_id(),
				'type'               => $this->capture ? 'sale' : 'auth',
				'first_name'         => $order->get_billing_first_name(),
				'last_name'          => $order->get_billing_last_name(),
				'address1'           => $order->get_billing_address_1(),
				'address2'           => $order->get_billing_address_2(),
				'city'               => $order->get_billing_city(),
				'state'              => $order->get_billing_state(),
				'country'            => $order->get_billing_country(),
				'zip'                => $order->get_billing_postcode(),
				'email'              => $order->get_billing_email(),
				'phone'              => $order->get_billing_phone(),
				'company'            => $order->get_billing_company(),
				'shipping_firstname' => $order->get_shipping_first_name(),
				'shipping_lastname'  => $order->get_shipping_last_name(),
				'shipping_company'   => $order->get_shipping_company(),
				'shipping_address1'  => $order->get_shipping_address_1(),
				'shipping_address2'  => $order->get_shipping_address_2(),
				'shipping_city'      => $order->get_shipping_city(),
				'shipping_state'     => $order->get_shipping_state(),
				'shipping_country'   => $order->get_shipping_country(),
				'shipping_zip'       => $order->get_shipping_postcode(),
				'customer_vault_id'  => $card_id ? $card_id : $nmi_customer,
				'currency'           => $this->get_payment_currency( $order->get_id() ),
			);

			if( $three_ds_args = $order->get_meta( '_nmi_three_ds_args' ) ) {
				$args = array_merge( $args, $three_ds_args );
			}

			$args = apply_filters( 'wc_nmi_request_args', $args, $order );

			// Make the request
			$response = $this->nmi_request( $args );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			// Store charge ID
			$order->update_meta_data( '_nmi_charge_id', $response['transactionid'] );
			$order->update_meta_data( 'NMI Payment ID', $response['transactionid'] );
			$order->update_meta_data( '_nmi_authorization_code', $response['authcode'] );

			$order->set_transaction_id( $response['transactionid'] );

			if ( $args['type'] == 'sale' ) {

				// Store captured value
				$order->update_meta_data( '_nmi_charge_captured', 'yes' );

				// Payment complete
				$order->payment_complete( $response['transactionid'] );

				// Add order note
				$complete_message = trim( sprintf( __( "NMI charge complete (Charge ID: %s) %s %s", 'wc-nmi' ), $response['transactionid'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) );
				$order->add_order_note( $complete_message );

				$this->log( "Success: $complete_message" );

			} else {

				// Store captured value
				$order->update_meta_data( '_nmi_charge_captured', 'no' );

				// Mark as on-hold
				$authorized_message = trim( sprintf( __( "NMI charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization. %s %s", 'wc-nmi' ), $response['transactionid'], self::get_avs_message( $response['avsresponse'] ), self::get_cvv_message( $response['cvvresponse'] ) ) );
				$order->update_status( 'on-hold', $authorized_message . "\n" );

				$this->log( "Success: $authorized_message" );

			}

			$order->save();

		} catch ( Exception $e ) {
			$order_note = sprintf( __( 'NMI Transaction Failed (%s)', 'wc-nmi' ), $e->getMessage() );

			// Mark order as failed if not already set,
			// otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
			if ( 'failed' != $order->get_status() ) {
				$order->update_status( 'failed', $order_note );
			} else {
				$order->add_order_note( $order_note );
			}
		}
	}
}