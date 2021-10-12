<?php
/**
 * WPCD_POSTS_TEAM class for team.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_POSTS_TEAM
 */
class WPCD_POSTS_TEAM {

	/**
	 * WPCD_POSTS_TEAM instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_POSTS_TEAM constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->register();  // register the custom post type.
		$this->hooks(); // register hooks to make the custom post type do things...
	}

	/**
	 * To hook custom actions and filters for wpcd_team post type
	 *
	 * @return void
	 */
	private function hooks() {

		// Filter hook to add custom meta box.
		add_filter( 'rwmb_meta_boxes', array( $this, 'wpcd_team_register_meta_boxes' ), 10, 1 );

		// Load up css and js scripts used for managing this cpt data screens.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

		// Filter to force post status to private.
		add_filter( 'wp_insert_post_data', array( $this, 'wpcd_team_force_type_private' ), 10, 1 );

		// Action hook on save post.
		add_action( 'save_post', array( $this, 'wpcd_team_save_post' ), 10, 1 );

		// Filter hook to filter team listing.
		add_filter( 'parse_query', array( $this, 'wpcd_team_parse_query' ), 10, 1 );

		// Action hook to check if user has permission to edit teams.
		add_action( 'load-post.php', array( $this, 'wpcd_team_load_post' ) );

		// Filter hook to change post count on team listing screen based on logged in users permissions.
		add_filter( 'views_edit-wpcd_team', array( $this, 'wpcd_team_custom_view_count' ), 10, 1 );

		// Action hook to check if user has capability to access team listing or add new team page.
		add_action( 'load-edit.php', array( $this, 'wpcd_team_load_edit_post_new' ) );
		add_action( 'load-post-new.php', array( $this, 'wpcd_team_load_edit_post_new' ) );

		// Action hook to add custom back to list button.
		add_action( 'admin_footer-post.php', array( $this, 'wpcd_team_backtolist_btn' ) );
		add_action( 'admin_footer-post-new.php', array( $this, 'wpcd_team_backtolist_btn' ) );

		// Action hook to register custom meta box for team.
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );

		// Filter hook to add new columns.
		add_filter( 'manage_wpcd_team_posts_columns', array( $this, 'wpcd_team_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_team_posts_custom_column', array( $this, 'wpcd_team_table_content' ), 10, 2 );

		// Filter hook to change Publish button text.
		add_filter( 'gettext', array( $this, 'wpcd_team_text_filter' ), 20, 3 );

		// Action hook to remove team permissions from custom table.
		add_action( 'wp_trash_post', array( $this, 'wpcd_team_delete_post' ), 10, 1 );

		// Action hook to restore team permissions to custom table.
		add_action( 'untrashed_post', array( $this, 'wpcd_team_untrashed_post' ), 10, 1 );

		// Action hook to show custom section on user profile screen.
		add_action( 'show_user_profile', array( $this, 'wpcd_team_user_profile_section' ), 10, 1 );
		add_action( 'edit_user_profile', array( $this, 'wpcd_team_user_profile_section' ), 10, 1 );

		// Action hook to check the rules before saving the post.
		add_action( 'rwmb_wpcd_team_permissions_before_save_post', array( $this, 'wpcd_team_permissions_before_save_post' ), 10, 1 );

	}

