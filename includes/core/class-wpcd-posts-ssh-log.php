<?php
/**
 * WPCD_SSH_LOG class for ssh log.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_SSH_LOG
 */
class WPCD_SSH_LOG extends WPCD_POSTS_LOG {

	/**
	 * WPCD_SSH_LOG instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_SSH_LOG constructor.
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
		add_filter( 'manage_wpcd_ssh_log_posts_columns', array( $this, 'ssh_log_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_ssh_log_posts_custom_column', array( $this, 'ssh_log_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_ssh_log_sortable_columns', array( $this, 'ssh_log_table_sorting' ), 10, 1 );

		// Filter hook to remove edit bulk action.
		add_filter( 'bulk_actions-edit-wpcd_ssh_log', array( $this, 'wpcd_log_bulk_actions' ), 10, 1 );
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {
		register_post_type(
			'wpcd_ssh_log',
			array(
				'labels'              => array(
					'name'                  => _x( 'SSH Logs', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'SSH Log', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'SSH Log', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'SSH Log', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit SSH Log', 'wpcd' ),
					'view_item'             => __( 'View SSH Log', 'wpcd' ),
					'all_items'             => __( 'SSH Logs', 'wpcd' ), // Label to signify all items in a submenu link.
					'search_items'          => __( 'Search SSH Logs', 'wpcd' ),
					'not_found'             => __( 'No SSH Logs were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No SSH Logs were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter SSH Logs list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'SSH Logs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'SSH Logs list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
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

		$this->set_post_type( 'wpcd_ssh_log' );

		$search_fields = array(
			'parent_post_id',
			'ssh_cmd',
			'ssh_cmd_result',
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
	public function ssh_log_table_sorting( $columns ) {

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
	public function ssh_log_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_server':
				// Display the name of the server.
				$server_post_id = get_post_meta( $post_id, 'parent_post_id', true );
				$server_post    = get_post( $server_post_id );
				if ( $server_post ) {
					$server_title = wp_kses_post( $server_post->post_title );
					$value        = sprintf( '<a href="%s">' . $server_title . '</a>', get_edit_post_link( $server_post_id ) );
					$value        = $value . '<br />' . 'id: ' . (string) $server_post_id;
				} else {
					$value = __( 'missing server record - server deleted?', 'wpcd' );
				}

				break;

			case 'wpcd_ssh_cmd':
				// Display the ssh command that was executed.
				$string_length = 100;
				$ssh_cmd       = wp_kses_post( get_post_meta( $post_id, 'ssh_cmd', true ) );
				if ( strlen( $ssh_cmd ) > $string_length ) {
					$ssh_cmd = substr( $ssh_cmd, 0, $string_length ) . ' ...more...';
				}

				$value = $ssh_cmd;
				break;

			case 'wpcd_ssh_result':
				// Display a portion of the ssh command result.
				$string_length  = 100;
				$ssh_cmd_result = wp_kses_post( get_post_meta( $post_id, 'ssh_cmd_result', true ) );
				if ( strlen( $ssh_cmd_result ) > $string_length ) {
					$ssh_cmd_result = substr( $ssh_cmd_result, $string_length * -1 ) . ' ...more...';
				}

				$value = $ssh_cmd_result;
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
	public function ssh_log_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_server']     = __( 'Server', 'wpcd' );
		$defaults['wpcd_ssh_cmd']    = __( 'SSH CMD', 'wpcd' );
		$defaults['wpcd_ssh_result'] = __( 'SSH Result', 'wpcd' );
		$defaults['date']            = __( 'Date', 'wpcd' );

		return $defaults;

	}

	/**
	 * Register meta box(es).
	 */
	public function add_meta_boxes() {

		add_meta_box(
			'ssh_log',
			__( 'SSH Log', 'wpcd' ),
			array( $this, 'render_ssh_log_meta_box' ),
			'wpcd_ssh_log',
			'advanced',
			'high'
		);

	}

	/**
	 * Render the SSH LOG detail meta box
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_ssh_log_meta_box( $post ) {

		$html = '';

		$ssh_cmd        = get_post_meta( $post->ID, 'ssh_cmd', true );
		$parent_post_id = get_post_meta( $post->ID, 'parent_post_id', true );
		$ssh_cmd_result = get_post_meta( $post->ID, 'ssh_cmd_result', true );

		ob_start();
		require wpcd_path . 'includes/templates/ssh_log.php';
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
	 * Add a new app record
	 *
	 * @param int    $parent_post_id The post id that represents the item this log is being done against.
	 * @param string $cmd The command that was executed.
	 * @param string $result The result of that command.
	 */
	public function add_ssh_log_entry( $parent_post_id, $cmd, $result ) {

		// Author is current user or system.
		$author_id = get_current_user();

		// Get parent post.
		$post = get_post( $parent_post_id );

		// if $result is an error, convert to string...
		if ( is_wp_error( $result ) ) {
			$result = print_r( $result, true );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'wpcd_ssh_log',
				'post_status' => 'private',
				'post_title'  => 'Command executed against: ' . $post->post_title,
				'post_author' => $author_id,
			)
		);

		// Remove some strings from the result that could be confusing to the reader.
		$result = $this->remove_common_strings( $result );

		// Remove known password strings.
		$pwarray = $this->wpcd_get_pw_terms_to_clean();
		$cmd     = wpcd_replace_key_value_paired_strings( $pwarray, $cmd );
		$result  = wpcd_replace_key_value_paired_strings( $pwarray, $result );

		if ( ! is_wp_error( $post_id ) && ! empty( $post_id ) ) {
			update_post_meta( $post_id, 'parent_post_id', $parent_post_id );    // using the parent post id to link back to the master record.
			update_post_meta( $post_id, 'ssh_cmd', $cmd );
			update_post_meta( $post_id, 'ssh_cmd_result', $result );
		}

		/* Clean up old log entries */
		$this->clean_up_old_log_entries( 'wpcd_ssh_log' );

		return $post_id;

	}

}
