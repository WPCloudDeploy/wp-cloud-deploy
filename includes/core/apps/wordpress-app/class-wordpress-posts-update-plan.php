<?php
/**
 * This class handles declaration of the the post types needed for site update plans.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_POSTS_App_Update_Plan
 */
class WPCD_POSTS_App_Update_Plan extends WPCD_Posts_Base {

	/**
	 * WPCD_POSTS_App_Update_Plan instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_POSTS_App_Update_Plan constructor.
	 */
	public function __construct() {

		$this->register();  // register the custom post type.
		$this->hooks();     // register hooks to make the custom post type do things...
	}

	/**
	 * WPCD_POSTS_App_Update_Plan hooks.
	 */
	private function hooks() {

		// Register custom fields for our post types.
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_post_type_fields' ), 20, 1 );

		// Change ADD TITLE placeholder text.
		add_filter( 'enter_title_here', array( $this, 'change_enter_title_text' ) );

		// Filter hook to add new columns.
		add_filter( 'manage_wpcd_app_update_plan_posts_columns', array( $this, 'manage_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_app_update_plan_posts_custom_column', array( $this, 'manage_table_content' ), 10, 2 );

		// Add a message after the title field when add/editing an item.
		add_action( 'edit_form_after_title', array( $this, 'wpcd_after_title_notice' ), 10, 1 );

	}


	/**
	 * Registers the custom post type and taxonomies (if any )
	 */
	public function register() {

		$menu_name = __( 'Site Update Plans', 'wpcd' );
		$menu_icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg fill="black" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="20px" height="20px"><path fill="black" d="M 20 9 C 18.355469 9 17 10.355469 17 12 L 17 68 C 17 69.644531 18.355469 71 20 71 L 60 71 C 61.644531 71 63 69.644531 63 68 L 63 12 C 63 10.355469 61.644531 9 60 9 Z M 20 11 L 60 11 C 60.566406 11 61 11.433594 61 12 L 61 68 C 61 68.566406 60.566406 69 60 69 L 20 69 C 19.433594 69 19 68.566406 19 68 L 19 12 C 19 11.433594 19.433594 11 20 11 Z M 24 16 L 24 42 L 56 42 L 56 16 Z M 26 18 L 54 18 L 54 24 L 26 24 Z M 50 20 C 49.449219 20 49 20.449219 49 21 C 49 21.550781 49.449219 22 50 22 C 50.550781 22 51 21.550781 51 21 C 51 20.449219 50.550781 20 50 20 Z M 26 26 L 54 26 L 54 32 L 26 32 Z M 50 28 C 49.449219 28 49 28.449219 49 29 C 49 29.550781 49.449219 30 50 30 C 50.550781 30 51 29.550781 51 29 C 51 28.449219 50.550781 28 50 28 Z M 26 34 L 54 34 L 54 40 L 26 40 Z M 50 36 C 49.449219 36 49 36.449219 49 37 C 49 37.550781 49.449219 38 50 38 C 50.550781 38 51 37.550781 51 37 C 51 36.449219 50.550781 36 50 36 Z M 25 47 C 24.449219 47 24 47.449219 24 48 C 24 48.550781 24.449219 49 25 49 C 25.550781 49 26 48.550781 26 48 C 26 47.449219 25.550781 47 25 47 Z M 25 51 C 24.449219 51 24 51.449219 24 52 C 24 52.550781 24.449219 53 25 53 C 25.550781 53 26 52.550781 26 52 C 26 51.449219 25.550781 51 25 51 Z M 40 52 C 37.800781 52 36 53.800781 36 56 C 36 58.199219 37.800781 60 40 60 C 42.199219 60 44 58.199219 44 56 C 44 53.800781 42.199219 52 40 52 Z M 40 54 C 41.117188 54 42 54.882813 42 56 C 42 57.117188 41.117188 58 40 58 C 38.882813 58 38 57.117188 38 56 C 38 54.882813 38.882813 54 40 54 Z M 25 55 C 24.449219 55 24 55.449219 24 56 C 24 56.550781 24.449219 57 25 57 C 25.550781 57 26 56.550781 26 56 C 26 55.449219 25.550781 55 25 55 Z M 25 59 C 24.449219 59 24 59.449219 24 60 C 24 60.550781 24.449219 61 25 61 C 25.550781 61 26 60.550781 26 60 C 26 59.449219 25.550781 59 25 59 Z M 25 63 C 24.449219 63 24 63.449219 24 64 C 24 64.550781 24.449219 65 25 65 C 25.550781 65 26 64.550781 26 64 C 26 63.449219 25.550781 63 25 63 Z"/></svg>' );

		register_post_type(
			'wpcd_app_update_plan',
			array(
				'labels'              => array(
					'name'                  => _x( 'Site Update Plan', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Site Update Plan', 'Post type singular name', 'wpcd' ),
					'menu_name'             => $menu_name,
					'name_admin_bar'        => _x( 'Site Update Plan', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => _x( 'Add New Site Update Plan', 'Add New Button', 'wpcd' ),
					'add_new_item'          => _x( 'Add New Site Update Plan', 'Add New Item', 'wpcd' ),
					'edit_item'             => __( 'Edit Site Update Plan', 'wpcd' ),
					'view_item'             => _x( 'Site Update Plan', 'Post type general name', 'wpcd' ),
					'all_items'             => _x( 'Site Update Plan', 'Label for use with all items', 'wpcd' ),
					'search_items'          => __( 'Search Site Update Plans', 'wpcd' ),
					'not_found'             => __( 'No Site Update Plans were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Site Update Plans were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Site Update Plans list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Site Update Plan list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Site Update Plan list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
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

		/* What to copy? */
		$fields_things_to_copy = array(
			array(
				'name'    => __( 'What Files Will Be Copied?', 'wpcd' ),
				'type'    => 'custom_html',
				'std'     => __( 'All plugins, themes, uploads & core wp files will be copied.  wp-config.php will not be copied.', 'wpcd' ),
				'columns' => 6,
			),
			/*
			array(
				'name'       => __( 'Copy All Plugins?', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_copy_all_plugins',
				'type'       => 'checkbox',
				'std'        => true,
				'save_field' => true,
				'desc'       => __( 'Copy all plugins from template site to target sites?', 'wpcd' ),
				'columns'    => 6,
			),
			array(
				'name'       => __( 'Copy All Themes?', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_copy_all_themes',
				'type'       => 'checkbox',
				'std'        => true,
				'save_field' => true,
				'desc'       => __( 'Copy all themes from template site to target sites?', 'wpcd' ),
				'columns'    => 6,
			),
			*/
		);

		/* Select servers and sites */
		$servers_and_sites = array(
			array(
				'name'        => __( 'Servers to Update', 'wpcd' ),
				'id'          => 'wpcd_app_update_plan_servers',
				'type'        => 'post',
				'post_type'   => 'wpcd_app_server',
				'query_args'  => array(
					'post_status'    => 'private',
					'posts_per_page' => - 1,
				),
				'field_type'  => 'select_advanced',
				'multiple'    => true,
				'save_field'  => true,
				'desc'        => __( 'Apply this update plan to all sites on these servers.', 'wpcd' ),
				'placeholder' => __( 'Select one or more Cloud Servers.', 'wpcd' ),
				'columns'     => 4,
			),
			array(
				'name'        => __( 'Server Groups to Update', 'wpcd' ),
				'id'          => 'wpcd_app_update_plan_servers_by_tag',
				'type'        => 'taxonomy_advanced',
				'taxonomy'    => 'wpcd_app_server_group',
				'field_type'  => 'select_advanced',
				'multiple'    => true,
				'save_field'  => true,
				'desc'        => __( 'Apply this update plan to all sites on servers with these groups.', 'wpcd' ),
				'placeholder' => __( 'Select one or more Server Groups.', 'wpcd' ),
				'columns'     => 4,
			),
			array(
				'name'        => __( 'Site Groups to Update', 'wpcd' ),
				'id'          => 'wpcd_app_update_plan_sites_by_tag',
				'type'        => 'taxonomy_advanced',
				'taxonomy'    => 'wpcd_app_group',
				'field_type'  => 'select_advanced',
				'multiple'    => true,
				'save_field'  => true,
				'desc'        => __( 'Apply this update plan to all sites with these tags.', 'wpcd' ),
				'placeholder' => __( 'Select one or more Site/App Groups.', 'wpcd' ),
				'columns'     => 4,
			),
			array(
				'name' => __( 'Note', 'wpcd' ),
				'id'   => 'wpcd_app_update_plan_server_selection_note',
				'type' => 'custom_html',
				'std'  => __( 'Sites will be combined from all three items above - servers + server groups + site groups into a single master list of sites to update. You do not have to specify all three fields - empty fields will be ignored.', 'wpcd' ),
			),

		);

		$fields_taxonomy = array(
			array(
				'name'       => __( 'Apply These Groups', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_apply_categories',
				'type'       => 'taxonomy_advanced',
				'taxonomy'   => 'wpcd_app_group',
				'field_type' => 'select_advanced',
				'multiple'   => true,
				'save_field' => true,
				'desc'       => __( 'These groups/categories will the applied to affected sites.', 'wpcd' ),
				'columns'    => 6,
			),
			array(
				'name'       => __( 'Remove These Groups', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_remove_categories',
				'type'       => 'taxonomy_advanced',
				'taxonomy'   => 'wpcd_app_group',
				'field_type' => 'select_advanced',
				'multiple'   => true,
				'save_field' => true,
				'desc'       => __( 'These groups/categories will the removed from the site', 'wpcd' ),
				'tooltip'    => __( 'This is for future use - not currently working.', 'wpcd' ),
				'columns'    => 6,
			),
		);

		$fields_plugin_actions = array(
			array(
				'name'       => __( 'Activate These Plugins', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_plugins_to_activate',
				'type'       => 'textarea',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'Enter plugins - one line per plugin in the format plugin-folder/main-plugin-file-name.php.', 'wpcd' ),
				'columns'    => 6,
			),
			array(
				'name'       => __( 'De-activate These Plugins', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_plugins_to_deactivate',
				'type'       => 'textarea',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'Enter plugins - one line per plugin in the format plugin-folder/main-plugin-file-name.php.', 'wpcd' ),
				'columns'    => 6,
			),
		);

		/* Fields to push to wp-config.php */
		$fields_wp_config_custom_data = array(
			array(
				'name'       => __( 'WPConfig Custom Data', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_wp_config_custom_data',
				'type'       => 'key_value',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'These key-value pairs will be added to wp-config.php.', 'wpcd' ),
				'tooltip'    => __( 'Use these to add things you might need in updated custom plugins used on your site.', 'wpcd' ),
			),
		);

		/* Fields to push to site meta */
		$fields_site_metas = array(
			array(
				'name'       => __( 'Add To Site Meta', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_site_package_site_meta',
				'type'       => 'key_value',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'These key-value pairs will be added to the metas on the custom post type for the site (i.e.: the site record).', 'wpcd' ),
				'tooltip'    => __( 'Use these to add things you might need on the site custom post type record.  In many cases the same things you are adding to wp-config are likely the same items you want to push to the site metas.', 'wpcd' ),
			),
		);

		/* Fields to push to options on tenant site */
		$fields_tenant_wp_options = array(
			array(
				'name'       => __( 'Add Option', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_site_package_tenant_wp_option',
				'type'       => 'key_value',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'These key-value pairs will be added to the WordPress options on the new site.', 'wpcd' ),
				'tooltip'    => __( 'Use these to add or update options on the new site - many plugins and themes use options to store their data so you might be able to overwrite them with your custom values if you know their option formats.', 'wpcd' ),
			),
		);

		/* Fields for custom bash scripts. */
		$fields_bash_scripts = array(
			array(
				'name'       => __( 'Bash Script Before Copy', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_bash_scripts_before',
				'type'       => 'text',
				'save_field' => true,
				'desc'       => __( 'Run this script before plugins and themes are copied to the destination site.', 'wpcd' ),
				'tooltip'    => __( 'We will inject certain vars into the environment before the script is executed.  See docs for the full list. This script will NOT run for staging sites, clones or sites that are copied to a new server', 'wpcd' ),
				'columns'    => 6,
			),
			array(
				'name'       => __( 'Bash Script After Copy', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_bash_scripts_after',
				'type'       => 'text',
				'save_field' => true,
				'desc'       => __( 'Run this script after plugins and themes have been copied to the destination site.', 'wpcd' ),
				'tooltip'    => __( 'We will inject certain vars into the environment before the script is executed.  See docs for the full list. This script will NOT run for staging sites, clones or sites that are copied to a new server', 'wpcd' ),
				'columns'    => 6,
			),
		);

		/* Note field. */
		$fields_note = array(
			array(
				'name'       => __( 'Notes', 'wpcd' ),
				'id'         => 'wpcd_app_update_plan_notes',
				'type'       => 'textarea',
				'rows'       => 10,
				'save_field' => true,
				'desc'       => __( 'Your notes about this update plan.', 'wpcd' ),
				'columns'    => 6,
			),
		);

		/* Add the fields defined above to various metaboxes. */
		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_mb_what_to_copy',
			'title'      => __( 'Files', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_plan' ),
			'priority'   => 'default',
			'fields'     => $fields_things_to_copy,
		);

		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_mb_sites',
			'title'      => __( 'To Which Sites Will This Plan Be Applied?', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_plan' ),
			'priority'   => 'default',
			'fields'     => $servers_and_sites,
		);

		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_mb_manage_groups',
			'title'      => __( 'Manage Groups', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_plan' ),
			'priority'   => 'default',
			'fields'     => $fields_taxonomy,
		);

		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_mb_plugins',
			'title'      => __( 'Manage Plugins', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_plan' ),
			'priority'   => 'default',
			'fields'     => $fields_plugin_actions,
		);

		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_custom_wpconfig_entries',
			'title'      => __( 'WP-CONFIG Entries', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_plan' ),
			'priority'   => 'default',
			'fields'     => $fields_wp_config_custom_data,
		);

		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_site_metas',
			'title'      => __( 'Site Metas', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_plan' ),
			'priority'   => 'default',
			'fields'     => $fields_site_metas,
		);

		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_tenant_wp_options',
			'title'      => __( 'WP Options', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_plan' ),
			'priority'   => 'default',
			'fields'     => $fields_tenant_wp_options,
		);

		if ( $this->can_user_execute_bash_scripts() ) {
			// Site is allowed to execute bash scripts for site packages.
			$metaboxes[] = array(
				'id'         => 'wpcd_app_update_plan_mb_bash_scripts',
				'title'      => __( 'Bash Scripts', 'wpcd' ),
				'post_types' => array( 'wpcd_app_update_plan' ),
				'priority'   => 'default',
				'fields'     => $fields_bash_scripts,
			);
		}

		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_mb_site_package_notes',
			'title'      => __( 'Notes', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_plan' ),
			'priority'   => 'default',
			'fields'     => $fields_note,
		);

		return $metaboxes;
	}

	/**
	 * Add table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function manage_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_update_plan_server_count'] = __( 'Planned Servers', 'wpcd' );
		$defaults['wpcd_update_plan_site_count']   = __( 'Planned Sites', 'wpcd' );
		$defaults['date']                          = __( 'Date', 'wpcd' );

		return $defaults;

	}

	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * print column value.
	 */
	public function manage_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_update_plan_server_count':
				$servers_and_sites = $this->get_server_and_sites( $post_id );
				$value             = count( $servers_and_sites['servers'] );
				break;

			case 'wpcd_update_plan_site_count':
				$servers_and_sites = $this->get_server_and_sites( $post_id );
				$value             = count( $servers_and_sites['sites'] );
				break;

			default:
				break;
		}

		$allowed_html = array(
			'a'      => array(
				'href'  => array(),
				'title' => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'span'   => array( 'class' => true ),
			'class'  => array(),
		);

		echo wp_kses( $value, $allowed_html );

	}

	/**
	 * Return an array of postids=>title of all published app update plan records.
	 */
	public function get_app_update_plans() {

		// Get list of product packages into an array to prepare it for display.
		$wpcd_plans = get_posts(
			array(
				'post_type'   => 'wpcd_app_update_plan',
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);
		$wpcd_plans = array( 0 => __( 'None', 'wpcd' ) );
		foreach ( $wpcd_plans as $key => $value ) {
			$wpcd_plan_list[ $value->ID ] = $value->post_title;
		}

		return $wpcd_plan_list;

	}

	/**
	 * Return an array with servers and sites.
	 *
	 * Array format will be as follows:
	 * array(
	 *      servers => array ( 'server_title' => server_id, 'server_title' => server_id ),
	 *      sites   => array ( 'domain' => site_id, 'domain' => site_id ),
	 *      mapped_server_to_sites = array( server_id[site_id] => domain ),
	 * )
	 *
	 * Dev Note: Yes, this function is very inefficient.
	 * But it's clearer!
	 * Even if you have 1000 servers and 5000 sites, the number of times
	 * in which this gets executed is so small that the tradeoff of
	 * code clarity to efficient code is worth it.  If you disagree
	 * feel free to rewrite it.
	 *
	 * @param int $plan_id The plan id we're working with.
	 */
	public function get_server_and_sites( $plan_id ) {

		if ( empty( $plan_id ) ) {
			return array();
		}

		// Get server and site requirements from plan record.
		// Note that the last parameter for the server_Ids get_post_meta is FALSE so that we can return an array of values.
		// Metabox stores multiple values in a POST multi-select field as multiple rows in the database with the same post_meta key!
		$server_ids    = get_post_meta( $plan_id, 'wpcd_app_update_plan_servers', false );
		$server_groups = get_post_meta( $plan_id, 'wpcd_app_update_plan_servers_by_tag', true ); // taxomomy_advanced fields stores multiple values in a single comma delimited row so this will return a comma delimited string.
		$site_groups   = get_post_meta( $plan_id, 'wpcd_app_update_plan_sites_by_tag', true ); // taxomomy_advanced fields stores multiple values in a single comma delimited row so this will return a comma delimited string.

		// Explode the taxonomy fields since they're comma separated strings.
		$server_groups = ! empty( $server_groups ) ? explode( ',', $server_groups ) : $server_groups;
		$site_groups   = ! empty( $site_groups ) ? explode( ',', $site_groups ) : $site_groups;

		// These vars will hold the key-value pairs for servers and sites that we'll return.
		$servers = array();
		$sites   = array();

		// Loop through the $server_ids and populate the $servers array.
		foreach ( $server_ids as $key => $server_id ) {
			$server_title = wpcd_get_the_title( $server_id );
			if ( ! empty( $server_title ) ) {
				$servers[ $server_title ] = $server_id;

				// Now get the sites on each server.
				$these_sites = WPCD_SERVER()->get_apps_by_server_id( $server_id );

				// Populate the sites array since for this item we're applying changes to all sites on these servers.
				foreach ( $these_sites as $this_site ) {
					$domain      = WPCD_WORDPRESS_APP()->get_domain_name( $this_site->ID );
					$is_template = WPCD_WORDPRESS_APP()->wpcd_is_template_site( $this_site->ID );
					if ( ! empty( $domain ) && false === $is_template ) {
						$sites[ $domain ] = $this_site->ID;
					}
				}
			}
		}

		// Get the list of servers from server groups.
		if ( ! empty( $server_groups ) ) {
			foreach ( $server_groups as $key => $server_group ) {

				// Bail if id is empty.
				if ( empty( $server_group ) ) {
					continue;
				}

				// Construct args array for get_posts function.
				$args = array(
					'posts_per_page' => -1,
					'post_type'      => 'wpcd_app_server',
					'post_status'    => 'private',
					'tax_query'      => array(
						array(
							'taxonomy' => 'wpcd_app_server_group',
							'field'    => 'term_id',
							'terms'    => $server_group,
						),
					),
				);

				// Get the servers.
				$these_servers = get_posts( $args );

				// Add each server to the final array of servers.
				foreach ( $these_servers as $key => $this_server ) {

					// Add server to the final array of servers.
					if ( ! empty( $this_server->post_title ) ) {
						$servers[ $this_server->post_title ] = $this_server->ID;
					}

					// Now get the sites on this server.
					$these_sites = WPCD_SERVER()->get_apps_by_server_id( $this_server->ID );

					// Populate the sites array since for this item we're applying changes to all sites on these servers.
					foreach ( $these_sites as $this_site ) {
						$domain      = WPCD_WORDPRESS_APP()->get_domain_name( $this_site->ID );
						$is_template = WPCD_WORDPRESS_APP()->wpcd_is_template_site( $this_site->ID );
						if ( ! empty( $domain ) && false === $is_template ) {
							$sites[ $domain ] = $this_site->ID;
						}
					}
				}
			}
		}

		// Get the list of sites from site groups.
		if ( ! empty( $site_groups ) ) {
			foreach ( $site_groups as $key => $site_group ) {

				// Bail if id is empty.
				if ( empty( $site_group ) ) {
					continue;
				}

				// Construct args array for get_posts function.
				$args = array(
					'posts_per_page' => -1,
					'post_type'      => 'wpcd_app',
					'post_status'    => 'private',
					'tax_query'      => array(
						array(
							'taxonomy' => 'wpcd_app_group',
							'field'    => 'term_id',
							'terms'    => $site_group,
						),
					),
				);

				// Get the sites.
				$these_sites = get_posts( $args );

				// Add each site to the final array of sites.
				foreach ( $these_sites as $key => $this_site ) {
					// Add it to the $sites array.
					$domain      = WPCD_WORDPRESS_APP()->get_domain_name( $this_site->ID );
					$is_template = WPCD_WORDPRESS_APP()->wpcd_is_template_site( $this_site->ID );
					if ( ! empty( $domain ) && false === $is_template ) {
						$sites[ $domain ] = $this_site->ID;

						// Get the parent id - which is the server, from the site record.  We want to make sure the server is added to the $servers array.
						$parent_id = WPCD_WORDPRESS_APP()->get_server_id_by_app_id( $this_site->ID );
						if ( ! empty( $parent_id ) ) {
							// Get the server title.
							$this_server_title = WPCD_WORDPRESS_APP()->get_server_name( $parent_id );
							if ( ! empty( $this_server_title ) ) {
								// Add to the site server to the final array of servers.
								$servers[ $this_server_title ] = $parent_id;
							}
						}
					}
				}
			}
		}

		// At this point, we have a list of servers and a list of sites which calling functions will need.
		// But now we need to create a MAP that indicates which servers belong to which sites.
		// So we loop through the sites once again.
		$mapped_servers_to_sites = array();
		foreach ( $sites as $domain => $site_id ) {
			$server_id = WPCD_WORDPRESS_APP()->get_server_id_by_app_id( $site_id );
			if ( ! empty( $server_id ) ) {
				$mapped_servers_to_sites[ $server_id ][ $site_id ] = $domain;
			}
		}

		// Return consolidated array of servers and sites.
		$return = array(
			'servers'                => $servers,
			'sites'                  => $sites,
			'mapped_server_to_sites' => $mapped_servers_to_sites,
		);

		// Return/exit.
		return $return;

	}

	/**
	 * Return whether or not bash scripts can be run in site update plans.
	 *
	 * You might not want to run them if WPCD is installed in a shared sites
	 * environment (saas).
	 *
	 * @param int $user_id User id running site packages - not used yet.
	 */
	public function can_user_execute_bash_scripts( $user_id = 0 ) {

		if ( ! defined( 'WPCD_SITE_UPDATE_PLANS_NO_BASH' ) || ( defined( 'WPCD_SITE_UPDATE_PLANS_NO_BASH' ) && false === (bool) WPCD_SITE_UPDATE_PLANS_NO_BASH ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Change ADD TITLE placeholder text on new CPT items.
	 *
	 * @param string $title Current title.
	 *
	 * @return string New Title.
	 */
	public function change_enter_title_text( $title ) {

		$screen = get_current_screen();

		if ( 'wpcd_app_update_plan' == $screen->post_type ) {
			$title = 'Enter a name for this new update plan';
		}

		return $title;

	}

	/**
	 * Show a message when the user is added or editing a plan.
	 *
	 * Action Hook: edit_form_after_title.
	 *
	 * @param object $post The post object being added or edited.
	 */
	public function wpcd_after_title_notice( $post ) {

		if ( ! empty( $post ) && is_object( $post ) && 'wpcd_app_update_plan' === $post->post_type ) {
			echo '<hr/>';
			echo '<b>';
			echo wp_kses_post( __( 'Warning - Plans are for standard sites only!', 'wpcd' ) );
			echo '</b>';
			echo '<br/>';
			echo wp_kses_post( __( 'Update Plans are used to perform bulk updates of core, theme and plugin files to standard sites.', 'wpcd' ) );
			echo '<br/>';
			echo wp_kses_post( __( 'For updates to Multi-tenant sites you should use the versioning and update options on the multi-tenant tab of the associated template site.', 'wpcd' ) );
			echo '<hr/>';
		}

	}

}
