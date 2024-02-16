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

		// Include Styles & Scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'wpcd_app_admin_enqueue_styles_and_scripts' ) );

		// Action hook to load options for server & app owners filter.
		add_action( 'wp_ajax_wpcd_load_server_app_owners_options', array( $this, 'wpcd_load_server_app_owners_options' ) );

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
		$columns['wpcd_server_ipv6']     = 'wpcd_server_ipv6';
		$columns['wpcd_server_provider'] = 'wpcd_server_provider';
		$columns['wpcd_server_region']   = 'wpcd_server_region';
		$columns['wpcd_owner']           = 'wpcd_owner';
		$columns['wpcd_app_group']       = 'wpcd_app_group';

		return $columns;
	}

	/**
	 * Register styles and scripts in the admin area for app screens.
	 *
	 * @param string $hook hook.
	 */
	public function wpcd_app_admin_enqueue_styles_and_scripts( $hook ) {
		$screen = get_current_screen();

		if ( 'wpcd_app' === $screen->post_type ) {

			wp_enqueue_style( 'wpcd-select2-css', wpcd_url . 'assets/css/select2.min.css', array(), wpcd_scripts_version );

			wp_enqueue_script( 'wpcd-select2-js', wpcd_url . 'assets/js/select2.min.js', array( 'jquery' ), wpcd_scripts_version, true );

			wp_enqueue_style( 'wpcd-app-admin-css', wpcd_url . 'assets/css/wpcd-app-admin.css', array(), wpcd_scripts_version );

			wp_enqueue_script( 'wpcd-app-admin-js', wpcd_url . 'assets/js/wpcd-app-admin.js', array( 'jquery' ), wpcd_scripts_version, false );

			wp_localize_script(
				'wpcd-app-admin-js',
				'app_owner_params',
				apply_filters(
					'wpcd_app_script_args',
					array(
						'i10n' => array(
							'nonce'                    => wp_create_nonce( 'wpcd-server-app-owners-selection' ),
							'action'                   => 'wpcd_load_server_app_owners_options',
							'server_post_type'         => 'wpcd_app_server',
							'server_field_key'         => 'wpcd_server_owner',
							'server_first_option'      => __( 'All Server Owners', 'wpcd' ),
							'app_post_type'            => 'wpcd_app',
							'app_filter_key'           => 'wpcd_app_owner',
							'app_first_option'         => __( 'All App Owners', 'wpcd' ),
							'no_owners_found_msg'      => __( 'No owners found.', 'wpcd' ),
							'search_owner_placeholder' => __( 'Search owner here', 'wpcd' ),
						),
					),
					'wpcd-app-admin-js'
				)
			);

		}
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
				// Display the name of the server.
				// Start with getting the post id of the server.
				$server_post_id = get_post_meta( $post_id, 'parent_post_id', true );

				if ( true === (bool) wpcd_get_option( 'wpcd_hide_app_list_server_name_in_server_column' ) && ( ! wpcd_is_admin() ) ) {
					// do nothing, only admins are allowed to see this data.
				} else {
					// Get server title.
					$server_title = wp_kses_post( get_post( $server_post_id )->post_title );

					// Show the server title - with a link if the user is able to edit it otherwise without the link.
					$user_id = get_current_user_id();
					if ( wpcd_user_can( $user_id, 'view_server', $server_post_id ) || get_post( $server_post_id )->post_author === $user_id ) {
						$display_name = sprintf( '<a href="%s">' . $server_title . '</a>', ( is_admin() ? get_edit_post_link( $server_post_id ) : get_permalink( $server_post_id ) ) );
					} else {
						$display_name = $server_title;
					}

					if ( is_admin() ) {
						// Only need name in wp-admin area.
						$value = $this->wpcd_column_wrap_string_with_span_and_class( $display_name, 'server_title', 'left' );
						$value = $this->wpcd_column_wrap_string_with_div_and_class( $value, 'server_title' );
					} else {
						// Frontend need label and name.
						$value2  = $this->wpcd_column_wrap_string_with_span_and_class( __( 'Name: ', 'wpcd' ), 'server_name', 'left' );
						$value2 .= $this->wpcd_column_wrap_string_with_span_and_class( $display_name, 'server_name', 'right' );
						$value  .= $this->wpcd_column_wrap_string_with_div_and_class( $value2, 'server_name' );
					}
				}

				// Server post id.
				// *** removing for now to save vertical space.
				// $value = $value . '<br />' . __( 'id: ', 'wpcd' ) . (string) $server_post_id; .

				// server provider.
				if ( true === (bool) wpcd_get_option( 'wpcd_hide_app_list_provider_in_server_column' ) && ( ! wpcd_is_admin() ) ) {
					// do nothing, only admins are allowed to see this data.
				} else {
					$value   = empty( $value ) ? $value : $value;
					$value2  = $this->wpcd_column_wrap_string_with_span_and_class( __( 'Provider: ', 'wpcd' ), 'server_provider', 'left' );
					$value2 .= $this->wpcd_column_wrap_string_with_span_and_class( WPCD()->wpcd_get_cloud_provider_desc( $this->get_server_meta_value( $post_id, 'wpcd_server_provider' ) ), 'server_provider', 'right' );
					$value  .= $this->wpcd_column_wrap_string_with_div_and_class( $value2, 'server_provider' );
				}

				// server region.
				if ( true === (bool) wpcd_get_option( 'wpcd_hide_app_list_region_in_server_column' ) && ( ! wpcd_is_admin() ) ) {
					// do nothing, only admins are allowed to see this data.
				} else {
					$value2  = $this->wpcd_column_wrap_string_with_span_and_class( __( 'Region: ', 'wpcd' ), 'region', 'left' );
					$value2 .= $this->wpcd_column_wrap_string_with_span_and_class( $this->get_server_meta_value( $post_id, 'wpcd_server_region' ), 'region', 'right' );
					$value  .= $this->wpcd_column_wrap_string_with_div_and_class( $value2, 'server_region' );
				}

				// ipv4.
				if ( is_admin() ) {
					$value2 = $this->wpcd_column_wrap_string_with_span_and_class( __( 'ipv4: ', 'wpcd' ), 'ipv4', 'left' );
				} else {
					$value2 = $this->wpcd_column_wrap_string_with_span_and_class( __( 'IPv4: ', 'wpcd' ), 'ipv4', 'left' );
				}
				$get_ipv4 = $this->wpcd_column_wrap_string_with_span_and_class( $this->get_server_meta_value( $post_id, 'wpcd_server_ipv4' ), 'ipv4', 'right' );
				if ( is_admin() ) {
					$value2 .= wpcd_wrap_clipboard_copy( $get_ipv4 );
				} else {
					$value2 .= wpcd_wrap_clipboard_copy( $get_ipv4, false );
				}
				$value .= $this->wpcd_column_wrap_string_with_div_and_class( $value2, 'ipv4' );

				// ipv6.
				if ( wpcd_get_early_option( 'wpcd_show_ipv6' ) ) {
					$ipv6 = $this->get_server_meta_value( $post_id, 'wpcd_server_ipv6' );
					if ( is_admin() ) {
						$value2 = $this->wpcd_column_wrap_string_with_span_and_class( __( 'ipv6: ', 'wpcd' ), 'ipv6', 'left' );
					} else {
						$value2 = $this->wpcd_column_wrap_string_with_span_and_class( __( 'IPv6: ', 'wpcd' ), 'ipv6', 'left' );
					}
					$get_ipv6 = $this->wpcd_column_wrap_string_with_span_and_class( $this->get_server_meta_value( $post_id, 'wpcd_server_ipv6' ), 'ipv6', 'right' );
					if ( is_admin() ) {
						$value2 .= wpcd_wrap_clipboard_copy( $get_ipv6 );
					} else {
						$value2 .= wpcd_wrap_clipboard_copy( $get_ipv6, false );
					}
					$value .= $this->wpcd_column_wrap_string_with_div_and_class( $value2, 'ipv6' );
				}

				// Add hook here so that other apps can insert data.
				$hooked_value = apply_filters( 'wpcd_app_admin_list_server_column_before_apps_link', '', $post_id );
				if ( ! empty( $hooked_value ) ) {
					$value .= $hooked_value;
				}

				// Show a link that takes you to a list of apps on the server.
				if ( true === (bool) wpcd_get_option( 'wpcd_hide_app_list_appslink_in_server_column' ) && ( ! wpcd_is_admin() ) ) {
					// do nothing, only admins are allowed to see this data.
				} else {
					if ( is_admin() ) {
						$url = admin_url( 'edit.php?post_type=wpcd_app&server_id=' . (string) $server_post_id );
					} else {
						$url = get_permalink( WPCD_WORDPRESS_APP_PUBLIC::get_apps_list_page_id() ) . '?server_id=' . (string) $server_post_id;
					}
					$value2 = sprintf( '<a href="%s">%s</a>', $url, __( 'Apps on this server', 'wpcd' ) );
					$value2 = $this->wpcd_column_wrap_string_with_span_and_class( $value2, 'apps_on_server', 'left' );
					$value .= $this->wpcd_column_wrap_string_with_div_and_class( $value2, 'apps_on_server' );
				}

				// Add hook here so that other apps can insert data.
				$hooked_value = apply_filters( 'wpcd_app_admin_list_server_column_after_apps_link', '', $post_id );
				if ( ! empty( $hooked_value ) ) {
					$value .= $hooked_value;
				}

				break;

			case 'wpcd_server_ipv4':
				// Copy IP.
				$copy_app_ipv4 = wpcd_wrap_clipboard_copy( $this->get_server_meta_value( $post_id, 'wpcd_server_ipv4' ) );
				// Display the ip(v4) of the server.
				$value = $copy_app_ipv4;
				break;

			case 'wpcd_server_ipv6':
				// Copy IP.
				$copy_app_ipv6 = wpcd_wrap_clipboard_copy( $this->get_server_meta_value( $post_id, 'wpcd_server_ipv6' ) );
				// Display the ip(v6) of the server.
				$value = $copy_app_ipv6;
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
				if ( ! empty( get_post( $post_id )->post_author ) ) {
					$app_owner = esc_html( get_user_by( 'ID', get_post( $post_id )->post_author )->user_login );
				} else {
					$app_owner = __( 'Unable to get author/owner', 'wpcd' );
				}
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

			case 'wpcd_app_health':
				// Nothing here - instead individual app classes will use this filter to populate data about the app.
				// This way the list can show data about different apps.
				$value = apply_filters( 'wpcd_app_admin_list_app_health_column', $value, $post_id );

				if ( empty( $value ) ) {
					$value = 'No data for this app';
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

		// Title.
		$show_it = true;
		if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_app_title_in_app_list' ) ) ) ) {
			$show_it = false;
		}
		if ( ! $show_it ) {
			unset( $defaults['title'] );
		} else {
			if ( ! is_admin() ) {
				// Change the column title for the front-end.
				$defaults['title'] = __( 'Site', 'wpcd' );
			}
		}

		// App Type.
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_app_type' ) ) ) {
			$defaults['wpcd_app_type'] = __( 'App Type', 'wpcd' );
		}

		// Short Description.
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_short_desc' ) ) ) {
			$show_it = true;
			if ( ! is_admin() && ! ( boolval( wpcd_get_option( 'wordpress_app_fe_show_description_in_app_list' ) ) ) ) {
				$show_it = false;
			}
			if ( $show_it ) {
				$defaults['wpcd_app_short_desc'] = __( 'Description', 'wpcd' );
			}
		}

		// App Group.
		$show_it = true;
		if ( ! is_admin() && ! ( boolval( wpcd_get_option( 'wordpress_app_fe_show_app_group_in_app_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_app_group'] = __( 'App Group', 'wpcd' );
		}

		// App Summary.
		$show_it = true;
		if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_app_summary_in_app_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_app_summary'] = __( 'App Summary', 'wpcd' );
		}

		// App Health.
		$show_it = true;
		if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_app_health_in_app_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			if ( boolval( wpcd_get_option( 'wpcd_show_app_list_health' ) ) ) {
				$defaults['wpcd_app_health'] = __( 'App Health', 'wpcd' );
			}
		}

		// Server Data.
		$show_it = true;
		if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_server_in_app_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_server'] = __( 'Server', 'wpcd' );
		}

		// IPv4 & IPv6.
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_ipv4' ) ) ) {
			$defaults['wpcd_server_ipv4'] = __( 'IPv4', 'wpcd' );
			if ( boolval( wpcd_get_option( 'wpcd_show_ipv6' ) ) ) {
				// Assume if you want to show IPv4 as a separate column in the list then you also want to show IPv6 as a separate column as well.
				$defaults['wpcd_server_ipv6'] = __( 'IPv6', 'wpcd' );
			}
		}

		// Provider.
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_provider' ) ) ) {
			$defaults['wpcd_server_provider'] = __( 'Provider', 'wpcd' );
		}

		// Region.
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_region' ) ) ) {
			$defaults['wpcd_server_region'] = __( 'Region', 'wpcd' );
		}

		// Owners.
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_owner' ) ) ) {
			$show_it = false;

			if ( wpcd_is_admin() ) {
				$show_it = true;
			}

			if ( ! wpcd_is_admin() && boolval( wpcd_get_option( 'wpcd_hide_app_list_owner_non_admins' ) ) ) {
				$show_it = false;
			}

			if ( ! is_admin() && ! ( boolval( wpcd_get_option( 'wordpress_app_fe_show_owner_in_app_list' ) ) ) ) {
				$show_it = false;
			}

			if ( $show_it ) {
				$defaults['wpcd_owner'] = __( 'Owners', 'wpcd' );
			}
		}

		// Date.
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_date' ) ) ) {
			$defaults['date'] = __( 'Date', 'wpcd' );
		}

		// Team.
		if ( boolval( wpcd_get_option( 'wpcd_show_app_list_team' ) ) ) {
			$show_it = true;
			if ( ! is_admin() && ! ( boolval( wpcd_get_option( 'wordpress_app_fe_show_teams_in_app_list' ) ) ) ) {
				$show_it = false;
			}
			if ( $show_it ) {
				$defaults['wpcd_assigned_teams'] = __( 'Teams', 'wpcd' );
			}
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
		$nonce_name   = sanitize_text_field( filter_input( INPUT_POST, 'app_meta', FILTER_UNSAFE_RAW ) );
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

		$app_post_title      = sanitize_text_field( filter_input( INPUT_POST, 'app_post_title', FILTER_UNSAFE_RAW ) );
		$wpcd_app_type       = sanitize_text_field( filter_input( INPUT_POST, 'app_type', FILTER_UNSAFE_RAW ) );
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
	 * Return prompt messages while deleting/restoring an app
	 *
	 * @return array
	 */
	public function wpcd_app_trash_prompt_messages() {
		return array(
			'delete'  => __( 'Are you sure? This will only delete the data from our database.  The application itself will remain on your server. To remove a WordPress app from the server, cancel this operation and use the REMOVE SITE option under the MISC tab.', 'wpcd' ),
			'restore' => __( 'Please note: Restoring this item will not necessarily restore your app on the server. This item will likely become an orphaned/ghost item - i.e: it will not have a connection to any app or server.', 'wpcd' ),
		);
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

		$messages = $this->wpcd_app_trash_prompt_messages();
		$screen   = get_current_screen();
		if ( in_array( $screen->id, array( 'edit-wpcd_app', 'wpcd_app' ), true ) ) {
			$prompt_message = isset( $messages['delete'] ) ? $messages['delete'] : '';
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
			$prompt_message = isset( $messages['restore'] ) ? $messages['restore'] : '';
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

		if ( ( is_admin() && 'edit.php' === $pagenow && $typenow === $post_type ) || WPCD_WORDPRESS_APP_PUBLIC::is_apps_list_page() ) {

			$apps = $this->generate_meta_dropdown( $post_type, 'app_type', __( 'All App Types', 'wpcd' ) );
			echo $apps;

			if ( current_user_can( 'wpcd_manage_servers' ) ) {
				$servers = $this->generate_server_dropdown( __( 'All Servers', 'wpcd' ) );
				echo wpcd_kses_select( $servers );

				$providers = $this->generate_meta_dropdown( 'wpcd_app_server', 'wpcd_server_provider', __( 'All Providers', 'wpcd' ) );
				echo wpcd_kses_select( $providers );

				$regions = $this->generate_meta_dropdown( 'wpcd_app_server', 'wpcd_server_region', __( 'All Regions', 'wpcd' ) );
				echo wpcd_kses_select( $regions );

				$server_owners = $this->generate_owner_dropdown( 'wpcd_app_server', 'wpcd_server_owner', __( 'All Server Owners', 'wpcd' ) );
				echo wpcd_kses_select( $server_owners );
			}

			$app_owners = $this->generate_owner_dropdown( $post_type, 'wpcd_app_owner', __( 'All App Owners', 'wpcd' ) );
			echo wpcd_kses_select( $app_owners );

			$ipv4 = $this->generate_meta_dropdown( 'wpcd_app_server', 'wpcd_server_ipv4', __( 'All IPv4', 'wpcd' ) );
			echo wpcd_kses_select( $ipv4 );

			if ( wpcd_get_early_option( 'wpcd_show_ipv6' ) ) {
				$ipv6 = $this->generate_meta_dropdown( 'wpcd_app_server', 'wpcd_server_ipv6', __( 'All IPv6', 'wpcd' ) );
				echo wpcd_kses_select( $ipv6 );
			}

			$taxonomy  = 'wpcd_app_group';
			$app_group = $this->generate_term_dropdown( $taxonomy, __( 'App Groups', 'wpcd' ) );
			echo wpcd_kses_select( $app_group );
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

		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ! wpcd_is_admin() ) {

			$qv          = &$query->query_vars;
			$post_status = sanitize_text_field( filter_input( INPUT_GET, 'post_status', FILTER_UNSAFE_RAW ) );
			$post_status = ! empty( $post_status ) ? $post_status : 'private';
			$post__in    = wpcd_get_posts_by_permission( 'view_app', 'wpcd_app', $post_status );

			if ( count( $post__in ) ) {
				$qv['post__in'] = $post__in;
			} else {
				$qv['post__in'] = array( 0 );
			}
		}

		$filter_action = sanitize_text_field( filter_input( INPUT_GET, 'filter_action', FILTER_UNSAFE_RAW ) );
		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ( ! empty( $filter_action ) ) ) {
			$qv = &$query->query_vars;

			// APP TYPE.
			if ( isset( $_GET['app_type'] ) && ! empty( $_GET['app_type'] ) ) {
				$app_type = sanitize_text_field( filter_input( INPUT_GET, 'app_type', FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'app_type',
					'value'   => $app_type,
					'compare' => '=',
				);
			}

			// SERVER.
			$_wpcd_app_server = is_admin() ? 'wpcd_app_server_dd' : '_wpcd_app_server_dd';
			if ( isset( $_GET[ $_wpcd_app_server ] ) && ! empty( $_GET[ $_wpcd_app_server ] ) ) {
				$wpcd_app_server = sanitize_text_field( filter_input( INPUT_GET, $_wpcd_app_server, FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'parent_post_id',
					'value'   => $wpcd_app_server,
					'compare' => '=',
				);
			}

			// SERVER PROVIDER.
			if ( isset( $_GET['wpcd_server_provider'] ) && ! empty( $_GET['wpcd_server_provider'] ) ) {
				$wpcd_server_provider = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_server_provider', FILTER_UNSAFE_RAW ) );

				$parents = $this->get_app_server_ids( 'wpcd_server_provider', $wpcd_server_provider );

				$qv['meta_query'][] = array(
					'field' => 'parent_post_id',
					'value' => $parents,
				);
			}

			// REGION.
			if ( isset( $_GET['wpcd_server_region'] ) && ! empty( $_GET['wpcd_server_region'] ) ) {
				$wpcd_server_region = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_server_region', FILTER_UNSAFE_RAW ) );

				$parents = $this->get_app_server_ids( 'wpcd_server_region', $wpcd_server_region );

				$qv['meta_query'][] = array(
					'field' => 'parent_post_id',
					'value' => $parents,
				);
			}

			// SERVER OWNER.
			if ( isset( $_GET['wpcd_server_owner'] ) && ! empty( $_GET['wpcd_server_owner'] ) ) {
				$wpcd_server_owner = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_server_owner', FILTER_UNSAFE_RAW ) );

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
				$wpcd_app_owner = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_app_owner', FILTER_UNSAFE_RAW ) );

				$qv['author'] = $wpcd_app_owner;

			}

			// IPv4.
			if ( isset( $_GET['wpcd_server_ipv4'] ) && ! empty( $_GET['wpcd_server_ipv4'] ) ) {
				$wpcd_server_ipv4 = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_server_ipv4', FILTER_UNSAFE_RAW ) );

				$parents = $this->get_app_server_ids( 'wpcd_server_ipv4', $wpcd_server_ipv4 );

				$qv['meta_query'][] = array(
					'field' => 'parent_post_id',
					'value' => $parents,
				);
			}

			// IPv6.
			if ( isset( $_GET['wpcd_server_ipv6'] ) && ! empty( $_GET['wpcd_server_ipv6'] ) ) {
				$wpcd_server_ipv6 = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_server_ipv6', FILTER_UNSAFE_RAW ) );

				$parents = $this->get_app_server_ids( 'wpcd_server_ipv6', $wpcd_server_ipv6 );

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

		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ! empty( $_GET['server_id'] ) && empty( $filter_action ) ) {

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

		// if ( is_admin() && $query->is_main_query() && 'wpcd_app' === $query->query['post_type'] && 'edit.php' === $pagenow && ! empty( $_GET['team_id'] ) && empty( $filter_action ) ) {
		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ! empty( $_GET['team_id'] ) && empty( $filter_action ) ) {
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

		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ! empty( $_GET['wpcd_app_group'] ) && empty( $filter_action ) ) {

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

				// add a checkbox field to remove all delete links from the screen.
				array(
					'desc' => __( 'Check this box to remove all delete links from the screen - it will prevent this app from being accidentally deleted.', 'wpcd' ),
					'id'   => 'wpcd_app_delete_protection',
					'type' => 'checkbox',
					'std'  => $checked,
				),

			),
		);

		// Register the metabox for site expiration.
		$metaboxes[] = array(
			'id'       => 'wpcd_app_site_expiration_metabox',
			'title'    => __( 'Site Expiration (UTC +0)', 'wpcd' ),
			'pages'    => array( 'wpcd_app' ), // displays on wpcd_app post type only.
			'context'  => 'side',
			'priority' => 'low',
			'fields'   => array(

				// Explantion field.
				array(
					'type' => 'custom_html',
					'std'  => __( 'You can control what happens when a site expires in SETTINGS.', 'wpcd' ),
				),
				// add a date-time field for site expiration.
				array(
					'desc'       => __( 'When does this site expire?', 'wpcd' ),
					'id'         => 'wpcd_app_expires',
					'type'       => 'datetime',
					'js_options' => array(
						'stepMinute'      => 1,
						'showTimepicker'  => true,
						'controlType'     => 'select',
						'showButtonPanel' => false,
						'oneLine'         => true,
					),
					'inline'     => false,
					'timestamp'  => true,
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
	 *                      Another tricky place is BULK SITE delete. If you add
	 *                      additional delete functions here (eg: deleting related posts)
	 *                      then the bulk site delete functions in tabs/misc.php will
	 *                      need to be redone.  Make sure you fully test bulk site delete
	 *                      with any changes you make here!
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function wpcd_app_delete_post( $post_id, $return = false ) {

		$success = true;

		// No permissions check if we're running tasks via cron. eg: bulk deletes triggered via pending logs.
		if ( true === wp_doing_cron() || true === wpcd_is_doing_cron() ) {
			return true;
		}

		if ( get_post_type( $post_id ) === 'wpcd_app' && ! wpcd_is_admin() ) {
			$user_id     = (int) get_current_user_id();
			$post_author = (int) get_post( $post_id )->post_author;
			if ( ! wpcd_user_can( $user_id, 'delete_app_record', $post_id ) && $post_author !== $user_id ) {
				$success = false;
			}
		}

		if ( $return ) {
			return $success;
		}

		if ( ! $success ) {
			wp_die( esc_html( __( 'You don\'t have permission to delete this post.', 'wpcd' ) ) );
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
				self::wpcd_app_do_activate_things();
				restore_current_blog();
			}
		} else {
			self::wpcd_app_do_activate_things();
		}
	}

	/**
	 * Registers the custom post type and taxonomy
	 * Creating custom taxonomy terms after execution of this function
	 */
	public static function wpcd_app_do_activate_things() {
		self::wpcd_app_register_post_and_taxonomy();
		self::wpcd_app_create_default_taxonomy_terms();
		self::wpcd_plugin_first_time_activate_check();
	}

	/**
	 * Registers the custom post type and taxonomy
	 * Creating custom taxonomy terms after execution of this function
	 */
	public static function wpcd_app_register_post_and_taxonomy() {

		// If a user can manage apps but cannot manage servers, we need to make the parent menu something other than the server CPT.
		if ( current_user_can( 'wpcd_manage_apps' ) && ( ! current_user_can( 'wpcd_manage_servers' ) ) ) {
			$show_in_menu = true;
			$menu_name    = defined( 'WPCD_MENU_NAME' ) ? WPCD_MENU_NAME : _x( 'WPCloudDeploy', 'Admin Menu text', 'wpcd' );
			$menu_icon    = 'data:image/svg+xml;base64,' . base64_encode( '<svg fill="black" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="20px" height="20px"><path fill="black" d="M 20 9 C 18.355469 9 17 10.355469 17 12 L 17 68 C 17 69.644531 18.355469 71 20 71 L 60 71 C 61.644531 71 63 69.644531 63 68 L 63 12 C 63 10.355469 61.644531 9 60 9 Z M 20 11 L 60 11 C 60.566406 11 61 11.433594 61 12 L 61 68 C 61 68.566406 60.566406 69 60 69 L 20 69 C 19.433594 69 19 68.566406 19 68 L 19 12 C 19 11.433594 19.433594 11 20 11 Z M 24 16 L 24 42 L 56 42 L 56 16 Z M 26 18 L 54 18 L 54 24 L 26 24 Z M 50 20 C 49.449219 20 49 20.449219 49 21 C 49 21.550781 49.449219 22 50 22 C 50.550781 22 51 21.550781 51 21 C 51 20.449219 50.550781 20 50 20 Z M 26 26 L 54 26 L 54 32 L 26 32 Z M 50 28 C 49.449219 28 49 28.449219 49 29 C 49 29.550781 49.449219 30 50 30 C 50.550781 30 51 29.550781 51 29 C 51 28.449219 50.550781 28 50 28 Z M 26 34 L 54 34 L 54 40 L 26 40 Z M 50 36 C 49.449219 36 49 36.449219 49 37 C 49 37.550781 49.449219 38 50 38 C 50.550781 38 51 37.550781 51 37 C 51 36.449219 50.550781 36 50 36 Z M 25 47 C 24.449219 47 24 47.449219 24 48 C 24 48.550781 24.449219 49 25 49 C 25.550781 49 26 48.550781 26 48 C 26 47.449219 25.550781 47 25 47 Z M 25 51 C 24.449219 51 24 51.449219 24 52 C 24 52.550781 24.449219 53 25 53 C 25.550781 53 26 52.550781 26 52 C 26 51.449219 25.550781 51 25 51 Z M 40 52 C 37.800781 52 36 53.800781 36 56 C 36 58.199219 37.800781 60 40 60 C 42.199219 60 44 58.199219 44 56 C 44 53.800781 42.199219 52 40 52 Z M 40 54 C 41.117188 54 42 54.882813 42 56 C 42 57.117188 41.117188 58 40 58 C 38.882813 58 38 57.117188 38 56 C 38 54.882813 38.882813 54 40 54 Z M 25 55 C 24.449219 55 24 55.449219 24 56 C 24 56.550781 24.449219 57 25 57 C 25.550781 57 26 56.550781 26 56 C 26 55.449219 25.550781 55 25 55 Z M 25 59 C 24.449219 59 24 59.449219 24 60 C 24 60.550781 24.449219 61 25 61 C 25.550781 61 26 60.550781 26 60 C 26 59.449219 25.550781 59 25 59 Z M 25 63 C 24.449219 63 24 63.449219 24 64 C 24 64.550781 24.449219 65 25 65 C 25.550781 65 26 64.550781 26 64 C 26 63.449219 25.550781 63 25 63 Z"/></svg>' );
		} else {
			$show_in_menu = 'edit.php?post_type=wpcd_app_server';
			$menu_name    = _x( 'APPs', 'Admin Menu text', 'wpcd' );
			$menu_icon    = '';
		}

		$all_apps_label = defined( 'WPCD_APP_MENU_NAME' ) ? WPCD_APP_MENU_NAME : _x( 'All Apps', 'Admin Menu text', 'wpcd' );

		$create_posts = 'do_not_allow';
		if ( wpcd_is_admin() ) {
			$create_posts = 'wpcd_manage_all';  // This ensures that the ADD NEW APP RECORD button shows for the admin (since all admins will get the wpcd_manage_all capability).
		}

		register_post_type(
			'wpcd_app',
			array(
				'labels'              => array(
					'name'                  => _x( 'APPs', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'APP', 'Post type singular name', 'wpcd' ),
					'menu_name'             => $menu_name,
					'name_admin_bar'        => _x( 'APPs', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => _x( 'Add New App Record', 'Add New Button', 'wpcd' ),
					'edit_item'             => __( 'Edit APP', 'wpcd' ),
					'view_item'             => __( 'View APP', 'wpcd' ),
					'all_items'             => $all_apps_label,
					'search_items'          => __( 'Search APPs', 'wpcd' ),
					'not_found'             => __( 'No Applications were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Applications were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter APPs list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'APPs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'APPs list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => $show_in_menu,
				'menu_position'       => 10,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'menu_icon'           => $menu_icon,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
				'hierarchical'        => false,
				'supports'            => array( '' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts'           => $create_posts,
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
			'show_in_menu'      => false,
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
	 * Enable the options on first-time activation of the plugin.
	 */
	public static function wpcd_plugin_first_time_activate_check() {
		$plugin_activated = get_option( 'wpcd_plugin_first_time_activated' );

		$wpcd_settings = get_option( 'wpcd_settings' );

		// Check if plugin is being activating first time.
		if ( empty( $plugin_activated ) ) {
			$wpcd_settings['wordpress_app_servers_activate_callbacks']      = 1;
			$wpcd_settings['wordpress_app_servers_activate_config_backups'] = 1;
			$wpcd_settings['wordpress_app_servers_refresh_services']        = 1;

			// Set defaults for brand colors.
			$wpcd_settings['wordpress_app_primary_brand_color']               = WPCD_PRIMARY_BRAND_COLOR;
			$wpcd_settings['wordpress_app_secondary_brand_color']             = WPCD_SECONDARY_BRAND_COLOR;
			$wpcd_settings['wordpress_app_tertiary_brand_color']              = WPCD_TERTIARY_BRAND_COLOR;
			$wpcd_settings['wordpress_app_accent_background_color']           = WPCD_ACCENT_BG_COLOR;
			$wpcd_settings['wordpress_app_medium_accent_background_color']    = WPCD_MEDIUM_ACCENT_BG_COLOR;
			$wpcd_settings['wordpress_app_medium_background_color']           = WPCD_MEDIUM_BG_COLOR;
			$wpcd_settings['wordpress_app_light_background_color']            = WPCD_LIGHT_BG_COLOR;
			$wpcd_settings['wordpress_app_alternate_accent_background_color'] = WPCD_ALTERNATE_ACCENT_BG_COLOR;

			// Set auto trim log values.
			$wpcd_settings['auto_trim_notification_log_limit']      = 999;
			$wpcd_settings['auto_trim_notification_sent_log_limit'] = 999;
			$wpcd_settings['auto_trim_ssh_log_limit']               = 999;
			$wpcd_settings['auto_trim_command_log_limit']           = 999;
			$wpcd_settings['auto_trim_pending_log_limit']           = 999;
			$wpcd_settings['auto_trim_error_log_limit']             = 999;

			// Update the settings options.
			update_option( 'wpcd_settings', $wpcd_settings );

			// Update the option for first time activation.
			update_option( 'wpcd_plugin_first_time_activated', 1 );
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

	/**
	 * Takes a string and wraps it with a span and a class related to the column name.
	 *
	 * For example, if we get a string such as "Domain:" we might
	 * return <span class="wpcd-column-label-domain">Domain:</span>.
	 *
	 * Calls the global function wpcd_wrap_string_with_span_and_class
	 * defined in the functions.php which does the actual wrapping.
	 *
	 * @param string $string The string to wrap.
	 * @param string $column The column name.
	 * @param string $align Valid values are 'left' and 'right'.
	 *
	 * @return string
	 */
	public function wpcd_column_wrap_string_with_span_and_class( $string, $column, $align ) {

		if ( 'left' === $align ) {
			return wpcd_wrap_string_with_span_and_class( $string, $column, 'app-col-element-label' );
		} else {
			return wpcd_wrap_string_with_span_and_class( $string, $column, 'app-col-element-value' );
		}

	}

	/**
	 * Takes a string and wraps it with a div.
	 *
	 * For example, if we get a string such as "Domain:" we might
	 * return <div class="wpcd-column-label-domain">Domain:</div>.
	 *
	 * @param string $string The string to wrap.
	 * @param string $column The column name.
	 *
	 * @return string
	 */
	public function wpcd_column_wrap_string_with_div_and_class( $string, $column ) {

		return wpcd_wrap_string_with_div_and_class( $string, $column, 'app-col-element-wrap' );

	}

	/**
	 * Load the options for server or app owner filter.
	 *
	 * Action hook: wp_ajax_wpcd_load_server_app_owners_options
	 *
	 * @return void
	 */
	public function wpcd_load_server_app_owners_options() {
		// Nonce check.
		check_ajax_referer( 'wpcd-server-app-owners-selection', 'nonce' );

		// Permissions check by user to load sites which user has access to view.
		$current_user_id = get_current_user_id();

		$post_type         = sanitize_text_field( filter_input( INPUT_POST, 'post_type', FILTER_UNSAFE_RAW ) );
		$field_key         = sanitize_text_field( filter_input( INPUT_POST, 'field_key', FILTER_UNSAFE_RAW ) );
		$first_option      = sanitize_text_field( filter_input( INPUT_POST, 'first_option', FILTER_UNSAFE_RAW ) );
		$search_term       = sanitize_text_field( filter_input( INPUT_POST, 'search_term', FILTER_UNSAFE_RAW ) );
		$search_term       = trim( $search_term );
		$owner_options_arr = array( '0' => __( $first_option, 'wpcd' ) );

		if ( ! empty( $search_term ) ) {
			global $wpdb;

			$post_status = 'private';

			if ( 'wpcd_app_server' === $post_type ) {
				$permission = 'view_server';
			} elseif ( 'wpcd_app' === $post_type ) {
				$permission = 'view_app';
			}

			$posts = wpcd_get_posts_by_permission( $permission, $post_type, $post_status );

			if ( ! $posts || empty( $posts ) ) {
				return;
			}

			if ( count( $posts ) == 0 ) {
				return '';
			}

			$posts_placeholder = implode( ', ', array_fill( 0, count( $posts ), '%d' ) );
			$query_fields      = array_merge( array( $post_type, $post_status ), $posts );

			$sql   = $wpdb->prepare( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s  AND ID IN ( " . $posts_placeholder . ' ) ORDER BY post_author', $query_fields );
			$posts = $wpdb->get_results( $sql );

			$owners = array(); // Setup initial blank array of owners.

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $p ) {
					if ( in_array( $p->post_author, $owners ) ) {
						continue;
					}
					$owners[]         = $p->post_author;
					$post_author_id   = $p->post_author;
					$post_author_name = empty( $post_author_id ) ? __( 'No Author or Owner provided.', 'wpcd' ) : esc_html( get_user_by( 'ID', $post_author_id )->user_login );

					// Match search term with owner name.
					if ( strpos( $post_author_name, $search_term ) !== false ) {
						$owner_options_arr[ $post_author_id ] = $post_author_name;
					}
				}
			}
		}

		$result = array(
			'items' => $owner_options_arr,
		);

		wp_send_json_success( $result );

		exit;
	}

}