	/**
	 * Register the scripts for the wpcd_team post type
	 *
	 * @param  string $hook hook.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {

		if ( in_array( $hook, array( 'post-new.php', 'post.php' ) ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && 'wpcd_team' == $screen->post_type ) {
				wp_enqueue_script( 'wpcd-team-admin', wpcd_url . 'assets/js/wpcd-team-admin.js', array( 'jquery' ), wpcd_version, true );
				wp_localize_script(
					'wpcd-team-admin',
					'params',
					array(
						'i10n' => array(
							'empty_title'     => __( 'Please enter the Team Title.', 'wpcd' ),
							'no_team_manager' => __( 'Please select atleast one Team Manager.', 'wpcd' ),
							'no_team_member'  => __( 'Please select the Team Member.', 'wpcd' ),
							'no_permission'   => __(
								'Choose atleast one server or app permission.',
								'wpcd'
							),
						),
					)
				);
			}
		}

	}

	/**
	 * Register the custom post type wpcd_team.
	 *
	 * @return void
	 */
	public function register() {
		register_post_type(
			'wpcd_team',
			array(
				'labels'              => array(
					'name'                  => _x( 'Teams', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Team', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Teams', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Team', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => __( 'Add Team', 'wpcd' ),
					'add_new_item'          => __( 'Add New Team', 'wpcd' ),
					'edit_item'             => __( 'Edit Team', 'wpcd' ),
					'view_item'             => __( 'View Team', 'wpcd' ),
					'all_items'             => __( 'All Teams', 'wpcd' ),
					'search_items'          => __( 'Search Teams', 'wpcd' ),
					'not_found'             => __( 'No Teams were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Teams were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Teams list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Teams list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Teams list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( 'title' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'edit_post'              => 'wpcd_manage_teams',
					'edit_posts'             => 'wpcd_manage_teams',
					'edit_others_posts'      => 'wpcd_manage_teams',
					'edit_published_posts'   => 'wpcd_manage_teams',
					'delete_post'            => 'wpcd_manage_teams',
					'publish_posts'          => 'wpcd_manage_teams',
					'delete_posts'           => 'wpcd_manage_teams',
					'delete_others_posts'    => 'wpcd_manage_teams',
					'delete_published_posts' => 'wpcd_manage_teams',
					'delete_private_posts'   => 'wpcd_manage_teams',
					'edit_private_posts'     => 'wpcd_manage_teams',
					'read_private_posts'     => 'wpcd_manage_teams',
				),
			)
		);
	}

	/**
	 * To add custom metabox on team details screen
	 * This meta box will allow admin to select team members and permissions
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param  array $metaboxes meta boxes.
	 *
	 * @return array
	 */
	public function wpcd_team_register_meta_boxes( $metaboxes ) {

		$prefix = 'wpcd_';

		// Register the metabox to team members and permissions.
		$metaboxes[] = array(
			'id'         => $prefix . 'team_permissions',
			'title'      => __( 'Team and Permissions', 'wpcd' ),
			'post_types' => array( 'wpcd_team' ), // displays on wpcd_team post type only.
			'context'    => 'normal',
			'fields'     => array(
				array(
					'id'         => $prefix . 'permission_rule',
					'type'       => 'group',
					'clone'      => true, // ALLOW GROUP TO BE CLONED.
					'add_button' => __( '+ Add New', 'wpcd' ),
					'class'      => $prefix . 'permission-rule',
					'fields'     => array(
						array(
							'name'        => __( 'Team Member', 'wpcd' ),
							'id'          => $prefix . 'team_member',
							'type'        => 'user',
							'placeholder' => __( 'Select Team Member', 'wpcd' ),
							'class'       => $prefix . 'team-member',
							'query_args'  => array(
								'role__not_in' => array( 'administrator' ),
							),
						),
						array(
							'name'    => __( 'Team Manager', 'wpcd' ),
							'id'      => $prefix . 'team_manager',
							'type'    => 'checkbox',
							'class'   => $prefix . 'team-manager-checkbox',
							'columns' => 3,
						),
						array(
							'name'        => __( 'Server Permisssions', 'wpcd' ),
							'id'          => $prefix . 'server_permissions',
							'type'        => 'checkbox_list',
							'class'       => $prefix . 'server-permissions',
							'options'     => $this->wpcd_get_permission_groups( 1 ),
							'multiple'    => true,
							'placeholder' => __( 'Select a permission', 'wpcd' ),
							'columns'     => 3,
						),

						array(
							'name'        => __( 'Server Tab Permisssions', 'wpcd' ),
							'id'          => $prefix . 'server_tab_permissions',
							'type'        => 'checkbox_list',
							'class'       => $prefix . 'server-permissions',
							'options'     => $this->wpcd_get_permission_groups( 2 ),
							'multiple'    => true,
							'placeholder' => __( 'Select a permission', 'wpcd' ),
							'columns'     => 3,
						),
						array(
							'name'        => __( 'App Permisssions', 'wpcd' ),
							'id'          => $prefix . 'app_permissions',
							'type'        => 'checkbox_list',
							'class'       => $prefix . 'app-permissions',
							'options'     => $this->wpcd_get_permission_groups( 3 ),
							'multiple'    => true,
							'placeholder' => __( 'Select a permission', 'wpcd' ),
							'columns'     => 3,
						),

						/** Placeholder for when we need app tab permissions.
						array(
							'name' => __( 'App Tab Permisssions', 'wpcd' ),
							'id' => $prefix . 'app_tab_permissions',
							'type'=> 'checkbox_list',
							'class' => $prefix . 'app-permissions',
							'options' => $this->wpcd_get_permission_groups(4),
							'multiple' => true,
							'placeholder' =>  __( 'Select a permission', 'wpcd' ),
							'columns' => 3,
						),
						*/
					),
				),
			),
		);
		return $metaboxes;

	}

	/**
	 * Filters the post status to private on saving on wpcd_team detail screen
	 *
	 * Filter hook: wp_insert_post_data
	 *
	 * @param  object $post post.
	 *
	 * @return object
	 */
	public function wpcd_team_force_type_private( $post ) {

		if ( 'wpcd_team' === $post['post_type'] ) {
			if ( $post['post_status'] != 'trash' && $post['post_status'] != 'auto-draft' && $post['post_status'] != 'draft' ) {
				$post['post_status'] = 'private';
			}
		}
		return $post;

	}

	/**
	 * Grab the permissions from the edit teams screen before metabox updates it,
	 * parse it out and save it to a custom table.
	 *
	 * @param  integer $post_id post id.
	 *
	 * @return void
	 */
	public function wpcd_team_save_post( $post_id ) {
		if ( isset( $_POST['post_type'] ) && 'wpcd_team' == $_POST['post_type'] ) {
			$wpcd_permission_rules = wpcd_filter_input_numeric_array( $_POST['wpcd_permission_rule'] );

			$team_id = filter_input( INPUT_POST, 'post_ID', FILTER_SANITIZE_NUMBER_INT );

			foreach ( $wpcd_permission_rules as $rule ) {

				$user_id                = (int) sanitize_text_field( $rule['wpcd_team_member'] );
				$server_permissions     = ! empty( $rule['wpcd_server_permissions'] ) ? $rule['wpcd_server_permissions'] : array();
				$server_tab_permissions = ! empty( $rule['wpcd_server_tab_permissions'] ) ? $rule['wpcd_server_tab_permissions'] : array();
				$app_permissions        = ! empty( $rule['wpcd_app_permissions'] ) ? $rule['wpcd_app_permissions'] : array();
				$app_tab_permissions    = ! empty( $rule['wpcd_app_tab_permissions'] ) ? $rule['wpcd_app_tab_permissions'] : array();

				// Combine server_permissions group and server_tab_permissions group into one array.
				$server_permissions = array_merge( $server_permissions, $server_tab_permissions );

				// Combine app_permissions group and app_tab_permissions group into one array.
				// Note that as of V 4.6.1 there are no entries for the app_tab_permissions group.
				$app_permissions = array_merge( $app_permissions, $app_tab_permissions );

				$all_passed_permissions = array();
				$all_passed_permissions = array_merge( $server_permissions, $app_permissions );

				$this->wpcd_update_excluded_permissions( $team_id, $user_id, $all_passed_permissions );

				foreach ( $server_permissions as $server_permission ) {
					$permission_type_id = (int) sanitize_text_field( $server_permission );
					$this->wpcd_assign_permissions( $team_id, $user_id, $permission_type_id ); // save to custom table.
				}

				foreach ( $app_permissions as $app_permission ) {
					$permission_type_id = (int) sanitize_text_field( $app_permission );
					$this->wpcd_assign_permissions( $team_id, $user_id, $permission_type_id ); // save to custom table.
				}
			}
		}
	}

	/**
	 * To get the list of permissions for the specified object type from cpt wpcd_permission_type cpt.
	 *
	 * @param  integer $object_type can be "1" for server and "2" for app.
	 * @param  boolean $only_ids    return only ids for permission types.
	 *
	 * @return array
	 */
	public function wpcd_get_permissions( $object_type, $only_ids = false ) {

		$args = array(
			'post_type'   => 'wpcd_permission_type',
			'post_status' => 'private',
			'numberposts' => -1,
			'order'       => 'ASC',
			'meta_query'  => array(
				array(
					'key'     => 'wpcd_object_type',
					'value'   => $object_type,
					'compare' => '=',
				),
			),
		);

		$posts       = get_posts( $args );
		$permissions = array();
		if ( count( $posts ) ) {
			foreach ( $posts as $post ) {
				$post_id = $post->ID;
				$title   = get_the_title( $post_id );

				if ( $only_ids ) {
					$permissions[] = (string) $post_id;
				} else {
					$permissions[ $post_id ] = $title;
				}
			}
		}

		return $permissions;
	}

	/**
	 * To get the list of groups for specified group type from wpcd_permission_type cpt.
	 *
	 * @param  integer $group can be "1" for server and "2" for app.
	 * @param  boolean $only_ids    return only ids for permission types.
	 *
	 * @return array
	 */
	public function wpcd_get_permission_groups( $group, $only_ids = false ) {

		$args = array(
			'post_type'   => 'wpcd_permission_type',
			'post_status' => 'private',
			'numberposts' => -1,
			'order'       => 'ASC',
			'meta_query'  => array(
				array(
					'key'     => 'wpcd_permission_group',
					'value'   => $group,
					'compare' => '=',
				),
			),
		);

		$posts       = get_posts( $args );
		$permissions = array();
		if ( count( $posts ) ) {
			foreach ( $posts as $post ) {
				$post_id = $post->ID;
				$title   = get_the_title( $post_id );

				if ( $only_ids ) {
					$permissions[] = (string) $post_id;
				} else {
					$permissions[ $post_id ] = $title;
				}
			}
		}

		return $permissions;
	}

	/**
	 * This will store/update permissions in custom table
	 *
	 * @param  integer $team_id team id.
	 * @param  integer $user_id user id.
	 * @param  integer $permission_type_id permission type id.
	 *
	 * @return void
	 */
	public function wpcd_assign_permissions( $team_id, $user_id, $permission_type_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'permission_assignments';

		$get_teams_sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE team_id = %d AND user_id = %d AND permission_type_id = %d", array( $team_id, $user_id, $permission_type_id ) );
		$results       = $wpdb->get_results( $get_teams_sql );

		if ( count( $results ) == 0 ) {
			// Insert if no such record found.
			$wpdb->insert(
				$wpdb->prefix . 'permission_assignments',
				array(
					'team_id'            => $team_id,
					'user_id'            => $user_id,
					'permission_type_id' => $permission_type_id,
					'granted'            => 1,
				)
			);
		} else {
			foreach ( $results as $row ) {
				if ( $row->granted == 0 ) {
					$wpdb->update(
						$wpdb->prefix . 'permission_assignments',
						array(
							'granted' => 1,
						),
						array(
							'team_id'            => $row->team_id,
							'user_id'            => $row->user_id,
							'permission_type_id' => $row->permission_type_id,
						)
					);
				}
			}
		}

	}

	/**
	 * Update excluded permission type id.
	 * This will change granted to 0 for the permission type that are not passed for specific team_id and user_id
	 *
	 * @param  int   $team_id team id.
	 * @param  int   $user_id user id.
	 * @param  array $all_passed_permissions all passed permission.
	 *
	 * @return void
	 */
	public function wpcd_update_excluded_permissions( $team_id, $user_id, $all_passed_permissions ) {
		global $wpdb;

		$all_server_permissions   = $this->wpcd_get_permissions( 1, true );
		$all_app_permissions      = $this->wpcd_get_permissions( 2, true );
		$all_permissions          = array_merge( $all_server_permissions, $all_app_permissions );
		$all_excluded_permissions = array_diff( $all_permissions, $all_passed_permissions );

		if ( count( $all_excluded_permissions ) ) {
			foreach ( $all_excluded_permissions as $permission_type_id ) {
				$wpdb->update(
					$wpdb->prefix . 'permission_assignments',
					array(
						'granted' => 0,
					),
					array(
						'team_id'            => $team_id,
						'user_id'            => $user_id,
						'permission_type_id' => $permission_type_id,
						'granted'            => 1,
					)
				);
			}
		}

	}

	/**
	 * To modify default query parameters and to show team listing according to user permission
	 *
	 * Action filter: parse_query
	 *
	 * @param  object $query query.
	 *
	 * @return void
	 */
	public function wpcd_team_parse_query( $query ) {

		global $pagenow;

		if ( is_admin() && $query->is_main_query() && $query->query['post_type'] == 'wpcd_team' && $pagenow == 'edit.php' && ! wpcd_is_admin() ) {

			$qv              = &$query->query_vars;
			$user_id         = get_current_user_id();
			$is_team_manager = wpcd_check_user_is_team_manager( $user_id );
			$post__in        = array();

			if ( $is_team_manager || current_user_can( 'wpcd_manage_teams' ) ) {
				$post_status = filter_input( INPUT_GET, 'post_status', FILTER_SANITIZE_STRING );
				$post_status = ! empty( $post_status ) ? $post_status : 'private';
				$post__in    = wpcd_get_team_manager_posts( $user_id, $post_status );
			}

			if ( $post__in ) {
				$qv['post__in'] = $post__in;
			} else {
				$qv['post__in'] = array( 0 );
			}
		}
	}

	/**
	 * Checks if user has permission to edit the team
	 *
	 * Action hook: load-post.php
	 *
	 * @return void
	 */
	public function wpcd_team_load_post() {

		global $post;

		$screen = get_current_screen();

		if ( $screen->post_type == 'wpcd_team' && isset( $_GET['action'] ) && $_GET['action'] == 'edit' && ! wpcd_is_admin() ) {

			if ( ! current_user_can( 'wpcd_manage_teams' ) ) {
				wp_die( esc_html( __( 'You don\'t have access to this page.', 'wpcd' ) ) );
			}

			$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
			$user_id = get_current_user_id();
			$post_author = get_post( $post_id )->post_author;

			$is_team_manager = wpcd_check_user_is_team_manager( $user_id, $post_id );

			if ( (int) $post_author !== $user_id && ! $is_team_manager ) {
				wp_die( esc_html( __( 'You don\'t have permission to edit this post.', 'wpcd' ) ) );
			}
		}
	}

	/**
	 * Filters table views for the wpcd_team post type
	 *
	 * @param  array $views Array of table view links keyed by status slug.
	 * @return array Filtered views.
	 */
	public function wpcd_team_custom_view_count( $views ) {
		global $current_screen;
		if ( $current_screen->id == 'edit-wpcd_team' && ! wpcd_is_admin() ) {
			$views = $this->wpcd_team_manipulate_views( 'wpcd_team', $views );
		}
		return $views;
	}

	/**
	 * Manipulate post counts by post status for team listing screen
	 *
	 * @param  string $post_type post type.
	 * @param  array  $views Array of table view links keyed by status slug.
	 *
	 * @return array
	 */
	public function wpcd_team_manipulate_views( $post_type, $views ) {

		if ( ! is_admin() && $post_type != 'wpcd_team' ) {
			return $views;
		}

		$user_id       = get_current_user_id();
		$private_posts = wpcd_get_team_manager_posts( $user_id, 'private' );
		$private       = count( $private_posts );

		$trash_posts = wpcd_get_team_manager_posts( $user_id, 'trash' );
		$trash       = count( $trash_posts );

		$total = $private;

		$views['all'] = preg_replace( '/\(.+\)/U', '(' . $total . ')', $views['all'] );
		if ( array_key_exists( 'private', $views ) ) {
			$views['private'] = preg_replace( '/\(.+\)/U', '(' . $private . ')', $views['private'] );
		}

		if ( array_key_exists( 'trash', $views ) ) {
			$views['trash'] = preg_replace( '/\(.+\)/U', '(' . $trash . ')', $views['trash'] );
		}

		return $views;
	}

	/**
	 * Checks if current user has the capability to access team listing or add new team page.
	 *
	 * Action hook: load-edit.php, load-post-new.php
	 *
	 * @return void
	 */
	public function wpcd_team_load_edit_post_new() {
		$screen = get_current_screen();

		if ( 'wpcd_team' === $screen->post_type && ! wpcd_is_admin() ) {
			if ( ! current_user_can( 'wpcd_manage_teams' ) ) {
				wp_die( esc_html( __( 'You don\'t have access to this page.', 'wpcd' ) ) );
			}
		}
	}

	/**
	 * Adds custom back to list button for team post type
	 *
	 * @return void
	 */
	public function wpcd_team_backtolist_btn() {
		$screen    = get_current_screen();
		$post_type = 'wpcd_team';

		if ( $screen->id === $post_type ) {
			$query          = sprintf( 'edit.php?post_type=%s', $post_type );
			$backtolist_url = admin_url( $query );
			$backtolist_txt = __( 'Back To List', 'wpcd' );
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('<a href="<?php echo esc_attr( $backtolist_url ); ?>" class="page-title-action"><?php echo esc_html( $backtolist_txt ); ?></a>').insertBefore('hr.wp-header-end');					
				});
			</script>
			<?php
		}
	}

