<?php
/**
 * WPCD_Setup class for wpcd setup.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_Setup.
 */
class WPCD_Setup {
	var $settings_defaults;

	/**
	 * WPCD_Setup constructer.
	 */
	public function __construct() {
		// empty - normally just call a function to set an array of defaults.
	}


	/**
	 * Add default settings upon first activation.
	 *
	 * Settings are stored in the 'wpcd_settings'
	 * option and managed by metabox.io.
	 *
	 * But, in this function we have to access it
	 * and apply default values directly to each
	 * array element in it.
	 */
	public function set_default_settings() {

		$options = get_option( 'wpcd_settings' );
		$options = empty( $options ) ? array() : $options;

		if ( $this->settings_defaults ) {
			foreach ( $this->settings_defaults as $key => $value ) {
				// set new options to default.
				if ( ! isset( $options[ $key ] ) ) {
					$options[ $key ] = $value;
				}
			}
		}
		update_option( 'wpcd_settings', $options );

	}

	/**
	 * Add default settings to the database
	 */
	public function run_setup() {
		$this->set_default_settings();
	}

}
