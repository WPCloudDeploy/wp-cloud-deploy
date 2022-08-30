<?php
/**
 * WPCD_POSTS_PERMISSION_TYPE class for permission type.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_POSTS_PERMISSION_TYPE
 */
class WPCD_POSTS_PERMISSION_TYPE {

	/**
	 * WPCD_POSTS_PERMISSION_TYPE instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_POSTS_PERMISSION_TYPE constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->register();  // register the custom post type.
		$this->hooks(); // register hooks to make the custom post type do things...
	}

	/**
	 * To hook custom actions and filters for wpcd_permission_type post type
	 *
	 * @return void
	 */
	private function hooks() {

		// Load up css and js scripts used for managing this cpt data screens.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 100, 1 );

		// Filter hook to add custom meta box.
		add_filter( 'rwmb_meta_boxes', array( $this, 'wpcd_permission_type_register_meta_boxes' ), 10, 1 );

		// Filter to force post status to private.
		add_filter( 'wp_insert_post_data', array( $this, 'wpcd_permission_type_force_type_private' ), 10, 1 );

		// Action hook to check duplicate permission name.
		add_action( 'wp_ajax_check_permission_name', array( $this, 'wpcd_check_permission_name' ) );

		// Filter hook to add app types to app_list array.
		add_filter( 'wpcd_app_list', array( $this, 'wpcd_permission_type_app_list' ), 10, 1 );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpcd_permission_type_new_site' ), 10, 2 );

		// Action hook to check if user has capability to access add new, edit or listing screen for wpcd_permission_type cpt.
		add_action( 'load-edit.php', array( $this, 'wpcd_permission_type_load' ) );
		add_action( 'load-post-new.php', array( $this, 'wpcd_permission_type_load' ) );
		add_action( 'load-post.php', array( $this, 'wpcd_permission_type_load' ) );

		// Action hook to add custom "back to list" button.
		add_action( 'admin_footer-post.php', array( $this, 'wpcd_permission_type_backtolist_btn' ) );
		add_action( 'admin_footer-post-new.php', array( $this, 'wpcd_permission_type_backtolist_btn' ) );
	}

