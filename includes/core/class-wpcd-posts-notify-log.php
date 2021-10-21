<?php
/**
 * This class is used for notification log.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_NOTIFY_LOG
 */
class WPCD_NOTIFY_LOG extends WPCD_POSTS_LOG {

	/**
	 * WPCD_NOTIFY_LOG instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_NOTIFY_LOG constructor.
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
		add_filter( 'manage_wpcd_notify_log_posts_columns', array( $this, 'notify_log_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_notify_log_posts_custom_column', array( $this, 'notify_log_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_notify_log_sortable_columns', array( $this, 'notify_log_table_sorting' ), 10, 1 );

		// Action hook to extend admin filter options.
		add_action( 'restrict_manage_posts', array( $this, 'wpcd_notify_log_table_filtering' ) );

		// Filter hook to filter notification listing on custom meta data.
		add_filter( 'parse_query', array( $this, 'wpcd_notification_list_parse_query' ), 10, 1 );

		// Filter hook to remove edit bulk action.
		add_filter( 'bulk_actions-edit-wpcd_notify_log', array( $this, 'wpcd_log_bulk_actions' ), 10, 1 );
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {
		register_post_type(
			'wpcd_notify_log',
			array(
				'labels'              => array(
					'name'                  => _x( 'Notification Logs', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Notification Log', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Notification Log', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Notification Log', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit Notification Log', 'wpcd' ),
					'view_item'             => __( 'View Notification Log', 'wpcd' ),
					'all_items'             => __( 'Notifications', 'wpcd' ), // Label to signify all items in a submenu link.
					'search_items'          => __( 'Search Notification Log', 'wpcd' ),
					'not_found'             => __( 'No Notification Logs were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Notification Logs were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Notification Log list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Notification Log list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Notification Log list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wpcd_notify_log',
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
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

		$this->set_post_type( 'wpcd_notify_log' );

		$search_fields = array(
			'parent_post_id',
			'notification_type',
			'notification_message',
			'notification_reference',
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
	public function notify_log_table_sorting( $columns ) {
		$columns['wpcd_notification_parent_id'] = 'wpcd_notification_parent_id';
		$columns['wpcd_notification_type']      = 'wpcd_notification_type';
		$columns['wpcd_notification_reference'] = 'wpcd_notification_reference';
		$columns['wpcd_notification_count']     = 'wpcd_notification_count';
		$columns['wpcd_notification_sent']      = 'wpcd_notification_sent';
		return $columns;
	}

	/**
	 * Add filters on the notification listing screen at the backend
	 *
	 * Action hook: restrict_manage_posts
	 *
	 * @return void
	 */
	public function wpcd_notify_log_table_filtering() {

		global $typenow, $pagenow;

		$post_type = 'wpcd_notify_log';

		if ( is_admin() && 'edit.php' === $pagenow && $typenow === $post_type ) {

			// Notification Type.
			$notify_type = $this->generate_notify_meta_dropdown( $post_type, 'notification_type', __( 'All Types', 'wpcd' ) );
			echo $notify_type;

			// Notification Reference.
			$notify_reference = $this->generate_notify_meta_dropdown( $post_type, 'notification_reference', __( 'All References', 'wpcd' ) );
			echo $notify_reference;

		}
	}

	/**
	 * To add custom filtering options based on meta fields.
	 * This filter will be added on notification listing screen at the backend
	 *
	 * @param  string $post_type post type.
	 * @param  string $field_key field key.
	 * @param  string $first_option first option.
	 *
	 * @return string
	 */
	public function generate_notify_meta_dropdown( $post_type, $field_key, $first_option ) {

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
	 * To modify default query parameters and to show notification listing based on custom filters
	 *
	 * Action filter: parse_query
	 *
	 * @param  object $query query.
	 */
	public function wpcd_notification_list_parse_query( $query ) {
		global $pagenow;

		$filter_action = filter_input( INPUT_GET, 'filter_action', FILTER_SANITIZE_STRING );
		if ( is_admin() && $query->is_main_query() && 'wpcd_notify_log' === $query->query['post_type'] && 'edit.php' === $pagenow && 'Filter' === $filter_action ) {
			$qv = &$query->query_vars;

			// NOTIFICATION TYPE.
			if ( isset( $_GET['notification_type'] ) && ! empty( $_GET['notification_type'] ) ) {
				$notification_type = filter_input( INPUT_GET, 'notification_type', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'notification_type',
					'value'   => $notification_type,
					'compare' => '=',
				);
			}

			// NOTIFICATION REFERENCE.
			if ( isset( $_GET['notification_reference'] ) && ! empty( $_GET['notification_reference'] ) ) {
				$notification_reference = filter_input( INPUT_GET, 'notification_reference', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'notification_reference',
					'value'   => $notification_reference,
					'compare' => '=',
				);
			}
		}
	}

	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name string column name.
	 * @param post   $post_id int post id.
	 *
	 *   print column value.
	 */
	public function notify_log_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_notification_parent_id':
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

			case 'wpcd_notification_type':
				// Display the command type.
				$value = wp_kses_post( get_post_meta( $post_id, 'notification_type', true ) );

				break;

			case 'wpcd_notification_message':
				// Display a portion of the command result.
				$string_length        = 100;
				$notification_message = wp_kses_post( get_post_meta( $post_id, 'notification_message', true ) );
				if ( strlen( $notification_message ) > $string_length ) {
					$notification_message = substr( $notification_message, $string_length * -1 ) . ' ...more...';
				}
				$value = $notification_message;
				break;

			case 'wpcd_notification_count':
				// Display the number of alerts in a 2 min period.
				$value = wp_kses_post( get_post_meta( $post_id, 'notification_count', true ) );
				break;

			case 'wpcd_notification_sent':
				// Display whether or not the notification has been processed for sending alerts.
				$value = wp_kses_post( get_post_meta( $post_id, 'notification_sent', true ) );
				break;

			case 'wpcd_notification_reference':
				// Display a notification reference if any.
				$value = wp_kses_post( get_post_meta( $post_id, 'notification_reference', true ) );

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
	public function notify_log_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_notification_parent_id'] = __( 'Owner/Parent', 'wpcd' );
		$defaults['wpcd_notification_type']      = __( 'Type', 'wpcd' );
		$defaults['wpcd_notification_message']   = __( 'Message', 'wpcd' );
		$defaults['wpcd_notification_reference'] = __( 'Reference', 'wpcd' );
		$defaults['wpcd_notification_count']     = __( 'Count', 'wpcd' );
		$defaults['wpcd_notification_sent']      = __( 'Sent', 'wpcd' );
		$defaults['date']                        = __( 'Date', 'wpcd' );

		return $defaults;

	}

