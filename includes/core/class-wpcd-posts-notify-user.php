<?php
/**
 * WPCD_NOTIFY_USER class for user notifications.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCD_NOTIFY_USER
 */
class WPCD_NOTIFY_USER extends WPCD_Posts_Base {

	/* Include traits */
	use wpcd_get_set_post_type;

	/**
	 * WPCD_NOTIFY_USER - instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_NOTIFY_USER constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->register();  // register the custom post type - wpcd_notify_user.
		$this->hooks();     // register hooks to make the custom post type do things.
	}

	/**
	 * Add all the hook inside the this private method.
	 */
	private function hooks() {
		// Action hook to add values in new columns.
		add_action( 'manage_wpcd_notify_user_posts_custom_column', array( $this, 'notify_user_table_content' ), 10, 2 );

		// Filter hook to add new columns on user notifications screen.
		add_filter( 'manage_wpcd_notify_user_posts_columns', array( $this, 'notify_user_table_head' ), 10, 1 );

		// Filter hook to add custom meta box.
		add_filter( 'rwmb_meta_boxes', array( $this, 'wpcd_notify_user_register_meta_boxes' ), 10, 1 );

		// Enqueue scripts to add into shortcode page.
		add_action( 'wp_enqueue_scripts', array( $this, 'wpcd_user_notifications_shortcode_scripts' ) );

		// Enqueue admin scripts to add on backend.
		add_action( 'admin_enqueue_scripts', array( $this, 'wpcd_notify_user_admin_enqueue_scripts' ), 10, 1 );

		// Add shortcode to add on frontend page.
		add_shortcode( 'wpcd_user_notifications_form', array( $this, 'wpcd_user_notify_form_content' ) );

		// Action hook to display form of user notifications.
		add_action( 'wp_ajax_wpcd_user_notification_display_form_popup', array( $this, 'wpcd_user_notification_display_form_popup' ) );

		// Action hook to save data of user notifications.
		add_action( 'wp_ajax_wpcd_user_notification_data_save', array( $this, 'wpcd_user_notification_data_save' ) );

		// Action hook to test zapier webhook.
		add_action( 'wp_ajax_wpcd_user_zapier_webhook_test', array( $this, 'wpcd_user_zapier_webhook_test' ) );

		// Action hook to delete data of notifications.
		add_action( 'wp_ajax_wpcd_user_notification_data_delete', array( $this, 'wpcd_user_notification_data_delete' ) );

		// Remove edit and quick-edit from user-notify-list rows.
		add_filter( 'post_row_actions', array( $this, 'wpcd_user_notify_post_row_actions' ), 10, 2 );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpcd_scan_notifications_schedule_events_for_new_site' ), 10, 2 );

		// Admin can add/save notification profile data from backend.
		add_action( 'save_post_wpcd_notify_user', array( $this, 'wpcd_save_notification_user_profile_data' ), 10, 2 );

		// Force the post to become private.
		add_filter( 'wp_insert_post_data', array( $this, 'wpcd_wpcd_notify_user_force_type_private' ), 10, 1 );

		// Action hook to add custom back to list button.
		add_action( 'admin_footer-post.php', array( $this, 'wpcd_user_notifications_backtolist_btn' ) );

		// Display all users in author dropdown.
		add_filter( 'wp_dropdown_users_args', array( $this, 'wpcd_user_notify_display_all_users_dropdown' ), 10, 2 );
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {
		register_post_type(
			'wpcd_notify_user',
			array(
				'labels'              => array(
					'name'                  => _x( 'User Notifications', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'User Notification', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'User Notification', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'User Notification', 'Add New on Toolbar', 'wpcd' ),
					'add_new'               => __( 'New User Notification', 'wpcd' ),
					'add_new_item'          => __( 'New User Notification', 'wpcd' ),
					'edit_item'             => __( 'Edit User Notification', 'wpcd' ),
					'view_item'             => __( 'View User Notification', 'wpcd' ),
					'all_items'             => __( 'Profiles', 'wpcd' ), // Label to signify all items in a submenu link.
					'search_items'          => __( 'Search User Notification', 'wpcd' ),
					'not_found'             => __( 'No User Notifications were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No User Notifications were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter User Notification list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'User Notification list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'User Notification list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
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
				'supports'            => array( 'author' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts'           => true,
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
			)
		);

	}

	/**
	 * To add custom metabox on notify user details screen.
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param array $metaboxes metaboxes.
	 *
	 * @return array
	 */
	public function wpcd_notify_user_register_meta_boxes( $metaboxes ) {

		$prefix = 'wpcd_';

		$user_id = get_current_user_id();

		// Register the metabox fields.
		$metaboxes[] = array(
			'id'       => $prefix . 'notify_user_basic',
			'title'    => __( 'Setup Alert', 'wpcd' ),
			'pages'    => array( 'wpcd_notify_user' ), // displays on wpcd_notify_user post type only.
			'priority' => 'high',
			'fields'   => array(

				array(
					'name' => __( 'Name / Description', 'wpcd' ),
					'id'   => $prefix . 'notify_user_profile_name',
					'type' => 'text',
					'size' => 60,
					'desc' => __( 'Please enter a notification profile name or description.', 'wpcd' ),
				),

				array(
					'type' => 'divider',
				),

				array(
					'name' => __( 'Email Addresses', 'wpcd' ),
					'id'   => $prefix . 'notify_user_email_addresses',
					'type' => 'text',
					'size' => 60,
					'desc' => __( 'Separate multiple email addresses with commas.', 'wpcd' ),
				),

				array(
					'type' => 'divider',
				),

				array(
					'name' => __( 'Slack Webhooks', 'wpcd' ),
					'id'   => $prefix . 'notify_user_slack_webhooks',
					'type' => 'text',
					'size' => 60,
					'desc' => __( 'Separate multiple Slack hooks with commas.', 'wpcd' ),
				),

				array(
					'type' => 'divider',
				),

				array(
					'name' => __( 'Send to zapier', 'wpcd' ),
					'id'   => $prefix . 'notify_user_zapier_send',
					'type' => 'checkbox',
				),

				array(
					'name' => __( 'Zapier Webhooks', 'wpcd' ),
					'id'   => $prefix . 'notify_user_zapier_webhooks',
					'type' => 'text',
					'size' => 60,
					'desc' => __( 'Separate multiple Zapier hooks with commas.', 'wpcd' ),
				),

				array(
					'type'       => 'button',
					'std'        => __( 'TEST', 'wpcd' ),
					'attributes' => array(
						'id'          => 'wpcd-user-zapier-webhook-test',
						'data-action' => 'wpcd_user_zapier_webhook_test',
						'data-nonce'  => wp_create_nonce( 'wpcd-zapier-webhook-test' ),
					),
					'desc'       => __( 'Test zapier webhooks by sending dummy data.', 'wpcd' ),
				),

				array(
					'type' => 'divider',
				),

				array(
					'name'            => 'Select Servers',
					'id'              => $prefix . 'notify_user_servers',
					'type'            => 'select',
					'multiple'        => true,
					'select_all_none' => __( 'Select All | None', 'wpcd' ),
					'options'         => $this->get_user_notify_servers( $user_id ),
				),

				array(
					'type' => 'divider',
				),

				array(
					'name'            => 'Select Sites',
					'id'              => $prefix . 'notify_user_sites',
					'type'            => 'select',
					'multiple'        => true,
					'select_all_none' => __( 'Select All | None', 'wpcd' ),
					'options'         => $this->get_user_notify_sites( $user_id ),
				),

				array(
					'type' => 'divider',
				),

				array(
					'name'            => 'Select Notification Types',
					'id'              => $prefix . 'notify_user_type',
					'type'            => 'select',
					'multiple'        => true,
					'select_all_none' => __( 'Select All | None', 'wpcd' ),
					'options'         => $this->get_user_notify_types(),
				),

				array(
					'type' => 'divider',
				),

				array(
					'name'            => 'Select Notification References',
					'id'              => $prefix . 'notify_user_reference',
					'type'            => 'select',
					'multiple'        => true,
					'select_all_none' => __( 'Select All | None', 'wpcd' ),
					'options'         => $this->get_user_notify_references(),
				),

			),
		);

		return $metaboxes;

	}

	/**
	 * This filter modifies post rows for "wpcd_notify_user"
	 * post rows such as "Edit", "Quick Edit".
	 *
	 * Action hook: post_row_actions
	 *
	 * @param array  $actions actions.
	 * @param object $post post.
	 *
	 * @return array
	 */
	public function wpcd_user_notify_post_row_actions( $actions, $post ) {
		// Permissions check.
		if ( 'wpcd_notify_user' === $post->post_type && ! wpcd_is_admin() ) {
			// Removes "Edit".
			unset( $actions['edit'] );
			// Removes "Quick Edit".
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * print column value.
	 */
	public function notify_user_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_user_notification_type':
				// Display the notification type.
				$value = get_metadata( 'post', $post_id, 'wpcd_notify_user_type', false );
				if ( ! empty( $value ) ) {
					$value = $this->wpcd_show_notification_serialize_data( $value );
				} else {
					$value = '-';
				}
				break;

			case 'wpcd_user_notification_reference':
				// Display the notification reference.
				$value = get_metadata( 'post', $post_id, 'wpcd_notify_user_reference', false );
				if ( ! empty( $value ) ) {
					$value = $this->wpcd_show_notification_serialize_data( $value );
				} else {
					$value = '-';
				}
				break;

			case 'wpcd_user_notification_servers':
				// Display the list of servers.
				$value = get_metadata( 'post', $post_id, 'wpcd_notify_user_servers', false );
				if ( ! empty( $value ) ) {
					$value = $this->wpcd_show_notify_serialize_post_data( $value );
				} else {
					$value = '-';
				}
				break;

			case 'wpcd_user_notification_sites':
				// Display the list of sites.
				$value = get_metadata( 'post', $post_id, 'wpcd_notify_user_sites', false );
				if ( ! empty( $value ) ) {
					$value = $this->wpcd_show_notify_serialize_post_data( $value );
				} else {
					$value = '-';
				}
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
	 * Get user notify meta.
	 *
	 * @param int   $object_id object_id.
	 * @param int   $meta_key meta_key.
	 * @param array $args args.
	 */
	public function get_user_notify_meta( $object_id, $meta_key, $args = false ) {
		if ( is_array( $args ) ) {
			$single = ! empty( $args['single'] );
		} else {
			$single = (bool) $args;
		}

		return get_metadata( 'post', $object_id, $meta_key, $single );
	}

	/**
	 * Add table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function notify_user_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_user_notification_type']      = __( 'Type', 'wpcd' );
		$defaults['wpcd_user_notification_reference'] = __( 'Reference', 'wpcd' );
		$defaults['wpcd_user_notification_servers']   = __( 'Servers', 'wpcd' );
		$defaults['wpcd_user_notification_sites']     = __( 'Sites', 'wpcd' );
		$defaults['date']                             = __( 'Date', 'wpcd' );

		return $defaults;
	}


	/**
	 * Enqueue the scripts if the wpcd_user_notifications_form shortcode is being used
	 */
	public function wpcd_user_notifications_shortcode_scripts() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wpcd_user_notifications_form' ) ) {
			wp_enqueue_style( 'wpcd-bootstrap-css', wpcd_url . 'assets/css/bootstrap.min.css', array(), wpcd_scripts_version );
			wp_enqueue_style( 'wpcd-user-notify-css', wpcd_url . 'assets/css/wpcd-user-notifications-front.css', array(), wpcd_scripts_version );

			$translation_array = array(
				'site_url'   => site_url(),
				'admin_ajax' => admin_url( 'admin-ajax.php' ),
				'i10n'       => array(
					'wpcd_notify_method_empty' => __( 'Please add value in any of the notification methods.', 'wpcd' ),
					'wpcd_notify_alert_delete' => __( 'Are you sure?', 'wpcd' ),
				),
			);
			wp_register_script( 'wpcd-user-notify-js', wpcd_url . 'assets/js/wpcd-user-notifications-front.js', array( 'jquery', 'wp-util' ), wpcd_scripts_version, true );
			wp_localize_script( 'wpcd-user-notify-js', 'wpcdusernotify', $translation_array );
			wp_enqueue_script( 'wpcd-user-notify-js' );

		}
	}

	/**
	 * Enqueue the scripts if the wpcd_notify_user screen
	 *
	 * @param string $hook hook.
	 */
	public function wpcd_notify_user_admin_enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'post-new.php', 'post.php', 'edit.php' ), true ) ) {

			$screen = get_current_screen();
			if ( is_object( $screen ) && 'wpcd_notify_user' === $screen->post_type ) {
				wp_dequeue_script( 'autosave' );

				wp_enqueue_script( 'wpcd-user-notify-admin', wpcd_url . 'assets/js/wpcd-user-notifications-admin.js', array( 'jquery' ), wpcd_version, true );
				wp_localize_script(
					'wpcd-user-notify-admin',
					'notifyparams',
					array(
						'i10n' => array(
							'invalid_emails'       => __( 'Please enter valid email addresses.', 'wpcd' ),
							'invalid_slack'        => __( 'Please enter valid Slack webhooks.', 'wpcd' ),
							'invalid_zapier'       => __( 'Please enter valid Zapier webhooks.', 'wpcd' ),
							'empty_methods'        => __( 'Please add value to at least one of the notification methods - email, Slack or Zapier.', 'wpcd' ),
							'empty_zapier_webhook' => __( 'Please enter the Zapier webhooks.', 'wpcd' ),
							'waiting_msg'          => __( 'Please wait...', 'wpcd' ),
							'empty_servers'        => __( 'Please select one or more servers.', 'wpcd' ),
							'empty_sites'          => __( 'Please select one or more sites.', 'wpcd' ),
							'empty_types'          => __( 'Please select one ore more notification types.', 'wpcd' ),
							'empty_references'     => __(
								'Please select one or more references.',
								'wpcd'
							),
						),
					)
				);

				wp_enqueue_script( 'wpcd-select2-js', wpcd_url . 'assets/js/select2.min.js', array( 'jquery' ), wpcd_scripts_version, false );
				wp_enqueue_style( 'wpcd-select2-css', wpcd_url . 'assets/css/select2.min.css', array(), wpcd_scripts_version );
			}
		}
	}

