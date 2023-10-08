<?php
/**
 * This class handles declaration of the the post types needed for site packages.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_POSTS_Site_Package
 */
class WPCD_POSTS_Site_Package extends WPCD_Posts_Base {

	/**
	 * WPCD_POSTS_Site_Package instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_POSTS_Site_Package constructor.
	 */
	public function __construct() {

		$this->register();  // register the custom post type.
		$this->hooks();     // register hooks to make the custom post type do things...
	}

	/**
	 * WPCD_POSTS_Site_Package hooks.
	 */
	private function hooks() {

		// Register custom fields for our post types.
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_post_type_fields' ), 20, 1 );

	}


	/**
	 * Registers the custom post type and taxonomies (if any )
	 */
	public function register() {

		$menu_name = __( 'Site Packages', 'wpcd' );
		$menu_icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg fill="black" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="20px" height="20px"><path fill="black" d="M 20 9 C 18.355469 9 17 10.355469 17 12 L 17 68 C 17 69.644531 18.355469 71 20 71 L 60 71 C 61.644531 71 63 69.644531 63 68 L 63 12 C 63 10.355469 61.644531 9 60 9 Z M 20 11 L 60 11 C 60.566406 11 61 11.433594 61 12 L 61 68 C 61 68.566406 60.566406 69 60 69 L 20 69 C 19.433594 69 19 68.566406 19 68 L 19 12 C 19 11.433594 19.433594 11 20 11 Z M 24 16 L 24 42 L 56 42 L 56 16 Z M 26 18 L 54 18 L 54 24 L 26 24 Z M 50 20 C 49.449219 20 49 20.449219 49 21 C 49 21.550781 49.449219 22 50 22 C 50.550781 22 51 21.550781 51 21 C 51 20.449219 50.550781 20 50 20 Z M 26 26 L 54 26 L 54 32 L 26 32 Z M 50 28 C 49.449219 28 49 28.449219 49 29 C 49 29.550781 49.449219 30 50 30 C 50.550781 30 51 29.550781 51 29 C 51 28.449219 50.550781 28 50 28 Z M 26 34 L 54 34 L 54 40 L 26 40 Z M 50 36 C 49.449219 36 49 36.449219 49 37 C 49 37.550781 49.449219 38 50 38 C 50.550781 38 51 37.550781 51 37 C 51 36.449219 50.550781 36 50 36 Z M 25 47 C 24.449219 47 24 47.449219 24 48 C 24 48.550781 24.449219 49 25 49 C 25.550781 49 26 48.550781 26 48 C 26 47.449219 25.550781 47 25 47 Z M 25 51 C 24.449219 51 24 51.449219 24 52 C 24 52.550781 24.449219 53 25 53 C 25.550781 53 26 52.550781 26 52 C 26 51.449219 25.550781 51 25 51 Z M 40 52 C 37.800781 52 36 53.800781 36 56 C 36 58.199219 37.800781 60 40 60 C 42.199219 60 44 58.199219 44 56 C 44 53.800781 42.199219 52 40 52 Z M 40 54 C 41.117188 54 42 54.882813 42 56 C 42 57.117188 41.117188 58 40 58 C 38.882813 58 38 57.117188 38 56 C 38 54.882813 38.882813 54 40 54 Z M 25 55 C 24.449219 55 24 55.449219 24 56 C 24 56.550781 24.449219 57 25 57 C 25.550781 57 26 56.550781 26 56 C 26 55.449219 25.550781 55 25 55 Z M 25 59 C 24.449219 59 24 59.449219 24 60 C 24 60.550781 24.449219 61 25 61 C 25.550781 61 26 60.550781 26 60 C 26 59.449219 25.550781 59 25 59 Z M 25 63 C 24.449219 63 24 63.449219 24 64 C 24 64.550781 24.449219 65 25 65 C 25.550781 65 26 64.550781 26 64 C 26 63.449219 25.550781 63 25 63 Z"/></svg>' );

		register_post_type(
			'wpcd_site_package',
			array(
				'labels'              => array(
					'name'                  => _x( 'Site Package', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Site Package', 'Post type singular name', 'wpcd' ),
					'menu_name'             => $menu_name,
					'name_admin_bar'        => _x( 'Site Package', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => _x( 'Add New Site Package', 'Add New Button', 'wpcd' ),
					'edit_item'             => __( 'Edit Site Package', 'wpcd' ),
					'view_item'             => _x( 'Site Package', 'Post type general name', 'wpcd' ),
					'all_items'             => _x( 'Site Packages', 'Label for use with all items', 'wpcd' ),
					'search_items'          => __( 'Search Site Packages', 'wpcd' ),
					'not_found'             => __( 'No Site Packages were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Site Packages were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Site Packages list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Site Package list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Site Package list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wpcd_app_server',
				'menu_position'       => 10,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'menu_icon'           => $menu_icon,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts'           => 'wpcd_manage_all',
					'edit_post'              => 'wpcd_manage_all',
					'edit_posts'             => 'wpcd_manage_all',
					'edit_others_posts'      => 'wpcd_manage_all',
					'edit_published_posts'   => 'wpcd_manage_all',
					'delete_post'            => 'wpcd_manage_all',
					'publish_posts'          => 'wpcd_manage_all',
					'delete_posts'           => 'wpcd_manage_all',
					'delete_others_posts'    => 'wpcd_manage_all',
					'delete_published_posts' => 'wpcd_manage_all',
					'delete_private_posts'   => 'wpcd_manage_all',
					'edit_private_posts'     => 'wpcd_manage_all',
					'read_private_posts'     => 'wpcd_manage_all',
				),
				'taxonomies'          => array(),
			)
		);

	}

	/**
	 * Add fields to post types.
	 *
	 * Action Hook: rwmb_meta_boxes
	 *
	 * @param array $metaboxes Array of existing metaboxes.
	 *
	 * @return array new array of metaboxes.
	 */
	public function register_post_type_fields( $metaboxes ) {

		$prefix = 'wpcd_';

		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			/* Fields for plugins to activate from template. Only applies if WooCommerce is active.*/
			$fields_activate = array(
				array(
					'name'       => __( 'Activate These Plugins', 'wpcd' ),
					'id'         => $prefix . 'plugins_to_activate',
					'type'       => 'textarea',
					'rows'       => 10,
					'save_field' => true,
					'desc'       => __( 'Enter plugins - one line per plugin in the format plugin-folder/main-plugin-file-name.php.', 'wpcd' ),
					'tooltip'    => __( 'These plugins will be activated when a new site is created or when SITE products associated with this package is purchased.', 'wpcd' ),
					'columns'    => 6,
				),
			);
		}

		/* Fields for theme to activate. */
		$fields_theme_activate = array(
			array(
				'name'       => __( 'Activate This Theme', 'wpcd' ),
				'id'         => $prefix . 'theme_to_activate',
				'type'       => 'text',
				'save_field' => true,
				'desc'       => __( 'Activate this theme - it must already be present on the site or scheduled for install in the INSTALL THEMES metabox below.', 'wpcd' ),
				'columns'    => 6,
			),
		);

		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			/* Fields for plugins to deactivate.  Only applies if WooCommerce is active. */
			$fields_deactivate = array(
				array(
					'name'       => __( 'Deactivate These Plugins', 'wpcd' ),
					'id'         => $prefix . 'plugins_to_deactivate',
					'type'       => 'textarea',
					'rows'       => 10,
					'save_field' => true,
					'desc'       => __( 'Enter plugins - one line per plugin in the format plugin-folder/main-plugin-file-name.php', 'wpcd' ),
					'tooltip'    => __( 'These plugins will be deactivated when a new site is created or when SITE products associated with this package is purchased.', 'wpcd' ),
				),
			);
		};

		/* Fields for plugins to install and activate. */
		$fields_repo_and_url = array(
			array(
				'name'       => __( 'Install These Plugins from WordPress.org', 'wpcd' ),
				'id'         => $prefix . 'plugins_to_install_from_repo',
				'type'       => 'textarea',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'Enter the plugin slugs as defined by the wordpress.org repo - enter one line per plugin.', 'wpcd' ),
				'tooltip'    => __( 'These plugins will be installed AND activated when a new site is created or when SITE products associated with this package is purchased.', 'wpcd' ),
				'columns'    => 6,
			),
			array(
				'name'       => __( 'Install These Plugins from a URL', 'wpcd' ),
				'id'         => $prefix . 'plugins_to_install_from_url',
				'type'       => 'textarea',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'Enter the PUBLIC plugin urls to .zip files - enter one line per plugin.', 'wpcd' ),
				'tooltip'    => __( 'These plugins will be installed AND activated when a new site is created or when SITE products associated with this package is purchased.', 'wpcd' ),
				'columns'    => 6,
			),
		);