	/**
	 * Register meta box(es) for post type wpcd_team.
	 *
	 * Action hook: add_meta_boxes
	 */
	public function meta_boxes() {

		// Add TEAM Assignemtns meta box into Team custom post type.
		add_meta_box(
			'wpcd_team_detail',
			__( 'Team Assignments', 'wpcd' ),
			array( $this, 'render_team_details_meta_box' ),
			'wpcd_team',
			'advanced',
			'low'
		);

	}

	/**
	 * Render the TEAM detail meta box
	 *
	 * @param  object $post Current post object.
	 */
	public function render_team_details_meta_box( $post ) {
		$args = array(
			'post_type'    => array( 'wpcd_app_server', 'wpcd_app' ),
			'post_status'  => 'private',
			'meta_key'     => 'wpcd_assigned_teams',
			'meta_value'   => $post->ID,
			'meta_compare' => 'IN',
			'numberposts'  => -1,
			'fields'       => 'ids',
		);

		$posts        = get_posts( $args );
		$server_posts = array();
		$app_posts    = array();

		foreach ( $posts as $post ) {
			if ( get_post_type( $post ) == 'wpcd_app_server' ) {
				$server_posts[] = $post;
			}

			if ( get_post_type( $post ) == 'wpcd_app' ) {
				$app_posts[] = $post;
			}
		}

		ob_start();
		require wpcd_path . 'includes/templates/team_details.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;
	}

