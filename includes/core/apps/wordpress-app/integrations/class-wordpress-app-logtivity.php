<?php
/**
 * This class handles the WPCD Integration with Logtivity.
 *
 * @package WPCD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_DNS
 */
class WPCD_WORDPRESS_APP_LOGTIVITY extends WPCD_Base {

	/**
	 * Constructor
	 */
	public function __construct() {

		// Setup WordPress hooks.
		$this->hooks();

	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {

		// Nothing needed.
	}

	/**
	 * Sets the Logtivity API key for an app.
	 *
	 * @since 5.6
	 *
	 * @param int    $app_id The post id of the app we're working with.
	 * @param string $api_key The API key.
	 */
	public function set_api_key( $app_id, $api_key ) {
		error_log( "API KEY IS: $api_key" );
		update_post_meta( $app_id, 'wpcd_app_wordpress-app_logtivity_api_key', WPCD()->encrypt( $api_key ) );

	}

	/**
	 * Sets the Logtivity API key for an app.
	 *
	 * @since 5.6
	 *
	 * @param int $app_id The post id of the app we're working with.
	 */
	public function get_api_key( $app_id ) {

		return WPCD()->decrypt( get_post_meta( $app_id, 'wpcd_app_wordpress-app_logtivity_api_key', true ) );

	}

	/**
	 * Set whether or not we're waiting for an API key to be returned from a site.
	 * The idea is that if we're not waiting for an api key we shoudn't be setting one.
	 * The function that calls set_api_key should first call get_waiting_status
	 * to check if we're even waitng for one.
	 *
	 * @param int    $app_id The post id of the app we're working with.
	 * @param string $waiting_status The API key.
	 */
	public function set_waiting_status( $app_id, $waiting_status ) {

		update_post_meta( $app_id, 'wpcd_app_wordpress-app_logtivity_api_key_waiting_status', $waiting_status );

	}

	/**
	 * Get whether or not we're waiting for an API key to be returned from a site.
	 * The function that calls set_api_key should first call this function
	 * to check if we're even waitng for one.
	 *
	 * @param int $app_id The post id of the app we're working with.
	 */
	public function get_waiting_status( $app_id ) {

		return boolval( Get_post_meta( $app_id, 'wpcd_app_wordpress-app_logtivity_api_key_waiting_status', true ) );

	}

	/**
	 * Delete the site from the remote logtivity server console.
	 *
	 * @param int $app_id The post id of the app we're working with.
	 */
	public function delete_from_remote_console( $app_id ) {

		// If logtivity isn't active, return immediately.
		if ( ! class_exists( 'Logtivity_Register_Site' ) ) {
			return false;
		}

		// Is there a Logtivity teams API key?
		$teams_api_key = WPCD()->decrypt( wpcd_get_option( 'wordpress_app_logtivity_teams_api_key' ) );
		if ( empty( $teams_api_key ) ) {
			return false;
		}

		// What's the API key for the site.
		$api_key = $this->get_api_key( $app_id );

		// Only do things if there is an api key.
		if ( ! empty( $api_key ) ) {

			$response = json_decode( ( new Logtivity_Api() )->setApiKey( $teams_api_key )->post( '/sites/' . $api_key . '/delete', array() ) );

			if ( $response && property_exists( $response, 'message' ) ) {
				if ( ! empty( $response ) ) {
					return $response;
				} else {
					return true;
				}
			}
		}

		return false;

	}

}
