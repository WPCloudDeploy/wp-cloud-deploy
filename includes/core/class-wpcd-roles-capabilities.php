<?php
/**
 * WPCD_ROLES_CAPABILITIES class for user roles capabilities.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_ROLES_CAPABILITIES
 */
class WPCD_ROLES_CAPABILITIES {

	/**
	 * WPCD_ROLES_CAPABILITIES instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_ROLES_CAPABILITIES constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hooks(); // register hooks to do things...
	}

	/**
	 * To hook custom actions and filters
	 *
	 * @return void
	 */
	private function hooks() {
		// Action hook to create roles and capabilities when a new site is created.
		add_action( 'wp_initialize_site', array( $this, 'wpcd_roles_capabilities_new_site' ), 10, 2 );

		// Action hook to remove wpcd_app listing menu from admin menu.
		add_action( 'admin_menu', array( $this, 'wpcd_roles_capabilities_remove_menus' ) );

	}

	/**
	 * Fires on activation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is activated network-wide.
	 *
	 * @return void
	 */
	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::wpcd_create_roles_capabilities();
				restore_current_blog();
			}
		} else {
			self::wpcd_create_roles_capabilities();
		}
	}

	/**
	 * Creates new roles and adds custom capabilities to existing and new roles
	 *
	 * @return void
	 */
	public static function wpcd_create_roles_capabilities() {

		// get author role and capabilities.
		$author      = get_role( 'author' );
		$author_caps = $author->capabilities;

		// 1. WPCDAdmin Role.
		// list of custom capabilities for WPCDAdmin role.
		$wpcd_admin_caps = array(
			'wpcd_provision_servers' => true,
			'wpcd_manage_servers'    => true,
			'wpcd_manage_apps'       => true,
			'wpcd_manage_logs'       => true,
			'wpcd_manage_settings'   => true,
			'wpcd_manage_groups'     => true,
			'wpcd_manage_teams'      => true,
			'wpcd_manage_all'        => true,
		);

		$all_wpcd_admin_caps = array_merge( $author_caps, $wpcd_admin_caps );
		add_role( 'wpcdadmin', __( 'WPCDAdmin', 'wpcd' ), $all_wpcd_admin_caps );

		// 2. WPCDManager Role.
		// list of custom capabilities for WPCDManager role.
		$wpcd_manager_caps = array(
			'wpcd_provision_servers' => true,
			'wpcd_manage_servers'    => true,
			'wpcd_manage_apps'       => true,
			'wpcd_manage_logs'       => true,
			'wpcd_manage_settings'   => true,
			'wpcd_manage_groups'     => true,
		);

		$all_wpcd_manager_caps = array_merge( $author_caps, $wpcd_manager_caps );
		add_role( 'wpcdmanager', __( 'WPCDManager', 'wpcd' ), $all_wpcd_manager_caps );

		// get subscriber role and capabilities.
		$subscriber      = get_role( 'subscriber' );
		$subscriber_caps = $subscriber->capabilities;

		// 3. WPCDSysAdmin Role.
		// list of custom capabilities for WPCDSysAdmin role.
		$wpcd_sys_admin_caps = array(
			'wpcd_manage_servers' => true,
			'wpcd_manage_apps'    => true,
		);

		$all_wpcd_sys_admin_caps = array_merge( $subscriber_caps, $wpcd_sys_admin_caps );
		add_role( 'wpcdsysadmin', __( 'WPCDSysAdmin', 'wpcd' ), $all_wpcd_sys_admin_caps );

		// 4. WPCDAppManager Role.
		// list of custom capabilities for WPCDAppManager role.
		$wpcd_app_manager_caps = array(
			'wpcd_manage_apps' => true,
		);

		$all_wpcd_app_manager_caps = array_merge( $subscriber_caps, $wpcd_app_manager_caps );
		add_role( 'wpcdappmanager', __( 'WPCDAppManager', 'wpcd' ), $all_wpcd_app_manager_caps );

		// 5. Administrator Role.
		$administrator = get_role( 'administrator' );

		// list of custom capabilities for administrator role.
		$admin_caps = array(
			'wpcd_provision_servers' => true,
			'wpcd_manage_servers'    => true,
			'wpcd_manage_apps'       => true,
			'wpcd_manage_logs'       => true,
			'wpcd_manage_settings'   => true,
			'wpcd_manage_groups'     => true,
			'wpcd_manage_teams'      => true,
			'wpcd_manage_all'        => true,
		);

		foreach ( $admin_caps as $key => $cap ) {
			$administrator->add_cap( $key );
		}

	}

	/**
	 * To add roles and capabilities on the new site creation
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function wpcd_roles_capabilities_new_site( $new_site, $args ) {
		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {
			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::wpcd_create_roles_capabilities();
			restore_current_blog();
		}
	}

	/**
	 * Removes custom roles and capabilities created by the plugin
	 * This function will be invoked at the time of uninstallation/deletion.
	 *
	 * @return void
	 */
	public static function wpcd_delete_roles_capabilities() {
		$roles_to_remove = array(
			'wpcdadmin',
			'wpcdmanager',
			'wpcdsysadmin',
			'wpcdappmanager',
		);

		// Removing the custom roles.
		foreach ( $roles_to_remove as $role ) {
			remove_role( $role );
		}

		$admin_caps = array(
			'wpcd_provision_servers',
			'wpcd_manage_servers',
			'wpcd_manage_apps',
			'wpcd_manage_logs',
			'wpcd_manage_settings',
			'wpcd_manage_groups',
			'wpcd_manage_teams',
			'wpcd_manage_all',
		);

		$administrator = get_role( 'administrator' );
		// Removing the custom capabilities assigned to administrator role.
		foreach ( $admin_caps as $cap ) {
			$administrator->remove_cap( $cap );
		}
	}

	/**
	 * Removes the wpcd_app post type menu created when registering post type
	 */
	public function wpcd_roles_capabilities_remove_menus() {
		remove_menu_page( 'edit.php?post_type=wpcd_app' );
	}
}