	/**
	 * Add TEAM list table header values
	 *
	 * Filter hook: manage_wpcd_team_posts_columns
	 *
	 * @param  array $defaults array of default head values.
	 *
	 * @return array           modified array with new columns
	 */
	public function wpcd_team_table_head( $defaults ) {

		unset( $defaults['date'] );
		$defaults['wpcd_assigned_servers'] = __( 'Servers', 'wpcd' );
		$defaults['wpcd_assigned_apps']    = __( 'Apps', 'wpcd' );
		$defaults['wpcd_team_members']     = __( 'Members', 'wpcd' );
		$defaults['date']                  = __( 'Date', 'wpcd' );

		return $defaults;
	}

	/**
	 * Add contents to the TEAM table columns
	 *
	 * Action hook: manage_wpcd_team_posts_custom_column
	 *
	 * @param  string $column_name Name of the column.
	 * @param  int    $post_id     ID of the post.
	 *
	 * @return void                Returns nothing - prints column value instead
	 */
	public function wpcd_team_table_content( $column_name, $post_id ) {
		$value = '';

		switch ( $column_name ) {
			case 'wpcd_assigned_servers':
				$args = array(
					'post_type'    => 'wpcd_app_server',
					'post_status'  => 'private',
					'meta_key'     => 'wpcd_assigned_teams',
					'meta_value'   => $post_id,
					'meta_compare' => 'IN',
					'numberposts'  => -1,
					'fields'       => 'ids',
				);

				$posts = get_posts( $args );

				if ( count( $posts ) ) {
					$value = sprintf( '<a href="%s" target="_blank">%d</a>', esc_url( admin_url( 'edit.php?post_type=wpcd_app_server&team_id=' . $post_id ) ), count( $posts ) );
				} else {
					$value = 0;
				}

				break;
			case 'wpcd_assigned_apps':
				$args = array(
					'post_type'    => 'wpcd_app',
					'post_status'  => 'private',
					'meta_key'     => 'wpcd_assigned_teams',
					'meta_value'   => $post_id,
					'meta_compare' => 'IN',
					'numberposts'  => -1,
					'fields'       => 'ids',
				);

				$posts = get_posts( $args );

				if ( count( $posts ) ) {
					$value = sprintf( '<a href="%s" target="_blank">%d</a>', esc_url( admin_url( 'edit.php?post_type=wpcd_app&team_id=' . $post_id ) ), count( $posts ) );
				} else {
					$value = 0;
				}

				break;
			case 'wpcd_team_members':
				$wpcd_permission_rule = get_post_meta( $post_id, 'wpcd_permission_rule', true );

				if ( empty( $wpcd_permission_rule ) ) {
					$value = '-';
				} else {
					$value = array();
					$count = 1;
					foreach ( $wpcd_permission_rule as $rule ) {

						if ( $count > 5 ) {
							break;
						}

						$user_info = get_userdata( $rule['wpcd_team_member'] );
						$user_name = ! empty( $user_info->display_name ) ? $user_info->display_name : $user_info->user_login;

						$server_permissions = array();
						if ( ! empty( $rule['wpcd_server_permissions'] ) ) {
							foreach ( $rule['wpcd_server_permissions'] as $permission ) {
								$server_permissions[] = get_the_title( $permission );
							}
						}

						$app_permissions = array();
						if ( ! empty( $rule['wpcd_app_permissions'] ) ) {
							foreach ( $rule['wpcd_app_permissions'] as $permission ) {
								$app_permissions[] = get_the_title( $permission );
							}
						}
						$permissions = array_merge( $server_permissions, $app_permissions );

						if ( empty( $permissions ) ) {
							$permissions = __( 'No permissions assigned.' );
						} else {
							$permissions = implode( ', ', $permissions );
						}

						$value[] = sprintf( '%s: %s', $user_name, $permissions );

						$count++;

					}

					$value = implode( '<br /><br />', $value );
				}

				break;
		}

		echo $value;

	}

