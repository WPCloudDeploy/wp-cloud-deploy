<?php
/**
 * This class is used as a base class
 * to share methods that are needed by multiple classes.
 *
 * Use it as the ancestor class if you need these methods.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCD Base Class
 *
 * @package wpcd
 * @version 1.0.0 / wpcd
 * @since 4.2.0
 */
class WPCD_Base {

	/**
	 * Constructor function.
	 */
	public function __construct() {
		// Empty.
	}

	/**
	 * Adds a notation to the server or app record about
	 * who is performing deferred actions as well as
	 * keeping a history of those deferred actions.
	 *
	 * @param int    $id The id of the server or app to record the deferred action to.
	 * @param string $source The app that is performing the deferred action.
	 * @param string $meta_key The key that is holding the history.
	 *
	 * @return void
	 */
	public function add_deferred_action_history( $id, $source, $meta_key = 'wpcd_server_last_deferred_action_source' ) {

		$def_history = wpcd_maybe_unserialize( get_post_meta( $id, $meta_key, true ) );
		if ( ! $def_history ) {
			$def_history = array();
		}

		// First trim the array in order to make the display more manageable, we'll just show the last X elements...\.
		$keepcnt = 15;
		if ( count( $def_history ) > $keepcnt ) {

			$cnt             = count( $def_history );
			$counter         = 0;
			$start           = $cnt - $keepcnt;
			$def_old_history = array_merge( array(), $def_history ); // make a copy that is not a reference.
			$def_history     = null;
			foreach ( $def_old_history as $key => $value ) {
				$counter++;
				if ( $counter > $start ) {
					$def_history[ $key ] = $value;
				}
			}

			$notation[' 0'] = __( 'Data has been purged.  Only the last 15 elements are shown.', 'wpcd' );
			$def_history    = array_merge( $notation, $def_history );
		}

		// Add in the new value.
		$def_history[ ' ' . (string) time() ] = $source; // we have to force the time element to be a string by putting a space in front of it otherwise manipulating the array as a key-value pair is a big problem when purging.

		update_post_meta( $id, $meta_key, $def_history );
	}


	/**
	 * Get formatted custom links
	 *
	 * Servers and apps *MAY* contain a list of custom links.
	 * This function will return those links as a formatted, clickable
	 * string to be used by the calling program.
	 *
	 * @param string $post_id is the post id of the server or app record we're asking about.
	 *
	 * @return string
	 */
	public function get_formatted_custom_links( $post_id ) {

		$raw_links = get_post_meta( $post_id, 'wpcd_links', true );

		// shortcut - bail if nothing.
		if ( empty( $raw_links ) ) {
			return '';
		}

		// start to construct strings...
		$return_string = '';
		foreach ( $raw_links as $raw_link ) {
			$return_string .= '<div class="wpcd_custom_link">' . sprintf( '<a href = "%s" target="_blank">' . esc_html( $raw_link['wpcd_link_label'] ) . '</a>', esc_url( $raw_link['wpcd_link_url'] ) ) . '</div>';
		}

		return $return_string;
	}

}
