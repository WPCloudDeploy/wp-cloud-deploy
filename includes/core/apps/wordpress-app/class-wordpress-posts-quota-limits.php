<?php
/**
 * This class handles declaration of the the post types needed for Quota Limits.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_POSTS_Quota_Limits
 */
class WPCD_POSTS_Quota_Limits extends WPCD_Posts_Base {

	/**
	 * WPCD_POSTS_Quota_Limits instance.
	 *
	 * @since 5.7
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_POSTS_Quota_Limits constructor.
	 *
	 * @since 5.7
	 */
	public function __construct() {

		$this->register();  // register the custom post type.
		$this->hooks();     // register hooks to make the custom post type do things.
	}

	/**
	 * WPCD_POSTS_Quota_Limits hooks.
	 */
	private function hooks() {

		// Register custom fields for our post types.
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_post_type_fields' ), 20, 1 );

		// Change ADD TITLE placeholder text.
		add_filter( 'enter_title_here', array( $this, 'change_enter_title_text' ) );

		// Filter hook to add new columns.
		add_filter( 'manage_wpcd_quota_limits_posts_columns', array( $this, 'wpcd_quota_limits_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_quota_limits_posts_custom_column', array( $this, 'wpcd_quota_limits_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_quota_limits_sortable_columns', array( $this, 'wpcd_quota_limits_table_sorting' ), 10, 1 );

		// Remove limit records when a site is being deleted.
		add_action( 'wpcd_wordpress-app_after_remove_site_action_before_record_delete', array( $this, 'wpcd_after_remove_site_action_before_record_delete' ), 10, 2 );

		// Trigger evalation of a site's limit via an action hook. Hook Name: wpcd_wordpress-app_evaluate_quota_limits_for_site.
		add_action( 'wpcd_{WPCD_WORDPRESS_APP()->get_app_name()}_evaluate_quota_limits_for_site', array( $this, 'evaluate_quota_limits_for_site' ), 10, 2 );

		/* Pending Logs Background Task: Trigger evaluation of limits for a site. */
		add_action( 'wpcd_pending_log_evaluate_quota_limits', array( $this, 'pending_log_evaluate_quota_limits' ), 10, 3 );

	}


	/**
	 * Registers the custom post type and taxonomies (if any )
	 *
	 * @since 5.7
	 */
	public function register() {

		$menu_name = __( 'Quota Limits', 'wpcd' );
		$menu_icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg fill="black" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="20px" height="20px"><path fill="black" d="M 20 9 C 18.355469 9 17 10.355469 17 12 L 17 68 C 17 69.644531 18.355469 71 20 71 L 60 71 C 61.644531 71 63 69.644531 63 68 L 63 12 C 63 10.355469 61.644531 9 60 9 Z M 20 11 L 60 11 C 60.566406 11 61 11.433594 61 12 L 61 68 C 61 68.566406 60.566406 69 60 69 L 20 69 C 19.433594 69 19 68.566406 19 68 L 19 12 C 19 11.433594 19.433594 11 20 11 Z M 24 16 L 24 42 L 56 42 L 56 16 Z M 26 18 L 54 18 L 54 24 L 26 24 Z M 50 20 C 49.449219 20 49 20.449219 49 21 C 49 21.550781 49.449219 22 50 22 C 50.550781 22 51 21.550781 51 21 C 51 20.449219 50.550781 20 50 20 Z M 26 26 L 54 26 L 54 32 L 26 32 Z M 50 28 C 49.449219 28 49 28.449219 49 29 C 49 29.550781 49.449219 30 50 30 C 50.550781 30 51 29.550781 51 29 C 51 28.449219 50.550781 28 50 28 Z M 26 34 L 54 34 L 54 40 L 26 40 Z M 50 36 C 49.449219 36 49 36.449219 49 37 C 49 37.550781 49.449219 38 50 38 C 50.550781 38 51 37.550781 51 37 C 51 36.449219 50.550781 36 50 36 Z M 25 47 C 24.449219 47 24 47.449219 24 48 C 24 48.550781 24.449219 49 25 49 C 25.550781 49 26 48.550781 26 48 C 26 47.449219 25.550781 47 25 47 Z M 25 51 C 24.449219 51 24 51.449219 24 52 C 24 52.550781 24.449219 53 25 53 C 25.550781 53 26 52.550781 26 52 C 26 51.449219 25.550781 51 25 51 Z M 40 52 C 37.800781 52 36 53.800781 36 56 C 36 58.199219 37.800781 60 40 60 C 42.199219 60 44 58.199219 44 56 C 44 53.800781 42.199219 52 40 52 Z M 40 54 C 41.117188 54 42 54.882813 42 56 C 42 57.117188 41.117188 58 40 58 C 38.882813 58 38 57.117188 38 56 C 38 54.882813 38.882813 54 40 54 Z M 25 55 C 24.449219 55 24 55.449219 24 56 C 24 56.550781 24.449219 57 25 57 C 25.550781 57 26 56.550781 26 56 C 26 55.449219 25.550781 55 25 55 Z M 25 59 C 24.449219 59 24 59.449219 24 60 C 24 60.550781 24.449219 61 25 61 C 25.550781 61 26 60.550781 26 60 C 26 59.449219 25.550781 59 25 59 Z M 25 63 C 24.449219 63 24 63.449219 24 64 C 24 64.550781 24.449219 65 25 65 C 25.550781 65 26 64.550781 26 64 C 26 63.449219 25.550781 63 25 63 Z"/></svg>' );

		register_post_type(
			'wpcd_quota_limits',
			array(
				'labels'              => array(
					'name'                  => _x( 'Quota Limits', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Quota Limit', 'Post type singular name', 'wpcd' ),
					'menu_name'             => $menu_name,
					'name_admin_bar'        => _x( 'Quota Limits', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => _x( 'Add New Quota Limit', 'Add New Button', 'wpcd' ),
					'add_new_item'          => _x( 'Add New Quota Limit', 'Add New Item', 'wpcd' ),
					'edit_item'             => __( 'Edit Quota Limit', 'wpcd' ),
					'view_item'             => _x( 'Quota Limit', 'Post type general name', 'wpcd' ),
					'all_items'             => _x( 'Quota Limits', 'Label for use with all items', 'wpcd' ),
					'search_items'          => __( 'Search Quota Limits', 'wpcd' ),
					'not_found'             => __( 'No Quota Limits were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Quota Limits were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Quota Limits list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Quota Limits list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Quota Limits list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
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
	 * @since 5.7
	 *
	 * @param array $metaboxes Array of existing metaboxes.
	 *
	 * @return array new array of metaboxes.
	 */
	public function register_post_type_fields( $metaboxes ) {

		$prefix = 'wpcd_';

		$fields = array(
			array(
				'name'       => __( 'Site', 'wpcd' ),
				'id'         => 'parent_id',
				'type'       => 'post',
				'post_type'  => 'wpcd_app',
				'query_args' => array(
					'post_status'    => 'private',
					'posts_per_page' => - 1,
				),
				'field_type' => 'select_advanced',
				'columns'    => 6,
			),
			array(
				'name'    => __( 'Custom Post Type Internal Name', 'wpcd' ),
				'desc'    => __( 'This is the internal name used by the post type on the customer site. For example: wpcd_products.', 'wpcd' ),
				'id'      => 'wpcd_quota_limits_name',
				'type'    => 'text',
				'columns' => 6,
			),
			array(
				'name'    => __( 'Custom Post Type Limit', 'wpcd' ),
				'tooltip' => __( 'This is the max items that will be allow before the site using the site is flagged as being over the quota limit.', 'wpcd' ),
				'id'      => 'wpcd_quota_limits_limit',
				'type'    => 'number',
				'size'    => 10,
				'columns' => 4,
			),
			array(
				'name'    => __( 'Custom Post Type Last Value', 'wpcd' ),
				'tooltip' => __( 'This is the last value recorded from the site..', 'wpcd' ),
				'id'      => 'wpcd_quota_limits_last_value_received',
				'type'    => 'number',
				'size'    => 10,
				'columns' => 4,
			),
			array(
				'name'    => __( 'Note', 'wpcd' ),
				'tooltip' => __( 'This is usually set by WooCommerce or other calling function.', 'wpcd' ),
				'id'      => 'wpcd_quota_limits_action_note',
				'type'    => 'textarea',
				'columns' => 4,
			),
		);

		/* Add the fields defined above to various metaboxes. */
		$metaboxes[] = array(
			'id'         => 'wpcd_quota_limits',
			'title'      => __( 'Manage Quota Limits', 'wpcd' ),
			'post_types' => array( 'wpcd_quota_limits' ),
			'priority'   => 'default',
			'fields'     => $fields,
		);

		return $metaboxes;
	}

	/**
	 * Change ADD TITLE placeholder text on new CPT items.
	 *
	 * @since 5.7
	 *
	 * @param string $title Current title.
	 *
	 * @return string New Title.
	 */
	public function change_enter_title_text( $title ) {

		$screen = get_current_screen();

		if ( 'wpcd_quota_limits' === $screen->post_type ) {
			$title = 'Enter a name for this new Quota Limit';
		}

		return $title;

	}

	/**
	 * Add table header values.
	 *
	 * @since 5.7
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function wpcd_quota_limits_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_quota_limits_domain']     = __( 'Domain', 'wpcd' );
		$defaults['wpcd_quota_limits_name']       = __( 'Custom Post Type', 'wpcd' );
		$defaults['wpcd_quota_limits_limit']      = __( 'Limit', 'wpcd' );
		$defaults['wpcd_quota_limits_last_value'] = __( 'Most Recent Value', 'wpcd' );
		$defaults['date']                         = __( 'Date', 'wpcd' );

		return $defaults;

	}

	/**
	 * Add contents to the table columns.
	 *
	 * @since 5.7
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * print column value.
	 */
	public function wpcd_quota_limits_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_quota_limits_domain':
				$app_id = (int) get_post_meta( $post_id, 'parent_id', true );
				$value  = WPCD_WORDPRESS_APP()->get_domain_name( $app_id );
				break;
			case 'wpcd_quota_limits_name':
				$value = get_post_meta( $post_id, 'wpcd_quota_limits_name', true );
				break;
			case 'wpcd_quota_limits_limit':
				$value = get_post_meta( $post_id, 'wpcd_quota_limits_limit', true );
				break;
			case 'wpcd_quota_limits_last_value':
				$value = get_post_meta( $post_id, 'wpcd_quota_limits_last_value_received', true );
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
	 * Add table header sorting columns.
	 *
	 * @since 5.7
	 *
	 * @param array $columns array of default head columns.
	 *
	 * @return $columns modified array with new columns
	 */
	public function wpcd_quota_limits_table_sorting( $columns ) {

		return $columns;
	}


	/**
	 * Create quota limit records for a site based on a quota profile.
	 *
	 * @since 5.7
	 *
	 * @param int $quota_profile The post id of a quota profile.
	 * @param int $app_id The post id of the app for which we're going to create limit records.
	 */
	public function create_limits( $quota_profile, $app_id ) {

		$limits = wpcd_maybe_unserialize( get_post_meta( $quota_profile, 'wpcd_quota_profile_set', true ) );

		// Bail if the quota profile has no limits.
		if ( empty( $limits ) ) {
			return;
		}

		foreach ( $limits as $limit ) {
			$item       = $limit['wpcd_quota_profile_item_name'];
			$item_title = $limit['wpcd_quota_profile_title'];
			$domain     = WPCD_WORDPRESS_APP()->get_domain_name( $app_id );
			$max        = $limit['wpcd_quota_profile_item_limit'];
			/* Translators: %1%s is the domain name and %2$s is a custom post type internal name such as 'wpcd_products'. */
			$limit_title = sprintf( __( 'Limit for site %1$s for item: %2$s', 'wpcd' ), $domain, $item );
			/* Translators: %1%s is an integer for a quota profile post id */
			$note = sprintf( __( 'This item was created based on profile id: %1$s.', 'wpcd' ), $quota_profile, );

			$post_arr = array(
				'post_type'   => 'wpcd_quota_limits',
				'post_title'  => $limit_title,
				'post_status' => 'private',
				'post_author' => get_current_user_id(),
			);

			$new_limit_id = wp_insert_post( $post_arr );

			if ( (int) $new_limit_id > 0 ) {
				add_post_meta( $new_limit_id, 'parent_id', $app_id );
				add_post_meta( $new_limit_id, 'wpcd_quota_limits_name', $item );
				add_post_meta( $new_limit_id, 'wpcd_quota_limits_item_title', $item_title );
				add_post_meta( $new_limit_id, 'wpcd_quota_limits_limit', $max );
				add_post_meta( $new_limit_id, 'wpcd_quota_limits_last_value_received', 0 );
				add_post_meta( $new_limit_id, 'wpcd_quota_limits_action_note', $note );
			}
		}

	}

	/**
	 *
	 * Remove associated records when a site is deleted.
	 *
	 * @since 5.7
	 *
	 * Action hook: wpcd_wordpress-app_after_remove_site_action_before_record_delete
	 *
	 * @param int    $app_id The post id of the site we're working with.
	 * @param string $action The action string used in the calling program - not used here.
	 */
	public function wpcd_after_remove_site_action_before_record_delete( $app_id, $action ) {

		$args = array(
			'post_type'      => 'wpcd_quota_limits',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => 'parent_id',
					'value'   => $app_id,
					'type'    => 'NUMERIC',
					'compare' => '=',
				),
			),
		);

		$limits = get_posts( $args );

		if ( $limits ) {
			foreach ( $limits as $limit ) {
				if ( 'wpcd_quota_limits' === get_post_type( $limit->ID ) ) {
					wp_delete_post( $limit->ID );
				}
			}
		}

	}

	/**
	 * Create pending log quota limit actions for a site.
	 *
	 * Called from PUSH COMMAND.
	 *
	 * We're doing this because we don't want the PUSH COMMAND to run too long.
	 * So putting these non-critical actions into the background.
	 *
	 * @since 5.7
	 *
	 * @param int $app_id The post id for the site we're creating actions for.
	 */
	public function create_pending_log_quota_limit_actions_for_site( $app_id ) {

		// Grab the posttypes data out of the site metadata.
		$site_data = wpcd_maybe_unserialize( get_post_meta( $app_id, 'wpcd_site_posttypes_push', true ) );

		// No data to evaluate?  Bail out.
		if ( empty( $site_data ) || is_wp_error( $site_data ) ) {
			return;
		}

		// Get limits that apply to the site.
		$args = array(
			'post_type'      => 'wpcd_quota_limits',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => 'parent_id',
					'value'   => $app_id,
					'type'    => 'NUMERIC',
					'compare' => '=',
				),
			),
		);

		$limits = get_posts( $args );

		// Bail if no limit records found.
		if ( empty( $limits ) || is_wp_error( $limits ) ) {
			return;
		}

		// At this point we have limits that apply to the site and PUSH data.  Lets evaluate the two together.
		foreach ( $limits as $limit ) {

			// Get the limit item name and max value (eg: 'post' or 'attachment').
			$limit_item_name = get_post_meta( $limit->ID, 'wpcd_quota_limits_name', true );
			$limit_item_max  = (int) get_post_meta( $limit->ID, 'wpcd_quota_limits_limit', true );

			// We must make sure we have array items otherwise PHP 8 errors out.
			if ( ! empty( $site_data[ $limit_item_name ] ) ) {

				// Compare the two values - received in $site_data and limit set in $limits (and extracted into vars above).
				if ( (int) $site_data[ $limit_item_name ] > $limit_item_max ) {
					error_log( 'exceeded!' );
				}

				// Put the value we just recieved into the last value field on the limit record.
				update_post_meta( $limit->ID, 'wpcd_quota_limits_last_value_received', $site_data[ $limit_item_name ] );
			}
		}

	}

	/**
	 * Evaluate Limits - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * @since 5.7
	 *
	 * Action Hook: wpcd_pending_log_manual_site_backup
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site on which this action apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function pending_log_evaluate_quota_limits( $task_id, $site_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		// Add a postmeta to the site we can use later.
		update_post_meta( $site_id, 'wpapp_pending_log_manual_backup_task_id', $task_id );

		/* Trigger manual backup for the site */
		do_action( 'wpcd_wordpress-app_evaluate_quota_limits_for_site', 'evaluate-quota-limits', $site_id );

	}

	/**
	 * Evaluate quota limits for a site.
	 *
	 * @since 5.7
	 *
	 * @param string $action action - not used.
	 * @param int    $app_id The site id for which we'll be evaluating and applying quota limits.
	 */
	public function evaluate_quota_limits_for_site( $action, $app_id ) {
		error_log( "in evaluation for site id: $id with action $action" );
	}

}
