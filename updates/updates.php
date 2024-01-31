<?php

//set_site_transient( 'update_plugins', null );

class WC_NMI_License_Updates {

	const SECRET_KEY = '5b34ae90764af4.57283112';
	const SERVER_URL = 'https://pledgedplugins.com';
	const LONG_NAME = 'WooCommerce Network Merchants (NMI) Gateway (Enterprise)';
	const SHORT_NAME = 'WooCommerce NMI';
	const VAR_IDS = '3426,3428,3430,14934,21333,21341,21346,21353';
	const PLUGIN_SLUG = 'woocommerce-gateway-nmi';
	const PLUGIN_FILE = 'woocommerce-gateway-nmi.php';

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new WC_NMI_License_Updates();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'license_menu' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_version_update' ), 100 );
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 100, 3 );
		add_filter( 'plugin_action_links_' . self::PLUGIN_SLUG . '/' . self::PLUGIN_FILE, array( $this, 'plugin_action_links' ) );
	}

	public function license_menu() {
		add_management_page( sprintf( '%s License', self::SHORT_NAME ), sprintf( '%s License', self::SHORT_NAME ), 'manage_options', sanitize_title( self::LONG_NAME ), array( $this, 'license_page' ) );
	}

	public function license_page() {
		$slug = sanitize_title( self::LONG_NAME );
		echo '<div class="wrap">';
		echo '<h2>' . sprintf( 'Plugin License Activation for: %s', self::LONG_NAME ) . '</h2>';

		/*** License activate button was clicked ***/
		if( isset( $_REQUEST['activate_license'] ) ) {
			$license_key = $_REQUEST['license_key'];

			// API query parameters
			$api_params = array(
				'slm_action' => 'slm_activate',
				'secret_key' => self::SECRET_KEY,
				'license_key' => $license_key,
				'registered_domain' => get_option( 'siteurl' ),
				'item_reference' => urlencode( self::LONG_NAME ),
			);
			$active = 1;
		}
		/*** End of license activation ***/

		/*** License deactivate button was clicked ***/
		if( isset( $_REQUEST['deactivate_license'] ) ) {
			$license_key = $_REQUEST['license_key'];

			// API query parameters
			$api_params = array(
				'slm_action' => 'slm_deactivate',
				'secret_key' => self::SECRET_KEY,
				'license_key' => $license_key,
				'registered_domain' => get_option( 'siteurl' ),
				'item_reference' => urlencode( self::LONG_NAME ),
			);
			$license_key = '';
			$active = '';

		}
		/*** End of license deactivation ***/

		if( isset( $api_params ) ) {

			// License data.
			$license_data = self::call_service_api( $api_params );

			if( is_wp_error( $license_data ) ) {
				$class = 'error';
				$message = $license_data->get_error_message();
			} elseif ( $license_data->result == 'success' ) {
				$class = 'success';
				$message = $license_data->message;
				//Save the license key in the options table
				update_option( $slug . '_license_key', $license_key );
				update_option( $slug . '_license_status', $active );
			} else{
				$class = 'error';
				$message = $license_data->message;
			}

			echo '<div class="notice notice-' . $class . '"><p>' . $message . '</p></div>';
		}

		/*** License check button was clicked ***/
		if( isset( $_REQUEST['check_license'] ) ) {
			$license_key = $_REQUEST['license_key'];

			// API query parameters
			$api_params = array(
				'slm_action' => 'slm_check',
				'secret_key' => self::SECRET_KEY,
				'license_key' => $license_key,
			);
			$license_data = self::call_service_api( $api_params );

			if( is_wp_error( $license_data ) ) {
				$class = 'error';
				$message = $license_data->get_error_message();
			} elseif ( $license_data->result == 'success' ) {
				$clear_license = 0;
				if( in_array( $license_data->status, array( 'blocked', 'expired' ) ) ) {
					$class = 'error';
					$message = 'Your license status is: ' . $license_data->status;
					$clear_license = 1;
				} elseif( in_array( $license_data->status, array( 'pending', 'active' ) ) ) {
					$this_domain = false;
					foreach( $license_data->registered_domains as $domain ) {
						if( $domain->registered_domain == get_option( 'siteurl' ) ) {
							$this_domain = true;
						}
					}
					if( $this_domain ) {
						$class = 'success';
						$message = 'Your license key is valid and active on this site';
						update_option( $slug . '_license_key', $license_key );
						update_option( $slug . '_license_status', '1' );
					} else {
						$class = 'warning';
						$message = 'Your license key is valid but not active on this site, please enter the key again and click activate.';
						$clear_license = 1;
					}

				}
				if( $clear_license && get_option( $slug . '_license_key' ) ==  $license_key ) {
					update_option( $slug . '_license_key', '' );
					update_option( $slug . '_license_status', '' );
				}

			} else{
				$class = 'error';
				$message = $license_data->message;
			}

			echo '<div class="notice notice-' . $class . '"><p>' . $message . '</p></div>';

		}
		/*** End of license checking ***/

		?>
		<p>Please enter the license key for this product to activate it. You were given a license key when you purchased this item.</p>
		<form action="" method="post">
			<table class="form-table">
				<tr>
					<th style="width:100px;"><label for="license_key">License Key</label></th>
					<td ><input class="regular-text" type="text" id="license_key" name="license_key"  value="<?php echo get_option( $slug . '_license_key'); ?>" ></td>
				</tr>
				<tr>
					<th style="width:100px;"><label for="license_key">Status</label></th>
					<td ><?php echo get_option( $slug . '_license_status' ) ? 'Active' : 'Not Active'; ?></td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="activate_license" value="Activate" class="button-primary" />
				<input type="submit" name="deactivate_license" value="Deactivate" class="button" />
				<input type="submit" name="check_license" value="Check" class="button" />
			</p>
		</form>
		<?php

		echo '</div>';
	}

	public function check_version_update( $checked_data ) {

		//Comment out these three lines during testing.
		if( !isset( $checked_data->checked ) || empty( $checked_data->checked ) || !isset( $checked_data->checked[self::PLUGIN_SLUG . '/' . self::PLUGIN_FILE] ) ) {
			return $checked_data;
		}

		global $wp_version;
		$slug = sanitize_title( self::LONG_NAME );
		$license_key = get_option( $slug . '_license_key' );
		$license_status = get_option( $slug . '_license_status' );

		$current_version = $checked_data->checked[self::PLUGIN_SLUG . '/' . self::PLUGIN_FILE];

		// API query parameters
		$api_params = array(
			'slm_action' => 'slm_check_version_update',
			'secret_key' => self::SECRET_KEY,
			'var_ids' => self::VAR_IDS,
			'current_version' => $current_version,
			'item_reference' => urlencode( self::LONG_NAME ),
		);

		$response = self::call_service_api( $api_params );
		//print_r( $response );

		if( is_wp_error( $response ) || ! is_object( $response ) || $response->result == 'error' ) {
			return $checked_data;
		}

		$plugin_data = wp_parse_args( (array) $response->plugin_data, array(
			'name' => self::LONG_NAME,
			'slug' => self::PLUGIN_SLUG,
			'icons' => array(),
			'new_version' => '0.0.0',
		) );

		$plugin_data['icons'] = (array) $plugin_data['icons'];
		if( $license_key && $license_status ) {
			$expiry = time() + ( 24 * HOUR_IN_SECONDS );
			$token = base64_encode( $expiry . '|' . $license_key . '|' . get_option( 'siteurl' ) . '|' . self::SECRET_KEY );
			//return $checked_data;
			// API query parameters
			$params = array(
				'slm_action' => 'slm_download',
				'secret_key' => self::SECRET_KEY,
				'var_ids' => self::VAR_IDS,
				'token' => $token,
			);
			$download_url = esc_url_raw( add_query_arg( $params, trailingslashit( self::SERVER_URL ) ) );
			$plugin_data['download_link'] = $plugin_data['package'] = $download_url;
		}

		if( version_compare( $plugin_data['new_version'], $current_version, '>' ) ) {
			$checked_data->response[self::PLUGIN_SLUG . '/' . self::PLUGIN_FILE] = (object) $plugin_data;
		}
		//print_r( $checked_data->response );
		//print_r( $plugin_data );

		return $checked_data;
	}

	public function get_plugin_info( $data, $action, $args ) {

		if ( $action != 'plugin_information' || ! isset( $args->slug ) || $args->slug != self::PLUGIN_SLUG ) {
			return $data;
		}

		// API query parameters
		$api_params = array(
			'slm_action' => 'slm_' . $action,
			'secret_key' => self::SECRET_KEY,
			'var_ids' => self::VAR_IDS,
		);

		$response = self::call_service_api( $api_params );

		if( is_wp_error( $response ) || !$response->plugin_data ) {
			return $data;
		}

		$plugin_data = wp_parse_args( (array) $response->plugin_data, array(
			'name' => self::LONG_NAME,
			'slug' => self::PLUGIN_SLUG,
		));

		$plugin_data['sections'] = (array) $plugin_data['sections'];
		//print_r( $plugin_data );
		return (object) $plugin_data;
	}

	/**
	 * Execute plugin service call
	 *
	 * @since     1.0.0
	 */
	public static function call_service_api( $api_params ) {

		// Send query to the license manager server
        $query = esc_url_raw( add_query_arg( $api_params, trailingslashit( self::SERVER_URL ) ) );
        $response = wp_remote_get( $query, array( 'timeout' => 20, 'sslverify' => false ) );

		if( wp_remote_retrieve_response_code( $response ) != 200 ) {
			$response = new WP_Error( 'connection-error', 'Cannot connect to license server.' );
		}

        // Check for error in the response
        if( is_wp_error( $response ) ) {
            return $response;
        }

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'tools.php?page=' . sanitize_title( self::LONG_NAME ) ) . '">License</a>',
		);
		return array_merge( $plugin_links, $links );
	}
}
WC_NMI_License_Updates::get_instance();