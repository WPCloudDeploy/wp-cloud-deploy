<?php
/**
 * This class handles app.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_POSTS_APP
 */
class WPCD_POSTS_APP extends WPCD_Posts_Base {

	/* Include traits */
	use wpcd_get_set_post_type;
	use wpcd_metaboxes_for_taxonomies_for_servers_and_apps;
	use wpcd_metaboxes_for_teams_for_servers_and_apps;
	use wpcd_metaboxes_for_labels_notes_for_servers_and_apps;

	/**
	 * POSTS_APP instance.
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

		/**
		 * The global exclude app term array.
		 * This is needed because we're going to hook into the get_terms_args filter
		 * to filter out app group items based on permissions.
		 * We filter these out because certain app group items should
		 * not be shown to certain users - especially users who might be purchasing
		 * sites.
		 *
		 * The global is needed to prevent an infinite loop as we modify the data
		 * passed into the get_terms_args filter.
		 */
		$wpcd_exclude_app_group = array();

		$this->register();  // register the custom post type.
		$this->hooks();     // register hooks to make the custom post type do things...
		$this->init_hooks_for_taxonomies_for_servers_and_apps();    // located in the wpcd_metaboxes_for_taxonomies_for_servers_and_apps trait file.
		$this->init_hooks_for_teams_for_servers_and_apps(); // located in the wpcd_metaboxes_for_teams_for_servers_and_apps trait file.
		$this->init_hooks_for_labels_notes_for_servers_and_apps();  // located in the wpcd_metaboxes_for_labels_notes_for_servers_and_apps trait file.
	}

	/**
	 * POSTS_APP constructor.
	 */
	private function hooks() {

		// Meta box display callback.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Save Meta Values.
		add_action( 'save_post', array( $this, 'save_meta_values' ), 10, 2 );

		// Filter hook to add new columns.
		add_filter( 'manage_wpcd_app_posts_columns', array( $this, 'app_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_app_posts_custom_column', array( $this, 'app_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-wpcd_app_sortable_columns', array( $this, 'app_table_sorting' ), 10, 1 );

		// Remove PRIVATE state label from certain custom post types - function is actually in ancestor class.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 20, 2 );

		// Action hook to add prompt when a post is going to be deleted.
		add_action( 'admin_footer-edit.php', array( $this, 'wpcd_app_trash_prompt' ) );
		add_action( 'admin_footer-post.php', array( $this, 'wpcd_app_trash_prompt' ) );

		// Action hook to extend admin search.
		add_action( 'pre_get_posts', array( $this, 'wpcd_app_extend_admin_search' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'wpcd_app_meta_or_title_search' ), 10, 1 );

		// Filter hook to modify where clause.
		add_filter( 'posts_where', array( $this, 'wpcd_app_posts_where' ), 10, 2 );

		// Action hook to extend admin filter options.
		add_action( 'restrict_manage_posts', array( $this, 'wpcd_app_table_filtering' ) );

		// Filter hook to filter app listing on custom meta data.
		add_filter( 'parse_query', array( $this, 'wpcd_app_parse_query' ), 10, 1 );

		// Filter hook to add custom meta boxes.
		add_filter( 'rwmb_meta_boxes', array( $this, 'wpcd_app_register_meta_boxes' ), 10, 1 );

		// Action hook to change author for app type post.
		add_action( 'rwmb_wpcd_change_app_owner_after_save_post', array( $this, 'wpcd_change_app_owner_after_save_post' ), 10, 1 );

		// Action hook to check if user has permission to edit app.
		add_action( 'load-post.php', array( $this, 'wpcd_app_load_post' ) );

		// Action hook to check if user has permission to delete app.
		add_action( 'wp_trash_post', array( $this, 'wpcd_app_delete_post' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'wpcd_app_delete_post' ), 10, 1 );

		// Filter hook to change post count on app listing screen based on logged in users permissions.
		add_filter( 'views_edit-wpcd_app', array( $this, 'wpcd_app_custom_view_count' ), 10, 1 );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpcd_app_new_musite' ), 10, 2 );

		// Filter hook to remove delete bulk action if user is not admin or super admin.
		add_filter( 'bulk_actions-edit-wpcd_app', array( $this, 'wpcd_app_bulk_actions' ), 10, 1 );

		// Filter hook to remove delete link from app listing row if user is not admin or super admin.
		add_filter( 'post_row_actions', array( $this, 'wpcd_app_post_row_actions' ), 10, 2 );

		// Action hook to hide "Move to trash" link on app detail screen if user is not admin or super admin.
		add_action( 'admin_head-post.php', array( $this, 'wpcd_app_hide_delete_link' ) );

		// Filter hook to restrict change app owner if user does not have right to change.
		add_action( 'rwmb_wpcd_change_app_owner_before_save_post', array( $this, 'wpcd_change_app_owner_before_save_post' ), 10, 1 );

		// Filter hook to restrict trash post if delete protection is enabled.
		add_filter( 'pre_trash_post', array( $this, 'wpcd_app_restrict_trash_post' ), 10, 2 );

		// Action hook to clean up apps.
		add_action( 'wp_ajax_wpcd_cleanup_apps', array( $this, 'wpcd_cleanup_apps' ) );

		// Filter hook to add custom column header for wpcd_app_group listing.
		add_filter( 'manage_edit-wpcd_app_group_columns', array( $this, 'wpcd_manage_wpcd_app_group_columns' ) );

		// Filter hook to add custom column content for wpcd_app_group listing.
		add_filter( 'manage_wpcd_app_group_custom_column', array( $this, 'wpcd_manage_wpcd_app_group_columns_content' ), 10, 3 );

		// Action hook to add meta values for restored post.
		add_action( 'untrashed_post', array( $this, 'wpcd_app_untrashed_post' ), 10, 1 );

		// Action hook to get all app terms - will be used to exclude certain items in the app group metabox.
		add_action( 'admin_head', array( $this, 'wpcd_exclude_app_group_taxonomy_ids' ), 99 );

		// Filter hook to change the argument to exclude app terms - will be used to exclude certain items in the app group metabox.
		add_action( 'get_terms_args', array( $this, 'wpcd_exclude_from_app_term_args' ), 1000, 2 );
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {
		self::wpcd_app_register_post_and_taxonomy();

		$this->set_post_taxonomy( 'wpcd_app_group' );

		$this->set_group_key( 'wpcd_app_group' );

		$this->set_post_type( 'wpcd_app' );

	}

	/**
	 * Add APPs table header sorting columns
	 *
	 * @param array $columns array of default head columns.
	 *
	 * @return $columns modified array with new columns
	 */
	public function app_table_sorting( $columns ) {

		$columns['wpcd_app_type']        = 'wpcd_app_type';
		$columns['wpcd_app_short_desc']  = 'wpcd_app_short_desc';
		$columns['wpcd_server']          = 'wpcd_server';
		$columns['wpcd_server_ipv4']     = 'wpcd_server_ipv4';
		$columns['wpcd_server_provider'] = 'wpcd_server_provider';
		$columns['wpcd_server_region']   = 'wpcd_server_region';
		$columns['wpcd_owner']           = 'wpcd_owner';
		$columns['wpcd_app_group']       = 'wpcd_app_group';

		return $columns;
	}

	/**
	 * Add contents to the APPs table columns
	 *
	 * @param string $column_name string column name.
	 * @param int    $post_id post id.
	 *
	 * print column value.
	 */
	public function app_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_app_short_desc':
				// Display the short description.
				$value = esc_html( get_post_meta( $post_id, 'wpcd_short_description', true ) );
				break;

			case 'wpcd_app_type':
				// Display the app type (eg: vpn or WordPress or fractional-vpn).
				$value = esc_html( get_post_meta( $post_id, 'app_type', true ) );
				$value = apply_filters( 'wpcd_app_type_column_contents', $value, $column_name, $post_id );
				break;

			case 'wpcd_server':
				// Display the name of the server - get the server id and title first.
				$server_post_id = get_post_meta( $post_id, 'parent_post_id', true );
				$server_title   = wp_kses_post( get_post( $server_post_id )->post_title );

				// Show the server title - with a link if the user is able to edit it otherwise without the link.
				$user_id = get_current_user_id();
				if ( wpcd_user_can( $user_id, 'view_server', $server_post_id ) || get_post( $server_post_id )->post_author === $user_id ) {
					$value = sprintf( '<a href="%s">' . $server_title . '</a>', get_edit_post_link( $server_post_id ) );
				} else {
					$value = $server_title;
				}

				// Server post id.
				// *** removing for now to save vertical space.
				// $value = $value . '<br />' . __( 'id: ', 'wpcd' ) . (string) $server_post_id; .

				// server provider.
				$value = $value . '<br />' . __( 'Provider: ', 'wpcd' ) . WPCD()->wpcd_get_cloud_provider_desc( $this->get_server_meta_value( $post_id, 'wpcd_server_provider' ) );

				// server region.
				$value = $value . '<br />' . __( 'Region: ', 'wpcd' ) . $this->get_server_meta_value( $post_id, 'wpcd_server_region' );

				// ipv4.
				$value = $value . '<br />' . __( 'ipv4: ', 'wpcd' ) . $this->get_server_meta_value( $post_id, 'wpcd_server_ipv4' );

				// Show a link that takes you to a list of apps on the server.
				$url   = admin_url( 'edit.php?post_type=wpcd_app&server_id=' . (string) $server_post_id );
				$value = $value . '<br />' . sprintf( '<a href="%s">%s</a>', $url, __( 'Apps on this server', 'wpcd' ) );

				break;

			case 'wpcd_server_ipv4':
				// Display the ip(v4) of the server.
				$value = $this->get_server_meta_value( $post_id, 'wpcd_server_ipv4' );
				break;

			case 'wpcd_server_provider':
				// Display the cloud provider.
				$value = $this->get_server_meta_value( $post_id, 'wpcd_server_provider' );
				break;

			case 'wpcd_server_region':
				// Display the region the server is located in.
				$value = $this->get_server_meta_value( $post_id, 'wpcd_server_region' );
				break;

			case 'wpcd_owner':
				// Display the name of the owner who set up the server...
				$server_post_id = get_post_meta( $post_id, 'parent_post_id', true );
				$server_owner   = esc_html( get_user_by( 'ID', get_post( $server_post_id )->post_author )->user_login );
				$app_owner      = esc_html( get_user_by( 'ID', get_post( $post_id )->post_author )->user_login );
				if ( $server_owner === $app_owner ) {
					// both owners are the same so show one item.
					$value = $server_owner; // @Todo: Make a hyperlink to user profile screens in admin.
				} else {
					// two different owners so show both items on two different lines.
					$value = __( 'Svr:', 'wpcd' ) . ' ' . $server_owner . '<br />' . __( 'App:', 'wpcd' ) . ' ' . $app_owner;  // @Todo: Make both owners a hyperlink to their respective user profile screens in admin.
				}

				break;

			case 'wpcd_app_summary':
				// Nothing here - instead individual app classes will use this filter to populate data about the app.
				// This way the list can show data about different apps.
				$value = apply_filters( 'wpcd_app_admin_list_summary_column', $value, $post_id );

				if ( empty( $value ) ) {
					$value = 'no data for this app';
				}

				break;

			case 'wpcd_app_group':
				$terms = get_the_terms( $post_id, 'wpcd_app_group' );
				if ( ! empty( $terms ) ) {
					$value = '';
					foreach ( $terms as $term ) {
						$term_id   = $term->term_id;
						$term_name = $term->name;
						$color     = get_term_meta( $term_id, 'wpcd_group_color', true );
						$url       = esc_url( add_query_arg( 'wpcd_app_group', $term_id ) );
						$value    .= sprintf( '<a href="%s"><span class="wpcd-app-server-app-group" style="background-color: %s">%s</span></a>', $url, $color, $term_name );
					}
				} else {
					$value = sprintf( '<span class="wpcd-app-server-app-group" style="background-color: %s">%s</span>', 'gray', __( 'None', 'wpcd' ) );
				}

				break;

			case 'wpcd_assigned_teams':
				$wpcd_assigned_teams = get_post_meta( $post_id, 'wpcd_assigned_teams', false );

				$teams = array();
				if ( $wpcd_assigned_teams ) {
					foreach ( $wpcd_assigned_teams as $team ) {

						if ( ! empty( $team ) ) {
							if ( 'trash' === get_post_status( $team ) || false === (bool) get_post_status( $team ) ) {
								continue;
							}

							$user_id         = get_current_user_id();
							$is_team_manager = wpcd_check_user_is_team_manager( $user_id, $team );

							if ( current_user_can( 'wpcd_manage_teams' ) && ( get_post( $team )->post_author === $user_id || $is_team_manager ) ) {
								$url     = admin_url( sprintf( 'post.php?post=%s&action=edit', $team ) );
								$teams[] = sprintf( '<a href="%s" target="_blank">%s</a>', $url, get_the_title( $team ) );
							} else {
								$teams[] = get_the_title( $team );
							}
						}
					}

					$value = implode( ', ', $teams );
				} else {
					$value = __( 'No team assigned.', 'wpcd' );
				}

				// If Teams array is still empty...
				if ( empty( $teams ) ) {
					$value = __( 'No team assigned.', 'wpcd' );
				}

				break;

			default:
				break;
		}

		echo $value;

	}

	/**
	 * Add APPs table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function app_table_head( $defaults ) {

		unset( $defaults['date'] );

		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_app_type' ) ) ) {
			$defaults['wpcd_app_type'] = __( 'App Type', 'wpcd' );
		}
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_short_desc' ) ) ) {
			$defaults['wpcd_app_short_desc'] = __( 'Description', 'wpcd' );
		}
		$defaults['wpcd_app_group']   = __( 'App Group', 'wpcd' );
		$defaults['wpcd_app_summary'] = __( 'App Summary', 'wpcd' );
		$defaults['wpcd_server']      = __( 'Server', 'wpcd' );
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_ipv4' ) ) ) {
			$defaults['wpcd_server_ipv4'] = __( 'IPv4', 'wpcd' );
		}
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_provider' ) ) ) {
			$defaults['wpcd_server_provider'] = __( 'Provider', 'wpcd' );
		}
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_region' ) ) ) {
			$defaults['wpcd_server_region'] = __( 'Region', 'wpcd' );
		}
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_owner' ) ) ) {
			$defaults['wpcd_owner'] = __( 'Owners', 'wpcd' );
		}
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_date' ) ) ) {
			$defaults['date'] = __( 'Date', 'wpcd' );
		}
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_team' ) ) ) {
			$defaults['wpcd_assigned_teams'] = __( 'Teams', 'wpcd' );
		}

		return $defaults;
	}

	/**
	 * Register meta box(es).
	 */
	public function add_meta_boxes() {

		/* Only render for true admins! */
		if ( ! wpcd_is_admin() ) {
			return;
		}

		/* Only render if the settings option is turned on. */
		if ( ! (bool) wpcd_get_option( 'show-advanced-metaboxes' ) ) {
			return;
		}

		// Add APP detail meta box into the APP custom post type.
		add_meta_box(
			'app_detail',
			__( 'Application Details', 'wpcd' ),
			array( $this, 'render_app_details_meta_box' ),
			'wpcd_app',
			'advanced',
			'low'
		);
	}

	/**
	 * Render the APPs detail meta box
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_app_details_meta_box( $post ) {

		$html = '';

		$app_post_title = $post->post_title;
		$app_type       = get_post_meta( $post->ID, 'app_type', true );
		$parent_post_id = get_post_meta( $post->ID, 'parent_post_id', true );

		/* Get some data about the server so that the metabox template can use it*/
		$ipv4            = WPCD_SERVER()->get_ipv4_address( $parent_post_id );
		$server_name     = WPCD_SERVER()->get_server_name( $parent_post_id );
		$server_provider = WPCD_SERVER()->get_server_provider( $parent_post_id );

		ob_start();
		require wpcd_path . 'includes/templates/app_details.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

	}

	/**
	 * Handles saving the meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return null
	 */
	public function save_meta_values( $post_id, $post ) {
		// Add nonce for security and authentication.
		$nonce_name   = filter_input( INPUT_POST, 'app_meta', FILTER_SANITIZE_STRING );
		$nonce_action = 'wpcd_app_nonce_meta_action';

		// Check if nonce is valid.
		if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
			return;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if not an autosave.
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check if not a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Make sure post type is wpcd_app.
		if ( 'wpcd_app' !== $post->post_type ) {
			return;
		}

		$app_post_title      = filter_input( INPUT_POST, 'app_post_title', FILTER_SANITIZE_STRING );
		$wpcd_app_type       = filter_input( INPUT_POST, 'app_type', FILTER_SANITIZE_STRING );
		$wpcd_parent_post_id = filter_input( INPUT_POST, 'parent_post_id', FILTER_SANITIZE_NUMBER_INT );
		$wpcd_app_owner      = filter_input( INPUT_POST, 'wpcd_app_owner', FILTER_SANITIZE_NUMBER_INT );

		update_post_meta( $post_id, 'app_type', $wpcd_app_type );
		update_post_meta( $post_id, 'parent_post_id', $wpcd_parent_post_id );

		// Update post title.
		$post->post_title = $app_post_title;

		// Update the post author.
		$post->post_author = $wpcd_app_owner;

		remove_action( 'save_post', array( $this, 'save_meta_values' ), 10 ); // remove hook to prevent infinite loop.
		wp_update_post( $post );
		add_action( 'save_post', array( $this, 'save_meta_values' ), 10, 2 ); // re-add hook.

	}

	/**
	 * Add a new app record.
	 *
	 * @param string $app_name An id for the application - using "name" instead so as not to confuse everyone with 'app_id' not being a number.
	 * @param int    $server_post_id The post id that represents the server this app is being install upon.
	 * @param int    $author_id The person who purchased this app (should be the same as the person who purchased the server).
	 * @param string $title title.
	 */
	public function add_app( $app_name = 'NoName', $server_post_id = false, $author_id = false, $title = false ) {

		// Make sure we have some sort of an author.
		if ( empty( $author_id ) ) {
			$author_id = get_current_user();
		}

		if ( ! empty( $title ) ) {
			$post_title = $title;
		} else {
			$post_title = $app_name;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'post_title'  => $post_title,
				'post_author' => $author_id,
			)
		);

		if ( ! is_wp_error( $post_id ) && ! empty( $post_id ) ) {
			update_post_meta( $post_id, 'parent_post_id', $server_post_id );    // using the parent post id to link back to the server record.
			update_post_meta( $post_id, 'app_type', $app_name );                 // app_type is used to hold the type of app because the post_title field will eventually be more descriptive.
			update_post_meta( $post_id, 'wpcd_app_plugin_initial_version', wpcd_version ); // initial plugin version.
			update_post_meta( $post_id, 'wpcd_app_plugin_updated_version', wpcd_version ); // current plugin version.
		}

		do_action( 'wpcd_log_error', "Created APP CPT with ID $post_id ", 'debug', __FILE__, __LINE__ );

		return $post_id;
	}

	/**
	 * Get a meta value from a server record, given an app id.
	 *
	 * @param int    $app_id app id.
	 * @param string $meta meta name to retrieve.
	 * @param string $single - treat retrieved value as single value or multiple/array elements? See $single param in https://developer.wordpress.org/reference/functions/get_post_meta/.
	 */
	public function get_server_meta_value( $app_id, $meta, $single = true ) {
		$server_post_id    = get_post_meta( $app_id, 'parent_post_id', true );
		$server_meta_value = wp_kses_post( get_post_meta( $server_post_id, $meta, $single ) );
		return $server_meta_value;
	}

	/**
	 * Confirmation prompt for all trash actions on app list/detail screen.
	 *
	 * Action hook: admin_footer-edit.php
	 * Action hook: admin_footer-post.php
	 *
	 * @return true
	 */
	public function wpcd_app_trash_prompt() {
		$screen = get_current_screen();
		if ( in_array( $screen->id, array( 'edit-wpcd_app', 'wpcd_app' ), true ) ) {
			$prompt_message = __( 'Are you sure? This will only delete the data from our database.  The application itself will remain on your server. To remove a WordPress app from the server, cancel this operation and use the REMOVE SITE option under the MISC tab.', 'wpcd' );
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('a.submitdelete').click(function(e){
						e.preventDefault();
						var href = $(this).attr('href');
						var r = confirm('<?php echo esc_html( $prompt_message ); ?>');
						if(r){
							window.location = href;
						}
					});

					$('#doaction').click(function(e){
						if($('#bulk-action-selector-top').val() == 'trash'){
							if($('input[name="post[]"]:checked').length > 0){
								var r = confirm('<?php echo esc_html( $prompt_message ); ?>');
								if(!r){
									e.preventDefault();
								}
							}
						}
					});
				});
			</script>
			<?php
		}

		if ( 'edit-wpcd_app' === $screen->id ) {
			$prompt_message = __( 'Please note: Restoring this item will not necessarily restore your app on the server. This item will likely become an orphaned/ghost item - i.e: it will not have a connection to any app or server.', 'wpcd' );
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('span.untrash a').click(function(e){
						e.preventDefault();
						var href = $(this).attr('href');
						var r = confirm('<?php echo esc_html( $prompt_message ); ?>');
						if(r){
							window.location = href;
						}
					});
				});
			</script>
			<?php
		}
		return true;
	}

	/**
	 * Enhance search for server listing screen
	 *
	 * Action hook: pre_get_posts
	 *
	 * @param object $query query.
	 *
	 * @return null
	 */
	public function wpcd_app_extend_admin_search( $query ) {

		global $typenow;

		// use your post type.
		$post_type = 'wpcd_app';

		if ( is_admin() && $typenow === $post_type && $query->is_search() ) {

			// Use your Custom fields/column name to search for.
			$search_fields = array(
				'parent_post_id',
				'app_type',
				'wpapp_domain',
				'wpapp_user',
				'wpapp_version',
				'wpcd_short_description',
				'wpcd_long_description',
			);

			$search_term = $query->query_vars['s'];

			if ( '' !== $search_term ) {
				$meta_query = array( 'relation' => 'OR' );

				foreach ( $search_fields as $search_field ) {
					array_push(
						$meta_query,
						array(
							'key'     => $search_field,
							'value'   => $search_term,
							'compare' => 'LIKE',
						)
					);
				}

				// Use an 'OR' comparison for each additional custom meta field.
				if ( count( $meta_query ) > 1 ) {
					$meta_query['relation'] = 'OR';
				}
				// Set the meta_query parameter.
				$query->set( 'meta_query', $meta_query );

				// To allow the search to also return "OR" results on the post_title.
				$query->set( '_meta_or_title', $search_term );
			};
		} else {
			return;
		}

	}

	/**
	 * Meta or title search for wpcd_app post type
	 *
	 * Action hook: pre_get_posts
	 *
	 * @param object $query query.
	 */
	public function wpcd_app_meta_or_title_search( $query ) {

		global $typenow;

		$post_type = 'wpcd_app';
		$title     = $query->get( '_meta_or_title' );
		if ( is_admin() && $typenow === $post_type && $query->is_search() && $title ) {
			add_filter(
				'get_meta_sql',
				function( $sql ) use ( $title ) {
					global $wpdb;

					// Only run once.
					static $nr = 0;
					if ( 0 !== $nr++ ) {
						return $sql;
					}

					$server_meta_search = "{$wpdb->postmeta}.meta_key = 'parent_post_id' AND {$wpdb->postmeta}.meta_value IN ( SELECT P.ID FROM {$wpdb->posts} AS P LEFT JOIN {$wpdb->postmeta} AS PM on PM.post_id = P.ID WHERE P.post_type = 'wpcd_app_server' and P.post_status = 'private' and ( ( PM.meta_key = 'wpcd_server_provider' AND PM.meta_value LIKE '" . esc_sql( '%' . $wpdb->esc_like( $title ) . '%' ) . "' ) OR ( PM.meta_key = 'wpcd_server_ipv4' AND PM.meta_value LIKE '" . esc_sql( '%' . $wpdb->esc_like( $title ) . '%' ) . "' ) OR ( PM.meta_key = 'wpcd_server_region' AND PM.meta_value LIKE '" . esc_sql( '%' . $wpdb->esc_like( $title ) . '%' ) . "' ) OR ( PM.meta_key = 'wpcd_server_name' AND PM.meta_value LIKE '" . esc_sql( '%' . $wpdb->esc_like( $title ) . '%' ) . "' ) ) )";

					// Modified WHERE.
					$sql['where'] = sprintf(
						' AND ( (%s) OR (%s) OR (%s) ) ',
						$wpdb->prepare( "{$wpdb->posts}.post_title LIKE '%%%s%%'", $title ),
						mb_substr( $sql['where'], 5, mb_strlen( $sql['where'] ) ),
						$server_meta_search
					);

					return $sql;
				}
			);
		}
	}

	/**
	 * Change where clause for sql query.
	 *
	 * Action hook: posts_where
	 *
	 * @param  string $where where string.
	 * @param  object $wp_query wp_query object.
	 *
	 * @return string
	 */
	public function wpcd_app_posts_where( $where, $wp_query ) {
		global $wpdb, $typenow;

		$post_type = 'wpcd_app';

		if ( ! is_admin() ) {
			return $where;
		}

		if ( is_admin() && $typenow === $post_type && $wp_query->is_search() ) {
			if ( isset( $wp_query->query_vars['_meta_or_title'] ) && '' !== $wp_query->query_vars['_meta_or_title'] ) {

				$_meta_or_title = $wp_query->query_vars['_meta_or_title'];
				$_meta_or_title = trim( $_meta_or_title );

				if ( strpos( $_meta_or_title, ' ' ) !== false ) {
					$search_terms = explode( ' ', $_meta_or_title );

					foreach ( $search_terms as $search_term ) {
						$find[] = '((' . $wpdb->posts . '.post_title LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $search_term ) . '%' ) . '\') OR (' . $wpdb->posts . '.post_excerpt LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $search_term ) . '%' ) . '\') OR (' . $wpdb->posts . '.post_content LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $search_term ) . '%' ) . '\'))';
					}

					$find = implode( ' AND ', $find );
					$find = sprintf( 'AND (%s) ', $find );

					$replace = '';
					$where   = str_replace( $find, $replace, $where );

				} else {
					$find    = 'AND (((' . $wpdb->posts . '.post_title LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $_meta_or_title ) . '%' ) . '\') OR (' . $wpdb->posts . '.post_excerpt LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $_meta_or_title ) . '%' ) . '\') OR (' . $wpdb->posts . '.post_content LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $_meta_or_title ) . '%' ) . '\')))';
					$replace = '';
					$where   = str_replace( $find, $replace, $where );
				}
			}
		}
		return $where;
	}

	/**
	 * Add filters on the app listing screen at the backend
	 *
	 * Action hook: restrict_manage_posts
	 *
	 * @return void
	 */
	public function wpcd_app_table_filtering() {

		global $typenow, $pagenow;

		$post_type = 'wpcd_app';

		if ( is_admin() && 'edit.php' === $pagenow && $typenow === $post_type ) {

			$apps = $this->generate_meta_dropdown( $post_type, 'app_type', __( 'All App Types', 'wpcd' ) );
			echo $apps;

			$servers = $this->generate_server_dropdown( __( 'All Servers', 'wpcd' ) );
			echo $servers;

			$providers = $this->generate_meta_dropdown( 'wpcd_app_server', 'wpcd_server_provider', __( 'All Providers', 'wpcd' ) );
			echo $providers;

			$regions = $this->generate_meta_dropdown( 'wpcd_app_server', 'wpcd_server_region', __( 'All Regions', 'wpcd' ) );
			echo $regions;

			$server_owners = $this->generate_owner_dropdown( 'wpcd_app_server', 'wpcd_server_owner', __( 'All Server Owners', 'wpcd' ) );
			echo $server_owners;

			$app_owners = $this->generate_owner_dropdown( $post_type, 'wpcd_app_owner', __( 'All App Owners', 'wpcd' ) );
			echo $app_owners;

			$ipv4 = $this->generate_meta_dropdown( 'wpcd_app_server', 'wpcd_server_ipv4', __( 'All IPv4', 'wpcd' ) );
			echo $ipv4;

			$taxonomy  = 'wpcd_app_group';
			$app_group = $this->generate_term_dropdown( $taxonomy, __( 'App Groups', 'wpcd' ) );
			echo $app_group;
		}
	}

	/**
	 * To modify default query parameters and to show app listing based on custom filters
	 *
	 * Action filter: parse_query
	 *
	 * @param  object $query query object.
	 */
	public function wpcd_app_parse_query( $query ) {
		global $pagenow;

		if ( is_admin() && $query->is_main_query() && 'wpcd_app' === $query->query['post_type'] && 'edit.php' === $pagenow && ! wpcd_is_admin() ) {

			$qv          = &$query->query_vars;
			$post_status = filter_input( INPUT_GET, 'post_status', FILTER_SANITIZE_STRING );
			$post_status = ! empty( $post_status ) ? $post_status : 'private';
			$post__in    = wpcd_get_posts_by_permission( 'view_app', 'wpcd_app', $post_status );

			if ( count( $post__in ) ) {
				$qv['post__in'] = $post__in;
			} else {
				$qv['post__in'] = array( 0 );
			}
		}

		$filter_action = filter_input( INPUT_GET, 'filter_action', FILTER_SANITIZE_STRING );
		if ( is_admin() && $query->is_main_query() && 'wpcd_app' === $query->query['post_type'] && 'edit.php' === $pagenow && 'Filter' === $filter_action ) {
			$qv = &$query->query_vars;

			// APP TYPE.
			if ( isset( $_GET['app_type'] ) && ! empty( $_GET['app_type'] ) ) {
				$app_type = filter_input( INPUT_GET, 'app_type', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'app_type',
					'value'   => $app_type,
					'compare' => '=',
				);
			}

			// SERVER.
			if ( isset( $_GET['wpcd_app_server'] ) && ! empty( $_GET['wpcd_app_server'] ) ) {
				$wpcd_app_server = filter_input( INPUT_GET, 'wpcd_app_server', FILTER_SANITIZE_STRING );

				$qv['meta_query'][] = array(
					'field'   => 'parent_post_id',
					'value'   => $wpcd_app_server,
					'compare' => '=',
				);
			}

			// SERVER PROVIDER.
			if ( isset( $_GET['wpcd_server_provider'] ) && ! empty( $_GET['wpcd_server_provider'] ) ) {
				$wpcd_server_provider = filter_input( INPUT_GET, 'wpcd_server_provider', FILTER_SANITIZE_STRING );

				$parents = $this->get_app_server_ids( 'wpcd_server_provider', $wpcd_server_provider );

				$qv['meta_query'][] = array(
					'field' => 'parent_post_id',
					'value' => $parents,
				);
			}

			// REGION.
			if ( isset( $_GET['wpcd_server_region'] ) && ! empty( $_GET['wpcd_server_region'] ) ) {
				$wpcd_server_region = filter_input( INPUT_GET, 'wpcd_server_region', FILTER_SANITIZE_STRING );

				$parents = $this->get_app_server_ids( 'wpcd_server_region', $wpcd_server_region );

				$qv['meta_query'][] = array(
					'field' => 'parent_post_id',
					'value' => $parents,
				);
			}

			// SERVER OWNER.
			if ( isset( $_GET['wpcd_server_owner'] ) && ! empty( $_GET['wpcd_server_owner'] ) ) {
				$wpcd_server_owner = filter_input( INPUT_GET, 'wpcd_server_owner', FILTER_SANITIZE_STRING );

				$parents = get_posts(
					array(
						'posts_per_page' => -1,
						'post_type'      => 'wpcd_app_server',
						'post_status'    => 'private',
						'fields'         => 'ids', // Just get IDs, not objects.
						'author'         => $wpcd_server_owner,
					)
				);

				$qv['meta_query'][] = array(
					'field' => 'parent_post_id',
					'value' => $parents,
				);
			}

			// APP OWNER.
			if ( isset( $_GET['wpcd_app_owner'] ) && ! empty( $_GET['wpcd_app_owner'] ) ) {
				$wpcd_app_owner = filter_input( INPUT_GET, 'wpcd_app_owner', FILTER_SANITIZE_STRING );

				$qv['author'] = $wpcd_app_owner;

			}

			// IPv4.
			if ( isset( $_GET['wpcd_server_ipv4'] ) && ! empty( $_GET['wpcd_server_ipv4'] ) ) {
				$wpcd_server_ipv4 = filter_input( INPUT_GET, 'wpcd_server_ipv4', FILTER_SANITIZE_STRING );

				$parents = $this->get_app_server_ids( 'wpcd_server_ipv4', $wpcd_server_ipv4 );

				$qv['meta_query'][] = array(
					'field' => 'parent_post_id',
					'value' => $parents,
				);
			}

			// APP GROUP.
			if ( isset( $_GET['wpcd_app_group'] ) && ! empty( $_GET['wpcd_app_group'] ) ) {
				$term_id = filter_input( INPUT_GET, 'wpcd_app_group', FILTER_SANITIZE_NUMBER_INT );

				$qv['tax_query'] = array(
					'relation' => 'OR',
					array(
						'taxonomy' => 'wpcd_app_group',
						'field'    => 'term_id',
						'terms'    => array( (int) $term_id ),
					),
				);

			}
		}

		if ( is_admin() && $query->is_main_query() && 'wpcd_app' === $query->query['post_type'] && 'edit.php' === $pagenow && ! empty( $_GET['server_id'] ) && empty( $filter_action ) ) {

			$qv               = &$query->query_vars;
			$qv['meta_query'] = array();

			$server_id = filter_input( INPUT_GET, 'server_id', FILTER_SANITIZE_NUMBER_INT );

			$qv['meta_query'][] = array(
				'field'   => 'parent_post_id',
				'value'   => $server_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			);

		}

		if ( is_admin() && $query->is_main_query() && 'wpcd_app' === $query->query['post_type'] && 'edit.php' === $pagenow && ! empty( $_GET['team_id'] ) && empty( $filter_action ) ) {

			$qv               = &$query->query_vars;
			$qv['meta_query'] = array();

			$team_id = filter_input( INPUT_GET, 'team_id', FILTER_SANITIZE_NUMBER_INT );

			$qv['meta_query'][] = array(
				'field'   => 'wpcd_assigned_teams',
				'value'   => $team_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			);

		}

		if ( is_admin() && $query->is_main_query() && 'wpcd_app' === $query->query['post_type'] && 'edit.php' === $pagenow && ! empty( $_GET['wpcd_app_group'] ) && empty( $filter_action ) ) {

			$qv = &$query->query_vars;

			$wpcd_app_group = filter_input( INPUT_GET, 'wpcd_app_group', FILTER_SANITIZE_NUMBER_INT );

			$qv['tax_query'] = array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'wpcd_app_group',
					'field'    => 'term_id',
					'terms'    => array( (int) $wpcd_app_group ),
				),
			);

		}

	}

	/**
	 * To add custom metabox on app details screen.
	 * Multiple metaboxes created for:
	 * 1. Allow user to change app owner(author)
	 * 2. Allow user to make app delete protected
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param  array $metaboxes metaboxes.
	 *
	 * @return array
	 */
	public function wpcd_app_register_meta_boxes( $metaboxes ) {

		// Get some values that we're going to need for fields later.
		// Start with the author of the post.
		$author_id = wpcd_get_form_submission_post_author();

		$users_to_include = array();

		if ( ! wpcd_is_admin() ) {
			$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
			$users   = wpcd_get_users_in_team( $post_id );
			if ( ! in_array( $author_id, $users, true ) ) {
				array_push( $users, $author_id );
			}
			$users_to_include = $users;
		}

		// Register the metabox to change app owner.
		$metaboxes[] = array(
			'id'         => 'wpcd_change_app_owner',
			'title'      => __( 'Change App Owner', 'wpcd' ),
			'pages'      => array( 'wpcd_app' ), // displays on wpcd_app post type only.
			'context'    => 'side',
			'priority'   => 'low',
			'fields'     => array(

				// add a user type field.
				array(
					'name'        => '',
					'desc'        => '',
					'id'          => 'wpcd_app_owner',
					'type'        => 'user',
					'std'         => $author_id,
					'placeholder' => __( 'Select App Owner', 'wpcd' ),
					'query_args'  => array(
						'include' => $users_to_include,
					),
				),

			),
			'validation' => array(
				'rules'    => array(
					'wpcd_app_owner' => array(
						'required' => true,
					),
				),
				'messages' => array(
					'wpcd_app_owner' => array(
						'required' => __( 'App Owner is required.', 'wpcd' ),
					),
				),
			),

		);

		$checked = rwmb_meta( 'wpcd_app_delete_protection' );

		// Register the metabox for delete app protection.
		$metaboxes[] = array(
			'id'       => 'wpcd_app_delete_protection_metabox',
			'title'    => __( 'App Delete Protection', 'wpcd' ),
			'pages'    => array( 'wpcd_app' ), // displays on wpcd_app post type only.
			'context'  => 'side',
			'priority' => 'low',
			'fields'   => array(

				// add a checkbox field.
				array(
					'desc' => 'Check this box to remove all delete links from the screen - it will prevent this app from being accidentally deleted.',
					'id'   => 'wpcd_app_delete_protection',
					'type' => 'checkbox',
					'std'  => $checked,
				),

			),
		);

		return $metaboxes;

	}

	/**
	 * Removes the wpcd_app_owner meta from the app detail screen.
	 *
	 * Action hook: rwmb_wpcd_change_app_owner_after_save_post
	 *
	 * @param  integer $post_id post id.
	 *
	 * @return void
	 */
	public function wpcd_change_app_owner_after_save_post( $post_id ) {

		// Remove the wpcd_app_owner meta - we dont' want a separate meta since that could be confusing.
		// The post author is the owner so no need to have a separate meta stored in the db.
		delete_post_meta( $post_id, 'wpcd_app_owner' );

	}

	/**
	 * Checks if user has permission to edit the app
	 *
	 * @return void
	 */
	public function wpcd_app_load_post() {

		$screen = get_current_screen();

		if ( 'wpcd_app' === $screen->post_type && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && ! wpcd_is_admin() ) {
			$post_id     = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
			$user_id     = (int) get_current_user_id();
			$post_author = (int) get_post( $post_id )->post_author;

			if ( ! wpcd_user_can( $user_id, 'view_app', $post_id ) && (int) $post_author !== $user_id ) {
				wp_die( esc_html( __( 'You don\'t have permission to edit this post.', 'wpcd' ) ) );
			}
		}
	}

	/**
	 * Restricts user to delete a post if he/she doesn't have delete_app_record permission
	 *
	 * Action Hook: before_delete_post
	 *
	 * Important Note: *****If you change this function or the action hook declaration then
	 *                      you must make sure that you search for any place where we have
	 *                      unhooked this action and make sure that the unhook process
	 *                      still works.
	 *                      One place is in tabs tabs/site-sync.php
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function wpcd_app_delete_post( $post_id ) {

		if ( get_post_type( $post_id ) === 'wpcd_app' && ! wpcd_is_admin() ) {
			$user_id     = (int) get_current_user_id();
			$post_author = (int) get_post( $post_id )->post_author;
			if ( ! wpcd_user_can( $user_id, 'delete_app_record', $post_id ) && $post_author !== $user_id ) {
				wp_die( esc_html( __( 'You don\'t have permission to delete this post.', 'wpcd' ) ) );
			}
		}

	}

	/**
	 * Filters table views for the wpcd_app post type
	 *
	 * @param  array $views Array of table view links keyed by status slug.
	 * @return array Filtered views.
	 */
	public function wpcd_app_custom_view_count( $views ) {
		global $current_screen;
		if ( 'edit-wpcd_app' === $current_screen->id && ! wpcd_is_admin() ) {
			$views = $this->wpcd_app_manipulate_views( 'wpcd_app', $views, 'view_app' );
		}
		return $views;
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
				self::wpcd_app_register_post_and_taxonomy();
				self::wpcd_app_create_default_taxonomy_terms();
				restore_current_blog();
			}
		} else {
			self::wpcd_app_register_post_and_taxonomy();
			self::wpcd_app_create_default_taxonomy_terms();
		}
	}

	/**
	 * Registers the custom post type and taxonomy
	 * Creating custom taxonomy terms after execution of this function
	 */
	public static function wpcd_app_register_post_and_taxonomy() {
		register_post_type(
			'wpcd_app',
			array(
				'labels'              => array(
					'name'                  => _x( 'APPs', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'APP', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'APPs', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'APPs', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => _x( 'Add New App Record', 'Add New Button', 'wpcd' ),
					'edit_item'             => __( 'Edit APP', 'wpcd' ),
					'view_item'             => __( 'View APP', 'wpcd' ),
					'all_items'             => __( 'All APPs', 'wpcd' ),
					'search_items'          => __( 'Search APPs', 'wpcd' ),
					'not_found'             => __( 'No Applications were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Applications were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter APPs list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'APPs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'APPs list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
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
				'capabilities'        => array(
					'create_posts'           => false,
					'edit_post'              => 'wpcd_manage_apps',
					'edit_posts'             => 'wpcd_manage_apps',
					'edit_others_posts'      => 'wpcd_manage_apps',
					'edit_published_posts'   => 'wpcd_manage_apps',
					'delete_post'            => 'wpcd_manage_apps',
					'publish_posts'          => 'wpcd_manage_apps',
					'delete_posts'           => 'wpcd_manage_apps',
					'delete_others_posts'    => 'wpcd_manage_apps',
					'delete_published_posts' => 'wpcd_manage_apps',
					'delete_private_posts'   => 'wpcd_manage_apps',
					'edit_private_posts'     => 'wpcd_manage_apps',
					'read_private_posts'     => 'wpcd_manage_apps',
				),
				'taxonomies'          => array( 'wpcd_app_group' ),
			)
		);

		// Add new taxonomy, make it hierarchical (like categories).
		$labels = array(
			'name'              => _x( 'App Groups', 'taxonomy general name', 'wpcd' ),
			'singular_name'     => _x( 'App Group', 'taxonomy singular name', 'wpcd' ),
			'search_items'      => __( 'Search App Groups', 'wpcd' ),
			'all_items'         => __( 'All App Groups', 'wpcd' ),
			'parent_item'       => __( 'Parent App Group', 'wpcd' ),
			'parent_item_colon' => __( 'Parent App Group:', 'wpcd' ),
			'edit_item'         => __( 'Edit App Group', 'wpcd' ),
			'update_item'       => __( 'Update App Group', 'wpcd' ),
			'add_new_item'      => __( 'Add New App Group', 'wpcd' ),
			'new_item_name'     => __( 'New App Group Name', 'wpcd' ),
			'menu_name'         => __( 'App Group', 'wpcd' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => false,
			'query_var'         => true,
			'capabilities'      => array(
				'manage_terms' => 'wpcd_manage_groups',
				'edit_terms'   => 'wpcd_manage_groups',
				'delete_terms' => 'wpcd_manage_groups',
				'assign_terms' => 'wpcd_manage_groups',
			),
		);

		// Hide the app group metabox.
		$hide_site_group_mb = wpcd_get_early_option( 'wpcd_hide_site_group_mb' );
		if ( $hide_site_group_mb ) {
			if ( ! wpcd_is_admin() ) {
				$args['meta_box_cb'] = false;
			}
		}

		register_taxonomy( 'wpcd_app_group', array( 'wpcd_app' ), $args );
	}

	/**
	 * Creates the default terms for wpcd_app_group taxonomy
	 */
	public static function wpcd_app_create_default_taxonomy_terms() {
		$taxonomy  = 'wpcd_app_group';
		$app_terms = array(
			array(
				'name'  => 'Live',
				'color' => '#b71c1c',
			),
			array(
				'name'  => 'Staging',
				'color' => '#880E4F',
			),
			array(
				'name'  => 'Dev',
				'color' => '#4A148C',
			),
			array(
				'name'  => 'Demo',
				'color' => '#01579B',
			),
			array(
				'name'  => 'Other',
				'color' => '#33691E',
			),
			array(
				'name'  => 'Sensitive',
				'color' => '#006064',
			),
			array(
				'name'  => 'Adult',
				'color' => '#263238',
			),
		);

		foreach ( $app_terms as $app_term ) {
			if ( term_exists( $app_term['name'], $taxonomy ) ) {
				continue;
			}
			// Insert the term if not exists.
			$term = wp_insert_term( $app_term['name'], $taxonomy );

			if ( ! is_wp_error( $term ) ) {
				add_term_meta( $term['term_id'], 'wpcd_group_color', $app_term['color'] );
			}
		}
	}

	/**
	 * To create default terms for wpcd_app_group taxonomy for newly created site on WP Multisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function wpcd_app_new_musite( $new_site, $args ) {
		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {
			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::wpcd_app_create_default_taxonomy_terms();
			restore_current_blog();
		}
	}

	/**
	 * Removes delete option from bulk action on app listing screen if user is not admin or super admin
	 *
	 * Filter hook: bulk_actions-edit-wpcd_app
	 *
	 * @param  array $actions actions.
	 *
	 * @return array
	 */
	public function wpcd_app_bulk_actions( $actions ) {
		if ( ! wpcd_is_admin() ) {
			unset( $actions['trash'] );
		}

		// Also disable if the option it set in the SETTINGS screen.
		$disable_bulk_delete = (int) wpcd_get_option( 'wordpress_app_disable_bulk_delete_on_full_app_list' );
		if ( 1 === $disable_bulk_delete ) {
			unset( $actions['trash'] );
		}

		return $actions;
	}

	/**
	 * Removes link to delete post and hides the checkbox for app if Delete Protection is enabled or user does not have delete app permission.
	 *
	 * Filter hook: post_row_actions
	 *
	 * @param  array  $actions actions.
	 * @param  object $post post.
	 *
	 * @return array
	 */
	public function wpcd_app_post_row_actions( $actions, $post ) {
		$user_id = get_current_user_id();

		if ( 'wpcd_app' === $post->post_type && ( ! wpcd_can_current_user_delete_app( $post->ID ) ) ) {
			unset( $actions['trash'] );
			unset( $actions['delete'] );
		}

		if ( 'wpcd_app' === $post->post_type && wpcd_is_app_delete_protected( $post->ID ) ) {
			unset( $actions['trash'] );
			unset( $actions['delete'] );
			$enable_bulk_delete = (int) wpcd_get_option( 'wordpress_app_enable_bulk_delete_on_app_when_delete_protected' );
			if ( 1 <> $enable_bulk_delete ) {
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
	 * Removes "Move to trash" for app details screen if Delete Protection is enabled or user does not have delete app permission.
	 *
	 * Action hook: admin_head-post.php
	 *
	 * @return void
	 */
	public function wpcd_app_hide_delete_link() {

		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

		if ( wpcd_can_current_user_delete_app( $post_id ) ) {
			?>
			<style>#delete-action { display: none; }</style>
			<?php
		}

	}

	/**
	 * Restricts to change the app owner if the user does not have the right to change.
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function wpcd_change_app_owner_before_save_post( $post_id ) {
		$user_id     = get_current_user_id();
		$users       = wpcd_get_users_in_team( $post_id );
		$post_author = get_post( $post_id )->post_author;
		if ( ! in_array( $post_author, $users, true ) ) {
			array_push( $users, $post_author );
		}

		$wpcd_app_owner = filter_input( INPUT_POST, 'wpcd_app_owner', FILTER_VALIDATE_INT );

		if ( ! wpcd_is_admin() && ! in_array( $user_id, $users, true ) && (int) $post_author !== (int) $wpcd_app_owner ) {
			wp_die( esc_html( __( 'You are not allowed to change the owner.', 'wpcd' ) ) );
		}

		// Update the post author.
		$post              = get_post( $post_id );
		$post->post_author = $wpcd_app_owner;
		wp_update_post( $post );
	}

	/**
	 * Restricts the trash post of the app if Delete Protection is enabled.
	 *
	 * Filter hook: pre_trash_post
	 *
	 * @param  boolean $trash trash.
	 * @param  object  $post post.
	 *
	 * @return boolean
	 */
	public function wpcd_app_restrict_trash_post( $trash, $post ) {

		if ( 'wpcd_app' === $post->post_type ) {
			if ( ! wpcd_is_app_delete_protected( $post->ID ) ) {
				return $trash;
			}
			return true;
		}

		return $trash;
	}

	/**
	 * Clean ups some of the meta fields for wpcd_app posts.
	 *
	 * Action hook: wp_ajax_wpcd_cleanup_apps
	 *
	 * @return void
	 */
	public function wpcd_cleanup_apps() {

		// Nonce check.
		check_ajax_referer( 'wpcd-cleanup-apps', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		$args = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$app_ids = get_posts( $args );

		if ( ! empty( $app_ids ) ) {
			foreach ( $app_ids as $app_id ) {

				do_action( 'wpcd_cleanup_app_before', $app_id );

				update_post_meta( $app_id, 'wpcd_command_mutex', '' );
				update_post_meta( $app_id, 'wpcd_temp_log_id', '' );

				do_action( 'wpcd_cleanup_app_after', $app_id );

			}

			do_action( 'wpcd_cleanup_apps_after' );

			$msg = __( 'Clean Up completed!', 'wpcd' );

		} else {
			$msg = __( 'No APP Records found!', 'wpcd' );
		}

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );
		wp_die();

	}

	/**
	 * Adds custom column header for wpcd_app_group taxonomy listing screen.
	 *
	 * Filter hook: manage_edit-wpcd_app_group_columns
	 *
	 * @param  array $columns columns.
	 *
	 * @return array
	 */
	public function wpcd_manage_wpcd_app_group_columns( $columns ) {
		$columns['wpcd_color'] = __( 'Color', 'wpcd' );

		return $columns;
	}

	/**
	 * Adds custom column content for wpcd_app_group taxonomy listing screen.
	 *
	 * Filter hook: manage_wpcd_app_group_custom_column
	 *
	 * @param  string $content content.
	 * @param  string $column_name column name.
	 * @param  int    $term_id term_id.
	 */
	public function wpcd_manage_wpcd_app_group_columns_content( $content, $column_name, $term_id ) {
		if ( 'wpcd_color' === $column_name ) {
			$color_code = get_term_meta( $term_id, 'wpcd_group_color', true );
			$color      = ! empty( $color_code ) ? $color_code : '#999999';

			$content = sprintf( '<span class="wpcd-app-server-app-group" style="background-color: %s;">%s</span>', $color, $color );
		}
		return $content;
	}

	/**
	 * Adds meta value when wpcd_app type post is restored from trash.
	 *
	 * Action hook: untrashed_post
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function wpcd_app_untrashed_post( $post_id ) {

		if ( 'wpcd_app' === get_post_type( $post_id ) ) {
			update_post_meta( $post_id, 'wpapp_disconnected', 1 );
		}

	}


	/**
	 * Get all app terms which will going to be excluded
	 * from the app taxonomy metabox.
	 * They will be set up in a global variable defined
	 * in our constructor function - wpcd_exclude_app_group.
	 */
	public function wpcd_exclude_app_group_taxonomy_ids() {
		global $wpcd_exclude_app_group; // This global is defined in our CONSTRUCTOR function at the top of this file.

		if ( 'wpcd_app' === get_post_type() ) {
			// This function defined in the metaboxes_for_taxonomies_for_servers_and_apps file.
			$wpcd_exclude_app_group = $this->wpcd_common_code_server_app_group_metabox( 'wpcd_app_group' );
		}
	}

	/**
	 * Exclude app term ids based on the global variable $wpcd_exclude_app_group
	 * defined in our constructor function.
	 *
	 * @param array $args args.
	 * @param array $taxonomies taxonomies.
	 */
	public function wpcd_exclude_from_app_term_args( $args, $taxonomies ) {
		global $wpcd_exclude_app_group; // This global is defined in our CONSTRUCTOR function at the top of this file.

		if ( in_array( 'wpcd_app_group', $taxonomies ) ) {
			// This function defined in the metaboxes_for_taxonomies_for_servers_and_apps file.
			$args = $this->wpcd_exclude_term_ids_for_server_app_group( $args, $wpcd_exclude_app_group );
		}

		return $args;
	}

}
