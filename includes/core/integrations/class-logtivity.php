<?php
/**
 * This class handles the WPCD Integration with Logtivity.
 *
 * To clarify - this is about sending logs to logtivity from
 * the site on which WPCD is installed.
 * It is NOT about sending logs from sites that WPCD manages!
 * It is a subtle but important distinction!
 *
 * @package WPCD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_DNS
 */
class WPCD_LOGTIVITY extends WPCD_Base {

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

		add_action( 'wp_logtivity_instance', array( $this, 'logtivity_ignore_post_types' ) );

		// Nothing needed.
	}

	/**
	 * Stop logging for certain post types.
	 *
	 * Action Hook: wp_logtivity_instance
	 *
	 * @param object $logtivity_logger An instance of the logtivity_logger class.
	 */
	public function logtivity_ignore_post_types( $logtivity_logger ) {

		// Do not do anything unless Logtivity exists.
		if ( ! class_exists( 'Logtivity_Register_Site' ) ) {
			return;
		}

		// List of WPCD post types that we are willing to accept for Logtivity.
		$post_types_to_accept = array(
			'wpcd_app_server',
			'wpcd_app',
			'wpcd_error_log',
		);

		if ( true === wpcd_str_starts_with( $logtivity_logger->post_type, 'wpcd_' ) && ( ! in_array( $logtivity_logger->post_type, $post_types_to_accept, true ) ) ) {
			$logtivity_logger->stop();
		}
	}

}