	/**
	 * Register the custom post type wpcd_permission_type.
	 *
	 * @return void
	 */
	public function register() {
		register_post_type(
			'wpcd_permission_type',
			array(
				'labels'              => array(
					'name'                  => _x( 'WPCD Permission List', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Permission', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Permission List', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Permission', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => __( 'Add Permission', 'wpcd' ),
					'edit_item'             => __( 'EditPermission', 'wpcd' ),
					'view_item'             => __( 'View Permission', 'wpcd' ),
					'all_items'             => __( 'Permissions', 'wpcd' ), // Label to signify all items in a submenu link.
					'search_items'          => __( 'Search Permissions', 'wpcd' ),
					'not_found'             => __( 'No Permissions were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Permissions were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Permissions list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Permissions list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Permissions list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => ( defined( 'WPCD_SHOW_PERMISSION_LIST' ) && ( true === WPCD_SHOW_PERMISSION_LIST ) ) ? 'edit.php?post_type=wpcd_app_server' : false,
				'menu_position'       => null,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'rewrite'             => null,
				'map_meta_cap'        => true,
			)
		);

	}

	/**
	 * Register the scripts for the custom post type wpcd_permission_type.
	 *
	 * @param string $hook hook.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {

		if ( in_array( $hook, array( 'post-new.php', 'post.php' ) ) ) {

			$screen = get_current_screen();
			if ( is_object( $screen ) && 'wpcd_permission_type' == $screen->post_type ) {
				wp_enqueue_script( 'wpcd-permission-type-admin', wpcd_url . 'assets/js/wpcd-permission-type-admin.js', array( 'jquery' ), wpcd_version, true );
			}
		}

	}

	/**
	 * To add custom metabox on server/app permission details screen
	 * This meta box will allow admin to add permission type and name
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param  array $metaboxes metaboxes.
	 *
	 * @return array
	 */
	public function wpcd_permission_type_register_meta_boxes( $metaboxes ) {

		$prefix = 'wpcd_';

		$post_id = 0;

		if ( isset( $_GET['post'] ) && ! empty( $_GET['post'] ) ) {
			$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		}

		$permission_categories = WPCD()->get_app_list();

		// Register the metabox for.
		$metaboxes[] = array(
			'id'         => $prefix . 'permission_details',
			'title'      => __( 'Permission Details', 'wpcd' ),
			'post_types' => array( 'wpcd_permission_type' ), // displays on wpcd_permission_type post type only.
			'context'    => 'normal',
			'fields'     => array(
				array(
					'name'        => 'Object Type',
					'id'          => $prefix . 'object_type',
					'type'        => 'select',
					'class'       => $prefix . 'object-type',
					'options'     => array(
						1 => 'Server',
						2 => 'App',
					),
					'placeholder' => __( 'Select an Object Type', 'wpcd' ),
				),
				array(
					'name'              => 'Permission Name',
					'label_description' => __( 'Enter the permission name (eg: view_server, provision_server etc.)', 'wpcd' ),
					'id'                => $prefix . 'permission_name',
					'type'              => 'text',
					'class'             => $prefix . 'permission-name',
					'placeholder'       => __( 'Please enter permission name here.', 'wpcd' ),
				),
				array(
					'name'        => 'Permission Category',
					'id'          => $prefix . 'permission_category',
					'type'        => 'select',
					'class'       => $prefix . 'permission-category',
					'options'     => $permission_categories,
					'placeholder' => __( 'Select a permission category', 'wpcd' ),
				),
				array(
					'name'              => 'Permission Group',
					'label_description' => __( 'Enter a permission group - 1, 2, 3 or 4', 'wpcd' ),
					'id'                => $prefix . 'permission_group',
					'type'              => 'text',
					'class'             => $prefix . 'permission-group',
					'placeholder'       => __( 'Permission group', 'wpcd' ),
					'tooltip'           => __( 'Group is the column under which the permission will appear when editing a team. 1=Server Permissions; 2=Server Tab Permissions; 3=App Permissions; 4=App Server Permissions', 'wpcd' ),
				),
			),
			'validation' => array(
				'rules'    => array(
					$prefix . 'object_type'         => array(
						'required' => true,
					),
					$prefix . 'permission_name'     => array(
						'required'         => true,
						'valid_permission' => true,
						'remote'           => array(
							'url'  => admin_url( 'admin-ajax.php' ),
							'type' => 'POST',
							'data' => array(
								'action'  => 'check_permission_name',
								'nonce'   => wp_create_nonce( 'wpcd-permission' ),
								'post_id' => $post_id,
							),
						),
					),
					$prefix . 'permission_category' => array(
						'required' => true,
					),
					$prefix . 'permission_group'    => array(
						'required' => true,
					),
				),
				'messages' => array(
					$prefix . 'object_type'         => array(
						'required' => __( 'Object Type is required.', 'wpcd' ),
					),
					$prefix . 'permission_name'     => array(
						'required'         => __( 'Permission Name is required.', 'wpcd' ),
						'valid_permission' => __( 'Please enter a valid permission name.', 'wpcd' ),
						'remote'           => __( 'Permission name already exists. Try another!', 'wpcd' ),
					),
					$prefix . 'permission_category' => array(
						'required' => __( 'Permission Category is required.', 'wpcd' ),
					),
				),
			),
		);

		return $metaboxes;

	}

	/**
	 * Filters the post status to private on saving on wpcd_permission_type detail screen
	 *
	 * Filter hook: wp_insert_post_data
	 *
	 * @param  object $post post.
	 *
	 * @return object
	 */
	public function wpcd_permission_type_force_type_private( $post ) {

		if ( $post['post_type'] == 'wpcd_permission_type' ) {
			if ( $post['post_status'] != 'trash' && $post['post_status'] != 'auto-draft' && $post['post_status'] != 'draft' ) {
				$post['post_status'] = 'private';
			}
		}
		return $post;

	}

	/**
	 * Check for duplicate permission name exists or not for permission type posts
	 *
	 * Action hook : wp_ajax_check_permission_name
	 */
	public function wpcd_check_permission_name() {

		check_ajax_referer( 'wpcd-permission', 'nonce' );

		$wpcd_permission_name = sanitize_text_field( filter_input( INPUT_POST, 'wpcd_permission_name', FILTER_UNSAFE_RAW ) );
		$post_id              = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		$args = array(
			'post_type'   => 'wpcd_permission_type',
			'post_status' => 'private',
			'numberposts' => -1,
			'meta_query'  => array(
				array(
					'key'     => 'wpcd_permission_name',
					'value'   => $wpcd_permission_name,
					'compare' => '=',
				),
			),
		);

		// This will be needed when editing post.
		if ( $post_id != 0 ) {
			$args['post__not_in'] = array( (int) $post_id );
		}

		$posts = get_posts( $args );

		if ( count( $posts ) ) {
			wp_send_json( false );
		} else {
			wp_send_json( true );
		}

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
				self::wpcd_create_table();
				restore_current_blog();
			}
		} else {
			self::wpcd_create_table();
		}
	}

	/**
	 * Creates the custom table for permission assignments
	 * This will happen on Plugin Activation.
	 *
	 * @return void
	 */
	public static function wpcd_create_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'permission_assignments';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE IF NOT EXISTS $table_name (
			team_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			permission_type_id bigint(20) NOT NULL,
			granted tinyint(1) NOT NULL
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		add_option( 'wpcd_db_version', wpcd_db_version );

		// To create missing permission posts.
		self::wpcd_create_permissions();
	}

	/**
	 * This function will create missing permission posts on plugin activation
	 *
	 * @return void
	 */
	public static function wpcd_create_permissions() {
		// Default server/app permissions to insert if not available.
		$permission_posts = array(
			'view_server'                           => array(
				'post_title'               => __( 'View Server', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'core',
				'wpcd_permission_group'    => 1,
			),
			'delete_server'                         => array(
				'post_title'               => __( 'Delete Server', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'core',
				'wpcd_permission_group'    => 1,
			),
			'add_app_wpapp'                         => array(
				'post_title'               => __( 'Add WP App', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'core',
				'wpcd_permission_group'    => 1,
			),
			'add_app_wpapp'                         => array(
				'post_title'               => __( 'Add WP App', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'core',
				'wpcd_permission_group'    => 1,
			),
			'view_wpapp_server_ssh_console_tab'     => array(
				'post_title'               => __( 'View SSH Console Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_users_tab'           => array(
				'post_title'               => __( 'View Users Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_ssh_keys_tab'        => array(
				'post_title'               => __( 'View SSH Keys Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_upgrade_tab'         => array(
				'post_title'               => __( 'View Upgrade Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_fail2ban_tab'        => array(
				'post_title'               => __( 'View Fail2Ban Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_backup_tab'          => array(
				'post_title'               => __( 'View Backup Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_callback_tab'        => array(
				'post_title'               => __( 'View Callback Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_goaccess_tab'        => array(
				'post_title'               => __( 'View GoAccess Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_logs_tab'            => array(
				'post_title'               => __( 'View Logs Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_monit_tab'           => array(
				'post_title'               => __( 'View Healing Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_monitorix_tab'       => array(
				'post_title'               => __( 'View Monitorix Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_power_tab'           => array(
				'post_title'               => __( 'View Power Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_services_tab'        => array(
				'post_title'               => __( 'View Services Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_sites_tab'           => array(
				'post_title'               => __( 'View Sites Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_statistics_tab'      => array(
				'post_title'               => __( 'View Statistics Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_tools_tab'           => array(
				'post_title'               => __( 'View Tools Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_tweaks_tab'          => array(
				'post_title'               => __( 'View Tweaks Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_firewall_tab'        => array(
				'post_title'               => __( 'View Firewall Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),
			'view_wpapp_server_ols_console_tab'     => array(
				'post_title'               => __( 'View OLS Web Console Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),			
			'view_wpapp_server_serversync_tab'      => array(
				'post_title'               => __( 'View Server Sync Tab On WP Server Screen', 'wpcd' ),
				'wpcd_object_type'         => 1,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 2,
			),

			'view_app'                              => array(
				'post_title'               => __( 'View App', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'core',
				'wpcd_permission_group'    => 3,
			),
			'delete_app_record'                     => array(
				'post_title'               => __( 'Delete App', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'core',
				'wpcd_permission_group'    => 3,
			),
			'wpapp_remove_site'                     => array(
				'post_title'               => __( 'WP Remove Site', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 3,
			),
			'wpapp_update_site_php_options'         => array(
				'post_title'               => __( 'WP Update Site PHP Options', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 3,
			),

			'view_wpapp_site_6gfirewall_tab'        => array(
				'post_title'               => __( 'View 6g Firewall Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_7gfirewall_tab'        => array(
				'post_title'               => __( 'View 7g Firewall Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_backup_tab'            => array(
				'post_title'               => __( 'View Backup Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_cache_tab'             => array(
				'post_title'               => __( 'View Cache Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_change_domain_tab'     => array(
				'post_title'               => __( 'View Change Domain Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_clone_site_tab'        => array(
				'post_title'               => __( 'View Clone Site Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_copy_to_existing_tab'  => array(
				'post_title'               => __( 'View Copy To Existing Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_crons_tab'             => array(
				'post_title'               => __( 'View Cron Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_general_tab'           => array(
				'post_title'               => __( 'View General Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_logs_tab'              => array(
				'post_title'               => __( 'View Logs Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_misc_tab'              => array(
				'post_title'               => __( 'View Misc Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_phpmyadmin_tab'        => array(
				'post_title'               => __( 'View Database Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_php_options_tab'       => array(
				'post_title'               => __( 'View PHP Options Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_redirect_rules_tab'    => array(
				'post_title'               => __( 'View Redirect Rules Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_sftp_tab'              => array(
				'post_title'               => __( 'View sFTP Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_site_sync_tab'         => array(
				'post_title'               => __( 'View Site Sync Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_site_system_users_tab' => array(
				'post_title'               => __( 'View Site System Users Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_ssl_tab'               => array(
				'post_title'               => __( 'View SSL Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_staging_tab'           => array(
				'post_title'               => __( 'View Staging Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_statistics_tab'        => array(
				'post_title'               => __( 'View Statistics Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_updates_tab'           => array(
				'post_title'               => __( 'View Theme & Plugin Updates Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_tools_tab'             => array(
				'post_title'               => __( 'View Tools Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_tweaks_tab'            => array(
				'post_title'               => __( 'View Tweaks Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_wpsiteusers_tab'       => array(
				'post_title'               => __( 'View WP Site Users Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_wpconfig_tab' => array(
				'post_title'               => __( 'View WPCONFIG Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
			'view_wpapp_site_multisite_tab'         => array(
				'post_title'               => __( 'View Multisite Tab On WP Site Screen', 'wpcd' ),
				'wpcd_object_type'         => 2,
				'wpcd_permission_category' => 'wpapp',
				'wpcd_permission_group'    => 4,
			),
		);

		foreach ( $permission_posts as $permission_name => $permission_post ) {
			$posts = get_posts(
				array(
					'post_type'      => 'wpcd_permission_type',
					'post_status'    => 'private',
					'meta_key'       => 'wpcd_permission_name',
					'meta_value'     => $permission_name,
					'fields'         => 'ids',
					'posts_per_page' => 1,
				)
			);

			if ( count( $posts ) == 0 ) {
				// Create post array.
				$post = array(
					'post_title'  => wp_strip_all_tags( $permission_post['post_title'] ),
					'post_type'   => 'wpcd_permission_type',
					'post_status' => 'private',
					'post_author' => get_current_user_id(),
					'meta_input'  => array(
						'wpcd_object_type'         => intval( $permission_post['wpcd_object_type'] ),
						'wpcd_permission_category' => sanitize_text_field( $permission_post['wpcd_permission_category'] ),
						'wpcd_permission_name'     => sanitize_text_field( $permission_name ),
						'wpcd_permission_group'    => sanitize_text_field( $permission_post['wpcd_permission_group'] ),
					),
				);

				// Insert the post into the database.
				$post_id = wp_insert_post( $post );
			} else {
				// we have an existing permission so update it with the latest data from the array...
				if ( count( $posts ) == 1 ) {
					$id_to_update = $posts[0];
					update_post_meta( $id_to_update, 'wpcd_object_type', intval( $permission_post['wpcd_object_type'] ) );
					update_post_meta( $id_to_update, 'wpcd_permission_category', sanitize_text_field( $permission_post['wpcd_permission_category'] ) );
					update_post_meta( $id_to_update, 'wpcd_permission_group', sanitize_text_field( $permission_post['wpcd_permission_group'] ) );
				}
			}
		}
	}

	/**
	 * Deletes the custom table for permission assignments
	 * This will happen on Plugin Uninstallation.
	 *
	 * @return void
	 */
	public static function wpcd_delete_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'permission_assignments';
		$sql        = "DROP TABLE IF EXISTS $table_name";
		$wpdb->query( $sql );
		delete_option( 'wpcd_db_version' );
	}

	/**
	 * Adds new values to app list array
	 *
	 * Filter hook: wpcd_app_list
	 *
	 * @param  array $app_list app list.
	 *
	 * @return array
	 */
	public function wpcd_permission_type_app_list( $app_list ) {

		$custom_app_list = array( 'core', 'wpapp', 'vpn' );

		foreach ( $custom_app_list as $value ) {
			$app_list[ $value ] = $value;
		}

		return $app_list;
	}

	/**
	 * To schedule events for newly created site on WP Multisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function wpcd_permission_type_new_site( $new_site, $args ) {
		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {
			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::wpcd_create_table();
			restore_current_blog();
		}
	}

	/**
	 * Checks if current user has the capability to access add new, edit or listing page for permissions.
	 *
	 * Action hook: load-edit.php, load-post-new.php, load-post.php
	 *
	 * @return void
	 */
	public function wpcd_permission_type_load() {
		$screen = get_current_screen();

		if ( 'wpcd_permission_type' === $screen->post_type ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html( __( 'You don\'t have access to this page.', 'wpcd' ) ) );
			}
		}
	}

	/**
	 * Adds custom back to list button for permissions post type
	 *
	 * @return void
	 */
	public function wpcd_permission_type_backtolist_btn() {
		$screen    = get_current_screen();
		$post_type = 'wpcd_permission_type';

		if ( $screen->id == $post_type ) {
			$query          = sprintf( 'edit.php?post_type=%s', $post_type );
			$backtolist_url = admin_url( $query );
			$backtolist_txt = __( 'BACK TO LIST', 'wpcd' );
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('<a href="<?php echo esc_attr( $backtolist_url ); ?>" class="page-title-action"><?php echo esc_html( $backtolist_txt ); ?></a>').insertBefore('hr.wp-header-end');
				});
			</script>
			<?php
		}
	}

}