		/* Fields for themes to install from repo and external. */
		$fields_repo_and_url_themes = array(
			array(
				'name'       => __( 'Install These Themes from WordPress.org', 'wpcd' ),
				'id'         => $prefix . 'themes_to_install_from_repo',
				'type'       => 'textarea',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'Enter the theme slugs as defined by the wordpress.org repo - enter one line per theme.', 'wpcd' ),
				'tooltip'    => __( 'These themes will be installed when a new site is created or when SITE products associated with this package is purchased.', 'wpcd' ),
				'columns'    => 6,
			),
			array(
				'name'       => __( 'Install These Themes from a URL', 'wpcd' ),
				'id'         => $prefix . 'themes_to_install_from_url',
				'type'       => 'textarea',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'Enter the PUBLIC theme urls to .zip files - enter one line per theme.', 'wpcd' ),
				'tooltip'    => __( 'These themes will be installed when a new site is created or when SITE products associated with this package is purchased.', 'wpcd' ),
				'columns'    => 6,
			),
		);

		/* Fields to push to wp-config.php */
		$fields_wp_config_custom_data = array(
			array(
				'name'       => __( 'WPConfig Custom Data', 'wpcd' ),
				'id'         => $prefix . 'wp_config_custom_data',
				'type'       => 'key_value',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'These key-value pairs will be added to wp-config.php.', 'wpcd' ),
				'tooltip'    => __( 'Use these to add things you might need in custom plugins used on your site.  For example, if selling sites in WooCommerce these can be different for eac plan/product.  For example, a plan-id.', 'wpcd' ),
			),
		);

		/* Fields for custom bash scripts. */
		$fields_bash_scripts = array(
			array(
				'name'       => __( 'Bash Script for New Sites (Before)', 'wpcd' ),
				'id'         => $prefix . 'bash_scripts_new_sites_before',
				'type'       => 'text',
				'save_field' => true,
				'desc'       => __( 'Run this script when a site is first provisioned - BEFORE configuration updates and plugin/theme activation/deactivation.', 'wpcd' ),
				'tooltip'    => __( 'We will inject certain vars into the environment before the script is executed.  See docs for the full list. This script will NOT run for staging sites, clones or sites that are copied to a new server', 'wpcd' ),
				'columns'    => 6,
			),
			array(
				'name'       => __( 'Bash Script for New Sites (After)', 'wpcd' ),
				'id'         => $prefix . 'bash_scripts_new_sites_after',
				'type'       => 'text',
				'save_field' => true,
				'desc'       => __( 'Run this script when a site is first provisioned - AFTER configuration updates and plugin/theme activation/deactivation.', 'wpcd' ),
				'tooltip'    => __( 'We will inject certain vars into the environment before the script is executed.  See docs for the full list. This script will NOT run for staging sites, clones or sites that are copied to a new server', 'wpcd' ),
				'columns'    => 6,
			),
		);

		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			$fields_bash_scripts = array_merge(
				$fields_bash_scripts,
				array(
					array(
						'name'       => __( 'Bash Script for Subscription Switches (Before)', 'wpcd' ),
						'id'         => $prefix . 'bash_scripts_subscription_switch_before',
						'type'       => 'text',
						'save_field' => true,
						'desc'       => __( 'Run this script when an order is placed to upgrade or downgrade. It will run BEFORE configuration updates and plugin/theme activation/deactivation.', 'wpcd' ),
						'tooltip'    => __( 'We will inject certain vars into the environment before the script is executed.  See docs for the full list.', 'wpcd' ),
						'columns'    => 6,
					),
				),
			);
		}

		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			$fields_bash_scripts = array_merge(
				$fields_bash_scripts,
				array(
					array(
						'name'       => __( 'Bash Script for Subscription Switches (After)', 'wpcd' ),
						'id'         => $prefix . 'bash_scripts_subscription_switch_after',
						'type'       => 'text',
						'save_field' => true,
						'desc'       => __( 'Run this script when an order is placed to upgrade or downgrade. It will run AFTER configuration updates and plugin/theme activation/deactivation.', 'wpcd' ),
						'tooltip'    => __( 'We will inject certain vars into the environment before the script is executed.  See docs for the full list.', 'wpcd' ),
						'columns'    => 6,
					),
				),
			);
		}

		/* Fields for PHP workers. */
		$fields_php_workers = array(
			array(
				'name'       => __( 'PM', 'wpcd' ),
				'id'         => $prefix . 'php_pm',
				'type'       => 'select',
				'options'    => array(
					'dynamic'  => __( 'Dynamic', 'wpcd' ),
					'static'   => __( 'Static', 'wpcd' ),
					'ondemand' => __( 'On Demand', 'wpcd' ),
				),
				'save_field' => true,
				'columns'    => 3,
			),
			array(
				'name'       => __( 'PM - Max Children', 'wpcd' ),
				'id'         => $prefix . 'php_pm_max_children',
				'type'       => 'number',
				'save_field' => true,
				'columns'    => 3,
			),
			array(
				'name'       => __( 'PM - Start Servers', 'wpcd' ),
				'id'         => $prefix . 'php_pm_start_servers',
				'type'       => 'number',
				'save_field' => true,
				'columns'    => 3,
			),
			array(
				'name'       => __( 'PM - Min Spare Servers', 'wpcd' ),
				'id'         => $prefix . 'php_pm_min_spare_servers',
				'type'       => 'number',
				'save_field' => true,
				'columns'    => 3,
			),
			array(
				'name'       => __( 'PM - Max Spare Servers', 'wpcd' ),
				'id'         => $prefix . 'php_pm_max_spare_servers',
				'type'       => 'number',
				'save_field' => true,
				'columns'    => 3,
			),
		);

		/* Fields for misc php settings. */
		$fields_php_misc = array(
			array(
				'name'       => __( 'PHP Version', 'wpcd' ),
				'id'         => $prefix . 'new_php_version',
				'type'       => 'select',
				'options'    => array(
					'0'   => __( 'No Change', 'wpcd' ),
					'8.1' => '8.1',
					'8.0' => '8.0',
					'7.4' => '7.4',
					'8.2' => '8.2',
				),
				'save_field' => true,
				'columns'    => 3,
			),
			array(
				'name'       => __( 'PHP Memory Limit (Megabytes)', 'wpcd' ),
				'id'         => $prefix . 'php_memory_limit',
				'type'       => 'number',
				'save_field' => true,
				'columns'    => 3,
				'tooltip'    => __( 'Set to zero or empty for no change.', 'wpcd' ),
			),
			array(
				'name'       => __( 'PHP Max Execution Time (Seconds)', 'wpcd' ),
				'id'         => $prefix . 'php_max_execution_time',
				'type'       => 'number',
				'save_field' => true,
				'columns'    => 3,
				'tooltip'    => __( 'Set to zero or empty for no change.', 'wpcd' ),
			),
			array(
				'name'       => __( 'PHP Max Input Vars', 'wpcd' ),
				'id'         => $prefix . 'php_max_input_vars',
				'type'       => 'number',
				'save_field' => true,
				'columns'    => 3,
				'tooltip'    => __( 'Set to zero or empty for no change.', 'wpcd' ),
			),			
		);

		/* Add the fields defined above to various metaboxes. */
		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			$metaboxes[] = array(
				'id'         => $prefix . 'site_package_activate_template_plugins',
				'title'      => __( 'Activate Themes & Plugins Already Installed (Usually From Template Site)', 'wpcd' ),
				'post_types' => array( 'wpcd_site_package' ),
				'priority'   => 'default',
				'fields'     => array_merge( $fields_activate, $fields_theme_activate ),
			);
		} else {
			$metaboxes[] = array(
				'id'         => $prefix . 'site_package_activate_theme',
				'title'      => __( 'Activate This Theme if it\'s Installed', 'wpcd' ),
				'post_types' => array( 'wpcd_site_package' ),
				'priority'   => 'default',
				'fields'     => $fields_theme_activate,
			);
		}

		$metaboxes[] = array(
			'id'         => $prefix . 'site_package_install_plugins',
			'title'      => __( 'Install and Activate Plugins', 'wpcd' ),
			'post_types' => array( 'wpcd_site_package' ),
			'priority'   => 'default',
			'fields'     => $fields_repo_and_url,
		);

		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			$metaboxes[] = array(
				'id'         => $prefix . 'site_package_deactivate_plugins',
				'title'      => __( 'Deactivate Plugins', 'wpcd' ),
				'post_types' => array( 'wpcd_site_package' ),
				'priority'   => 'default',
				'fields'     => $fields_deactivate,
			);
		}

		$metaboxes[] = array(
			'id'         => $prefix . 'site_package_install_themes',
			'title'      => __( 'Install Themes', 'wpcd' ),
			'post_types' => array( 'wpcd_site_package' ),
			'priority'   => 'default',
			'fields'     => $fields_repo_and_url_themes,
		);

		$metaboxes[] = array(
			'id'         => $prefix . 'site_package_custom_wpconfig_entries',
			'title'      => __( 'Custom WPCONFIG Entries', 'wpcd' ),
			'post_types' => array( 'wpcd_site_package' ),
			'priority'   => 'default',
			'fields'     => $fields_wp_config_custom_data,
		);

		$metaboxes[] = array(
			'id'         => $prefix . 'site_package_php_workers',
			'title'      => __( 'PHP Workers', 'wpcd' ),
			'post_types' => array( 'wpcd_site_package' ),
			'priority'   => 'default',
			'fields'     => $fields_php_workers,
		);

		$metaboxes[] = array(
			'id'         => $prefix . 'site_package_php_misc',
			'title'      => __( 'Misc PHP Settings (NGINX)', 'wpcd' ),
			'post_types' => array( 'wpcd_site_package' ),
			'priority'   => 'default',
			'fields'     => $fields_php_misc,
		);

		$metaboxes[] = array(
			'id'         => $prefix . 'site_package_bash_scripts',
			'title'      => __( 'Bash Scripts', 'wpcd' ),
			'post_types' => array( 'wpcd_site_package' ),
			'priority'   => 'default',
			'fields'     => $fields_bash_scripts,
		);

		return $metaboxes;
	}

	/**
	 * Return an array of postids=>title of all published site package records.
	 */
	public function get_site_packages() {

		// Get list of product packages into an array to prepare it for display.
		$wpcd_site_packages     = get_posts(
			array(
				'post_type'   => 'wpcd_site_package',
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);
		$wpcd_site_package_list = array( 0 => __( 'None', 'wpcd' ) );
		foreach ( $wpcd_site_packages as $key => $value ) {
			$wpcd_site_package_list[ $value->ID ] = $value->post_title;
		}

		return $wpcd_site_package_list;

	}

}