	/**
	 * Renders the HTML for user notifications form
	 */
	public function wpcd_user_notify_form_content() {

		ob_start();
		require_once wpcd_path . 'includes/templates/notify_user_alert_list.php';
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Display the user notify form popup when click on add new OR edit button
	 */
	public function wpcd_user_notification_display_form_popup() {
		// nonce check.
		check_ajax_referer( 'wpcd-user-notify-form-display', 'nonce' );

		// Permission check - user login.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Sorry, you are not allowed to access this form. Please login to the system to access it.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// User alert id.
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		ob_start();
		require_once wpcd_path . 'includes/templates/notify_user_form_popup.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

		exit;
	}

	/**
	 * Save data to the user notifications
	 */
	public function wpcd_user_notification_data_save() {
		// nonce check.
		check_ajax_referer( 'wpcd-user-notify', 'nonce' );

		// Permissions check.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Sorry, you are not allowed to access this form. Please login to the system to access it.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		$selected_servers    = isset( $_POST['servers'] ) ? wp_unslash( $_POST['servers'] ) : array();
		$selected_sites      = isset( $_POST['sites'] ) ? wp_unslash( $_POST['sites'] ) : array();
		$selected_types      = isset( $_POST['types'] ) ? wp_unslash( $_POST['types'] ) : array();
		$selected_references = isset( $_POST['references'] ) ? wp_unslash( $_POST['references'] ) : array();

		// User alert id.
		$post_id         = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
		$profile_name    = filter_input( INPUT_POST, 'profile_name', FILTER_SANITIZE_STRING );
		$email_addresses = filter_input( INPUT_POST, 'email_addresses', FILTER_SANITIZE_STRING );
		$slack_webhooks  = filter_input( INPUT_POST, 'slack_webhooks', FILTER_SANITIZE_STRING );
		$send_to_zapier  = filter_input( INPUT_POST, 'send_to_zapier', FILTER_SANITIZE_NUMBER_INT );
		$zapier_webhooks = filter_input( INPUT_POST, 'zapier_webhooks', FILTER_SANITIZE_STRING );
		$servers         = filter_var_array( $selected_servers, FILTER_SANITIZE_STRING );
		$sites           = filter_var_array( $selected_sites, FILTER_SANITIZE_STRING );
		$types           = filter_var_array( $selected_types, FILTER_SANITIZE_STRING );
		$references      = filter_var_array( $selected_references, FILTER_SANITIZE_STRING );

		// Permission check - user access for server ids.
		if ( ! empty( $servers ) ) {
			foreach ( $servers as $ser_key => $ser_value ) {
				$this->wpcd_user_notify_server_app_permission( get_current_user_id(), $ser_value );
			}
		}

		// Permission check - user access for sites ids.
		if ( ! empty( $sites ) ) {
			foreach ( $sites as $site_key => $site_value ) {
				$this->wpcd_user_notify_server_app_permission( get_current_user_id(), $site_value );
			}
		}

		// Make array of email addresses.
		$all_emails = str_replace( ' ', '', $email_addresses );
		if ( ! empty( $all_emails ) ) {
			$email_addresses = array();
			$email_addresses = explode( ',', $all_emails );
			$email_addresses = array_filter( $email_addresses );
			ksort( $email_addresses );
			$email_addresses = implode( ',', $email_addresses );
		}

		// make array of slack webhooks.
		$all_webhooks = str_replace( ' ', '', $slack_webhooks );
		if ( ! empty( $all_webhooks ) ) {
			$slack_webhooks = array();
			$slack_webhooks = explode( ',', $all_webhooks );
			$slack_webhooks = array_filter( $slack_webhooks );
			ksort( $slack_webhooks );
			$slack_webhooks = implode( ',', $slack_webhooks );
		}

		// Make array of zapier webhooks.
		$all_zapier_webhooks = str_replace( ' ', '', $zapier_webhooks );
		if ( ! empty( $all_zapier_webhooks ) ) {
			$zapier_webhooks = array();
			$zapier_webhooks = explode( ',', $all_zapier_webhooks );
			$zapier_webhooks = array_filter( $zapier_webhooks );
			ksort( $zapier_webhooks );
			$zapier_webhooks = implode( ',', $zapier_webhooks );
		}

		// get current user details.
		$author_id  = get_current_user_id();
		$usermeta   = get_user_by( 'id', $author_id );
		$user_login = $usermeta->data->user_login;

		// check if data need to be added or whether we need to update an existing user.
		if ( $post_id == 0 ) {
			$post_title = '';
			if ( empty( $profile_name ) ) {
				$post_title = 'Submitted by ' . $user_login;
			} else {
				$post_title = $profile_name;
			}

			// insert user notification form data.
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'wpcd_notify_user',
					'post_status' => 'private',
					'post_title'  => $post_title,
					'post_author' => $author_id,
				)
			);
		} else {
			// check post_id in wpcd_notify_user.
			$notify_args = array(
				'post_type'      => 'wpcd_notify_user',
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'p'              => $post_id,
				'author'         => $author_id,
			);

			$alert_found = get_posts( $notify_args );

			if ( empty( $alert_found ) ) {
				$post_id = '';
			}
		}

