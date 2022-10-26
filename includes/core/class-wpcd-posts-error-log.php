<?php
/**
 * This class is used for error log.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_ERROR_LOG
 */
class WPCD_ERROR_LOG extends WPCD_POSTS_LOG {

	/**
	 * WPCD_ERROR_LOG instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_ERROR_LOG constructor.
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
		add_filter( 'manage_wpcd_error_log_posts_columns', array( $this, 'error_log_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_error_log_posts_custom_column', array( $this, 'error_log_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_error_log_sortable_columns', array( $this, 'error_log_table_sorting' ), 10, 1 );

		// Filter hook to remove edit bulk action.
		add_filter( 'bulk_actions-edit-wpcd_error_log', array( $this, 'wpcd_log_bulk_actions' ), 10, 1 );
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {
		register_post_type(
			'wpcd_error_log',
			array(
				'labels'              => array(
					'name'                  => _x( 'Error Logs', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Error Log', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Error Log', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Error Log', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit Error Log', 'wpcd' ),
					'view_item'             => __( 'View Error Log', 'wpcd' ),
					'all_items'             => __( 'Error Logs', 'wpcd' ),
					'search_items'          => __( 'Search Error Logs', 'wpcd' ),
					'not_found'             => __( 'No Error Logs were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Error Logs were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Error Log list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Error Logs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Error Logs list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wpcd_app_server',
				'menu_position'       => null,
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

		$this->set_post_type( 'wpcd_error_log' );

		$search_fields = array(
			'error_type',
			'error_msg',
			'error_file',
			'error_line',
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
	public function error_log_table_sorting( $columns ) {

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
	public function error_log_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_type':
				/* Display the error type*/
				$value = get_post_meta( $post_id, 'error_type', true );

				break;

			case 'wpcd_file':
				// Display the error file.
				$value = get_post_meta( $post_id, 'error_file', true );
				break;

			case 'wpcd_line':
				// Display the error line #.
				$value = get_post_meta( $post_id, 'error_line', true );
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
	public function error_log_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_type'] = __( 'Type', 'wpcd' );
		$defaults['wpcd_file'] = __( 'File', 'wpcd' );
		$defaults['wpcd_line'] = __( 'Line', 'wpcd' );
		$defaults['date']      = __( 'Date', 'wpcd' );

		return $defaults;

	}

	/**
	 * Register meta box(es).
	 */
	public function add_meta_boxes() {

		add_meta_box(
			'error_log',
			__( 'Error Log Data', 'wpcd' ),
			array( $this, 'render_error_log_meta_box' ),
			'wpcd_error_log',
			'advanced',
			'high'
		);

	}

	/**
	 * Render the Error LOG detail meta box
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_error_log_meta_box( $post ) {

		$html = '';

		$error_type = get_post_meta( $post->ID, 'error_type', true );
		$error_msg  = get_post_meta( $post->ID, 'error_msg', true );
		$error_file = get_post_meta( $post->ID, 'error_file', true );
		$error_line = get_post_meta( $post->ID, 'error_line', true );
		$error_data = get_post_meta( $post->ID, 'error_data', true );

		ob_start();
		require wpcd_path . 'includes/templates/error_log.php';
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
	 * Add a new error log record
	 *
	 * @param string $msg message to write.
	 * @param string $type type of message.
	 * @param string $file filename generating message.
	 * @param string $line linenumber related to the message being written.
	 * @param string $data optional data that will be logged to a separate field when writing to the database.
	 */
	public function add_error_log_entry( $msg, $type, $file, $line, $data ) {

		$ok_to_log = true;

		// Check for excluded text.
		if ( $ok_to_log ) {
			$exclude_msg_txt = wpcd_get_early_option( 'exclude_msg_txt' );
			foreach ( $exclude_msg_txt as $exclude ) {
				if ( ! empty( $exclude ) && isset( $exclude['exclude_msg'] ) && ( ! empty( $exclude['exclude_msg'] ) ) ) {
					if ( strpos( $msg, $exclude['exclude_msg'] ) !== false ) {
						$ok_to_log = false;
						break;
					}
				}
			}
		}

		// Check to see if the log entry meets the include message criteria.
		if ( $ok_to_log ) {
			$include_msg_txt = wpcd_get_early_option( 'include_msg_txt' );
			if ( ! empty( $include_msg_txt ) && isset( $include_msg_txt[0] ) && ( ! empty( $include_msg_txt[0]['include_msg'] ) ) ) {
				$ok_to_include = false;
				foreach ( $include_msg_txt as $include ) {
					if ( ! empty( $include ) && isset( $include['include_msg'] ) && ( ! empty( $include['include_msg'] ) ) ) {
						if ( strpos( $msg, $include['include_msg'] ) !== false ) {
							$ok_to_include = true;
							break;
						}
					}
				}

				/* At this point, the include criteria isn't met so no logging is to done */
				if ( ! $ok_to_include ) {
					$ok_to_log = false;
				}
			}
		}

		// Check to see if the log entry meets the include file criteria.
		if ( $ok_to_log ) {
			$include_file_txt = wpcd_get_early_option( 'include_file_txt' );
			if ( ! empty( $include_file_txt ) && isset( $include_file_txt[0] ) && ( ! empty( $include_file_txt[0]['include_files'] ) ) ) {
				$ok_to_include = false;
				foreach ( $include_file_txt as $include ) {
					if ( ! empty( $include ) && isset( $include['include_files'] ) && ( ! empty( $include['include_files'] ) ) ) {
						if ( strpos( $file, $include['include_files'] ) !== false ) {
							$ok_to_include = true;
							break;
						}
					}
				}

				/* At this point, the include criteria isn't met so no logging is to done */
				if ( ! $ok_to_include ) {
					$ok_to_log = false;
				}
			}
		}

		/* All checks done - maybe we add a log entry */
		$post_id = false;
		if ( $ok_to_log ) {
			// Author is current user or system.
			$author_id = get_current_user();

			// Remove known password strings.
			$pwarray = $this->wpcd_get_pw_terms_to_clean();
			$msg     = wpcd_replace_key_value_paired_strings( $pwarray, $msg );

			// Add post.
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'wpcd_error_log',
					'post_status' => 'private',
					'post_title'  => substr( $msg, 0, 100 ),
					'post_author' => $author_id,
				)
			);

			if ( ! is_wp_error( $post_id ) && ! empty( $post_id ) ) {
				update_post_meta( $post_id, 'error_type', $type );
				update_post_meta( $post_id, 'error_msg', $msg );
				update_post_meta( $post_id, 'error_file', $file );
				update_post_meta( $post_id, 'error_line', $line );
				if ( is_array( $data ) ) {
					update_post_meta( $post_id, 'error_data', print_r( $data, true ) );
				} elseif ( is_object( $data ) ) {
					update_post_meta( $post_id, 'error_data', print_r( $data, true ) );
				} else {
					update_post_meta( $post_id, 'error_data', $data );
				}
			}
		}
		/* End add log entry */

		/* Finally, we return to the calling program the post id of the inserted record or false if none */
		return $post_id;

	}

}
