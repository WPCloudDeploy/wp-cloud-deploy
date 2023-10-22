<?php
/**
 * This class is used for handling history logs for update plans.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_SITE_UPDATE_PLAN_LOG
 */
class WPCD_SITE_UPDATE_PLAN_LOG extends WPCD_POSTS_LOG {

	/**
	 * WPCD_SITE_UPDATE_PLAN_LOG instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_SITE_UPDATE_PLAN_LOG constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->register();  // register the custom post type.
		$this->hooks();     // register hooks to make the custom post type do things...
	}

	/**
	 * Hooks function.
	 */
	private function hooks() {

		// Register custom fields for our post types - these will be display only custom_html fields since this post type is just logs.
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_post_type_fields' ), 20, 1 );

		// Filter hook to add new columns.
		add_filter( 'manage_wpcd_app_update_log_posts_columns', array( $this, 'wpcd_app_update_log_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_app_update_log_posts_custom_column', array( $this, 'wpcd_app_update_log_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_app_update_log_sortable_columns', array( $this, 'wpcd_app_update_log_table_sorting' ), 10, 1 );

		// Filter hook to remove edit bulk action.
		add_filter( 'bulk_actions-edit-wpcd_app_update_log', array( $this, 'wpcd_log_bulk_actions' ), 10, 1 ); // Function wpcd_log_bulk_actions is in ancestor class.
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {

		$menu_name = __( 'Site Update Plan History', 'wpcd' );
		$menu_icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg fill="black" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="20px" height="20px"><path fill="black" d="M 20 9 C 18.355469 9 17 10.355469 17 12 L 17 68 C 17 69.644531 18.355469 71 20 71 L 60 71 C 61.644531 71 63 69.644531 63 68 L 63 12 C 63 10.355469 61.644531 9 60 9 Z M 20 11 L 60 11 C 60.566406 11 61 11.433594 61 12 L 61 68 C 61 68.566406 60.566406 69 60 69 L 20 69 C 19.433594 69 19 68.566406 19 68 L 19 12 C 19 11.433594 19.433594 11 20 11 Z M 24 16 L 24 42 L 56 42 L 56 16 Z M 26 18 L 54 18 L 54 24 L 26 24 Z M 50 20 C 49.449219 20 49 20.449219 49 21 C 49 21.550781 49.449219 22 50 22 C 50.550781 22 51 21.550781 51 21 C 51 20.449219 50.550781 20 50 20 Z M 26 26 L 54 26 L 54 32 L 26 32 Z M 50 28 C 49.449219 28 49 28.449219 49 29 C 49 29.550781 49.449219 30 50 30 C 50.550781 30 51 29.550781 51 29 C 51 28.449219 50.550781 28 50 28 Z M 26 34 L 54 34 L 54 40 L 26 40 Z M 50 36 C 49.449219 36 49 36.449219 49 37 C 49 37.550781 49.449219 38 50 38 C 50.550781 38 51 37.550781 51 37 C 51 36.449219 50.550781 36 50 36 Z M 25 47 C 24.449219 47 24 47.449219 24 48 C 24 48.550781 24.449219 49 25 49 C 25.550781 49 26 48.550781 26 48 C 26 47.449219 25.550781 47 25 47 Z M 25 51 C 24.449219 51 24 51.449219 24 52 C 24 52.550781 24.449219 53 25 53 C 25.550781 53 26 52.550781 26 52 C 26 51.449219 25.550781 51 25 51 Z M 40 52 C 37.800781 52 36 53.800781 36 56 C 36 58.199219 37.800781 60 40 60 C 42.199219 60 44 58.199219 44 56 C 44 53.800781 42.199219 52 40 52 Z M 40 54 C 41.117188 54 42 54.882813 42 56 C 42 57.117188 41.117188 58 40 58 C 38.882813 58 38 57.117188 38 56 C 38 54.882813 38.882813 54 40 54 Z M 25 55 C 24.449219 55 24 55.449219 24 56 C 24 56.550781 24.449219 57 25 57 C 25.550781 57 26 56.550781 26 56 C 26 55.449219 25.550781 55 25 55 Z M 25 59 C 24.449219 59 24 59.449219 24 60 C 24 60.550781 24.449219 61 25 61 C 25.550781 61 26 60.550781 26 60 C 26 59.449219 25.550781 59 25 59 Z M 25 63 C 24.449219 63 24 63.449219 24 64 C 24 64.550781 24.449219 65 25 65 C 25.550781 65 26 64.550781 26 64 C 26 63.449219 25.550781 63 25 63 Z"/></svg>' );
		register_post_type(
			'wpcd_app_update_log',
			array(
				'labels'              => array(
					'name'                  => _x( 'Site Update Plan History', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Site Update Plan History', 'Post type singular name', 'wpcd' ),
					'menu_name'             => $menu_name,
					'name_admin_bar'        => _x( 'Site Update Plan History', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => _x( 'Add New Site Update Plan History', 'Add New Button', 'wpcd' ),
					'edit_item'             => __( 'Edit Site Update Plan History', 'wpcd' ),
					'view_item'             => _x( 'Site Update Plan History', 'Post type general name', 'wpcd' ),
					'all_items'             => _x( 'Site Update Plan History', 'Label for use with all items', 'wpcd' ),
					'search_items'          => __( 'Search Site Update Plan History', 'wpcd' ),
					'not_found'             => __( 'No Site Update Plan Histories were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Site Update Plan Histories were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Site Update Plan Histories list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Site Update Plan History list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Site Update Plan History list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
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
					'create_posts'           => 'do_not_allow',
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

		$this->set_post_type( 'wpcd_app_update_log' );

		$search_fields = array(
			'wpcd_update_plan_servers',
			'wpcd_update_plan_sites',
			'wpcd_update_plan_mapped_servers_and_sites',
			'wpcd_update_plan_servers_by_id',
			'wpcd_update_plan_sites_by_id',
		);

		$this->set_post_search_fields( $search_fields );

	}

	/**
	 * Add table header sorting columns
	 *
	 * @param array $columns array of default head columns.
	 *
	 * @return $columns modified array with new columns
	 */
	public function wpcd_app_update_log_table_sorting( $columns ) {

		return $columns;
	}

	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * print column value.
	 */
	public function wpcd_app_update_log_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_update_plan_server_count':
				$value = get_post_meta( $post_id, 'wpcd_update_plan_server_count', true );
				break;

			case 'wpcd_update_plan_site_count':
				$value = get_post_meta( $post_id, 'wpcd_update_plan_site_count', true );
				break;

			case 'wpcd_update_plan_servers_template_push_success':
				$server_count = (int) get_post_meta( $post_id, 'wpcd_update_plan_server_count', true );
				$value        = get_post_meta( $post_id, 'wpcd_update_plan_servers_template_push_success', true );
				if ( $server_count === (int) $value ) {
					$value = $value . ' <span class="dashicons dashicons-yes-alt"></span>';
				}
				break;

			case 'wpcd_update_plan_sites_update_success':
				$site_count = (int) get_post_meta( $post_id, 'wpcd_update_plan_site_count', true );
				$value      = get_post_meta( $post_id, 'wpcd_update_plan_sites_update_success', true );
				if ( $site_count === (int) $value ) {
					$value = $value . ' <span class="dashicons dashicons-yes-alt"></span>';
				}
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
	 * Add table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function wpcd_app_update_log_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_update_plan_server_count']                  = __( 'Planned Servers', 'wpcd' );
		$defaults['wpcd_update_plan_site_count']                    = __( 'Planned Sites', 'wpcd' );
		$defaults['wpcd_update_plan_servers_template_push_success'] = __( 'Completed Servers', 'wpcd' );
		$defaults['wpcd_update_plan_sites_update_success']          = __( 'Completed Sites', 'wpcd' );
		$defaults['date'] = __( 'Date', 'wpcd' );

		return $defaults;

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

		// Current post id.
		$post_id = wpcd_get_post_id_from_global();

		if ( empty( $post_id ) ) {
			return $metaboxes;
		}

		// Server list from plan.
		$planned_servers         = wpcd_maybe_unserialize( get_post_meta( $post_id, 'wpcd_update_plan_servers', true ) );
		$planned_servers_display = '';
		if ( is_array( $planned_servers ) ) {
			$planned_servers         = array_keys( $planned_servers );
			$planned_servers_display = implode( '<br/>', $planned_servers );
		}

		// Site list from plan.
		$planned_sites_display = '';
		$planned_sites         = wpcd_maybe_unserialize( get_post_meta( $post_id, 'wpcd_update_plan_sites', true ) );
		if ( is_array( $planned_sites ) ) {
			$planned_sites         = array_keys( $planned_sites );
			$planned_sites_display = implode( '<br/>', $planned_sites );
		}

		// Completed Servers.
		$completed_server_ids      = wpcd_maybe_unserialize( get_post_meta( $post_id, 'wpcd_update_plan_servers_completed', true ) );
		$completed_servers_display = '';
		if ( is_array( $completed_server_ids ) ) {
			foreach ( $completed_server_ids as $server_id => $complete_status) {
				$completed_servers_display .= WPCD_WORDPRESS_APP()->get_server_name( $server_id ) .'<br/>';
			}
		}

		// Completed sites.
		$completed_site_ids      = wpcd_maybe_unserialize( get_post_meta( $post_id, 'wpcd_update_plan_sites_completed', true ) );
		$completed_sites_display = '';
		if ( is_array( $completed_site_ids ) ) {
			foreach ( $completed_site_ids as $site_id => $complete_status ) {
				$completed_sites_display .= WPCD_WORDPRESS_APP()->get_domain_name( $site_id ) .'<br/>';
			}
		}

		/* Fields that show planned servers and sites */
		$planned_fields = array(
			array(
				'name'    => __( 'Servers Planned', 'wpcd' ),
				'type'    => 'custom_html',
				'std'     => $planned_servers_display,
				'columns' => 6,
			),
			array(
				'name'    => __( 'Sites Planned', 'wpcd' ),
				'type'    => 'custom_html',
				'std'     => $planned_sites_display,
				'columns' => 6,
			),
		);

		/* Fields that show completed servers and sites */
		$completed_fields = array(
			array(
				'name'    => __( 'Servers Completed', 'wpcd' ),
				'type'    => 'custom_html',
				'std'     => $completed_servers_display,
				'columns' => 6,
			),
			array(
				'name'    => __( 'Sites Completed', 'wpcd' ),
				'type'    => 'custom_html',
				'std'     => $completed_sites_display,
				'columns' => 6,
			),
		);

		/* Add the fields defined above to various metaboxes. */
		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_log_completed_mb',
			'title'      => __( 'Completed Servers & Sites', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_log' ),
			'priority'   => 'default',
			'fields'     => $completed_fields,
		);
		$metaboxes[] = array(
			'id'         => 'wpcd_app_update_plan_log_planned_mb',
			'title'      => __( 'Planned Servers & Sites', 'wpcd' ),
			'post_types' => array( 'wpcd_app_update_log' ),
			'priority'   => 'default',
			'fields'     => $planned_fields,
		);

		return $metaboxes;
	}



}