		// check for errors.
		if ( ! is_wp_error( $post_id ) && ! empty( $post_id ) ) {
			// update all the fields values.

			if ( empty( $profile_name ) ) {
				$update_profile_name = 'Submitted by ' . $user_login;
			} else {
				$update_profile_name = $profile_name;
			}

			// Update default post title.
			$notify_post = array(
				'ID'         => $post_id,
				'post_title' => $update_profile_name,
			);
			wp_update_post( $notify_post );

			update_post_meta( $post_id, 'wpcd_notify_user_profile_name', $profile_name );
			update_post_meta( $post_id, 'wpcd_notify_user_email_addresses', $email_addresses );
			update_post_meta( $post_id, 'wpcd_notify_user_slack_webhooks', $slack_webhooks );
			update_post_meta( $post_id, 'wpcd_notify_user_zapier_send', $send_to_zapier );

			if ( $send_to_zapier == 1 ) {
				update_post_meta( $post_id, 'wpcd_notify_user_zapier_webhooks', $zapier_webhooks );
			}

			// For types - delete existing values and update new values.
			delete_metadata( 'post', $post_id, 'wpcd_notify_user_type' );
			if ( ! empty( $types ) ) {
				foreach ( $types as $type_key => $type_value ) {
					add_metadata( 'post', $post_id, 'wpcd_notify_user_type', $type_value, false );
				}
			}

			// For references - delete existing values and update new values.
			delete_metadata( 'post', $post_id, 'wpcd_notify_user_reference' );
			if ( ! empty( $references ) ) {
				foreach ( $references as $ref_key => $ref_value ) {
					add_metadata( 'post', $post_id, 'wpcd_notify_user_reference', $ref_value, false );
				}
			}

			// For servers - delete existing values and update new values.
			delete_metadata( 'post', $post_id, 'wpcd_notify_user_servers' );
			if ( ! empty( $servers ) ) {
				foreach ( $servers as $server_key => $server_value ) {
					add_metadata( 'post', $post_id, 'wpcd_notify_user_servers', $server_value, false );
				}
			}

			// For sites - delete existing values and update new values.
			delete_metadata( 'post', $post_id, 'wpcd_notify_user_sites' );
			if ( ! empty( $sites ) ) {
				foreach ( $sites as $site_key => $site_value ) {
					add_metadata( 'post', $post_id, 'wpcd_notify_user_sites', $site_value, false );
				}
			}

			$msg = array( 'msg' => __( 'Notification profile saved.', 'wpcd' ) );
			wp_send_json_success( $msg );
		} else {
			$msg = array( 'msg' => __( 'An error was encountered while attempting to save this data.', 'wpcd' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Test the zapier webhook.
	 */
	public function wpcd_user_zapier_webhook_test() {
		// nonce check.
		check_ajax_referer( 'wpcd-zapier-webhook-test', 'nonce' );

		// Permissions check.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Sorry, you are not allowed to access this form. Please login to the system to access it.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		$zapier_webhooks = filter_input( INPUT_POST, 'zapier_webhooks', FILTER_SANITIZE_STRING );

		$date              = gmdate( 'Y-m-d' );
		$time              = gmdate( 'H:i:s' );
		$msg               = array(
			'text'             => __( 'This is a test message from wpclouddeploy.', 'wpcd' ),
			'username'         => __( 'testuser', 'wpcd' ),
			'user_id'          => __( '12', 'wpcd' ),
			'user_email'       => __( 'testuser@gmail.com', 'wpcd' ),
			'notify_type'      => __( 'warning', 'wpcd' ),
			'notify_reference' => __( 'power', 'wpcd' ),
			'notify_message'   => __( 'The server is shutting down.', 'wpcd' ),
			'server_name'      => __( 'test-server-instance', 'wpcd' ),
			'domain_name'      => __( '123.45.67.890', 'wpcd' ),
			'date'             => $date,
			'time'             => $time,
			'server_id'        => __( '1234567', 'wpcd' ),
			'site_id'          => __( '9876543', 'wpcd' ),
			'first_name'       => __( 'Test', 'wpcd' ),
			'last_name'        => __( 'User', 'wpcd' ),
			'ipv4'             => __( '122.133.144.55', 'wpcd' ),
			'provider'         => __( 'digital-ocean', 'wpcd' ),
		);
		$json_encoded_data = wp_json_encode( $msg );

		$webhooks = explode( ',', $zapier_webhooks );

		if ( is_array( $webhooks ) ) {
			foreach ( $webhooks as $key => $value ) {
				// curl code for send message.
				$c = curl_init( $value );
				curl_setopt( $c, CURLOPT_HEADER, array( 'Content-Type: application/json', 'Content-Length: ' . strlen( $json_encoded_data ) ) );
				curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
				curl_setopt( $c, CURLOPT_POST, 1 );
				curl_setopt( $c, CURLOPT_POST, true );
				curl_setopt( $c, CURLOPT_POSTFIELDS, $json_encoded_data );
				$sent_message = curl_exec( $c );
				$status_code  = curl_getinfo( $c, CURLINFO_HTTP_CODE );
				curl_close( $c );
			}
		}

		if ( 200 !== $status_code ) {
			$msg = array( 'msg' => __( 'Can not able to test added zapier webhooks.', 'wpcd' ) );
			wp_send_json_success( $msg );
		} else {
			$msg = array( 'msg' => __( 'Zapier webhook tested successfully.', 'wpcd' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Delete data of the user notifications
	 */
	public function wpcd_user_notification_data_delete() {
		// nonce check.
		check_ajax_referer( 'wpcd-user-notify-delete', 'nonce' );

		// Permissions check.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Sorry, you are not allowed to access this form. Please login to the system to access it.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// User alert id.
		$post_id   = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
		$author_id = get_current_user_id();

		// check post_id in wpcd_notify_user.
		$notify_args = array(
			'post_type'      => 'wpcd_notify_user',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'p'              => $post_id,
			'author'         => $author_id,
		);

		$alert_found = get_posts( $notify_args );

		if ( ! empty( $alert_found ) ) {
			// delete post data.
			$deleted = wp_delete_post( $post_id, true );

			if ( $deleted ) {
				$msg = array( 'msg' => __( 'Data successfully deleted.', 'wpcd' ) );
				wp_send_json_success( $msg );
			} else {
				$msg = array( 'msg' => __( 'Error in deletion.', 'wpcd' ) );
				wp_send_json_success( $msg );
			}
		} else {
			$msg = array( 'msg' => __( 'Record not found in database.', 'wpcd' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * To display serialize values in backend entries
	 *
	 * @param array $notification_data notification_data.
	 */
	public function wpcd_show_notification_serialize_data( $notification_data ) {
		if ( ! empty( $notification_data ) && is_array( $notification_data ) ) {
			return wpautop( esc_html( implode( ', ', $notification_data ) ) );
		} else {
			return wpautop( esc_html( $notification_data ) );
		}
	}

	/**
	 * To display post title of serialize value in backend entries
	 *
	 * @param array $notification_data notification_data.
	 */
	public function wpcd_show_notify_serialize_post_data( $notification_data ) {
		$servers_list = '';
		if ( ! empty( $notification_data ) && is_array( $notification_data ) ) {
			foreach ( $notification_data as $key => $value ) {
				$post = get_post( $value );
				if ( $post ) {
					$value = $post->post_title; }

				if ( $key != 0 ) {
					$servers_list .= ', ' . $value;
				} else {
					$servers_list .= $value;
				}
			}
		} else {
			$servers_list = $notification_data;
		}
		return $servers_list;
	}


	/**
	 * Returns a filtered array of user notify servers
	 *
	 * @param int $user_id user_id.
	 */
	public function get_user_notify_servers( $user_id ) {

		// Get servers that can be accessible by the user.
		$all_servers_ids = wpcd_get_posts_by_permission( 'view_server', 'wpcd_app_server', 'private', $user_id );

		// Generate an array of all servers that can be accessible by the user.
		$user_servers = array();
		if ( count( $all_servers_ids ) > 0 ) {
			foreach ( $all_servers_ids as $serverkey => $servervalue ) {
				// get server name using id.
				$server_name                  = WPCD_SERVER()->get_server_name( $servervalue );
				$user_servers[ $servervalue ] = $server_name;
			}
		}

		asort( $user_servers );

		return apply_filters( 'wpcd_user_notify_servers', $user_servers );
	}

	/**
	 * Returns a filtered array of user notify sites
	 *
	 * @param int $user_id user id.
	 */
	public function get_user_notify_sites( $user_id ) {
		// Get sites that can be accessible by the user.
		$all_sites_ids = wpcd_get_posts_by_permission( 'view_app', 'wpcd_app', 'private', $user_id );

		// Generate an array of all sites that can be accessible by the user.
		$user_sites = array();
		if ( count( $all_sites_ids ) > 0 ) {
			foreach ( $all_sites_ids as $sitekey => $sitevalue ) {
				// get title using post id.
				$site_name = '';
				$post      = get_post( $sitevalue );
				if ( $post ) {
					$site_name = $post->post_title; }
				$user_sites[ $sitevalue ] = $site_name;
			}
		}

		asort( $user_sites );

		return apply_filters( 'wpcd_user_notify_sites', $user_sites );
	}

	/**
	 * Returns a filtered array of user notify types
	 */
	public function get_user_notify_types() {
		$user_types = array(
			'notice'  => 'Notice',
			'alert'   => 'Alert',
			'warning' => 'Warning',
			'other'   => 'Other',
		);
		return apply_filters( 'wpcd_user_notify_types', $user_types );
	}

	/**
	 * Returns a filtered array of user notify references
	 */
	public function get_user_notify_references() {
		$user_references = array(
			'backup'       => 'Backup',
			'malware'      => 'Malware',
			'misc'         => 'Misc',
			'power'        => 'Power',
			'updates'      => 'Updates',
			'snapshots'    => 'Snapshots',
			'site-updates' => 'Site Updates',
			'other'        => 'Other',
		);
		return apply_filters( 'wpcd_user_notify_references', $user_references );
	}

	/**
	 * Returns actual values array of notify fields
	 *
	 * @param array $field_data field_data.
	 */
	public function wpcd_get_actual_values_notify_fields( $field_data ) {
		$return_field_data = array();
		if ( ! empty( $field_data ) ) {
			foreach ( $field_data as $key => $value ) {
				$return_field_data[] = $key;
			}
		}
		return $return_field_data;
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
				self::wpcd_scan_notification_schedule_events();
				restore_current_blog();
			}
		} else {
			self::wpcd_scan_notification_schedule_events();
		}

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function wpcd_scan_notification_schedule_events() {
		// setup scan notification event.
		wp_clear_scheduled_hook( 'wpcd_scan_notifications_actions' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_scan_notifications_actions' );

		/**
		* Check if plugin active first time then create a page & add the shortcode into it.
		*/

		// Get current login user id.
		$author_id = get_current_user_id();

		// Check if flag not set then create the page.
		$check_flag = get_option( 'wpcd_user_notify_alert_page_created' );

		if ( $check_flag == 0 || empty( $check_flag ) ) {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'WPCD: Setup Notification Alerts', 'wpcd' ),
					'post_author'  => $author_id,
					'post_content' => '[wpcd_user_notifications_form]',
				)
			);

			// Update the option once page created when plugin active first time.
			if ( ! is_wp_error( $page_id ) && ! empty( $page_id ) ) {
				update_option( 'wpcd_user_notify_alert_page_created', 1 );
			}
		}

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
				self::wpcd_scan_notification_clear_scheduled_events();
				restore_current_blog();
			}
		} else {
			self::wpcd_scan_notification_clear_scheduled_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function wpcd_scan_notification_clear_scheduled_events() {
		wp_clear_scheduled_hook( 'wpcd_scan_notifications_actions' );
	}

	/**
	 * To schedule events for newly created site on WP Multisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new_site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function wpcd_scan_notifications_schedule_events_for_new_site( $new_site, $args ) {

		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {

			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::wpcd_scan_notification_schedule_events();
			restore_current_blog();
		}

	}

	/**
	 * Check the user permission in ajax call.
	 *
	 * @param int $user_id user id.
	 * @param int $post_id post id.
	 */
	public function wpcd_user_notify_server_app_permission( $user_id, $post_id ) {
		if ( get_post_type( $post_id ) === 'wpcd_app_server' ) {
			$servers_access = $this->get_user_notify_servers( $user_id );
			if ( ! array_key_exists( $post_id, $servers_access ) ) {
				$error_msg = array( 'msg' => __( 'You are not allowed to perform this action. You have not accesss for the selected servers/sites.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}
		} else {
			$sites_access = $this->get_user_notify_sites( $user_id );
			if ( ! array_key_exists( $post_id, $sites_access ) ) {
				$error_msg = array( 'msg' => __( 'You are not allowed to perform this action. You have not accesss for the selected servers/sites.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}
		}
	}

	/**
	 * Save fields for user notification profile from backend.
	 *
	 * @param int    $post_id post id.
	 * @param object $post post object.
	 */
	public function wpcd_save_notification_user_profile_data( $post_id, $post ) {
		global $wpdb;

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get current user details.
		$author_id  = get_current_user_id();
		$usermeta   = get_user_by( 'id', $author_id );
		$user_login = $usermeta->data->user_login;

		// Store custom fields values.
		$profile_name = '';
		$profile_name = filter_input( INPUT_POST, 'wpcd_notify_user_profile_name', FILTER_SANITIZE_STRING );

		if ( empty( $profile_name ) ) {
			$update_profile_name = 'Submitted by ' . $user_login;
		} else {
			$update_profile_name = $profile_name;
		}

		// Update default post title.
		$post_query = 'UPDATE ' . $wpdb->prefix . 'posts SET post_title="' . $update_profile_name . '" WHERE ID=' . $post_id;
		$wpdb->query( $post_query );

	}


	/**
	 * Force the post to become private.
	 *
	 * @param object $post post object.
	 */
	public function wpcd_wpcd_notify_user_force_type_private( $post ) {
		if ( 'wpcd_notify_user' === $post['post_type'] && 'draft' !== $post['post_status'] && 'auto-draft' !== $post['post_status'] && 'trash' !== $post['post_status'] ) {
			$post['post_status'] = 'private';
		}
		return $post;
	}

	/**
	 * Adds custom back to list button for all type of logs
	 *
	 * @return void
	 */
	public function wpcd_user_notifications_backtolist_btn() {
		$screen    = get_current_screen();
		$post_type = 'wpcd_notify_user';
		if ( $screen->id === $post_type ) {
			$query          = sprintf( 'edit.php?post_type=%s', $post_type );
			$backtolist_url = admin_url( $query );
			$backtolist_txt = __( 'Back To List', 'wpcd' );
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('.wp-heading-inline').append('<a href="<?php echo esc_html( $backtolist_url ); ?>" class="page-title-action"><?php echo esc_html( $backtolist_txt ); ?></a>');
				});
			</script>
			<?php
		}
	}

	/**
	 * Filter the author edit box for display all users
	 *
	 * @param array $query_args query arguments.
	 * @param array $r arguments.
	 */
	public function wpcd_user_notify_display_all_users_dropdown( $query_args, $r ) {
		// Get screen object.
		$screen = get_current_screen();

		if ( 'wpcd_notify_user' === $screen->post_type ) {
			$query_args['role__in'] = array();
			// Unset default role.
			unset( $query_args['who'] );
		}

		return $query_args;
	}

}
