<?php
/**
 * This class is used for command log.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_COMMAND_LOG
 */
class WPCD_COMMAND_LOG extends WPCD_POSTS_LOG {

	/**
	 * WPCD_COMMAND_LOG instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_COMMAND_LOG constructor.
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
		add_filter( 'manage_wpcd_command_log_posts_columns', array( $this, 'command_log_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_command_log_posts_custom_column', array( $this, 'command_log_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_command_log_sortable_columns', array( $this, 'command_log_table_sorting' ), 10, 1 );

		// Filter hook to remove edit bulk action.
		add_filter( 'bulk_actions-edit-wpcd_command_log', array( $this, 'wpcd_log_bulk_actions' ), 10, 1 );
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {
		register_post_type(
			'wpcd_command_log',
			array(
				'labels'              => array(
					'name'                  => _x( 'Command Logs', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Command Log', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Command Log', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Command Log', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit Command Log', 'wpcd' ),
					'view_item'             => __( 'View Command Log', 'wpcd' ),
					'all_items'             => __( 'Command Logs', 'wpcd' ),
					'search_items'          => __( 'Search Command Log', 'wpcd' ),
					'not_found'             => __( 'No Command Logs were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Command Logs were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Command Log list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Command Log list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Command Log list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wpcd_app_server',
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

		$this->set_post_type( 'wpcd_command_log' );

		$search_fields = array(
			'parent_post_id',
			'command_type',
			'command_result',
			'command_reference',
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
	public function command_log_table_sorting( $columns ) {

		return $columns;
	}

	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name string column name.
	 * @param int    $post_id int post id.
	 *
	 * print column value.
	 */
	public function command_log_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_command_parent_id':
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

			case 'wpcd_command_type':
				// Display the command type.
				$value = wp_kses_post( get_post_meta( $post_id, 'command_type', true ) );

				break;

			case 'wpcd_command_result':
				// Display a portion of the command result.
				$string_length  = 100;
				$command_result = wp_kses_post( get_post_meta( $post_id, 'command_result', true ) );
				if ( strlen( $command_result ) > $string_length ) {
					$command_result = substr( $command_result, $string_length * -1 ) . ' ...more...';
				}

				$value = $command_result;
				break;

			case 'wpcd_command_reference':
				// Display the command type.
				$value = wp_kses_post( get_post_meta( $post_id, 'command_reference', true ) );

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
	public function command_log_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_command_parent_id'] = __( 'Owner/Parent', 'wpcd' );
		$defaults['wpcd_command_type']      = __( 'Type', 'wpcd' );
		$defaults['wpcd_command_result']    = __( 'Result', 'wpcd' );
		$defaults['wpcd_command_reference'] = __( 'Reference', 'wpcd' );
		$defaults['date']                   = __( 'Date', 'wpcd' );

		return $defaults;

	}

	/**
	 * Register meta box(es).
	 */
	public function add_meta_boxes() {

		add_meta_box(
			'command_log',
			__( 'Command Log', 'wpcd' ),
			array( $this, 'render_command_log_meta_box' ),
			'wpcd_command_log',
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
	public function render_command_log_meta_box( $post ) {

		$html = '';

		$command_type      = get_post_meta( $post->ID, 'command_type', true );
		$command_result    = get_post_meta( $post->ID, 'command_result', true );
		$command_reference = get_post_meta( $post->ID, 'command_reference', true );
		$parent_post_id    = get_post_meta( $post->ID, 'parent_post_id', true );

		ob_start();
		require wpcd_path . 'includes/templates/command_log.php';
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
	 * Add a new Command Log record
	 *
	 * @param int    $parent_post_id The post id that represents the item this log is being done against.
	 * @param string $cmd_type The command that was executed.
	 * @param string $result The result of that command.
	 * @param string $cmd_reference ???.
	 * @param int    $post_id The ID of an existing log, if it exists.
	 */
	public function add_command_log_entry( $parent_post_id, $cmd_type, $result, $cmd_reference = '', $post_id = null ) {

		// Author is current user or system.
		$author_id = get_current_user();

		// Get parent post.
		$post = get_post( $parent_post_id );

		if ( empty( $post_id ) ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'wpcd_command_log',
					'post_status' => 'private',
					'post_title'  => 'Command executed against: ' . $post->post_title,
					'post_author' => $author_id,
				)
			);
		}

		// if $result is an error, convert to string...
		if ( is_wp_error( $result ) ) {
			$result = print_r( $result, true );
		}

		// Remove some strings from the result that could be confusing to the reader.
		$result = $this->remove_common_strings( $result );

		// Remove known password strings.
		$pwarray = $this->wpcd_get_pw_terms_to_clean();
		$result  = wpcd_replace_key_value_paired_strings( $pwarray, $result );

		if ( ! is_wp_error( $post_id ) && ! empty( $post_id ) ) {
			update_post_meta( $post_id, 'parent_post_id', $parent_post_id );    // using the parent post id to link back to the master record.  Sometimes that will be a server record.  Other types it will be an APP record.
			update_post_meta( $post_id, 'command_type', $cmd_type );
			update_post_meta( $post_id, 'command_result', $result );
			update_post_meta( $post_id, 'command_reference', $cmd_reference );
		}

		return $post_id;

	}

	/**
	 * Returns the command logs for the given command ID.
	 *
	 * @param int $id id.
	 */
	public static function get_command_log( $id ) {
		return get_post_meta( $id, 'command_result', true );
	}

	/**
	 * Returns the log ID for the given app post ID and command name.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 */
	public static function get_command_log_id( $id, $name ) {
		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_command_log',
				'post_status' => 'private',
				'numberposts' => 10,
				'meta_query'  => array(
					array(
						'key'   => 'parent_post_id',
						'value' => $id,
					),
					array(
						'key'   => 'command_type',
						'value' => $name,
					),
				),
				'fields'      => 'ids',
			)
		);

		if ( $posts && count( $posts ) !== 1 ) {
			// a command has more than 1 instances? Impossible. Or a scenario missed?.
			return new \WP_Error( "Command $name for $id has " . count( $posts ) . ' instances' );
		}

		return $posts[0];
	}

}