	/**
	 * Register meta box(es).
	 */
	public function add_meta_boxes() {

		add_meta_box(
			'notify_log',
			__( 'Notification Log', 'wpcd' ),
			array( $this, 'render_notify_log_meta_box' ),
			'wpcd_notify_log',
			'advanced',
			'high'
		);

	}

	/**
	 * Render the Command LOG detail meta box
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_notify_log_meta_box( $post ) {

		$html = '';

		$notification_type      = get_post_meta( $post->ID, 'notification_type', true );
		$notification_message   = get_post_meta( $post->ID, 'notification_message', true );
		$notification_reference = get_post_meta( $post->ID, 'notification_reference', true );
		$parent_post_id         = get_post_meta( $post->ID, 'parent_post_id', true );

		ob_start();
		require wpcd_path . 'includes/templates/notify_log.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

	}

	/**
	 * Handles saving the meta box.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post    Post object.
	 */
	public function save_meta_values( $post_id, $post ) {
		// nothing right now.
	}

	/**
	 * Add a new Notification Log record
	 *
	 * @param int    $parent_post_id The post id that represents the item this log is being done against.
	 * @param string $notification_type The type of notification.
	 * @param string $message The notification message itself.
	 * @param string $notification_reference any additional or third party reference.
	 * @param int    $post_id The ID of an existing log, if it exists.
	 */
	public function add_notify_log_entry( $parent_post_id, $notification_type = 'notice', $message, $notification_reference = '', $post_id = null ) {

		// Author is current user or system.
		$author_id = get_current_user();

		// Construct transient string to check to see if it's set.
		$transient_key = '';
		if ( ! empty( $parent_post_id ) ) {
			$transient_key = (string) $parent_post_id . $notification_type . $message;
			if ( get_transient( $transient_key ) ) {
				$post_id = get_transient( $transient_key );
				// transient exists so just increase the notification count and move on...
				update_post_meta( $post_id, 'notification_count', ( (int) get_post_meta( $post_id, 'notification_count', true ) ) + 1 );
				return $post_id;  // should be null most of the time.
			}
		}

		// Get parent post.
		$post = get_post( $parent_post_id );

		if ( empty( $post_id ) ) {
			if ( $post ) {
				$post_title = $post->post_title;
			} else {
				$post_title = $message;
			}
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'wpcd_notify_log',
					'post_status' => 'private',
					'post_title'  => $post_title,
					'post_author' => $author_id,
				)
			);
		}

		// if $message is an error, convert to string...
		if ( is_wp_error( $message ) ) {
			$message = print_r( $message, true );
		}

		if ( ! is_wp_error( $post_id ) && ! empty( $post_id ) ) {
			update_post_meta( $post_id, 'parent_post_id', $parent_post_id );    // using the parent post id to link back to the master record.  Sometimes that will be a server record.  Other types it will be an APP record.
			update_post_meta( $post_id, 'notification_type', $notification_type );
			update_post_meta( $post_id, 'notification_message', $message );
			update_post_meta( $post_id, 'notification_reference', $notification_reference );
			update_post_meta( $post_id, 'notification_count', 1 );
			update_post_meta( $post_id, 'notification_sent', 0 );

			// Add a transient which we'll check later to prevent adding duplicate notifications in a short period of time.
			if ( ! empty( $transient_key ) ) {
				set_transient( $transient_key, $post_id, 120 );
			}
		}

		// @TODO: This should not be called here every single time the logs are updated. This should have a cron job or something else.
		/* Clean up old log entries */
		$this->clean_up_old_log_entries( 'wpcd_notify_log' );

		return $post_id;

	}

}
