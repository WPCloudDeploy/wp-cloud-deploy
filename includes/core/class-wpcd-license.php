<?php
/**
 * This class handles license checks.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup certain license functions
 *
 * @package wpcd
 * @version 1.0.0 / wpcd
 * @since 4.2.0
 */
class WPCD_License {

	/**
	 * Constructor function.
	 */
	public function __construct() {
		// empty - normally just call a function to set an array of defaults.
	}

	/**
	 * Check a particular license.
	 *
	 * @param string $license_key The license key to be checked.
	 * @param string $item_id EDD Item id.
	 */
	public static function check_license( $license_key, $item_id ) {

		// Only do things inside the admin screen.
		if ( is_admin() ) {

			// what server are we contacting for license checks?
			$license_server = wpcd_get_option( 'wpcd_store_url' );
			if ( empty( $license_server ) ) {
				$license_server = 'https://wpclouddeploy.com';
			}

			// When we talk to the server, we need to set a timeout period.
			$timeout = wpcd_get_option( 'wpcd_license_check_timeout' );
			if ( empty( $timeout ) ) {
				$timeout = 30;
			}

			// data to send in our API request.
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license_key,
				'item_id'    => $item_id, // The ID of the item in EDD.
				'url'        => home_url(),
			);

			// Make the request.
			$response = wp_remote_post(
				$license_server,
				array(
					'timeout'   => $timeout,
					'sslverify' => false,
					'body'      => $api_params,
				)
			);

			// Check and evaluate the response.
			$message = '';
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

				$message = ( is_wp_error( $response ) && ! empty( $response->get_error_message() ) ) ? $response->get_error_message() : __( 'An unhandled error occurred, please try again.', 'wpcd' );

			} else {

				/**
				 * $license_data will consists of the following object format on a valid license check:
				 *  stdClass Object
				 *  (
				 *      [success] => 1
				 *      [license] => valid
				 *      [item_id] => 1493
				 *      [item_name] => WPCloud Deploy Core
				 *      [license_limit] => 0
				 *      [site_count] => 1
				 *      [expires] => 2021-03-12 23:59:59
				 *      [activations_left] => unlimited
				 *      [checksum] => fdd7707156d4389ff22538710a644098
				 *      [payment_id] => 1812
				 *      [customer_name] => WPC Demo01
				 *      [customer_email] => wpcdemo01@ncubesoftware.com
				 *      [price_id] => 1
				 *  )
				 */
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );

