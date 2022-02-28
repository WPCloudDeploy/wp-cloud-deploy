<?php
/**
 * This class is used for cloud provider.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_POSTS_CLOUD_PROVIDER
 */
class WPCD_POSTS_CLOUD_PROVIDER extends WPCD_Posts_Base {

	/* Include traits */
	use wpcd_get_set_post_type;

	/**
	 * WPCD_POSTS_CLOUD_PROVIDER instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * POSTS_CLOUD_PROVIDER constructor.
	 */
	public function __construct() {

		$this->register();  // register the custom post type.
		$this->hooks();     // register hooks to make the custom post type do things...

	}

	/**
	 * Hooks function.
	 */
	private function hooks() {

		// Filter hook to add new columns.
		add_filter( 'manage_wpcd_cloud_provider_posts_columns', array( $this, 'cloud_provider_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_cloud_provider_posts_custom_column', array( $this, 'cloud_provider_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_cloud_provider_sortable_columns', array( $this, 'wpcd_cloud_provider_table_sorting' ), 10, 1 );

		// Action hook to extend admin filter options.
		add_action( 'restrict_manage_posts', array( $this, 'wpcd_cloud_provider_table_filtering' ) );

		// Filter hook to filter cloud provider listing on custom meta data.
		add_filter( 'parse_query', array( $this, 'wpcd_cloud_provider_list_parse_query' ), 10, 1 );

		// Action hook to save provider status.
		add_action( 'wp_ajax_wpcd_provider_status_save', array( $this, 'wpcd_provider_status_save' ) );

		// Remove PRIVATE state label from certain custom post types - function is actually in ancestor class.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 20, 2 );

		// Load up css and js scripts used for managing this cpt data screens.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

		// Filter hook to add custom meta box.
		add_filter( 'rwmb_meta_boxes', array( $this, 'wpcd_cloud_provider_register_meta_boxes' ), 10, 1 );

		// Action hook after metabox is saved...
		add_action( 'rwmb_wpcd_cloud_provider_basic_after_save_post', array( $this, 'metabox_io_after_save_post_cloud_provider_basic' ), 10, 1 );

		// Add the new providers to the providers array.
		add_filter( 'wpcd_get_cloud_providers', array( $this, 'wpcd_get_cloud_providers' ), 10, 1 );

		// Change the placeholder in the title when adding a new virtual provider.
		add_filter( 'enter_title_here', array( $this, 'change_title_placeholder_text' ) );

		// Filter hook to remove delete link from wpcd_cloud_provider (virtual providers) listing row if it is used for any server.
		add_filter( 'post_row_actions', array( $this, 'wpcd_cloud_provider_post_row_actions' ), 10, 2 );

		// Action hook to hide "Move to trash" link on wpcd_cloud_provider (virtual providers) detail screen if it is used for any server.
		add_action( 'admin_head-post.php', array( $this, 'wpcd_cloud_provider_hide_delete_link' ) );

		// Filter hook to restrict trash post for virtual providers if it is used for any server.
		add_filter( 'pre_trash_post', array( $this, 'wpcd_cloud_provider_restrict_trash_post' ), 10, 2 );

	}

	/**
	 * Register the scripts for the custom post type.
	 *
	 * @param string $hook hook name.
	 */
	public function enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'post-new.php', 'post.php', 'edit.php' ), true ) ) {

			$screen = get_current_screen();
			if ( is_object( $screen ) && 'wpcd_cloud_provider' === $screen->post_type ) {

				wp_enqueue_script( 'wpcd-virtual-provider-admin', wpcd_url . 'assets/js/wpcd-virtual-provider-admin.js', array( 'jquery' ), wpcd_version, true );
				wp_localize_script( 'wpcd-virtual-provider-admin', 'params', array( 'i10n' => array( 'empty_title' => __( 'Please enter the virtual cloud provider title.', 'wpcd' ) ) ) );

			}
		}
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {

		self::wpcd_cloud_provider_register_post_and_taxonomy();

	}

	/**
	 * Add contents to the CLOUD PROVIDER post table columns
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * @return void returns nothing - prints column value instead
	 */
	public function cloud_provider_table_content( $column_name, $post_id ) {

		$value = '';
		switch ( $column_name ) {
			case 'wpcd_cloud_provider_type':
				// Display the short description.
				$value = get_post_meta( $post_id, 'wpcd_cloud_provider_type', true );
				break;
			case 'wpcd_cloud_provider_active':
				$inactive = get_post_meta( $post_id, 'wpcd_cloud_provider_inactive', true );
				if ( boolval( $inactive ) ) {
					$active      = 1;
					$checked     = '';
					$status_text = __( 'INACTIVE', 'wpcd' );
				} else {
					$active      = 0;
					$checked     = __( 'checked', 'wpcd' );
					$status_text = __( 'ACTIVE', 'wpcd' );
				}
				$value = '<label class="wpcd_provider_active_switch"><input data-post_id="' . $post_id . '" class="wpcd_active_provider" data-action="wpcd_provider_status_save" data-nonce="' . wp_create_nonce( 'wpcd-provider-status-save' ) . '" value="' . $active . '" type="checkbox" ' . $checked . '><span class="wpcd_provider_active_slider round" title="' . $status_text . '" ></span></label>';
				break;
		}

		$value = apply_filters( 'wpcd_cloud_provider_table_content', $value, $column_name, $post_id );

		echo $value;
	}

	/**
	 * Add CLOUD PROVIDER POST table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function cloud_provider_table_head( $defaults ) {

		unset( $defaults['date'] ); // remove date column.
		$defaults['title']                      = __( 'Virtual Provider', 'wpcd' );  // Add different title label to the title column.
		$defaults['wpcd_cloud_provider_type']   = __( 'Provider Type', 'wpcd' );
		$defaults['wpcd_cloud_provider_active'] = __( 'Active?', 'wpcd' );
		$defaults['date']                       = __( 'Date', 'wpcd' );  // Add back the date column.

		return $defaults;

	}

	/**
	 * Add table header sorting columns
	 *
	 * @param array $columns array of default head columns.
	 *
	 * @return $columns modified array with new columns
	 */
	public function wpcd_cloud_provider_table_sorting( $columns ) {
		$columns['wpcd_cloud_provider_type'] = 'wpcd_cloud_provider_type';
		return $columns;
	}

	/**
	 * Add filters on the cloud provider listing screen at the backend
	 *
	 * Action hook: restrict_manage_posts
	 *
	 * @return void
	 */
	public function wpcd_cloud_provider_table_filtering() {

		global $typenow, $pagenow;

		$post_type = 'wpcd_cloud_provider';

		if ( is_admin() && 'edit.php' === $pagenow && $typenow === $post_type ) {

			// Provider Type.
			$provider_type = $this->generate_provider_meta_dropdown( $post_type, 'wpcd_cloud_provider_type', __( 'Provider Type', 'wpcd' ) );
			echo $provider_type;

			// Active.
			$cloud_options = array(
				'active'   => __( 'Active', 'wpcd' ),
				'inactive' => __( 'Inactive', 'wpcd' ),
			);
			$cloud_active  = WPCD_WORDPRESS_APP()->generate_meta_dropdown( 'wpcd_cloud_provider_inactive', __( 'Provider Status', 'wpcd' ), $cloud_options );
			echo $cloud_active;

		}
	}

	/**
	 * To modify default query parameters and to show notification listing based on custom filters
	 *
	 * Action filter: parse_query
	 *
	 * @param  object $query query.
	 */
	public function wpcd_cloud_provider_list_parse_query( $query ) {
		global $pagenow;

		$filter_action = filter_input( INPUT_GET, 'filter_action', FILTER_SANITIZE_STRING );
		if ( is_admin() && $query->is_main_query() && 'wpcd_cloud_provider' === $query->query['post_type'] && 'edit.php' === $pagenow && __( 'Filter' ) === $filter_action ) {

			$qv = &$query->query_vars;

			// PROVIDER TYPE.
			if ( isset( $_GET['wpcd_cloud_provider_type'] ) && ! empty( $_GET['wpcd_cloud_provider_type'] ) ) {
				$wpcd_cloud_provider_type = filter_input( INPUT_GET, 'wpcd_cloud_provider_type', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'wpcd_cloud_provider_type',
					'value'   => $wpcd_cloud_provider_type,
					'compare' => '=',
				);
			}

			// PROVIDER STATUS.
			if ( isset( $_GET['wpcd_cloud_provider_inactive'] ) && ! empty( $_GET['wpcd_cloud_provider_inactive'] ) ) {
				$wpcd_cloud_provider_inactive = filter_input( INPUT_GET, 'wpcd_cloud_provider_inactive', FILTER_SANITIZE_STRING );

				if ( 'active' === $wpcd_cloud_provider_inactive ) {
					$qv['meta_query'][] = array(
						'key'     => 'wpcd_cloud_provider_inactive',
						'value'   => '0',
						'compare' => '=',
					);
				}

				if ( 'inactive' === $wpcd_cloud_provider_inactive ) {
					$qv['meta_query'][] = array(
						'key'     => 'wpcd_cloud_provider_inactive',
						'value'   => '1',
						'compare' => '=',
					);
				}
			}
		}
	}

	/**
	 * Save provider status
	 */
	public function wpcd_provider_status_save() {
		// nonce check.
		check_ajax_referer( 'wpcd-provider-status-save', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $msg );
			wp_die();

		}

		$post_id         = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
		$provider_active = filter_input( INPUT_POST, 'provider_active', FILTER_SANITIZE_NUMBER_INT );

		$provider_args = array(
			'post_type'      => 'wpcd_cloud_provider',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'p'              => $post_id,
		);

		$provider_found = get_posts( $provider_args );

		if ( ! empty( $provider_found ) && ( 0 === (int) $provider_active || 1 === (int) $provider_active ) ) {
			update_post_meta( $post_id, 'wpcd_cloud_provider_inactive', $provider_active );
			$msg = array( 'msg' => __( 'Provider status updated successfully.', 'wpcd' ) );
			wp_send_json_error( $msg );
		} else {
			$msg = array( 'msg' => __( 'Something went wrong. please try again', 'wpcd' ) );
			wp_send_json_error( $msg );
		}

		exit;
	}

	/**
	 * To add custom filtering options based on meta fields.
	 * This filter will be added on cloud provider listing screen at the backend
	 *
	 * @param  string $post_type post type.
	 * @param  string $field_key field key.
	 * @param  string $first_option first option.
	 *
	 * @return string
	 */
	public function generate_provider_meta_dropdown( $post_type, $field_key, $first_option ) {

		global $wpdb;

		$sql    = $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IS NOT NULL ORDER BY meta_value", $field_key );
		$result = $wpdb->get_results( $sql );
		if ( count( $result ) === 0 ) {
			return '';
		}

		$html          = '';
		$html         .= sprintf( '<select name="%s" id="filter-by-%s">', $field_key, $field_key );
		$html         .= sprintf( '<option value="">%s</option>', $first_option );
		$get_field_key = filter_input( INPUT_GET, $field_key, FILTER_SANITIZE_STRING );
		foreach ( $result as $row ) {
			if ( empty( $row->meta_value ) ) {
				continue;
			}
			$meta_value = $row->meta_value;
			$selected   = selected( $get_field_key, $meta_value, false );
			$html      .= sprintf( '<option value="%s" %s>%s</option>', $meta_value, $selected, $meta_value );
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * To add custom metabox on server details screen
	 * Multiple metaboxes created for:
	 * 1. Allow user to change server owner(author)
	 * 2. Allow user to make server delete protected
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param  array $metaboxes metaboxes.
	 *
	 * @return array
	 */
	public function wpcd_cloud_provider_register_meta_boxes( $metaboxes ) {

		$prefix = 'wpcd_';

		// Register a metabox to hold a description.
		$metaboxes[] = array(
			'id'       => $prefix . 'cloud_provider_help_description',
			'title'    => __( 'Help', 'wpcd' ),
			'pages'    => array( 'wpcd_cloud_provider' ), // displays on wpcd_app_server post type only.
			'priority' => 'high',
			'style'    => 'seamless',
			'fields'   => array(
				array(
					'name' => '',
					'id'   => $prefix . 'cloud_provider_help',
					'type' => 'custom_html',
					'std'  => __( 'A Virtual Cloud Provider allows you to define a new provider based on an existing one.  By doing this you can use multiple accounts at the same cloud provider.', 'wpcd' ),
				),
				array(
					'name' => '',
					'id'   => $prefix . 'cloud_provider_help_view_docs',
					'type' => 'custom_html',
					'std'  => '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/virtual-cloud-providers/">' . __( 'View Documentation', 'wpcd' ) . '</a>',
				),
			),
		);

		// Register the metabox with the input fields required.
		$metaboxes[] = array(
			'id'         => $prefix . 'cloud_provider_basic',
			'title'      => __( 'Virtual Cloud Provider Data', 'wpcd' ),
			'pages'      => array( 'wpcd_cloud_provider' ), // displays on wpcd_app_server post type only.
			'priority'   => 'high',
			'fields'     => array(
				array(
					'name'        => 'Cloud Provider Type',
					'desc'        => __( 'Choose one of our supported cloud providers', 'wpcd' ),
					'id'          => $prefix . 'cloud_provider_type',
					'type'        => 'select',
					'std'         => 'digital-ocean',
					'placeholder' => __( 'Select A Cloud Provider Type', 'wpcd' ),
					'options'     => $this->get_provider_types(),
				),

				array(
					'type' => 'divider',
				),
				array(
					'name' => 'Region Prefix',
					'desc' => __( 'Optional - When selecting a region from a drop-down this prefix can help distinguish between providers of the same type.', 'wpcd' ),
					'id'   => $prefix . 'cloud_provider_region_prefix',
					'type' => 'text',
				),

				array(
					'name' => 'Providers Dashboard',
					'desc' => __( 'Optional - Enter the URL to the providers dashboard where they can get a token/api keys. If left blank the default for the provider type will be used.', 'wpcd' ),
					'id'   => $prefix . 'cloud_provider_dashboard_link',
					'type' => 'text',
					'size' => 120,
				),
				array(
					'type' => 'divider',
				),

				array(
					'name' => 'Client Information',
					'desc' => __( 'If this virtual cloud provider is for a client, you can enter client data or notes here.', 'wpcd' ),
					'id'   => $prefix . 'cloud_provider_client_info',
					'type' => 'textarea',
					'rows' => 6,
					'cols' => 120,
				),

				array(
					'type' => 'divider',
				),

				array(
					'name' => 'Notes',
					'desc' => __( 'Internal notes about this virtual provider.', 'wpcd' ),
					'id'   => $prefix . 'cloud_provider_notes',
					'type' => 'textarea',
					'rows' => 6,
					'cols' => 120,
				),

				array(
					'type' => 'divider',
				),

				array(
					'name' => 'Deactivate',
					'desc' => __( 'Disable this virtual cloud provider.', 'wpcd' ),
					'id'   => $prefix . 'cloud_provider_inactive',
					'type' => 'checkbox',
				),

			),
			'validation' => array(
				'rules'    => array(
					$prefix . 'cloud_provider_type' => array(
						'required' => true,
					),
				),
				'messages' => array(
					$prefix . 'cloud_provider_type' => array(
						'required' => __( 'Cloud Provider Type is required.', 'wpcd' ),
					),
				),
			),
		);

		return $metaboxes;

	}

	/**
	 * Action hook after wpcd_cloud_provider_basic metabox is saved.
	 *
	 * @param int $object_id object_id.
	 */
	public function metabox_io_after_save_post_cloud_provider_basic( $object_id ) {

		// Get the post_id.
		$post_id = null;
		if ( isset( $_GET['post'] ) ) {
			$post_id = intval( $_GET['post'] );
		} elseif ( isset( $_POST['post_ID'] ) ) {
			$post_id = intval( $_POST['post_ID'] );
		}

		// return if not the right post type.
		if ( 'wpcd_cloud_provider' <> get_post_type( $post_id ) ) {
			return;
		}

		// set the slug.
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$slug = get_post_meta( $post_id, 'wpcd_cloud_provider_slug', true );
				if ( empty( $slug ) ) {
					update_post_meta( $post_id, 'wpcd_cloud_provider_slug', (string) $post_id . '-' . sanitize_title( $post->post_title ) );
				}
			}
		}
	}

	/**
	 * Registers the custom post type and related taxonomies (if any)
	 */
	public static function wpcd_cloud_provider_register_post_and_taxonomy() {
		register_post_type(
			'wpcd_cloud_provider',
			array(
				'labels'              => array(
					'name'                  => _x( 'Virtual Cloud Provider', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Virtual Cloud Provider', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Virtual Cloud Providers', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Virtual Cloud Providers', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => __( 'Add New Virtual Cloud Provider', 'wpcd' ),
					'add_new_item'          => __( 'Add New Virtual Cloud Provider', 'wpcd' ),
					'edit_item'             => __( 'Edit Virtual Cloud Provider', 'wpcd' ),
					'view_item'             => __( 'View Virtual Cloud Provider', 'wpcd' ),
					'all_items'             => __( 'Virtual Providers', 'wpcd' ), // Label to signify all items in a submenu link.
					'search_items'          => __( 'Search Virtual Cloud Providers', 'wpcd' ),
					'not_found'             => apply_filters( 'wpcd_no_virtual_cloud_providers_found_msg', __( 'No Virtual Cloud Providers were found.', 'wpcd' ) ),
					'not_found_in_trash'    => __( 'No Virtual Cloud Providers were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Virtual Cloud Providers list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Virtual Cloud Providers list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Virtual Cloud Providers list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => ( apply_filters( 'wpcd_show_virtual_cloud_providers', false ) ) ? 'edit.php?post_type=wpcd_app_server' : false,
				'menu_position'       => null,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'menu_icon'           => 'data:image/svg+xml;base64,' . base64_encode( '<svg fill="black" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="20px" height="20px"><path fill="black" d="M 20 9 C 18.355469 9 17 10.355469 17 12 L 17 68 C 17 69.644531 18.355469 71 20 71 L 60 71 C 61.644531 71 63 69.644531 63 68 L 63 12 C 63 10.355469 61.644531 9 60 9 Z M 20 11 L 60 11 C 60.566406 11 61 11.433594 61 12 L 61 68 C 61 68.566406 60.566406 69 60 69 L 20 69 C 19.433594 69 19 68.566406 19 68 L 19 12 C 19 11.433594 19.433594 11 20 11 Z M 24 16 L 24 42 L 56 42 L 56 16 Z M 26 18 L 54 18 L 54 24 L 26 24 Z M 50 20 C 49.449219 20 49 20.449219 49 21 C 49 21.550781 49.449219 22 50 22 C 50.550781 22 51 21.550781 51 21 C 51 20.449219 50.550781 20 50 20 Z M 26 26 L 54 26 L 54 32 L 26 32 Z M 50 28 C 49.449219 28 49 28.449219 49 29 C 49 29.550781 49.449219 30 50 30 C 50.550781 30 51 29.550781 51 29 C 51 28.449219 50.550781 28 50 28 Z M 26 34 L 54 34 L 54 40 L 26 40 Z M 50 36 C 49.449219 36 49 36.449219 49 37 C 49 37.550781 49.449219 38 50 38 C 50.550781 38 51 37.550781 51 37 C 51 36.449219 50.550781 36 50 36 Z M 25 47 C 24.449219 47 24 47.449219 24 48 C 24 48.550781 24.449219 49 25 49 C 25.550781 49 26 48.550781 26 48 C 26 47.449219 25.550781 47 25 47 Z M 25 51 C 24.449219 51 24 51.449219 24 52 C 24 52.550781 24.449219 53 25 53 C 25.550781 53 26 52.550781 26 52 C 26 51.449219 25.550781 51 25 51 Z M 40 52 C 37.800781 52 36 53.800781 36 56 C 36 58.199219 37.800781 60 40 60 C 42.199219 60 44 58.199219 44 56 C 44 53.800781 42.199219 52 40 52 Z M 40 54 C 41.117188 54 42 54.882813 42 56 C 42 57.117188 41.117188 58 40 58 C 38.882813 58 38 57.117188 38 56 C 38 54.882813 38.882813 54 40 54 Z M 25 55 C 24.449219 55 24 55.449219 24 56 C 24 56.550781 24.449219 57 25 57 C 25.550781 57 26 56.550781 26 56 C 26 55.449219 25.550781 55 25 55 Z M 25 59 C 24.449219 59 24 59.449219 24 60 C 24 60.550781 24.449219 61 25 61 C 25.550781 61 26 60.550781 26 60 C 26 59.449219 25.550781 59 25 59 Z M 25 63 C 24.449219 63 24 63.449219 24 64 C 24 64.550781 24.449219 65 25 65 C 25.550781 65 26 64.550781 26 64 C 26 63.449219 25.550781 63 25 63 Z"/></svg>' ),
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'capabilities'        => array(
					'create_posts'           => 'wpcd_manage_settings',
					'edit_post'              => 'wpcd_manage_settings',
					'edit_posts'             => 'wpcd_manage_settings',
					'edit_others_posts'      => 'wpcd_manage_settings',
					'edit_published_posts'   => 'wpcd_manage_settings',
					'delete_post'            => 'wpcd_manage_settings',
					'publish_posts'          => 'wpcd_manage_settings',
					'delete_posts'           => 'wpcd_manage_settings',
					'delete_others_posts'    => 'wpcd_manage_settings',
					'delete_published_posts' => 'wpcd_manage_settings',
					'delete_private_posts'   => 'wpcd_manage_settings',
					'edit_private_posts'     => 'wpcd_manage_settings',
					'read_private_posts'     => 'wpcd_manage_settings',
				),
			)
		);

	}

	/**
	 * Returns a filtered array of provider types
	 */
	public function get_provider_types() {

		$provider_types = array(
			'digital-ocean' => 'Digital Ocean',
		);

		/*
		$provider_types = array(
						'digital-ocean'	=> 'Digital Ocean',
						'linode'		=> 'Linode',
						'vultr'			=> 'Vultr',
						'awsec2'		=> 'AWS EC2',
						'awslightsail'	=> 'AWS Lightsail',
					);
		*/

		return apply_filters( 'wpcd_provider_types', $provider_types );

	}


	/**
	 * Add all the providers defined in this custom post type to the providers array.
	 *
	 * @param array $providers Existing key-value array of providers.
	 *
	 * @return array $providers New array of providers.
	 */
	public function wpcd_get_cloud_providers( $providers ) {

		// Only do this if the virtual provider add-on is enabled.
		if ( ! class_exists( 'WPCD_VirtualCloudProvider_Init' ) ) {
			return $providers;
		}

		// get all defined virtual providers.
		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_cloud_provider',
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);

		// loop through and add to the providers array.
		foreach ( $posts as $post ) {

			$inactive = boolval( get_post_meta( $post->ID, 'wpcd_cloud_provider_inactive', true ) );

			// if virtual provider is not inactive add it to the array.
			if ( ! $inactive ) {
				$providers[ get_post_meta( $post->ID, 'wpcd_cloud_provider_slug', true ) ] = $post->post_title;
			}
		}

		return $providers;

	}


	/**
	 * Change the placeholder title when adding a new record.
	 *
	 * Fiilter Hook: enter_title_here
	 *
	 * @param string $title The current placeholder title text.
	 *
	 * @return string $title New placeholder title text.
	 */
	public function change_title_placeholder_text( $title ) {
		$screen = get_current_screen();

		if ( 'wpcd_cloud_provider' === $screen->post_type ) {
			$title = __( 'Enter your virtual cloud provider title', 'wpcd' );
		}

		return $title;
	}

	/**
	 * Removes link to delete post and hides the checkbox for wpcd_cloud_provider if it is used for any server.
	 *
	 * Filter hook: post_row_actions
	 *
	 * @param  array  $actions actions.
	 * @param  object $post post.
	 *
	 * @return array
	 */
	public function wpcd_cloud_provider_post_row_actions( $actions, $post ) {

		if ( 'wpcd_cloud_provider' === $post->post_type ) {
			$slug = get_post_meta( $post->ID, 'wpcd_cloud_provider_slug', true );

			if ( empty( $slug ) ) {
				return $actions;
			}

			$args    = array(
				'post_type'   => 'wpcd_app_server',
				'post_status' => 'private',
				'meta_key'    => 'wpcd_server_provider',
				'meta_value'  => $slug,
				'fields'      => 'ids',
			);
			$servers = get_posts( $args );

			if ( count( $servers ) ) {
				unset( $actions['trash'] );
				unset( $actions['delete'] );
				?>
				<script type="text/javascript">
					jQuery(document).ready(function($){
						$('#cb-select-<?php echo esc_html( $post->ID ); ?>').attr('disabled', true);
					});
				</script>
				<?php
			}
		}

		return $actions;
	}

	/**
	 * Removes "Move to trash" for wpcd_cloud_provider details screen if it is used for any server.
	 *
	 * Action hook: admin_head-post.php
	 *
	 * @return void
	 */
	public function wpcd_cloud_provider_hide_delete_link() {
		$screen = get_current_screen();

		if ( 'wpcd_cloud_provider' === $screen->post_type ) {
			$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
			$slug    = get_post_meta( $post_id, 'wpcd_cloud_provider_slug', true );

			if ( ! empty( $slug ) ) {
				$args    = array(
					'post_type'   => 'wpcd_app_server',
					'post_status' => 'private',
					'meta_key'    => 'wpcd_server_provider',
					'meta_value'  => $slug,
					'fields'      => 'ids',
				);
				$servers = get_posts( $args );

				if ( count( $servers ) ) {
					?>
					<style>#delete-action { display: none; }</style>
					<?php
				}
			}
		}

	}

	/**
	 * Restricts the trash post of the wpcd_cloud_provider if it is used for any server.
	 *
	 * Filter hook: pre_trash_post
	 *
	 * @param  boolean $trash trash.
	 * @param  object  $post post.
	 *
	 * @return boolean
	 */
	public function wpcd_cloud_provider_restrict_trash_post( $trash, $post ) {

		if ( 'wpcd_cloud_provider' === $post->post_type ) {
			$slug = get_post_meta( $post->ID, 'wpcd_cloud_provider_slug', true );

			if ( ! empty( $slug ) ) {
				$args    = array(
					'post_type'   => 'wpcd_app_server',
					'post_status' => 'private',
					'meta_key'    => 'wpcd_server_provider',
					'meta_value'  => $slug,
					'fields'      => 'ids',
				);
				$servers = get_posts( $args );

				if ( count( $servers ) ) {
					return true;
				}
			}
		}

		return $trash;
	}

}
