<?php
/**
 * Uninstall WPCD
 *
 * @package WPCD
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'wpcd_path' ) ) {
	define( 'wpcd_path', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'wpcd_url' ) ) {
	define( 'wpcd_url', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'wpcd_plugin' ) ) {
	define( 'wpcd_plugin', plugin_basename( __FILE__ ) );
}

$options = get_option( 'wpcd_options', array() );

if ( ! empty( $options['uninstall_on_delete'] ) ) {
	if ( ! class_exists( 'WPCD_Setup' ) ) {
		require_once wpcd_path . 'includes/core/class-wpcd-setup.php';
	}

	$wpcd_setup = new WPCD_Setup();

	/* Remove settings */
	foreach ( $wpcd_setup->settings_defaults as $k => $v ) {
		unset( $options[ $k ] );
	}

	unset( $options['wpcd_license_key'] );
	update_option( 'wpcd_options', $options );
	/* End remove settings */

	/* This option was used to control whether our SSH console was hidden globally */
	delete_option( 'wpcd_wpapp_ssh_console_hide' );

	/* The option wpcd_last_upgrade_done holds the numeric version of the last update that was done via the upgrade scripts. */
	delete_option( 'wpcd_last_upgrade_done' );
	delete_option( 'wpcd_last_silent_auto_upgrade_done' );
	
	/* Incompatible addon version checks */
	delete_option( 'wpcd_addons_compatible_last_version_checked' );

	/* Options used in setup wizard */
	delete_option( 'wpcd_setup_wizard_selected_provider' );

	/* Wisdom Options */
	delete_option( 'wisdom_opt_out' );
	delete_option( 'wisdom_wpcd_server_count' );
	delete_option( 'wisdom_wpcd_app_count' );

	/* Setup Wizard Options */
	delete_option( 'wpcd_plugin_setup' );
	delete_option( 'wpcd_skip_wizard_setup' );

	/* Clear long-lived transients. */
	delete_transient( 'wpcd_wisdom_custom_options_first_run_done' );

	/* @TODO: Need to delete all data from all our custom post types. */

	/* These two options are not used right now - need to update an activation function to make them do stuff */
	delete_option( 'wpcd_last_version_upgrade' );
	delete_option( 'wpcd_version' );

}

// if defined in wp-config.php then remove the roles and capabilities as well as custom table.
if ( defined( 'wpcd_delete_data_on_plugin_removal' ) && wpcd_delete_data_on_plugin_removal ) {
	require_once wpcd_path . 'includes/core/class-wpcd-roles-capabilities.php';
	require_once wpcd_path . 'includes/core/class-wpcd-posts-permission-type.php';
	require_once wpcd_path . 'includes/core/class-wpcd-sync.php';

	if ( is_multisite() ) {
		$blog_ids = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			WPCD_ROLES_CAPABILITIES::wpcd_delete_roles_capabilities();
			WPCD_POSTS_PERMISSION_TYPE::wpcd_delete_table();
			WPCD_SYNC::wpcd_delete_restore_files_table();
			restore_current_blog();
		}
	} else {
		WPCD_ROLES_CAPABILITIES::wpcd_delete_roles_capabilities();
		WPCD_POSTS_PERMISSION_TYPE::wpcd_delete_table();
		WPCD_SYNC::wpcd_delete_restore_files_table();
	}
}