				if ( false === $license_data->success ) {

					switch ( $license_data->error ) {

						case 'expired':
							$message = sprintf(
								/* translators: %s: Date the license key expired. */
								__( 'Your license key expired on %s.' ),
								date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
							);
							break;

						case 'revoked':
							$message = __( 'Your license key has been disabled.', 'wpcd' );
							break;

						case 'missing':
							$message = __( 'Invalid license - missing/unable to find license on the license server.', 'wpcd' );
							break;

						case 'invalid':
							$message = __( 'Invalid license.', 'wpcd' );
							break;

						case 'invalid_item_id':
							$message = __( 'Invalid item id - likely because this license is not valid for this product or item.', 'wpcd' );
							break;

						case 'site_inactive':
							$message = __( 'Your license is not active for this URL.', 'wpcd' );
							break;

						case 'item_name_mismatch':
							$message = __( 'This appears to be an invalid license key for this item.', 'wpcd' );
							break;

						case 'no_activations_left':
							$message = __( 'Your license key has reached its activation limit.', 'wpcd' );
							break;

						case 'disabled':
							$message = __( 'Your license key has been disabled on the licensing server.', 'wpcd' );
							break;

						default:
							/* translators: %s: A default error message that was returned when checking for a license key. */
							$message = sprintf( __( 'An unhandled error occurred, please try again: %s', 'wpcd' ), $license_data->error );
							break;
					}
				}
			}

			if ( ! empty( $message ) ) {
				// Throw error in error log.
				do_action( 'wpcd_log_error', "Error on license check: $message. Checking item id: $item_id.", 'error', __FILE__, __LINE__, array(), false );

				// update license status and notes transient to show invalid.
				set_transient( "wpcd_license_status_for_$item_id", $message, 24 * 60 * 60 );
				set_transient( "wpcd_license_notes_for_$item_id", $message, 24 * 60 * 60 );

				// If this is the core plugin, make sure we remove any license limit options items we have set.
				if ( (int) WPCD_ITEM_ID === (int) $item_id ) {
					// @TODO: Don't do it yet otherwise it will remove a users' ability to use their product from their originally purchased license that might have expired.
					// We still want them to be able to use the plugin even though the license has expired.
					// i.e.: Once activated, they should be able to continue to use the currently activated site even if the license eventually becomes invalid for some reason.
				}
			} else {
				do_action( 'wpcd_log_error', "license check successful: $message. Checking item id: $item_id.", 'trace', __FILE__, __LINE__, array(), false );
				// Update license status - it will be 'valid' or 'invalid'.
				set_transient( "wpcd_license_status_for_$item_id", $license_data->license, 24 * 60 * 60 );
				if ( 'unlimited' === (string) $license_data->activations_left ) {
					/* translators: %1s: license expiration date. %2s: license activations. %3s: number of sites license is active on. */
					$license_string = sprintf( __( 'Your license is valid until %1$s. You have %2$s activations left. Your license is active on %3$s site(s).', 'wpcd' ), $license_data->expires, $license_data->activations_left, $license_data->site_count );
				} else {
					/* translators: %1s: license expiration date. %2s: license activations. %3s: license activation remaining. %4s: number of sites license is active on. */
					$license_string = sprintf( __( 'Your license is valid until %1$s. You have %2$s activations left of %3$s. Your license is active on %4$s site(s).', 'wpcd' ), $license_data->expires, $license_data->activations_left, $license_data->license_limit, $license_data->site_count );
				}
				set_transient( "wpcd_license_notes_for_$item_id", $license_string, 24 * 60 * 60 );

				// If this is the core plugin, store the license limits in a setting.
				if ( (int) WPCD_ITEM_ID === (int) $item_id ) {
					if ( '0' === (string) $license_data->license_limit ) {
						update_option( 'wpcd_license_limit', 'unlimited' );
					} else {
						update_option( 'wpcd_license_limit', $license_data->license_limit );
					}
				}
			}
		}

	}

	/**
	 * Apply the EDD updater class to each plugin and add-on.
	 *
	 * This function is usually called from an admin_init hook
	 * which is setup in the class-wpcd-settings.php file.
	 */
	public static function update_plugins() {

		/* Make sure the EDD updater class exists otherwise load the file */
		if ( ! class_exists( 'WPCD_EDD_SL_Plugin_Updater' ) ) {
			require_once wpcd_path . 'includes/vendor/WPCD_EDD_SL_Plugin_Updater.php';
		}

		/* Initial array of plugins that contain just the core plugin. */
		$plugins   = array();
		$plugins[] = array(
			WPCD_ITEM_ID => array(
				'name'        => WPCD_EXTENSION,
				'version'     => WPCD_VERSION,
				'add-on-file' => WPCD_BASE_FILE,
			),
		);

		/* Get the array of existing add-ons. */
		$plugins = apply_filters( 'wpcd_register_add_ons_for_licensing', $plugins );

		/* Loop through add-ons and instantiate updater object. */
		foreach ( $plugins as $item ) {
			if ( ! empty( $item ) ) {
				foreach ( $item as $item_id => $item_details ) {
					$edd_updater = new WPCD_EDD_SL_Plugin_Updater(
						wpcd_get_early_option( 'wpcd_store_url' ),
						$item_details['add-on-file'],
						array(
							'version' => $item_details['version'],       // current version number.
							'license' => wpcd_get_early_option( "wpcd_item_license_$item_id" ),  // license key.
							'item_id' => $item_id,
							'author'  => 'wpcd',
							'url'     => home_url(),
							'beta'    => false,
						)
					);
				}
			}
		}

	}

	/**
	 * Check for software updates to the core plugin and add-ons.
	 *
	 * Note that this function just checks the status of the
	 * plugin on the license server vs our current versions.
	 * It does not actually go through the EDD updater class.
	 * It updates a transient with the plugin status that is
	 * then displayed on the license screen.
	 */
	public static function check_for_updates() {

		/* Initial array of plugins that contain just the core plugin. */
		$plugins   = array();
		$plugins[] = array(
			WPCD_ITEM_ID => array(
				'name'        => WPCD_EXTENSION,
				'version'     => WPCD_VERSION,
				'add-on-file' => WPCD_BASE_FILE,
			),
		);

		/* Get the array of existing add-ons. */
		$plugins = apply_filters( 'wpcd_register_add_ons_for_licensing', $plugins );

		// what server are we contacting for license checks?
		$license_server = wpcd_get_option( 'wpcd_store_url' );

		// When we talk to the server, we need to set a timeout period.
		$timeout = wpcd_get_option( 'wpcd_license_check_timeout' );
		if ( empty( $timeout ) ) {
			$timeout = 30;
		}

		/* Loop through plugins & add-ons and send wp_remote_post request. */
		foreach ( $plugins as $item ) {
			if ( ! empty( $item ) ) {
				foreach ( $item as $item_id => $item_details ) {

					// data to send in our API request.
					$api_params = array(
						'edd_action' => 'get_version',
						'license'    => wpcd_get_early_option( "wpcd_item_license_$item_id" ),  // license key.
						'item_id'    => $item_id,
						'url'        => home_url(),
					);

					// Make the request.
					$response = wp_remote_post(
						$license_server,
						array(
							'timeout'   => $timeout,
							'sslverify' => false,
							'body'      => $api_params,
						)
					);

					// Check and evaluate the response.
					$message = '';
					if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

						$message = ( is_wp_error( $response ) && ! empty( $response->get_error_message() ) ) ? $response->get_error_message() : __( 'An unhandled error occurred, please try again.', 'wpcd' );
						do_action( 'wpcd_log_error', "Software update check unsuccessful: $message. Checking item id: $item_id.", 'error', __FILE__, __LINE__, array(), false );

					} else {

						$update_data = json_decode( wp_remote_retrieve_body( $response ) );
						if ( version_compare( $update_data->new_version, $item_details['version'] ) >= 1 ) {
							/* translators: %1s: Current version. %2s: New version. */
							$message = sprintf( __( 'An update is available for this item. The current version is %1$1s and the new version is %2$2s', 'wpcd' ), $item_details['version'], $update_data->new_version );
						}

						do_action( 'wpcd_log_error', "Software update check successful: $message. Checking item id: $item_id.", 'trace', __FILE__, __LINE__, array(), false );

					}

					// write the response to a transient for at least 7 days.
					set_transient( "wpcd_license_updates_for_$item_id", $message, 24 * 60 * 60 * 7 );
				}
			}
		}
	}

	/**
	 * Validate all licenses.
	 *
	 * This function is usually called from an admin_init hook
	 * which is setup in the class-wpcd-settings.php file.
	 */
	public static function validate_all_licenses() {

		/* Initial array of plugins that contain just the core plugin. */
		$plugins   = array();
		$plugins[] = array(
			WPCD_ITEM_ID => array(
				'name'        => WPCD_EXTENSION,
				'version'     => WPCD_VERSION,
				'add-on-file' => WPCD_BASE_FILE,
			),
		);

		/* Get the array of existing add-ons. */
		$plugins = apply_filters( 'wpcd_register_add_ons_for_licensing', $plugins );

		/* Loop through plugins & add-ons and call license check funtion. */
		foreach ( $plugins as $item ) {
			if ( ! empty( $item ) ) {
				foreach ( $item as $item_id => $item_details ) {
					if ( ! empty( wpcd_get_early_option( "wpcd_item_license_$item_id" ) ) ) {
						self::check_license( wpcd_get_early_option( "wpcd_item_license_$item_id" ), $item_id );
					}
				}
			}
		}

	}

	/**
	 * Get the maximum number of servers allowed for this product.
	 *
	 * Because EDD doesn't really have a 'server count' we'll use its license limit instead
	 * which is stored in an option.
	 *
	 * @return int maximum number of servers allowed with the current license.
	 */
	public static function get_server_limit() {
		$server_limit = get_option( 'wpcd_license_limit' );
		if ( 'unlimited' === (string) $server_limit ) {
			return 9999999;
		} else {
			if ( empty( $server_limit ) ) {
				return 3;  // Default allow 3 servers.
			} else {
				return (int) $server_limit;
			}
		}
		return 3; // Should never get here!
	}

	/**
	 * Get the maximum number of WordPress sites allowed for this product.
	 *
	 * Because EDD doesn't really have a 'WordPress sites count' we'll use its license limit instead
	 * which is stored in an option.
	 *
	 * @return int maximum number of sites allowed with the current license.
	 */
	public static function get_wpsite_limit() {
		$server_limit = get_option( 'wpcd_license_limit' );
		if ( 'unlimited' === (string) $server_limit ) {
			return 9999999;
		} else {
			if ( empty( $server_limit ) ) {
				return 5; // Default allow 5 sites.
			} else {
				return  ( ( (int) $server_limit ) * 3 );  // We'll allow up to three sites per server if a server limit is set.
			}
		}
		return 5; // Should never get here!
	}

	/**
	 * Check to see if we have exceeded the maximum number of allowed servers
	 *
	 * Note that this check includes ALL server types including WP, VPN, BASIC SERVER etc.
	 *
	 * @return boolean true=more servers are allowed; false = allowed server count limit reached.
	 */
	public static function check_server_limit() {

		$count_posts = wp_count_posts( 'wpcd_app_server' )->private;
		if ( self::get_server_limit() > $count_posts ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Check to see if we have exceeded the maximum number of allowed wp sites
	 *
	 * @return boolean true=more wp sites are allowed; false = allowed wpsite count limit reached.
	 */
	public static function check_wpsite_limit() {

		$count_posts = wp_count_posts( 'wpcd_app' )->private;
		if ( self::get_wpsite_limit() > $count_posts ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Function to determine whether to show license tab and/or do certain license related things.
	 */
	public static function show_license_tab() {
		if ( ! defined( 'WPCD_HIDE_LICENSE_TAB' ) || ( defined( 'WPCD_HIDE_LICENSE_TAB' ) && ( ! WPCD_HIDE_LICENSE_TAB ) && ( 1 === get_current_blog_id() ) ) ) {
			return true;
		} else {
			return false;
		}
	}

}