	/**
	 * Changes the Publish button text to Save Team for team screen
	 *
	 * Filter hook: gettext
	 *
	 * @param  string $translated_text translated text.
	 * @param  string $untranslated_text untranslated_text.
	 * @param  string $domain domain.
	 *
	 * @return string
	 */
	public function wpcd_team_text_filter( $translated_text, $untranslated_text, $domain ) {
		global $typenow;
		if ( is_admin() && 'Publish' === $untranslated_text && 'wpcd_team' === $typenow ) {
			$translated_text = __( 'Save Team', 'wpcd' );
		}

		return $translated_text;
	}

	/**
	 * Removes entries related to team in the custom table for permissions and remove the team from assigned teams for server and app.
	 *
	 * Action hook: wp_trash_post
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function wpcd_team_delete_post( $post_id ) {
		global $wpdb;

		if ( is_admin() && 'wpcd_team' === get_post_type( $post_id ) ) {
			$posts = get_posts(
				array(
					'post_type'    => array( 'wpcd_app_server', 'wpcd_app' ),
					'post_status'  => 'private',
					'meta_key'     => 'wpcd_assigned_teams',
					'meta_value'   => $post_id,
					'meta_compare' => 'IN',
					'numberposts'  => -1,
					'fields'       => 'ids',
				)
			);

			if ( $posts ) {
				foreach ( $posts as $post ) {
					delete_post_meta( $post, 'wpcd_assigned_teams', $post_id );
				}
			}

			// Delete the records in custom table for the team that is going to be trashed.
			$table_name = $wpdb->prefix . 'permission_assignments';
			$query      = $wpdb->prepare( "DELETE FROM {$table_name} WHERE team_id = %d", $post_id );
			$wpdb->query( $query );
		}
	}

	/**
	 * Restores entries related to team in the custom table for permissions when the team gets restored from trash
	 *
	 * Action hook: untrashed_post
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function wpcd_team_untrashed_post( $post_id ) {
		if ( is_admin() && 'wpcd_team' === get_post_type( $post_id ) ) {
			$wpcd_permission_rules = get_post_meta( $post_id, 'wpcd_permission_rule', true );

			foreach ( $wpcd_permission_rules as $rule ) {
				$user_id            = (int) sanitize_text_field( $rule['wpcd_team_member'] );
				$server_permissions = $rule['wpcd_server_permissions'] ? $rule['wpcd_server_permissions'] : array();
				$app_permissions    = $rule['wpcd_app_permissions'] ? $rule['wpcd_app_permissions'] : array();

				foreach ( $server_permissions as $server_permission ) {
					$permission_type_id = (int) sanitize_text_field( $server_permission );
					$this->wpcd_assign_permissions( $post_id, $user_id, $permission_type_id );
				}

				foreach ( $app_permissions as $app_permission ) {
					$permission_type_id = (int) sanitize_text_field( $app_permission );
					$this->wpcd_assign_permissions( $post_id, $user_id, $permission_type_id );
				}
			}
		}
	}

	/**
	 * Adds a section on User Profile screen to show the list of servers and apps that the user is allowed to view
	 *
	 * Action hook: show_user_profile
	 *
	 * @param  object $user WP User object.
	 *
	 * @return void
	 */
	public function wpcd_team_user_profile_section( $user ) {

		$args = array(
			'post_type'   => array( 'wpcd_app_server', 'wpcd_app' ),
			'post_status' => 'private',
			'numberposts' => -1,
			'fields'      => 'ids',
		);

		$posts        = get_posts( $args );
		$server_posts = array();
		$app_posts    = array();

		foreach ( $posts as $post ) {

			$post_type = get_post_type( $post );

			switch ( $post_type ) {
				case 'wpcd_app_server':
					if ( wpcd_is_admin( $user->ID ) ) {
						$server_posts[] = $post;
					} else {
						if ( user_can( $user, 'wpcd_manage_servers' ) && ( wpcd_user_can( $user->ID, 'view_server', $post ) || get_post( $post )->post_author == $user->ID ) ) {
							$server_posts[] = $post;
						}
					}
					break;
				case 'wpcd_app':
					if ( wpcd_is_admin( $user->ID ) ) {
						$app_posts[] = $post;
					} else {
						if ( user_can( $user, 'wpcd_manage_apps' ) && ( wpcd_user_can( $user->ID, 'view_app', $post ) || get_post( $post )->post_author == $user->ID ) ) {
							$app_posts[] = $post;
						}
					}
					break;
			}
		}

		ob_start();
		require wpcd_path . 'includes/templates/user_details.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

	}

