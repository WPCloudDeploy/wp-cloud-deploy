<?php
/**
 * This file contains functions that relate to creating
 * and display admin notices.
 *
 * Eventually all other admin notice related items should
 * end up in this file.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notice display.
 *
 * Action Hook: admin_notices
 *
 * Grab a transient that might exist and show an admin notice for
 * each entry in its array structure.
 */
function wpcd_global_handle_admin_notices() {

	if ( ! wpcd_is_admin() ) {
		return false;
	}

	// Transient Name.
	$transient_name = 'wpcd_tabs_' . get_current_user_id();

	// Check if there are any notices stored.
	$notifications = get_transient( $transient_name );

	if ( $notifications ) {
		foreach ( $notifications as $notification ) {
			echo '<div class="notice notice-custom notice-' . esc_html( $notification['type'] ) . ' is-dismissible">
					<p><strong>' . esc_html( $notification['message'] ) . '</strong></p>
				</div>';
		}
	}

	// Clear away our transient data, it's not needed any more.
	delete_transient( $transient_name );

}
add_action( 'admin_notices', 'wpcd_global_handle_admin_notices' );

/**
 * Add an entry to a transient for eventual display
 * at the top of the admin screen.
 * The transient can hold multiple notices.
 *
 * @param string $message The notice to display.
 * @param string $type    The type of notice.
 */
function wpcd_global_add_admin_notice( $message, $type ) {

	// Transient Name.
	$transient_name = 'wpcd_tabs_' . get_current_user_id();

	// Get a transient that could be holding messages to show at the top of the admin area.
	$transient_value = get_transient( $transient_name );

	if ( false === $transient_value ) {

		// The transient is blank or did not exist so just add a new entry to it.
		$notifications[] = array(
			'message' => $message,
			'type'    => $type,
		);

		// Set transient value.
		set_transient( $transient_name, $notifications );

	} else {

		// The transient already has at least one value in it so append to that our new array.
		$transient_value[] = array(
			'message' => $message,
			'type'    => $type,
		);

		// Set transient value.
		set_transient( $transient_name, $transient_value );

	}
}


