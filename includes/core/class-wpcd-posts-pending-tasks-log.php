<?php
/**
 * WPCD_PENDING_TASKS_LOG class for pending tasks log.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_PENDING_TASKS_LOG
 */
class WPCD_PENDING_TASKS_LOG extends WPCD_POSTS_LOG {

	/**
	 * WPCD_PENDING_TASKS_LOG instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * POSTS_APP constructor.
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

		// Meta box display callback.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Save Meta Values.
		add_action( 'save_post', array( $this, 'save_meta_values' ), 10, 2 );

		// Filter hook to add new columns.
		add_filter( 'manage_wpcd_pending_log_posts_columns', array( $this, 'pending_tasks_log_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_pending_log_posts_custom_column', array( $this, 'pending_tasks_log_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_pending_log_sortable_columns', array( $this, 'pending_tasks_log_table_sorting' ), 10, 1 );

		// Filter hook to remove edit bulk action - handled in ancestor class.
		add_filter( 'bulk_actions-edit-wpcd_pending_log', array( $this, 'wpcd_log_bulk_actions' ), 10, 1 );

		// Filter hook to add new bulk actions.
		add_filter( 'bulk_actions-edit-wpcd_pending_log', array( $this, 'wpcd_pending_log_bulk_actions' ), 10, 1 );

		// Action hook to handle bulk actions.
		add_filter( 'handle_bulk_actions-edit-wpcd_pending_log', array( $this, 'wpcd_bulk_action_handler_pending_log' ), 10, 3 );

		// When we're querying to find out the status of a server.
		add_filter( 'wpcd_is_server_available_for_commands', array( &$this, 'wpcd_is_server_available_for_commands' ), 10, 2 );

		// Action hook to extend admin filter options.
		add_action( 'restrict_manage_posts', array( $this, 'wpcd_pending_log_table_filtering' ) );

		// Filter hook to filter pending logs screen on custom meta data.
		add_filter( 'parse_query', array( $this, 'wpcd_pending_logs_list_parse_query' ), 10, 1 );

		// Action hook to clean up pending logs.
		add_action( 'wpcd_clean_up_pending_logs', array( $this, 'wpcd_clean_up_pending_logs_callback' ) );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpcd_clean_up_pending_logs_for_new_site' ), 10, 2 );

		// Action hook to clean up pending logs.
		add_action( 'wp_ajax_clean_up_pending_logs_action', array( $this, 'wpcd_clean_up_pending_logs_callback' ) );

		// Action hook of notification to long pending task.
		add_action( 'wpcd_email_alert_for_long_pending_tasks', array( $this, 'wpcd_send_email_alert_for_long_pending_tasks' ) );

		// Delete the pending logs on site deletion.
		add_action( 'wp_trash_post', array( $this, 'wpcd_wpapp_pending_log_post_delete' ), 11, 1 );
		add_action( 'before_delete_post', array( $this, 'wpcd_wpapp_pending_log_post_delete' ), 11, 1 );
		add_action( 'wpcd_before_remove_site_action', array( $this, 'wpcd_wpapp_pending_log_post_delete' ), 11, 1 );

	}

	/**
	 * Register the custom post type.
	 */
	public function register() {
		register_post_type(
			'wpcd_pending_log',
			array(
				'labels'              => array(
					'name'                  => _x( 'Pending Tasks', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Pending Task', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Pending Task', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Pending Task', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit Pending Task', 'wpcd' ),
					'view_item'             => __( 'View Pending Task', 'wpcd' ),
					'all_items'             => __( 'Pending Tasks', 'wpcd' ), // Label to signify all items in a submenu link.
					'search_items'          => __( 'Search Pending Task Log', 'wpcd' ),
					'not_found'             => __( 'No Pending Task Logs were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Pending Task Logs were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Pending Tasks Log list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Pending Tasks Log list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Pending Task list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wpcd_app_server',
				'menu_position'       => 10,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'supports'            => array( '' ),
				'rewrite'             => null,
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts' => false,
					'read_posts'   => 'wpcd_manage_logs',
					'edit_posts'   => 'wpcd_manage_logs',
				),
				'map_meta_cap'        => true,
			)
		);

		$this->set_post_type( 'wpcd_pending_log' );

		$search_fields = array(
			'parent_post_id',
			'pending_task_key',
			'pending_task_type',
			'pending_task_details',
			'pending_task_state',
			'pending_task_attempts',
			'pending_task_comment',
			'pending_task_reference',
			'pending_task_associated_server_id',
			'pending_task_parent_post_type',
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
	public function pending_tasks_log_table_sorting( $columns ) {

		return $columns;
	}

	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name string column name.
	 * @param int    $post_id int post id.
	 *
	 *    print column value.
	 */
	public function pending_tasks_log_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_pending_task_parent_id':
				// Display the name of the server or app.
				$parent_post_id = get_post_meta( $post_id, 'parent_post_id', true );
				if ( $parent_post_id ) {
					$parent_post = get_post( $parent_post_id );
					if ( $parent_post ) {
						$title = wp_kses_post( $parent_post->post_title );
						$value = sprintf( '<a href="%s">' . $title . '</a>', get_edit_post_link( $parent_post_id ) );
						$value = $value . '<br />' . 'id: ' . (string) $parent_post_id;
					} else {
						$value = __( 'Parent post not found', 'wpcd' );
					}
				} else {
					$value = __( 'Parent post not found', 'wpcd' );
				}

				break;
			case 'wpcd_pending_task_associated_server':
				// Display the name of the server.
				$server_id = get_post_meta( $post_id, 'pending_task_associated_server_id', true );
				if ( ! empty( $server_id ) ) {
					$server_post = get_post( $server_id );
					if ( $server_post ) {
						$title = wp_kses_post( $server_post->post_title );
						$value = sprintf( '<a href="%s">' . $title . '</a>', get_edit_post_link( $server_id ) );
						$value = $value . '<br />' . 'id: ' . (string) $server_id;
					} else {
						$value = __( 'No Server', 'wpcd' );
					}
				} else {
					$value = __( 'No Server', 'wpcd' );
				}

				break;
			case 'wpcd_pending_task_type':
			case 'wpcd_pending_task_key':
			case 'wpcd_pending_task_details':
			case 'wpcd_pending_task_state':
			case 'wpcd_pending_task_attempts':
			case 'wpcd_pending_task_comment':
				$value = wp_kses_post( get_post_meta( $post_id, substr( $column_name, 5 ), true ) );
				break;

			case 'wpcd_pending_task_reference':
				$value = wp_kses_post( get_post_meta( $post_id, substr( $column_name, 5 ), true ) );
				if ( is_numeric( $value ) ) {
					// There's a good chance it's a post id so make it an admin post link.
					$value = '<a href=' . get_edit_post_link( $value ) . '>' . $value . '</a>';
				}
				break;

			case 'wpcd_pending_task_start_date':
			case 'wpcd_pending_task_complete_date':
				$value = wp_kses_post( get_post_meta( $post_id, substr( $column_name, 5 ), true ) );
				if ( ! empty( $value ) ) {
					$value = date( date( 'Y-m-d @ H:i', $value ) );
				}

			default:
				break;
		}

		echo $value;

	}

	/**
	 * Add table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function pending_tasks_log_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_pending_task_parent_id']         = __( 'Owner/Parent', 'wpcd' );
		$defaults['wpcd_pending_task_type']              = __( 'Type', 'wpcd' );
		$defaults['wpcd_pending_task_key']               = __( 'Key', 'wpcd' );
		$defaults['wpcd_pending_task_state']             = __( 'State', 'wpcd' );
		$defaults['wpcd_pending_task_attempts']          = __( 'Attempts', 'wpcd' );
		$defaults['wpcd_pending_task_comment']           = __( 'Comment', 'wpcd' );
		$defaults['wpcd_pending_task_associated_server'] = __( 'Server', 'wpcd' );
		$defaults['wpcd_pending_task_start_date']        = __( 'Start Date', 'wpcd' );
		$defaults['wpcd_pending_task_complete_date']     = __( 'Complete Date', 'wpcd' );
		$defaults['date']                                = __( 'Date', 'wpcd' );

		// $defaults['wpcd_pending_task_details']    = __( 'Data', 'wpcd' );
		// $defaults['wpcd_pending_task_reference']     = __( 'Reference', 'wpcd' );

		return $defaults;

	}

	/**
	 * Add filters on the pending logs screen at the backend
	 *
	 * Action hook: restrict_manage_posts
	 *
	 * @return void
	 */
	public function wpcd_pending_log_table_filtering() {

		global $typenow, $pagenow;

		$post_type = 'wpcd_pending_log';

		if ( is_admin() && 'edit.php' === $pagenow && $typenow === $post_type ) {

			// Log Type.
			$log_type = $this->generate_pending_logs_meta_dropdown( $post_type, 'pending_task_type', __( 'All Types', 'wpcd' ) );
			echo $log_type;

			// Log State.
			$log_state = $this->generate_pending_logs_meta_dropdown( $post_type, 'pending_task_state', __( 'All States', 'wpcd' ) );
			echo $log_state;

			// Log Owner/Parent.
			$log_owner = $this->generate_pending_logs_meta_dropdown( $post_type, 'parent_post_id', __( 'All Parents', 'wpcd' ) );
			echo $log_owner;

			// Log Reference.
			$log_reference = $this->generate_pending_logs_meta_dropdown( $post_type, 'pending_task_reference', __( 'All References', 'wpcd' ) );
			echo $log_reference;

		}
	}

	/**
	 * To add custom filtering options based on meta fields.
	 * This filter will be added on pending logs screen at the backend
	 *
	 * @param  string $post_type post type.
	 * @param  string $field_key field key.
	 * @param  string $first_option first option.
	 *
	 * @return string
	 */
	public function generate_pending_logs_meta_dropdown( $post_type, $field_key, $first_option ) {

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
	 * To modify default query parameters and to show pending logs listing based on custom filters
	 *
	 * Action filter: parse_query
	 *
	 * @param  object $query query.
	 */
	public function wpcd_pending_logs_list_parse_query( $query ) {
		global $pagenow;

		$filter_action = filter_input( INPUT_GET, 'filter_action', FILTER_SANITIZE_STRING );
		if ( is_admin() && $query->is_main_query() && 'wpcd_pending_log' === $query->query['post_type'] && 'edit.php' === $pagenow && ! empty( $filter_action ) ) {
			$qv = &$query->query_vars;

			// Pending Task Type.
			if ( isset( $_GET['pending_task_type'] ) && ! empty( $_GET['pending_task_type'] ) ) {
				$pending_task_type = filter_input( INPUT_GET, 'pending_task_type', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'pending_task_type',
					'value'   => $pending_task_type,
					'compare' => '=',
				);
			}

			// Pending Task State.
			if ( isset( $_GET['pending_task_state'] ) && ! empty( $_GET['pending_task_state'] ) ) {
				$pending_task_state = filter_input( INPUT_GET, 'pending_task_state', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'pending_task_state',
					'value'   => $pending_task_state,
					'compare' => '=',
				);
			}

			// Pending Task Owner/Parent.
			if ( isset( $_GET['parent_post_id'] ) && ! empty( $_GET['parent_post_id'] ) ) {
				$parent_post_id = filter_input( INPUT_GET, 'parent_post_id', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'parent_post_id',
					'value'   => $parent_post_id,
					'compare' => '=',
				);
			}

			// Pending Task Reference.
			if ( isset( $_GET['pending_task_reference'] ) && ! empty( $_GET['pending_task_reference'] ) ) {
				$pending_task_reference = filter_input( INPUT_GET, 'pending_task_reference', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'pending_task_reference',
					'value'   => $pending_task_reference,
					'compare' => '=',
				);
			}
		}
	}

	/**
	 * Register meta box(es).
	 */
	public function add_meta_boxes() {

		add_meta_box(
			'pending_task_log',
			__( 'Pending Tasks Log', 'wpcd' ),
			array( $this, 'render_pending_task_log_meta_box' ),
			'wpcd_pending_log',
			'advanced',
			'high'
		);

	}

	/**
	 * Render the Pending Tasks LOG detail meta box
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_pending_task_log_meta_box( $post ) {

		$html = '';

		$pending_task_type                 = get_post_meta( $post->ID, 'pending_task_type', true );
		$pending_task_key                  = get_post_meta( $post->ID, 'pending_task_key', true );
		$pending_task_details              = get_post_meta( $post->ID, 'pending_task_details', true );
		$pending_task_state                = get_post_meta( $post->ID, 'pending_task_state', true );
		$pending_task_attempts             = get_post_meta( $post->ID, 'pending_task_attempts', true );
		$pending_task_reference            = get_post_meta( $post->ID, 'pending_task_reference', true );
		$pending_task_history              = get_post_meta( $post->ID, 'pending_task_history', true );
		$pending_task_messages             = get_post_meta( $post->ID, 'pending_task_messages', true );
		$pending_task_comment              = get_post_meta( $post->ID, 'pending_task_comment', true );
		$pending_task_start_date           = get_post_meta( $post->ID, 'pending_task_start_date', true );
		$pending_task_complete_date        = get_post_meta( $post->ID, 'pending_task_complete_date', true );
		$pending_task_parent_post_type     = get_post_meta( $post->ID, 'pending_task_parent_post_type', true );
		$pending_task_associated_server_id = get_post_meta( $post->ID, 'pending_task_associated_server_id', true );
		$parent_post_id                    = get_post_meta( $post->ID, 'parent_post_id', true );

		ob_start();
		require wpcd_path . 'includes/templates/pending_tasks_log.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

	}

	/**
	 * Handles saving the meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_values( $post_id, $post ) {
		// nothing right now.
	}

	/**
	 * Add a new Pending Task Log record
	 *
	 * @param int    $parent_post_id The post id that represents the item this log is being done against.
	 * @param string $task_type The task type that is to be executed later.
	 * @param string $task_key A user defined key that will allow us to retrieve an incomplete task later.
	 * @param array  $task_details An array with supporting details.
	 * @param string $task_state The state of the task when we're adding it.  Could be 'not-ready', 'ready'.  Later other states might be 'in-process', 'completed', 'errored'.
	 * @param string $task_reference other cross-reference data if needed.
	 * @param string $task_comment task comment.
	 */
	public function add_pending_task_log_entry( $parent_post_id, $task_type, $task_key, $task_details, $task_state, $task_reference = '', $task_comment = '' ) {

		// Author is current user or system.
		$author_id = get_current_user_id();

		// Get parent post.
		$post = get_post( $parent_post_id );

		if ( empty( $post_id ) ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'wpcd_pending_log',
					'post_status' => 'private',
					'post_title'  => 'Pending Task For: ' . $post->post_title,
					'post_author' => $author_id,
				)
			);
		}

		// if $result is an error, convert to string...
		if ( is_wp_error( $post_id ) ) {
			$result = print_r( $post_id, true );
		}

		// What post type is the parent?  I.e.: What post type is this task associated with?
		$parent_post_type = get_post_type( $parent_post_id );

		if ( ! is_wp_error( $post_id ) && ! empty( $post_id ) ) {
			update_post_meta( $post_id, 'parent_post_id', $parent_post_id );    // using the parent post id to link back to the master record.  Sometimes that will be a server record.  Other types it will be an APP record.
			update_post_meta( $post_id, 'pending_task_parent_post_type', $parent_post_type );
			update_post_meta( $post_id, 'pending_task_type', $task_type );
			update_post_meta( $post_id, 'pending_task_key', $task_key );
			update_post_meta( $post_id, 'pending_task_details', $task_details );
			update_post_meta( $post_id, 'pending_task_state', $task_state );
			update_post_meta( $post_id, 'pending_task_attempts', 0 ); // Number of attempts made to complete a task.
			update_post_meta( $post_id, 'pending_task_reference', $task_reference );
			update_post_meta( $post_id, 'pending_task_comment', $task_comment );

			// Now stamp the record with the SERVER ID regardless of the parent post type.
			if ( 'wpcd_app_server' === $parent_post_type ) {
				update_post_meta( $post_id, 'pending_task_associated_server_id', $parent_post_id );
			} elseif ( 'wpcd_app' === $parent_post_type ) {
				$server_id = WPCD_WORDPRESS_APP()->get_server_id_by_app_id( $parent_post_id );
				update_post_meta( $post_id, 'pending_task_associated_server_id', $server_id );
			}
		}

		// @TODO: This should not be called here every single time the logs are updated. This should have a cron job or something else.

		/* Clean up old log entries */

		/* @TODO: Need a custom version of this so that we only clean up COMPLETED items! */

		// $this->clean_up_old_log_entries( 'wpcd_command_log' );

		return $post_id;

	}