	/**
	 * Checks for the wpcd_team_permissions meta box rules before saving the team and updates the permissions in custom table for the team member
	 *
	 * Action hook: rwmb_wpcd_team_permissions_before_save_post
	 *
	 * @param  int $post_id ID of the team.
	 *
	 * @return void
	 */
	public function wpcd_team_permissions_before_save_post( $post_id ) {
		global $wpdb;
		$wpcd_permission_rules = get_post_meta( $post_id, 'wpcd_permission_rule', true );
		if ( $wpcd_permission_rules ) {
			$new_wpcd_permission_rules = wpcd_filter_input_numeric_array( $_POST['wpcd_permission_rule'] );

			$team_members = array();

			// Loop through team members in new rule.
			foreach ( $new_wpcd_permission_rules as $rule ) {
				if ( in_array( $rule['wpcd_team_member'], $team_members ) ) {
					continue;
				}
				$team_members[] = $rule['wpcd_team_member'];
			}

			// Update permissions for old team members.
			foreach ( $wpcd_permission_rules as $rule ) {
				if ( in_array( $rule['wpcd_team_member'], $team_members ) ) {
					continue;
				}

				$wpdb->update(
					$wpdb->prefix . 'permission_assignments',
					array(
						'granted' => 0,
					),
					array(
						'team_id' => $post_id,
						'user_id' => $rule['wpcd_team_member'],
						'granted' => 1,
					)
				);

			}
		}

	}

}
