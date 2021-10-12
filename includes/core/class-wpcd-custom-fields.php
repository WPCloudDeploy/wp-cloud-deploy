<?php
/**
 * This class handles custom fields.
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
class WPCD_Custom_Fields {

	/**
	 * The array of custom fields.
	 *
	 * @var array
	 */
	private $custom_fields = array();

	/**
	 * Constructor function.
	 */
	public function __construct() {
		// empty - normally just call a function to set an array of defaults.
	}

	/**
	 * Add a field to the custom fields array
	 *
	 * A field array looks like this:
	 *      ['name']
	 *      ['display_name']
	 *      ['location']  eg: "wordpress-app-app-popup"  (where will we be displaying this field?)
	 *      ['sublocation']
	 *      ['script_merge'] Ok to merge with bash control scripts?  True/False
	 *      ['script_name'] The name of the script to merge this field into - eg: 'install_wordpress_site.txt'  Only required if the 'script_merge' element is set to true.
	 *      ['type'] eg: 'text' or 'select'
	 *      ['display'] How will this field get displayed? Are we going to use metabox.io or generate the html ourselves?  Valid values are 'raw_html' and 'metabox.io'.
	 *      ['options'] if field is radio or select then an array of options is needed so we know what to display
	 *
	 * @param string $name internal name/id of the field - basically the index into the custom_fields array.
	 * @param array  $field an array of elements that describe the field.
	 */
	public function add_field( $name, $field ) {

		$this->custom_fields[ $name ] = $field;

	}

	/**
	 * Return the array of custom fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return apply_filters( 'wpcd_get_custom_fields', $this->custom_fields );
	}

	/**
	 * Helper function to return a list of fields for a particular location.
	 *
	 * @param string $location location to search fields for.
	 *
	 * @return array array of fields for the requested location.
	 */
	public function get_fields_for_location( $location ) {

		$return = array();

		foreach ( $this->get_fields() as $field ) {
			if ( $location === $field['location'] ) {
				$return[ $field['name'] ] = $field;
			}
		}

		return $return;

	}

	/**
	 * Helper function to return a list of fields for a particular merge script.
	 *
	 * Only returns fields that match the requested script name AND
	 * where the 'script_merge' element is set to TRUE.
	 *
	 * @param string $script merge script name to search fields for.
	 *
	 * @return array array of fields for the requested script.
	 */
	public function get_fields_for_script( $script ) {

		$return = array();

		foreach ( $this->get_fields() as $field ) {
			if ( isset( $field['script_name'] ) && isset( $field['script_merge'] ) ) {
				if ( $script === $field['script_name'] && true === $field['script_merge'] ) {
					$return[ $field['name'] ] = $field;
				}
			}
		}

		return $return;

	}

}