	/**
	 * Return a list of task posts, searching for a combination of
	 * pending_task_key, state and type.
	 *
	 * @param string $key    Match the 'pending_task_key' meta.
	 * @param string $state  Match the 'pending_task_state' meta.
	 * @param string $type   Match the 'pending_task_type' meta.
	 */
	public function get_tasks_by_key_state_type( $key, $state, $type ) {

		$args = array(
			'post_type'      => 'wpcd_pending_log',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'pending_task_key',
					'value' => $key,
				),
				array(
					'key'   => 'pending_task_state',
					'value' => $state,
				),
				array(
					'key'   => 'pending_task_type',
					'value' => $type,
				),
			),
		);

		$task_posts = get_posts( $args );

		return $task_posts;

	}

	/**
	 * Return a list of task posts, searching for a combination of
	 * parent, state and type.
	 *
	 * @param string $parent Match the 'parent_post_id' meta.
	 * @param string $state  Match the 'pending_task_state' meta.
	 * @param string $type   Match the 'pending_task_type' meta.
	 */
	public function get_tasks_by_parent_state_type( $parent, $state, $type ) {

		$args = array(
			'post_type'      => 'wpcd_pending_log',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'parent_post_id',
					'value' => $parent,
				),
				array(
					'key'   => 'pending_task_state',
					'value' => $state,
				),
				array(
					'key'   => 'pending_task_type',
					'value' => $type,
				),
			),
		);

		$task_posts = get_posts( $args );

		return $task_posts;

	}

	/**
	 * Update a task.  All parameters are optional except $id for the task.
	 *
	 * @param int      $id The id of the task record.
	 * @param array    $task_details An array with supporting details.
	 * @param string   $task_state The state of the task when we're adding it.  Could be 'not-ready', 'ready'.  Later other states might be 'in-process', 'completed', 'errored'.
	 * @param string   $task_reference other cross-reference data if needed.
	 * @param string   $task_comment task comment.
	 * @param bool|int $reset_start_date Date to put in the start date meta or, if set to TRUE, empty the start date meta completely.
	 * @param string   $task_message A message to add to the task record.
	 */
	public function update_task_by_id( $id, $task_details = array(), $task_state = '', $task_reference = '', $task_comment = '', $reset_start_date = false, $task_message = '' ) {

		if ( ! empty( $task_details ) ) {
			update_post_meta( $id, 'pending_task_details', $task_details );
		}

		if ( ! empty( $task_state ) ) {
			update_post_meta( $id, 'pending_task_state', $task_state );
		}

		if ( ! empty( $task_reference ) ) {
			update_post_meta( $id, 'pending_task_reference', $task_reference );
		}

		if ( ! empty( $task_comment ) ) {
			update_post_meta( $id, 'pending_task_comment', $task_comment );
		}

		// Update start date if empty.
		if ( empty( get_post_meta( $id, 'pending_task_start_date', true ) ) ) {
			update_post_meta( $id, 'pending_task_start_date', time() );
		}

		// If $task_state is "complete" then update the end date.
		if ( 'complete' === $task_state ) {
			update_post_meta( $id, 'pending_task_complete_date', time() );
		}

		// Update start date if parameter is set to do that.
		if ( ! empty( $reset_start_date ) ) {
			if ( true === $reset_start_date ) {
				// We got a boolean, this means blank out the date completely.
				delete_post_meta( $id, 'pending_task_start_date' );
			} else {
				// Assume we got a time and update the field with it.
				update_post_meta( $id, 'pending_task_start_date', $reset_start_date );
			}
		}

		// Add messages to message field.
		if ( ! empty( $task_message ) ) {
			if ( empty( get_post_meta( $id, 'pending_task_messages', true ) ) ) {
				update_post_meta( $id, 'pending_task_messages', $task_message );
			} else {
				$old_message  = get_post_meta( $id, 'pending_task_messages', true );
				$new_message .= '<br />' . $task_message;
				update_post_meta( $id, 'pending_task_messages', $new_message );
			}
		}

		// Increment the attempted count.
		update_post_meta( $id, 'pending_task_attempts', ( ( (int) get_post_meta( $id, 'pending_task_attempts', true ) ) + 1 ) );

	}

	/**
	 * Return the pending_task_details meta
	 *
	 * This is a synonym for get_pending_task_details_by_id.
	 *
	 * @param int $id id.
	 */
	public function get_data_by_id( $id ) {
		return $this->get_pending_task_details_by_id( $id );
	}

	/**
	 * Return the pending_task_details meta.
	 *
	 * @param int $id id.
	 */
	public function get_pending_task_details_by_id( $id ) {
		return get_post_meta( $id, 'pending_task_details', true );
	}

	/**
	 * This function checks to see if commands can be run
	 * on the server.
	 *
	 * It does this by checking a to see if there is a task that
	 * is 'in-process' on the server and where the start_time
	 * is less than an hour ago.
	 *
	 * Filter Hook: wpcd_is_server_available_for_commands
	 *
	 * @param boolean $is_available   Current boolean that indicates whether the server is available.
	 * @param int     $server_id      Server id to check.
	 *
	 * @return boolean
	 */
	public function wpcd_is_server_available_for_commands( $is_available, $server_id ) {

		// Only need to check if server is unavailable if $is_available is true - we're not going to override it if it's already false!.
		if ( true === $is_available ) {
			if ( ( ! empty( get_post_meta( $server_id, 'wpcd_server_wordpress-app_action', true ) ) ) || ( ! empty( get_post_meta( $server_id, 'wpcd_server_wordpress-app_action_status', true ) ) ) ) {
				return false;
			}
			if ( ( ! empty( get_post_meta( $server_id, 'wpcd_app_wordpress-app_action', true ) ) ) || ( ! empty( get_post_meta( $server_id, 'wpcd_app_wordpress-app_action_status', true ) ) ) ) {
				// We really shouldn't get here but there was a bug where we were using the incorrect metas.  This is prophylactic code just in case we missed some cleanup spots.
				return false;
			}
		}

		// Ok, so far the server is still available for commands.
		// So lets check the pending log records to make sure that nothing else is running right now.
		$args = array(
			'post_type'      => 'wpcd_pending_log',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'pending_task_associated_server_id',
					'value' => $server_id,
				),
				array(
					'key'   => 'pending_task_state',
					'value' => 'in-process',
				),
			),
		);

		$app_posts = get_posts( $args );

		// @TODO: We really need to check each record to make sure that the pending_task_start_date is less than an hour before we return false.
		if ( $app_posts ) {
			return false;
		}

		return $is_available;
	}

	/**
	 * Execute any background tasks that have not been started yet.
	 *
	 * This is generally called from a CRON process.
	 */
	public function do_tasks() {

		$args = array(
			'post_type'      => 'wpcd_pending_log',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => 'pending_task_state',
					'value' => 'ready',
				),
			),
		);

		$task_posts = get_posts( $args );

		foreach ( $task_posts as $task ) {

			// Get server id.
			$server_id        = get_post_meta( $task->ID, 'pending_task_associated_server_id', true );
			$parent_post_id   = get_post_meta( $task->ID, 'parent_post_id', true );
			$parent_post_type = get_post_meta( $task->ID, 'pending_task_parent_post_type', true );

			// Check to make sure that the server is available.
			$is_server_available = WPCD_SERVER()->is_server_available_for_commands( $server_id );

			// If server is available, see what we need to do...
			if ( true === $is_server_available ) {
				$args = get_post_meta( $task->ID, 'pending_task_details', true );
				if ( isset( $args['action_hook'] ) ) {

					// Add our task id to the args array.
					$args['pending_tasks_id'] = $task->ID;

					// Add the associated server id and the parent post type to the array - might make some things easier later.
					$args['pending_task_associated_server_id'] = $server_id;
					$args['pending_task_parent_post_type']     = $parent_post_type;

					// Mark the task as 'in process'.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task->ID, $args, 'in-process' );

					// Fire the hook - the hook needs to be responsible for marking the task as finished since we cannot receive any data directly from the action hook.
					if ( isset( $args['action_hook'] ) ) {
						do_action( $args['action_hook'], $task->ID, $parent_post_id, $args );
						break; // Only fire ONE action hook per do_tasks() call. Later we can try two or three but for now only do one at a time.
					}
				}
			} else {
				/* Translators: %s is the post id of the server we are skipping. */
				$msg = sprintf( __( 'The PENDING TASKS background process is skipping tasks on the server with ID %s because it is not available at this time - likely in use by another process.', 'wpcd' ), $server_id );
				do_action( 'wpcd_log_error', $msg, 'trace', __FILE__, __LINE__, array(), false );
				update_post_meta( $task->ID, 'pending_task_history', $msg );
			}
		}

	}

	/**
	 * Adds actions to set the state of a task.
	 *
	 * Filter hook: bulk_actions-edit-{cpt-name} | bulk_actions-edit-wpcd_pending_log
	 *
	 * @param  array $actions actions.
	 *
	 * @return array
	 */
	public function wpcd_pending_log_bulk_actions( $actions ) {
		$actions['wpcd-pending-log-reset-to-ready']      = __( 'Reset state to READY', 'wpcd' );
		$actions['wpcd-pending-log-reset-to-in-process'] = __( 'Reset state to IN-PROCESS', 'wpcd' );
		$actions['wpcd-pending-log-reset-to-complete']   = __( 'Mark as Complete', 'wpcd' );
		$actions['wpcd-pending-log-reset-to-failed']     = __( 'Mark as Failed', 'wpcd' );
		return $actions;
	}

	/**
	 * Handle bulk actions for pending logs.
	 *
	 * @param string $redirect_url  redirect url.
	 * @param string $action        bulk action slug/id - this is not the WPCD action key.
	 * @param array  $post_ids      all post ids.
	 */
	public function wpcd_bulk_action_handler_pending_log( $redirect_url, $action, $post_ids ) {
		// Let's remove query args first for redirect url.
		// $redirect_url = remove_query_arg( array( 'wpcd_soft_reboot' ), $redirect_url );

		// Lets make sure we're an admin otherwise return an error.
		if ( ! wpcd_is_admin() ) {
			do_action( 'wpcd_log_error', 'Someone attempted to run a function that required admin privileges.', 'security', __FILE__, __LINE__ );
			// @todo: show error message to user in a dialog box OR show error message at the top of the admin list as a dismissible notice.
			return $redirect_url;
		}

		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $app_id ) {
				switch ( $action ) {
					case 'wpcd-pending-log-reset-to-ready':
						$this->update_task_by_id( $app_id, array(), 'ready', '', '', true );
						break;
					case 'wpcd-pending-log-reset-to-in-process':
						$this->update_task_by_id( $app_id, array(), 'in-process', '', '', time() );
						break;
					case 'wpcd-pending-log-reset-to-complete':
						$this->update_task_by_id( $app_id, array(), 'complete-manual' );
						break;
					case 'wpcd-pending-log-reset-to-failed':
						$this->update_task_by_id( $app_id, array(), 'failed-manual' );
						break;
				}
			}
			return $redirect_url;
		}
	}


	/**
	 * Cron function code to clean up the pending logs.
	 * Anything that has been running for too long
	 * (around 2 hours) will be marked as failed.
	 */
	public function wpcd_clean_up_pending_logs_callback() {

		// The function ran so update the transient to let the monitoring process know that it ran (even if no records were processed).
		// We are using 180 minutes here instead of 15 minutes because this cron runs on a 60 minute schedule instead of a 1 min or 15 min schedule.
		set_transient( 'wpcd_clean_up_pending_logs_is_active', 1, ( 60 * 3 ) * MINUTE_IN_SECONDS );

		// Get pending logs.
		$compare_date = time() - ( 3600 * 2 );

		$pending_logs_args = array(
			'post_type'   => 'wpcd_pending_log',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'ASC',
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'     => 'pending_task_state',
					'value'   => 'not-ready',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'pending_task_state',
					'value'   => 'complete',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'pending_task_state',
					'value'   => 'complete-manual',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'pending_task_state',
					'value'   => 'ready',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'pending_task_state',
					'value'   => 'failed',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'pending_task_state',
					'value'   => 'failed-manual',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'pending_task_state',
					'value'   => 'failed-timeout',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'pending_task_start_date',
					'value'   => $compare_date,
					'compare' => '<=',
				),
			),
		);

		$logs_found       = get_posts( $pending_logs_args );
		$logs_found_count = count( $logs_found );

		$auto_trim_pending_log_limit = (int) wpcd_get_early_option( 'auto_trim_pending_log_limit' );

		if ( empty( $auto_trim_pending_log_limit ) ) {
			$auto_trim_pending_log_limit = 100;
		}

		if ( ! empty( $logs_found ) ) {
			$count = 1;
			foreach ( $logs_found as $key => $value ) {

				if ( $count <= $auto_trim_pending_log_limit && $count > 0 && $auto_trim_pending_log_limit > 0 ) {

					$log_id = $value->ID;
					update_post_meta( $log_id, 'pending_task_state', 'failed-timeout' );

					// Now, we need to make sure that any metas on the app or server record that marks it as unavailable/in-process are removed.
					$parent_post_type     = get_post_meta( $log_id, 'pending_task_parent_post_type', true );
					$parent_post_id       = get_post_meta( $log_id, 'parent_post_id', true );
					$pending_task_comment = get_post_meta( $log_id, 'pending_task_comment', true );
					if ( 'wpcd_app' === $parent_post_type ) {
						do_action( 'wpcd_wordpress-app_clear_background_processes', $parent_post_id, 'clear_background_processes_via_pending_log_action' );
					}
					if ( 'wpcd_app_server' === $parent_post_type ) {
						do_action( 'wpcd_wordpress-app_server_cleanup_metas', $parent_post_id, 'server_cleanup_metas_via_pending_log_action' );
					}

					/* Translators: %s is the ID of the pending log recording being cleaned up. */
					$message = sprintf( __( 'A pending task with id %s was marked as failed because it took too long to complete.', 'wpcd' ), (string) $log_id );
					if ( ! empty( $pending_task_comment ) ) {
						$message .= ' - ' . $pending_task_comment;
					}

					// Add a user friendly notification record.
					/* Translators: %s is pending log comment message. */
					do_action( 'wpcd_log_notification', $parent_post_id, 'alert', sprintf( __( 'Stuck pending log cleaned up successfully.(%s)', 'wpcd' ), $pending_task_comment ), 'stuck', null );

					$count++;
				}
			}
		}

		/* Clean up old log entries */
		$this->clean_up_old_log_entries( 'wpcd_command_log' );
		$this->clean_up_old_log_entries( 'wpcd_error_log' );
		$this->clean_up_old_log_entries( 'wpcd_notify_log' );
		$this->clean_up_old_log_entries( 'wpcd_notify_sent' );
		$this->clean_up_old_log_entries( 'wpcd_ssh_log' );

		$response = array(
			'message' => __( 'Pending logs cleaned up successfully.', 'wpcd' ),
		);
		wp_send_json( $response, 200 );
		exit();
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
				self::wpcd_clean_up_pending_logs_events();
				self::wpcd_send_email_alert_for_long_pending_tasks_events();
				restore_current_blog();
			}
		} else {
			self::wpcd_clean_up_pending_logs_events();
			self::wpcd_send_email_alert_for_long_pending_tasks_events();
		}

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function wpcd_clean_up_pending_logs_events() {
		// Clear old crons.
		wp_unschedule_hook( 'wpcd_clean_up_pending_logs' );

		wp_schedule_event( time(), 'hourly', 'wpcd_clean_up_pending_logs' );

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function wpcd_send_email_alert_for_long_pending_tasks_events() {
		// Clear old crons.
		wp_unschedule_hook( 'wpcd_email_alert_for_long_pending_tasks' );
		wp_schedule_event( time(), 'every_fifteen_minute', 'wpcd_email_alert_for_long_pending_tasks' );
	}

	/**
	 * Fires on deactivation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is deactivated network-wide.
	 *
	 * @return void
	 */
	public static function deactivate( $network_wide ) {

		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::wpcd_cleanup_pending_logs_clear_scheduled_events();
				self::wpcd_clear_send_email_for_long_pending_task_events();
				restore_current_blog();
			}
		} else {
			self::wpcd_cleanup_pending_logs_clear_scheduled_events();
			self::wpcd_clear_send_email_for_long_pending_task_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function wpcd_clear_send_email_for_long_pending_task_events() {
		wp_unschedule_hook( 'wpcd_email_alert_for_long_pending_tasks' );
	}


	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function wpcd_cleanup_pending_logs_clear_scheduled_events() {
		wp_unschedule_hook( 'wpcd_clean_up_pending_logs' );
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
	public function wpcd_clean_up_pending_logs_for_new_site( $new_site, $args ) {

		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {

			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::wpcd_clean_up_pending_logs_events();
			self::wpcd_send_email_alert_for_long_pending_tasks_events();
			restore_current_blog();
		}

	}

	/**
	 * Delete the pending logs on site deletion.
	 *
	 * @param int $post_id site post id.
	 */
	public function wpcd_wpapp_pending_log_post_delete( $post_id ) {

		$state_meta = array(
			'relation' => 'OR',
			array(
				'key'   => 'pending_task_state',
				'value' => 'ready',
			),
			array(
				'key'   => 'pending_task_state',
				'value' => 'in-process',
			),
			array(
				'key'   => 'pending_task_state',
				'value' => 'not-ready',
			),
		);

		// Delete the pending logs where the OWNER/PARENT is the same as site or server id.
		if ( ( get_post_type( $post_id ) === 'wpcd_app' || get_post_type( $post_id ) === 'wpcd_app_server' ) && wpcd_is_admin() ) {
			$log_args = array(
				'post_type'      => 'wpcd_pending_log',
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids', // Only get post IDs.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => 'parent_post_id',
						'value' => $post_id, // Server OR Site.
					),
					$state_meta,
				),
			);

			$log_posts = get_posts( $log_args );

			if ( ! empty( $log_posts ) ) {
				foreach ( $log_posts as $key => $value ) {
					wp_delete_post( $value );
				}
			}
		}

		// Delete the pending logs where the ASSOCIATED SERVER ID is the same as site id.
		if ( get_post_type( $post_id ) === 'wpcd_app_server' ) {
			$pending_log_args = array(
				'post_type'      => 'wpcd_pending_log',
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'fields'         => 'ids', // Only get post IDs.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => 'pending_task_associated_server_id',
						'value' => $post_id,
					),
					$state_meta,
				),
			);

			$pending_log_posts = get_posts( $pending_log_args );

			if ( ! empty( $pending_log_posts ) ) {
				foreach ( $pending_log_posts as $key => $value ) {
					wp_delete_post( $value );
				}
			}
		}

	}

	/**
	 * Scheduled notification cron for "in process" tasks which is running more than 15 minutes.
	 *
	 * @return void
	 */
	public function wpcd_send_email_alert_for_long_pending_tasks() {

		// Tasks should be running of more than 15 minutes.
		set_transient( 'wpcd_send_email_alert_for_long_pending_tasks_is_active', 1, ( 15 * MINUTE_IN_SECONDS ) );

		$compare_time = time() - ( 15 * MINUTE_IN_SECONDS );

		$pending_task_args = array(
			'post_type'   => 'wpcd_pending_log',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'fields'      => 'ids',
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'     => 'pending_task_state',
					'value'   => 'in-process',
					'compare' => '=',
				),
				array(
					'key'     => 'pending_task_start_date',
					'value'   => $compare_time,
					'compare' => '<=',
				),
			),
		);

		$pending_task_found = get_posts( $pending_task_args );

		if ( ! empty( $pending_task_found ) ) {

			foreach ( $pending_task_found as $task_details ) {

				$pending_task_type                 = get_post_meta( $task_details, 'pending_task_type', true );
				$pending_task_key                  = get_post_meta( $task_details, 'pending_task_key', true );
				$pending_task_state                = get_post_meta( $task_details, 'pending_task_state', true );
				$pending_task_attempts             = get_post_meta( $task_details, 'pending_task_attempts', true );
				$pending_task_reference            = get_post_meta( $task_details, 'pending_task_reference', true );
				$pending_task_comment              = get_post_meta( $task_details, 'pending_task_comment', true );
				$pending_task_start_date           = get_post_meta( $task_details, 'pending_task_start_date', true );
				$parent_post_id                    = get_post_meta( $task_details, 'parent_post_id', true );
				$pending_task_parent_post_type     = get_post_meta( $task_details, 'pending_task_parent_post_type', true );
				$pending_task_associated_server_id = get_post_meta( $task_details, 'pending_task_associated_server_id', true );

				$email_body  = wp_sprintf( '%s: %s', __( 'Pending Task Type', 'wpcd' ), $pending_task_type ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'Key', 'wpcd' ), $pending_task_key ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'State', 'wpcd' ), $pending_task_state ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'Attempts To Complete', 'wpcd' ), $pending_task_attempts ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'Reference', 'wpcd' ), $pending_task_reference ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'Comment', 'wpcd' ), $pending_task_comment ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'Date Started', 'wpcd' ), gmdate( 'Y-m-d @ H:i', $pending_task_start_date ) ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'Log Owner or Parent ID', 'wpcd' ), $parent_post_id ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'Parent Post Type', 'wpcd' ), $pending_task_parent_post_type ) . '<br /><br />';
				$email_body .= wp_sprintf( '%s: %s', __( 'Associated Server ID', 'wpcd' ), $pending_task_associated_server_id );

				wp_mail(
					get_option( 'admin_email' ),
					__( 'Long Running Pending Tasks', 'wpcd' ),
					$email_body,
					array( 'Content-Type: text/html; charset=UTF-8' )
				);
			}
		}
	}
}
