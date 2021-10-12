<?php
/**
 * This class handles metaboxes used in setting up email notifications for servers & sites.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_Settings
 */
class WPCD_EMAIL_NOTIFICATIONS {

	/**
	 * Return instance of self.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_EMAIL_NOTIFICATIONS constructor.
	 */
	public function __construct() {
		$this->display_email_notifications_metaboxes(); // show the meta boxes.
		$this->register();  // register the custom post type - wpcd_email_address.
		$this->hooks();     // register hooks to make the custom post type do things.
	}

	/**
	 * Add all the hook inside the this private method.
	 */
	public function hooks() {
		// Action hook to save/add all fields of email addresses tab.
		add_action( 'wp_ajax_wpcd_email_address_fields_save', array( $this, 'wpcd_email_address_fields_save' ) );

		// Action hook to delete email addresses entries.
		add_action( 'wp_ajax_wpcd_email_address_entry_delete', array( $this, 'wpcd_email_address_entry_delete' ) );

		// Action hook to save to draft compose email.
		add_action( 'wp_ajax_wpcd_compose_email_save_to_draft', array( $this, 'wpcd_compose_email_save_to_draft' ) );

		// Action hook to send email immediately.
		add_action( 'wp_ajax_wpcd_compose_email_send_now', array( $this, 'wpcd_compose_email_send_now' ) );

		// Enqueue admin scripts to add on backend.
		add_action( 'admin_enqueue_scripts', array( $this, 'wpcd_email_addresses_user_admin_enqueue_scripts' ), 10, 1 );

		// Action hook to send compose email on schedule date and time.
		add_action( 'wpcd_check_for_scheduled_compose_email_send', array( $this, 'wpcd_send_compose_email_on_scheduled_time_for_servers_apps' ), 1, 1 );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpcd_scheduled_compose_email_schedule_events_for_new_site' ), 10, 2 );

		// Action hook to display sent email details.
		add_action( 'wp_ajax_wpcd_sent_email_details_popup', array( $this, 'wpcd_sent_email_details_popup' ) );

		// Action hook to delete sent emails entries.
		add_action( 'wp_ajax_wpcd_sent_email_entry_delete', array( $this, 'wpcd_sent_email_entry_delete' ) );

		// Action hook to display the list of sent emails.
		add_action( 'wp_ajax_wpcd_sent_emails_list_pagination', array( $this, 'wpcd_sent_emails_list_pagination' ) );

		// Action hook to add new bulk option in server listing.
		add_filter( 'bulk_actions-edit-wpcd_app_server', array( $this, 'wpcd_add_new_bulk_actions_server' ) );

		// Action hook to add new bulk option in app listing.
		add_filter( 'bulk_actions-edit-wpcd_app', array( $this, 'wpcd_add_new_bulk_actions_app' ) );

		// Action hook to handle bulk action for server.
		add_filter( 'handle_bulk_actions-edit-wpcd_app_server', array( $this, 'wpcd_bulk_action_handler_server_app' ), 10, 3 );

		// Action hook to handle bulk action for app.
		add_filter( 'handle_bulk_actions-edit-wpcd_app', array( $this, 'wpcd_bulk_action_handler_server_app' ), 10, 3 );

		// Action hook to add values in new columns - Server batches.
		add_action( 'manage_wpcd_server_batch_posts_custom_column', array( $this, 'server_app_batches_table_content' ), 10, 2 );

		// Filter hook to add new columns on user notifications screen - Server batches.
		add_filter( 'manage_wpcd_server_batch_posts_columns', array( $this, 'server_app_batches_table_head' ), 10, 1 );

		// Action hook to add values in new columns - App batches.
		add_action( 'manage_wpcd_app_batch_posts_custom_column', array( $this, 'server_app_batches_table_content' ), 10, 2 );

		// Filter hook to add new columns on user notifications screen - App batches.
		add_filter( 'manage_wpcd_app_batch_posts_columns', array( $this, 'server_app_batches_table_head' ), 10, 1 );

		// Admin menu action for remove author box from batch posttypes.
		add_action( 'admin_menu', array( $this, 'wpcd_batch_posttypes_remove_author_box' ) );

		// Move author metabox to side in publish section.
		add_action( 'post_submitbox_misc_actions', array( $this, 'wpcd_batch_posttypes_author_in_publish' ) );
	}

	/**
	 * Register the custom post types.
	 */
	public function register() {
		// For user emails entries.
		register_post_type(
			'wpcd_email_address',
			array(
				'labels'              => array(
					'name'                  => _x( 'Email Address', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Email Address', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Email Address', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Email Address', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit Email Address', 'wpcd' ),
					'view_item'             => __( 'View Email Address', 'wpcd' ),
					'all_items'             => __( 'All Email Address Entries', 'wpcd' ),
					'search_items'          => __( 'Search Email Address', 'wpcd' ),
					'not_found'             => __( 'No Email Addresses were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Email Addresses were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Email Address list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Email Address list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Email Address list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'public'              => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( '' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts' => false,
				),
			)
		);

		// For sent user emails.
		register_post_type(
			'wpcd_sent_emails',
			array(
				'labels'              => array(
					'name'                  => _x( 'Sent Email', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Sent Email', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Sent Email', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Sent Email', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit Sent Email', 'wpcd' ),
					'view_item'             => __( 'View Sent Email', 'wpcd' ),
					'all_items'             => __( 'All Sent Email Entries', 'wpcd' ),
					'search_items'          => __( 'Search Sent Email', 'wpcd' ),
					'not_found'             => __( 'No Sent Email were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No Sent Email were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Sent Email list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Sent Email list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Sent Email list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'public'              => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( '' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts' => false,
				),
			)
		);

		// For server batch entries.
		register_post_type(
			'wpcd_server_batch',
			array(
				'labels'              => array(
					'name'                  => _x( 'Server Batch', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'Server Batch', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'Server Batch', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'Server Batch', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit Server Batch', 'wpcd' ),
					'view_item'             => __( 'View Server Batch', 'wpcd' ),
					'all_items'             => __( 'All Server Batch Entries', 'wpcd' ),
					'search_items'          => __( 'Search Server Batch', 'wpcd' ),
					'not_found'             => __( 'No server batches were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No server batches were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter Server Batch list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'Server Batch list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'Server Batch list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( 'author' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts'           => false,
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

		// For app batch entries.
		register_post_type(
			'wpcd_app_batch',
			array(
				'labels'              => array(
					'name'                  => _x( 'App Batch', 'Post type general name', 'wpcd' ),
					'singular_name'         => _x( 'App Batch', 'Post type singular name', 'wpcd' ),
					'menu_name'             => _x( 'App Batch', 'Admin Menu text', 'wpcd' ),
					'name_admin_bar'        => _x( 'App Batch', 'Add New on Toolbar', 'wpcd' ),
					'edit_item'             => __( 'Edit App Batch', 'wpcd' ),
					'view_item'             => __( 'View App Batch', 'wpcd' ),
					'all_items'             => __( 'All App Batch Entries', 'wpcd' ),
					'search_items'          => __( 'Search App Batch', 'wpcd' ),
					'not_found'             => __( 'No app batches were found.', 'wpcd' ),
					'not_found_in_trash'    => __( 'No app batches were found in Trash.', 'wpcd' ),
					'filter_items_list'     => _x( 'Filter App Batch list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'wpcd' ),
					'items_list_navigation' => _x( 'App Batch list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'wpcd' ),
					'items_list'            => _x( 'App Batch list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'wpcd' ),
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
				'supports'            => array( 'author' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts'           => false,
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
	 * Enqueue the scripts if we are on the servers and apps details screen.
	 *
	 * @param String $hook Hook name.
	 */
	public function wpcd_email_addresses_user_admin_enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'post-new.php', 'post.php', 'edit.php' ), true ) ) {

			$screen = get_current_screen();
			if ( is_object( $screen ) && ( 'wpcd_app_server' === $screen->post_type || 'wpcd_app' === $screen->post_type || 'wpcd_server_batch' === $screen->post_type || 'wpcd_app_batch' === $screen->post_type ) ) {
				wp_dequeue_script( 'autosave' );

				wp_enqueue_style( 'wpcd-email-addresses-css', wpcd_url . 'assets/css/wpcd-email-addresses-user.css', array(), wpcd_scripts_version );

				wp_enqueue_script( 'wpcd-email-addresses-user', wpcd_url . 'assets/js/wpcd-email-addresses-user.js', array( 'jquery' ), wpcd_version, true );
				wp_localize_script(
					'wpcd-email-addresses-user',
					'emailaddressesparams',
					array(
						'i10n' => array(
							'empty_firstname'      => __( 'Please enter first name.', 'wpcd' ),
							'empty_lastname'       => __( 'Please enter last name.', 'wpcd' ),
							'empty_company'        => __( 'Please enter company name.', 'wpcd' ),
							'empty_email'          => __( 'Please enter email address.', 'wpcd' ),
							'empty_notes'          => __( 'Please enter notes.', 'wpcd' ),
							'confirm_entry_delete' => __( 'Are you sure?', 'wpcd' ),
							'invalid_reply_to'     => __( 'Please enter a valid reply to email address.', 'wpcd' ),
							'empty_subject'        => __( 'Please enter email subject.', 'wpcd' ),
							'empty_body'           => __( 'Please enter email body.', 'wpcd' ),
							'invalid_other_emails' => __( 'Please enter valid email addresses in other emails field.', 'wpcd' ),
							'schedule_datetime'    => __( 'Please select date and time to schedule email.', 'wpcd' ),
							'add_wait_msg'         => __( 'Adding...', 'wpcd' ),
							'delete_wait_msg'      => __( 'Deleting...', 'wpcd' ),
							'save_wait_msg'        => __( 'Saving...', 'wpcd' ),
							'send_wait_msg'        => __( 'Sending...', 'wpcd' ),
							'empty_reply_to'       => __( 'Please enter the reply to email address.', 'wpcd' ),
							'empty_from_name'      => __( 'Please enter the from name.', 'wpcd' ),
							'schedule_later_btn'   => __( 'Schedule for Later', 'wpcd' ),
							'save_draft_btn'       => __( 'Save as draft', 'wpcd' ),
							'schedule_confirm_msg' => __( 'The message has been scheduled for ', 'wpcd' ),
						),
					)
				);
			}
		}
	}

	/**
	 * Function for showing the metabox fields.
	 */
	public function display_email_notifications_metaboxes() {

		// Register meta boxes and fields for email notifications.
		add_filter(
			'rwmb_meta_boxes',
			function ( $metaboxes ) {

				$server_time = gmdate( 'Y-m-d H:i:s' );
				$post_id     = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

				// This field only for server compose email tab.
				if ( get_post_type( $post_id ) === 'wpcd_app_server' ) {
					$server_screen = array(
						'id'         => 'wpcd_compose_email_send_to_server_emails',
						'type'       => 'select',
						'options'    => array(
							'servers_emails_only'      => __( 'Send to the server email addresses only', 'wpcd' ),
							'servers_apps_both_emails' => __( 'Send to both server & app email addresses', 'wpcd' ),
						),
						'name'       => __( 'Recipients', 'wpcd' ),
						'tab'        => 'wpcd_compose_email',
						'save_field' => true,
					);
				} else {
					$server_screen = array(
						'type' => 'custom_html',
						'std'  => '',
						'id'   => 'wpcd_compose_email_tab_html',
						'tab'  => 'wpcd_compose_email',
					);
				}

				$prefix = 'wpcd_';

				// Register the metabox for composing and sending emails.
				$metaboxes[] = array(
					'id'         => $prefix . 'email_notification_addresses',
					'title'      => __( 'Email Notifications', 'wpcd' ),
					'post_types' => array( 'wpcd_app_server', 'wpcd_app' ),
					'priority'   => 'default',
					'tabs'       => array(
						'wpcd_email_addresses' => array(
							'label' => 'Email Addresses',
						),
						'wpcd_compose_email'   => array(
							'label' => 'Compose Email',
						),
						'wpcd_sent_email'      => array(
							'label' => 'Sent Emails',
						),
					),
					'fields'     => array(
						array(
							'type' => 'custom_html',
							'std'  => $this->get_email_addresses_tab_form_html(),
							'id'   => 'wpcd_email_address_tab_form_html',
							'tab'  => 'wpcd_email_addresses',
						),
						array(
							'type' => 'heading',
							'name' => 'All email addresses',
							'tab'  => 'wpcd_email_addresses',
						),
						array(
							'type' => 'custom_html',
							'std'  => $this->get_list_of_email_addresses_html(),
							'id'   => 'wpcd_email_address_tab_form_html',
							'tab'  => 'wpcd_email_addresses',
						),
						$server_screen,
						array(
							'name'       => __( 'Additional Recipients', 'wpcd' ),
							'id'         => 'wpcd_compose_email_other_emails',
							'type'       => 'text',
							'size'       => 100,
							'desc'       => __( 'Add additional email addresses separated by commas.', 'wpcd' ),
							'tab'        => 'wpcd_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'From Name', 'wpcd' ),
							'id'         => 'wpcd_compose_email_from_name',
							'type'       => 'text',
							'size'       => 100,
							'tab'        => 'wpcd_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'Reply To', 'wpcd' ),
							'id'         => 'wpcd_compose_email_reply_to',
							'type'       => 'text',
							'size'       => 100,
							'desc'       => __( 'Add reply to email address.', 'wpcd' ),
							'tab'        => 'wpcd_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'Subject', 'wpcd' ),
							'id'         => 'wpcd_compose_email_subject',
							'type'       => 'text',
							'size'       => 100,
							'tab'        => 'wpcd_compose_email',
							'save_field' => true,
						),
						array(
							'id'         => 'wpcd_compose_email_body',
							'type'       => 'wysiwyg',
							'name'       => __( 'Body', 'wpcd' ),
							'desc'       => __( 'Valid substitutions are: ##USER_FIRSTNAME##, ##USER_LASTNAME##, ##OWNER_FIRSTNAME##, ##OWNER_LASTNAME##, ##SERVER_NAME##, ##SITE_NAME##', 'wpcd' ),
							'options'    => array(
								'textarea_rows' => 12,
							),
							'tab'        => 'wpcd_compose_email',
							'size'       => 60,
							'save_field' => true,
						),
						array(
							'name'       => __( 'Do you want to schedule the email?', 'wpcd' ),
							'id'         => 'wpcd_compose_email_schedule_email_enable',
							'type'       => 'checkbox',
							'desc'       => __( 'Turn this on to send this message later.', 'wpcd' ),
							'save_field' => true,
							'tab'        => 'wpcd_compose_email',
						),
						array(
							'name'       => __( 'When would you like to send this message?', 'wpcd' ),
							'id'         => 'wpcd_compose_email_schedule_email_datetime',
							'type'       => 'datetime',
							'js_options' => array(
								'stepMinute'      => 5,
								'showTimepicker'  => true,
								'controlType'     => 'select',
								'showButtonPanel' => false,
								'oneLine'         => true,
							),
							'attributes' => array(
								'autocomplete' => false,
							),
							'inline'     => false,
							'timestamp'  => false,
							/* translators: %s server time */
							'desc'       => sprintf( __( 'Your current server date & time is %s.', 'wpcd' ), $server_time ),
							'save_field' => true,
							'tooltip'    => __( 'Your message will be sent at the date and time set here.', 'wpcd' ),
							'tab'        => 'wpcd_compose_email',
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Save as draft', 'wpcd' ),
							'attributes' => array(
								'id'           => 'wpcd-compose-email-save-draft',
								'data-action'  => 'wpcd_compose_email_save_to_draft',
								'data-nonce'   => wp_create_nonce( 'wpcd-compose-email-save-draft' ),
								'data-post_id' => $post_id,
							),
							'desc'       => __( 'Save as draft or Schedule for Later.', 'wpcd' ),
							'tab'        => 'wpcd_compose_email',
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Send Now', 'wpcd' ),
							'attributes' => array(
								'id'           => 'wpcd-compose-email-send-now',
								'data-action'  => 'wpcd_compose_email_send_now',
								'data-nonce'   => wp_create_nonce( 'wpcd-compose-email-send-now' ),
								'data-post_id' => $post_id,
							),
							'desc'       => __( 'Send email immediately.', 'wpcd' ),
							'tab'        => 'wpcd_compose_email',
						),
						array(
							'type' => 'heading',
							'name' => 'Sent emails',
							'tab'  => 'wpcd_sent_email',
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Delete All', 'wpcd' ),
							'attributes' => array(
								'id'             => 'wpcd-sent-email-delete-all',
								'data-action'    => 'wpcd_sent_email_entry_delete',
								'data-nonce'     => wp_create_nonce( 'wpcd-sent-email-entry-delete' ),
								'data-parent_id' => $post_id,
								'data-entry_id'  => __( '0', 'wpcd' ),
							),
							'tab'        => 'wpcd_sent_email',
						),
						array(
							'type' => 'custom_html',
							'std'  => $this->get_list_of_sent_emails_html(),
							'id'   => 'wpcd_sent_emails_tab_list_html',
							'tab'  => 'wpcd_sent_email',
						),
					),
				);

				return $metaboxes;
			}
		);

		// Register meta boxes and fields for server/app batches.
		add_filter(
			'rwmb_meta_boxes',
			function ( $metaboxes ) {

				$server_time = gmdate( 'Y-m-d H:i:s' );
				$post_id     = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

				// This field only for server batch.
				if ( get_post_type( $post_id ) === 'wpcd_server_batch' ) {
					$server_batch_screen = array(
						'id'         => 'wpcd_compose_email_send_to_server_emails',
						'type'       => 'select',
						'options'    => array(
							'servers_emails_only'      => __( 'Send to the server email addresses only', 'wpcd' ),
							'servers_apps_both_emails' => __( 'Send to both the server & app email addresses', 'wpcd' ),
						),
						'name'       => __( 'Send to emails', 'wpcd' ),
						'save_field' => true,
					);
				} else {
					$server_batch_screen = array(
						'type' => 'custom_html',
						'std'  => '',
						'id'   => 'wpcd_compose_email_tab_html',
					);
				}

				$prefix = 'wpcd_';

				// Register the metabox for composing and sending emails.
				$metaboxes[] = array(
					'id'         => $prefix . 'email_notification_addresses_batch',
					'title'      => __( 'Email Notification Details', 'wpcd' ),
					'post_types' => array( 'wpcd_server_batch', 'wpcd_app_batch' ),
					'priority'   => 'high',
					'fields'     => array(
						array(
							'type' => 'custom_html',
							'std'  => $this->display_the_selected_server_app_bulk_ids( $post_id ),
							'id'   => 'wpcd_selected_server_app_bulk_ids_html',
						),
						$server_batch_screen,
						array(
							'name'       => __( 'Additional Recipients', 'wpcd' ),
							'id'         => 'wpcd_compose_email_other_emails',
							'type'       => 'text',
							'size'       => 100,
							'desc'       => __( 'Add additional email addresses separated by commas.', 'wpcd' ),
							'save_field' => true,
						),
						array(
							'name'       => __( 'From Name', 'wpcd' ),
							'id'         => 'wpcd_compose_email_from_name',
							'type'       => 'text',
							'size'       => 100,
							'tab'        => 'wpcd_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'Reply To', 'wpcd' ),
							'id'         => 'wpcd_compose_email_reply_to',
							'type'       => 'text',
							'size'       => 100,
							'desc'       => __( 'Add reply to email address.', 'wpcd' ),
							'tab'        => 'wpcd_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'Subject', 'wpcd' ),
							'id'         => 'wpcd_compose_email_subject',
							'type'       => 'text',
							'size'       => 100,
							'save_field' => true,
						),
						array(
							'id'         => 'wpcd_compose_email_body',
							'type'       => 'wysiwyg',
							'name'       => __( 'Body', 'wpcd' ),
							'desc'       => __( 'Valid substitutions are: ##USER_FIRSTNAME##, ##USER_LASTNAME##, ##OWNER_FIRSTNAME##, ##OWNER_LASTNAME##, ##SERVER_NAME##, ##SITE_NAME##', 'wpcd' ),
							'options'    => array(
								'textarea_rows' => 12,
							),
							'size'       => 60,
							'save_field' => true,
						),
						array(
							'name'       => __( 'Do you want to schedule the email?', 'wpcd' ),
							'id'         => 'wpcd_compose_email_schedule_email_enable',
							'type'       => 'checkbox',
							'desc'       => __( 'Turn this on to send this message later.', 'wpcd' ),
							'save_field' => true,
						),
						array(
							'name'       => __( 'When would you like to send this message?', 'wpcd' ),
							'id'         => 'wpcd_compose_email_schedule_email_datetime',
							'type'       => 'datetime',
							'js_options' => array(
								'stepMinute'      => 5,
								'showTimepicker'  => true,
								'controlType'     => 'select',
								'showButtonPanel' => false,
								'oneLine'         => true,
							),
							'inline'     => false,
							'timestamp'  => false,
							/* translators: %s server time */
							'desc'       => sprintf( __( 'Your current server date & time is %s.', 'wpcd' ), $server_time ),
							'save_field' => true,
							'tooltip'    => __( 'Your message will be sent at the date and time set here.', 'wpcd' ),
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Save as draft', 'wpcd' ),
							'attributes' => array(
								'id'           => 'wpcd-compose-email-save-draft',
								'data-action'  => 'wpcd_compose_email_save_to_draft',
								'data-nonce'   => wp_create_nonce( 'wpcd-compose-email-save-draft' ),
								'data-post_id' => $post_id,
							),
							'desc'       => __( 'Save as draft or Schedule for Later.', 'wpcd' ),
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Send Now', 'wpcd' ),
							'attributes' => array(
								'id'           => 'wpcd-compose-email-send-now',
								'data-action'  => 'wpcd_compose_email_send_now',
								'data-nonce'   => wp_create_nonce( 'wpcd-compose-email-send-now' ),
								'data-post_id' => $post_id,
							),
							'desc'       => __( 'Send email immediately.', 'wpcd' ),
						),
					),
				);

				return $metaboxes;
			}
		);

	}

	/**
	 * Gets the HTML for email address tab form.
	 *
	 * @return string
	 */
	public function get_email_addresses_tab_form_html() {
		$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

		$html = '';

		$html .= '<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="wpcd_email_addresses_first_name">' . __( 'First Name *', 'wpcd' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter a first name', 'wpcd' ) . '" type="text" id="wpcd_email_addresses_first_name" class="rwmb-text" name="wpcd_email_addresses_first_name">						
					</div>
				</div>
				<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="wpcd_email_addresses_last_name">' . __( 'Last Name *', 'wpcd' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter a last name', 'wpcd' ) . '" type="text" id="wpcd_email_addresses_last_name" class="rwmb-text" name="wpcd_email_addresses_last_name">						
					</div>
				</div>
				<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="wpcd_email_addresses_company">' . __( 'Company', 'wpcd' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter a company', 'wpcd' ) . '" type="text" id="wpcd_email_addresses_company" class="rwmb-text" name="wpcd_email_addresses_company">						
					</div>
				</div>
				<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="wpcd_email_addresses_email_id">' . __( 'Email Address *', 'wpcd' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter an email address', 'wpcd' ) . '" type="email" id="wpcd_email_addresses_email_id" class="rwmb-text" name="wpcd_email_addresses_email_id">						
					</div>
				</div>
				<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="wpcd_email_addresses_notes">' . __( 'Notes', 'wpcd' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter a notes', 'wpcd' ) . '" type="text" id="wpcd_email_addresses_notes" class="rwmb-text" name="wpcd_email_addresses_notes">						
					</div>
				</div>

				<div class="rwmb-field rwmb-button-wrapper"><div class="rwmb-input"><button type="button" id="wpcd-email-address-fields-save" class="rwmb-button button hide-if-no-js" data-post_id="' . $post_id . '" data-action="wpcd_email_address_fields_save" data-nonce="' . wp_create_nonce( 'wpcd-email-addresses-fields-save' ) . '">' . __( 'Add', 'wpcd' ) . '</button></div></div>';

		return $html;
	}

	/**
	 * Gets the HTML for listing all the email addresses.
	 *
	 * @return string
	 */
	public function get_list_of_email_addresses_html() {
		$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

		// Check and get all the email address either for server or site.
		$emails_args    = array(
			'post_type'   => 'wpcd_email_address',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => 'wpcd_email_addresses_parent_id',
					'value'   => $post_id,
					'compare' => '=',
				),
			),
		);
		$get_all_emails = get_posts( $emails_args );

		$html = '';

		$html .= '<div class="wpcd_email_list_sec">
					<table cellspacing="5" cellpadding="5px" class="wpcd_email_list_table">
					<tr>
						<th>' . __( 'First Name', 'wpcd' ) . '</th>
						<th>' . __( 'Last Name', 'wpcd' ) . '</th>
						<th>' . __( 'Company', 'wpcd' ) . '</th>
						<th>' . __( 'Email Address', 'wpcd' ) . '</th>
						<th>' . __( 'Notes', 'wpcd' ) . '</th>
						<th>' . __( 'Delete', 'wpcd' ) . '</th>
					</tr>';

		if ( ! empty( $get_all_emails ) ) {
			foreach ( $get_all_emails as $key => $value ) {
				$entry_id  = $value->ID;
				$parent_id = get_post_meta( $entry_id, 'wpcd_email_addresses_parent_id', true );

				if ( $post_id === $parent_id ) {

					$first_name = get_post_meta( $entry_id, 'wpcd_email_addresses_first_name', true );
					$last_name  = get_post_meta( $entry_id, 'wpcd_email_addresses_last_name', true );
					$company    = get_post_meta( $entry_id, 'wpcd_email_addresses_company', true );
					$company    = ! empty( $company ) ? $company : '-';
					$email_id   = get_post_meta( $entry_id, 'wpcd_email_addresses_email_id', true );
					$notes      = get_post_meta( $entry_id, 'wpcd_email_addresses_notes', true );
					$notes      = ! empty( $notes ) ? $notes : '-';

					$html .= '<tr>
											<td>' . esc_html( $first_name ) . '</td>
											<td>' . esc_html( $last_name ) . '</td>
											<td>' . esc_html( $company ) . '</td>
											<td>' . esc_html( $email_id ) . '</td>
											<td>' . esc_html( $notes ) . '</td>
											<td><a href="javascript:void(0)" class="wpcd_delete_entry" data-parent_id="' . esc_attr( $parent_id ) . '" data-entry_id="' . esc_attr( $entry_id ) . '" data-action="wpcd_email_address_entry_delete" data-nonce="' . wp_create_nonce( 'wpcd-email-address-entry-delete' ) . '" >' . __( 'Delete', 'wpcd' ) . '</a></td>
										</tr>';
				}
			}
		} else {
			$html .= '<tr><td colspan="6">' . __( 'No email addresses added.', 'wpcd' ) . '</td></tr>';
		}

			$html .= '</table>
				</div>';

		return $html;
	}

	/**
	 * Gets the HTML for listing all the sent emails.
	 *
	 * @return string
	 */
	public function get_list_of_sent_emails_html() {
		$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$paged   = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT );

		$html  = '';
		$html .= '<div class="wpcd_page_loading"><div class="wpcd_sent_emails_list_main_sec" data-parent_id="' . esc_attr( $post_id ) . '" data-action="wpcd_sent_emails_list_pagination" data-nonce="' . wp_create_nonce( 'wpcd-sent-email-list-pagination' ) . '">';
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Save data to the email address cpt.
	 */
	public function wpcd_email_address_fields_save() {
		// Nonce check.
		check_ajax_referer( 'wpcd-email-addresses-fields-save', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id.
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		$author_id = get_current_user_id();

		// Permission check - user access.
		$this->wpcd_email_user_check_server_app_permission( $author_id, $post_id );

		$wpcd_email_addresses_first_name = filter_input( INPUT_POST, 'wpcd_email_addresses_first_name', FILTER_SANITIZE_STRING );
		$wpcd_email_addresses_last_name  = filter_input( INPUT_POST, 'wpcd_email_addresses_last_name', FILTER_SANITIZE_STRING );
		$wpcd_email_addresses_company    = filter_input( INPUT_POST, 'wpcd_email_addresses_company', FILTER_SANITIZE_STRING );
		$wpcd_email_addresses_email_id   = filter_input( INPUT_POST, 'wpcd_email_addresses_email_id', FILTER_SANITIZE_STRING );
		$wpcd_email_addresses_notes      = filter_input( INPUT_POST, 'wpcd_email_addresses_notes', FILTER_SANITIZE_STRING );

		$post_title = $wpcd_email_addresses_first_name . ' ' . $wpcd_email_addresses_last_name;

		// Add post.
		$new_post_id = wp_insert_post(
			array(
				'post_type'   => 'wpcd_email_address',
				'post_status' => 'private',
				'post_title'  => $post_title,
				'post_author' => $author_id,
			)
		);

		if ( ! is_wp_error( $new_post_id ) && ! empty( $new_post_id ) ) {
			update_post_meta( $new_post_id, 'wpcd_email_addresses_parent_id', $post_id );
			update_post_meta( $new_post_id, 'wpcd_email_addresses_first_name', $wpcd_email_addresses_first_name );
			update_post_meta( $new_post_id, 'wpcd_email_addresses_last_name', $wpcd_email_addresses_last_name );
			update_post_meta( $new_post_id, 'wpcd_email_addresses_company', $wpcd_email_addresses_company );
			update_post_meta( $new_post_id, 'wpcd_email_addresses_email_id', $wpcd_email_addresses_email_id );
			update_post_meta( $new_post_id, 'wpcd_email_addresses_notes', $wpcd_email_addresses_notes );

			$msg = array( 'msg' => __( 'Email successfully added.', 'wpcd' ) );
			wp_send_json_success( $msg );
		} else {
			$msg = array( 'msg' => __( 'Error in saving.', 'wpcd' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Delete entry of email addresses.
	 */
	public function wpcd_email_address_entry_delete() {
		// Nonce check.
		check_ajax_referer( 'wpcd-email-address-entry-delete', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		$entry_id = filter_input( INPUT_POST, 'entry_id', FILTER_SANITIZE_NUMBER_INT );

		// Server id or app id.
		$parent_id = filter_input( INPUT_POST, 'parent_id', FILTER_SANITIZE_NUMBER_INT );

		// Permission check - user access.
		$this->wpcd_email_user_check_server_app_permission( get_current_user_id(), $parent_id );

		// Check entry_id in wpcd_notify_user.
		$email_args = array(
			'post_type'   => 'wpcd_email_address',
			'post_status' => 'private',
			'p'           => $entry_id,
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => 'wpcd_email_addresses_parent_id',
					'value'   => $parent_id,
					'compare' => '=',
				),
			),
		);

		$entry_found = get_posts( $email_args );

		if ( ! empty( $entry_found ) ) {
			// Delete entry.
			$deleted = wp_delete_post( $entry_id, true );

			if ( $deleted ) {
				$msg = array( 'msg' => __( 'Entry successfully deleted.', 'wpcd' ) );
				wp_send_json_success( $msg );
			} else {
				$msg = array( 'msg' => __( 'Error in deletion.', 'wpcd' ) );
				wp_send_json_success( $msg );
			}
		} else {
			$msg = array( 'msg' => __( 'Entry not found in database.', 'wpcd' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Save to draft the compose email.
	 */
	public function wpcd_compose_email_save_to_draft() {
		// Nonce check.
		check_ajax_referer( 'wpcd-compose-email-save-draft', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id OR server_batch_id or app_batch_id.
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		// Check for bulk action.
		if ( get_post_type( $post_id ) === 'wpcd_server_batch' || get_post_type( $post_id ) === 'wpcd_app_batch' ) {

			// Server or app ids.
			$bulk_action_ids = get_post_meta( $post_id, 'wpcd_bulk_server_app_ids', true );
			$all_bulk_ids    = explode( ', ', $bulk_action_ids );

			if ( ! empty( $all_bulk_ids ) ) {
				foreach ( $all_bulk_ids as $key => $value ) {
					// Permission check - user access.
					$this->wpcd_email_user_check_server_app_permission( get_current_user_id(), $value );
				}
			}
		} else {

			// Permission check - user access.
			$this->wpcd_email_user_check_server_app_permission( get_current_user_id(), $post_id );
		}

		$wpcd_compose_email_send_to_server_emails = filter_input( INPUT_POST, 'wpcd_compose_email_send_to_server_emails', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_other_emails          = filter_input( INPUT_POST, 'wpcd_compose_email_other_emails', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_from_name             = filter_input( INPUT_POST, 'wpcd_compose_email_from_name', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_reply_to              = filter_input( INPUT_POST, 'wpcd_compose_email_reply_to', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_subject               = filter_input( INPUT_POST, 'wpcd_compose_email_subject', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_body                  = filter_input( INPUT_POST, 'wpcd_compose_email_body', FILTER_UNSAFE_RAW );
		$wpcd_compose_email_schedule              = filter_input( INPUT_POST, 'wpcd_compose_email_schedule', FILTER_SANITIZE_NUMBER_INT );
		$wpcd_compose_email_schedule_datetime     = filter_input( INPUT_POST, 'wpcd_compose_email_schedule_datetime', FILTER_SANITIZE_STRING );

		$wpcd_compose_email_body = wpautop( $wpcd_compose_email_body );

		if ( ! empty( $post_id ) ) {

			// Update some common fields.
			$this->wpcd_update_common_fields_for_compose_email_tab( $post_id, $wpcd_compose_email_send_to_server_emails, $wpcd_compose_email_other_emails, $wpcd_compose_email_from_name, $wpcd_compose_email_reply_to, $wpcd_compose_email_subject, $wpcd_compose_email_body );

			update_post_meta( $post_id, 'wpcd_compose_email_schedule_email_enable', $wpcd_compose_email_schedule );

			// Cron args.
			$schedule_args = array( $post_id );

			if ( ! empty( $wpcd_compose_email_schedule ) && 1 === (int) $wpcd_compose_email_schedule ) {
				update_post_meta( $post_id, 'wpcd_compose_email_schedule_email_datetime', $wpcd_compose_email_schedule_datetime );

				$scheduled_time = strtotime( $wpcd_compose_email_schedule_datetime );
				$current_time   = time();

				update_post_meta( $post_id, 'wpcd_scheduled_email_cron_run', '0' );

				// Clear old cron for this post_id.
				wp_clear_scheduled_hook( 'wpcd_check_for_scheduled_compose_email_send', $schedule_args );

				// If scheduled time greater than the current time then set cron.
				if ( $scheduled_time > $current_time ) {
					// Schedule cron.
					wp_schedule_event( time(), 'every_two_minute', 'wpcd_check_for_scheduled_compose_email_send', $schedule_args );
				}
			} else {
				update_post_meta( $post_id, 'wpcd_scheduled_email_cron_run', '0' );

				// Clear old cron for this post_id.
				wp_clear_scheduled_hook( 'wpcd_check_for_scheduled_compose_email_send', $schedule_args );
			}

			$msg = array( 'msg' => __( 'The message has been saved as draft.', 'wpcd' ) );
			wp_send_json_success( $msg );
		} else {
			$msg = array( 'msg' => __( 'Error in saving.', 'wpcd' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Send compose email immediately.
	 */
	public function wpcd_compose_email_send_now() {
		// Nonce check.
		check_ajax_referer( 'wpcd-compose-email-send-now', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id.
		$post_id      = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
		$all_bulk_ids = array();

		// Check for bulk action.
		if ( get_post_type( $post_id ) === 'wpcd_server_batch' || get_post_type( $post_id ) === 'wpcd_app_batch' ) {

			// Server or app ids.
			$bulk_action_ids = get_post_meta( $post_id, 'wpcd_bulk_server_app_ids', true );
			$all_bulk_ids    = explode( ', ', $bulk_action_ids );

			if ( ! empty( $all_bulk_ids ) ) {
				foreach ( $all_bulk_ids as $key => $value ) {
					// Permission check - user access.
					$this->wpcd_email_user_check_server_app_permission( get_current_user_id(), $value );
				}
			}
		} else {

			// Permission check - user access.
			$this->wpcd_email_user_check_server_app_permission( get_current_user_id(), $post_id );
		}

		$wpcd_compose_email_send_to_server_emails = filter_input( INPUT_POST, 'wpcd_compose_email_send_to_server_emails', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_other_emails          = filter_input( INPUT_POST, 'wpcd_compose_email_other_emails', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_from_name             = filter_input( INPUT_POST, 'wpcd_compose_email_from_name', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_reply_to              = filter_input( INPUT_POST, 'wpcd_compose_email_reply_to', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_subject               = filter_input( INPUT_POST, 'wpcd_compose_email_subject', FILTER_SANITIZE_STRING );
		$wpcd_compose_email_body                  = filter_input( INPUT_POST, 'wpcd_compose_email_body', FILTER_UNSAFE_RAW );

		$wpcd_compose_email_body = wpautop( $wpcd_compose_email_body );

		if ( ! empty( $post_id ) ) {

			// Update some common fields.
			$this->wpcd_update_common_fields_for_compose_email_tab( $post_id, $wpcd_compose_email_send_to_server_emails, $wpcd_compose_email_other_emails, $wpcd_compose_email_from_name, $wpcd_compose_email_reply_to, $wpcd_compose_email_subject, $wpcd_compose_email_body );

			// Send email code.
			$success = $this->wpcd_send_compose_email_to_each_email_address( $post_id );

			if ( $success ) {
				$msg = array( 'msg' => __( 'Email successfully sent.', 'wpcd' ) );
				wp_send_json_success( $msg );
			} else {
				$error_msg = array( 'msg' => __( 'Email can not be sent due to some error.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
			}
		} else {
			$msg = array( 'msg' => __( 'Error in sending.', 'wpcd' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Update common fields for compose email tab.
	 *
	 * @param int    $post_id server or app id.
	 * @param string $wpcd_compose_email_send_to_server_emails wpcd_compose_email_send_to_server_emails.
	 * @param string $wpcd_compose_email_other_emails wpcd_compose_email_other_emails.
	 * @param string $wpcd_compose_email_from_name wpcd_compose_email_from_name.
	 * @param string $wpcd_compose_email_reply_to wpcd_compose_email_reply_to.
	 * @param string $wpcd_compose_email_subject wpcd_compose_email_subject.
	 * @param string $wpcd_compose_email_body wpcd_compose_email_body.
	 */
	public function wpcd_update_common_fields_for_compose_email_tab( $post_id, $wpcd_compose_email_send_to_server_emails, $wpcd_compose_email_other_emails, $wpcd_compose_email_from_name, $wpcd_compose_email_reply_to, $wpcd_compose_email_subject, $wpcd_compose_email_body ) {
		// Check if server screen then update the particular field.
		if ( get_post_type( $post_id ) === 'wpcd_app_server' || get_post_type( $post_id ) === 'wpcd_server_batch' ) {
			update_post_meta( $post_id, 'wpcd_compose_email_send_to_server_emails', $wpcd_compose_email_send_to_server_emails );
		}
		// Update all the fields values.
		update_post_meta( $post_id, 'wpcd_compose_email_other_emails', $wpcd_compose_email_other_emails );
		update_post_meta( $post_id, 'wpcd_compose_email_from_name', $wpcd_compose_email_from_name );
		update_post_meta( $post_id, 'wpcd_compose_email_reply_to', $wpcd_compose_email_reply_to );
		update_post_meta( $post_id, 'wpcd_compose_email_subject', $wpcd_compose_email_subject );
		update_post_meta( $post_id, 'wpcd_compose_email_body', $wpcd_compose_email_body );
	}

	/**
	 * Get all the emails of server or sites
	 *
	 * @param int $post_id server or app id.
	 */
	public function wpcd_get_all_emails_of_server_sites( $post_id ) {
		$all_email_ids = array();

		// Check and get all the email address either for server or site.
		$emails_args = array(
			'post_type'   => 'wpcd_email_address',
			'post_status' => 'private',
			'numberposts' => -1,
		);

		// Check for bulk action.
		if ( get_post_type( $post_id ) === 'wpcd_server_batch' || get_post_type( $post_id ) === 'wpcd_app_batch' ) {
			// Server or app ids.
			$bulk_action_ids = get_post_meta( $post_id, 'wpcd_bulk_server_app_ids', true );
			$all_bulk_ids    = explode( ', ', $bulk_action_ids );

			if ( ! empty( $all_bulk_ids ) ) {
				$emails_args['meta_query'] = array(
					array(
						'key'     => 'wpcd_email_addresses_parent_id',
						'value'   => $all_bulk_ids,
						'compare' => 'IN',
					),
				);
			}
		} else {
			$emails_args['meta_query'] = array(
				array(
					'key'     => 'wpcd_email_addresses_parent_id',
					'value'   => $post_id,
					'compare' => '=',
				),
			);
		}

		$get_all_emails = get_posts( $emails_args );

		if ( ! empty( $get_all_emails ) ) {
			foreach ( $get_all_emails as $key => $value ) {
				$entry_id  = $value->ID;
				$parent_id = get_post_meta( $entry_id, 'wpcd_email_addresses_parent_id', true );

				$email_address                                 = get_post_meta( $entry_id, 'wpcd_email_addresses_email_id', true );
				$first_name                                    = get_post_meta( $entry_id, 'wpcd_email_addresses_first_name', true );
				$last_name                                     = get_post_meta( $entry_id, 'wpcd_email_addresses_last_name', true );
				$all_email_ids[ $email_address ]['first_name'] = $first_name;
				$all_email_ids[ $email_address ]['last_name']  = $last_name;
				$all_email_ids[ $email_address ]['parent_id']  = $parent_id;
			}
		}

		return $all_email_ids;
	}

	/**
	 * Send compose email to each email address
	 *
	 * @param int $post_id server or app id.
	 */
	public function wpcd_send_compose_email_to_each_email_address( $post_id ) {
		$all_email_ids     = $this->wpcd_get_all_emails_of_server_sites( $post_id );
		$all_app_email_ids = array();

		// Get fields of compose email.
		$wpcd_compose_email_other_emails = get_post_meta( $post_id, 'wpcd_compose_email_other_emails', true );
		$wpcd_compose_email_from_name    = get_post_meta( $post_id, 'wpcd_compose_email_from_name', true );
		$wpcd_compose_email_reply_to     = get_post_meta( $post_id, 'wpcd_compose_email_reply_to', true );
		$wpcd_compose_email_subject      = get_post_meta( $post_id, 'wpcd_compose_email_subject', true );
		$wpcd_compose_email_body         = get_post_meta( $post_id, 'wpcd_compose_email_body', true );

		// Check for the server screen or server batch screen.
		if ( get_post_type( $post_id ) === 'wpcd_app_server' || get_post_type( $post_id ) === 'wpcd_server_batch' ) {
			$send_to_emails = get_post_meta( $post_id, 'wpcd_compose_email_send_to_server_emails', true );

			if ( 'servers_apps_both_emails' === $send_to_emails ) {
				// Check for bulk action.
				if ( get_post_type( $post_id ) === 'wpcd_server_batch' ) {
					// Server or app ids.
					$bulk_action_ids = get_post_meta( $post_id, 'wpcd_bulk_server_app_ids', true );
					$all_bulk_ids    = explode( ', ', $bulk_action_ids );

					if ( ! empty( $all_bulk_ids ) ) {
						$args['meta_query'] = array(
							array(
								'key'     => 'parent_post_id',
								'value'   => $all_bulk_ids,
								'compare' => 'IN',
							),
						);
					}
				} else {
					$args['meta_query'] = array(
						array(
							'key'   => 'parent_post_id',
							'value' => $post_id,
						),
					);
				}

				// Get all the apps associated with the server.
				$args = array(
					'post_type'      => 'wpcd_app',
					'post_status'    => 'private',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				);

				$app_ids = get_posts( $args );
				if ( ! empty( $app_ids ) ) {
					foreach ( $app_ids as $key => $value ) {
						$app_emails        = $this->wpcd_get_all_emails_of_server_sites( $value );
						$all_app_email_ids = array_merge( $app_emails, $all_app_email_ids );
					}
				}
			}
		}

		$final_emails = array_merge( $all_email_ids, $all_app_email_ids ); // Merge array.

		$other_emails = array();
		if ( ! empty( $wpcd_compose_email_other_emails ) ) {
			$other_emails = explode( ',', $wpcd_compose_email_other_emails );
		}
		$other_emails_arr = array();  // Create array of other emails.
		// Create full array with empty first name and last name for custom added emails.
		if ( get_post_type( $post_id ) === 'wpcd_server_batch' || get_post_type( $post_id ) === 'wpcd_app_batch' ) {
			if ( ! empty( $other_emails ) ) {
				foreach ( $other_emails as $key => $value ) {
					$other_emails_arr[ $value ] = array(
						'first_name' => '',
						'last_name'  => '',
						'parent_id'  => $post_id,
					);
				}
			}
		} else {
			if ( ! empty( $other_emails ) ) {
				foreach ( $other_emails as $key => $value ) {
					$other_emails_arr[ $value ] = array(
						'first_name' => '',
						'last_name'  => '',
						'parent_id'  => $post_id,
					);
				}
			}
		}
		$emails_to_send = array_merge( $final_emails, $other_emails_arr ); // Merge array.
		if ( empty( $emails_to_send ) ) {
			$msg = array( 'msg' => __( 'No email address founds to send.', 'wpcd' ) );
			/* translators: %d post id */
			do_action( 'wpcd_log_error', sprintf( __( 'Email can not be sent due to no email address for id : %d', 'wpcd' ), $post_id ), 'error', __FILE__, __LINE__ );
			wp_send_json_error( $msg );
			wp_die();
		}

		$email_subject = apply_filters( 'wpcd_compose_email_subject_text', $wpcd_compose_email_subject );
		$email_body    = apply_filters( 'wpcd_compose_email_body_text', $wpcd_compose_email_body );

		$success = false;
		// Send the email...
		if ( ! empty( $email_subject ) && ! empty( $email_body ) ) {
			foreach ( $emails_to_send as $mail_key => $mail_value ) {
				$email_subject = apply_filters( 'wpcd_compose_email_subject_text', $wpcd_compose_email_subject );
				$email_body    = apply_filters( 'wpcd_compose_email_body_text', $wpcd_compose_email_body );

				$parent_id         = $mail_value['parent_id'];
				$bulk_action_found = false;

				// Get server owner first name & last name and server name & site name.
				$server_name = '';
				$site_name   = '';
				if ( get_post_type( $parent_id ) === 'wpcd_server_batch' || get_post_type( $parent_id ) === 'wpcd_app_batch' ) {
					$bulk_action_found = true;
					// Server or app ids.
					$bulk_action_ids = get_post_meta( $parent_id, 'wpcd_bulk_server_app_ids', true );
					$all_bulk_ids    = explode( ', ', $bulk_action_ids );
					if ( ! empty( $all_bulk_ids ) ) {
						foreach ( $all_bulk_ids as $key => $value ) {
							if ( get_post_type( $value ) === 'wpcd_app' ) {
								$server_id   = get_post_meta( $value, 'parent_post_id', true );
								$server_name = WPCD_SERVER()->get_server_name( $server_id );
								$site_name   = get_post_meta( $value, 'wpapp_domain', true );
							} else {
								$server_id   = $value;
								$server_name = WPCD_SERVER()->get_server_name( $server_id );
								$site_name   = __( 'N/A', 'wpcd' );
							}

							$this->wpcd_common_code_for_send_compose_email( $post_id, $parent_id, $server_id, $server_name, $site_name, $mail_key, $mail_value, $email_subject, $email_body, $wpcd_compose_email_from_name, $wpcd_compose_email_reply_to );

							$success = true;
						}
					}
				} elseif ( get_post_type( $parent_id ) === 'wpcd_app' ) {
					$bulk_action_found = false;
					$server_id         = get_post_meta( $parent_id, 'parent_post_id', true );
					$server_name       = WPCD_SERVER()->get_server_name( $server_id );
					$site_name         = get_post_meta( $parent_id, 'wpapp_domain', true );
				} else {
					$bulk_action_found = false;
					$server_id         = $parent_id;
					$server_name       = WPCD_SERVER()->get_server_name( $server_id );
					$site_name         = __( 'N/A', 'wpcd' );
				}

				if ( false === (bool) $bulk_action_found ) {
					$this->wpcd_common_code_for_send_compose_email( $post_id, $parent_id, $server_id, $server_name, $site_name, $mail_key, $mail_value, $email_subject, $email_body, $wpcd_compose_email_from_name, $wpcd_compose_email_reply_to );

					$success = true;
				}
			}
		} else {
			$success = false;
			/* translators: %d post id */
			do_action( 'wpcd_log_error', sprintf( __( 'Email can not be sent due to empty subject and body for id : %d', 'wpcd' ), $post_id ), 'error', __FILE__, __LINE__ );
		}

		return $success;
	}

	/**
	 * Common code for send compose email
	 *
	 * @param int    $post_id post id.
	 * @param int    $parent_id parent id.
	 * @param int    $server_id server id.
	 * @param string $server_name server name.
	 * @param string $site_name site name.
	 * @param string $mail_key mail key.
	 * @param array  $mail_value mail value.
	 * @param string $email_subject email subject.
	 * @param string $email_body email body.
	 * @param string $wpcd_compose_email_from_name wpcd_compose_email_from_name.
	 * @param string $wpcd_compose_email_reply_to wpcd_compose_email_reply_to.
	 */
	public function wpcd_common_code_for_send_compose_email( $post_id, $parent_id, $server_id, $server_name, $site_name, $mail_key, $mail_value, $email_subject, $email_body, $wpcd_compose_email_from_name, $wpcd_compose_email_reply_to ) {
		// Get server owner user id.
		$server_owner_id  = get_post_meta( $server_id, 'wpcd_server_user_id', true );
		$owner_details    = get_userdata( $server_owner_id );
		$owner_first_name = $owner_details->first_name;
		$owner_last_name  = $owner_details->last_name;

		// Now set a standard array of replaceable parameters.
		$tokens                    = array();
		$tokens['USER_FIRSTNAME']  = $mail_value['first_name'];
		$tokens['USER_LASTNAME']   = $mail_value['last_name'];
		$tokens['OWNER_FIRSTNAME'] = $owner_first_name;
		$tokens['OWNER_LASTNAME']  = $owner_last_name;
		$tokens['SERVER_NAME']     = $server_name;
		$tokens['SITE_NAME']       = $site_name;

		// Replace tokens in email..
		$email_body = WPCD_WORDPRESS_APP()->replace_script_tokens( $email_body, $tokens );

		// Let developers have their way again with the email contents.
		$email_body = apply_filters( 'wpcd_compose_email_body_text', $email_body );

		$sent = wp_mail(
			$mail_key,
			$email_subject,
			$email_body,
			array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $wpcd_compose_email_from_name . ' <' . $wpcd_compose_email_reply_to . '>', 'Reply-To: ' . $wpcd_compose_email_reply_to )
		);

		if ( ! $sent ) {
			/* translators: %1$s mail key, %2$d post id */
			do_action( 'wpcd_log_error', sprintf( __( 'Email can not be sent due to some error to %1$s for id : %2$d', 'wpcd' ), $mail_key, $post_id ), 'error', __FILE__, __LINE__ );
		} else {
			/* translators: %1$s mail key, %2$d post id */
			do_action( 'wpcd_log_error', sprintf( __( 'Email successfully sent to %1$s for id : %2$d', 'wpcd' ), $mail_key, $post_id ), 'debug', __FILE__, __LINE__ );

			// Insert sent mail entry into cpt.
			$this->wpcd_sent_email_insert_entries( $post_id, $parent_id, $mail_key, $email_subject, $email_body );
		}
	}

	/**
	 * Sent mail entries insert into CPT.
	 *
	 * @param int    $post_id post id.
	 * @param int    $parent_id parent id.
	 * @param string $mail_key mail key.
	 * @param string $email_subject email subject.
	 * @param string $email_body email body.
	 */
	public function wpcd_sent_email_insert_entries( $post_id, $parent_id, $mail_key, $email_subject, $email_body ) {

		if ( empty( $parent_id ) ) {
			$parent_id = $post_id;
		}

		$post_title = $parent_id . ' - ' . $mail_key;
		$author_id  = get_current_user_id();

		// Add post.
		$new_post_id = wp_insert_post(
			array(
				'post_type'   => 'wpcd_sent_emails',
				'post_status' => 'private',
				'post_title'  => $post_title,
				'post_author' => $author_id,
			)
		);

		if ( ! is_wp_error( $new_post_id ) && ! empty( $new_post_id ) ) {
			update_post_meta( $new_post_id, 'wpcd_sent_email_parent_id', $parent_id );
			update_post_meta( $new_post_id, 'wpcd_sent_email_email_address', $mail_key );
			update_post_meta( $new_post_id, 'wpcd_sent_email_email_subject', $email_subject );
			update_post_meta( $new_post_id, 'wpcd_sent_email_email_body', $email_body );
		}
	}

	/**
	 * Cron function code to send compose email on scheduled time.
	 *
	 * @param int $post_id server or app id.
	 */
	public function wpcd_send_compose_email_on_scheduled_time_for_servers_apps( $post_id ) {

		$wpcd_compose_email_schedule_datetime = get_post_meta( $post_id, 'wpcd_compose_email_schedule_email_datetime', true );
		$cron_run                             = get_post_meta( $post_id, 'wpcd_scheduled_email_cron_run', true );

		// Convert date and time into seconds.
		$scheduled_time = strtotime( $wpcd_compose_email_schedule_datetime );
		$current_time   = time();

		if ( $current_time > $scheduled_time && '0' === $cron_run ) {
			// Send mail function call.
			$success = $this->wpcd_send_compose_email_to_each_email_address( $post_id );

			// Mark as cron run.
			update_post_meta( $post_id, 'wpcd_scheduled_email_cron_run', '1' );

			// Clear cron for this post_id.
			$schedule_args = array( $post_id );
			wp_clear_scheduled_hook( 'wpcd_check_for_scheduled_compose_email_send', $schedule_args );
		}
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
				self::wpcd_scheduled_compose_email_schedule_events();
				restore_current_blog();
			}
		} else {
			self::wpcd_scheduled_compose_email_schedule_events();
		}

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function wpcd_scheduled_compose_email_schedule_events() {
		// Clear old crons.
		wp_unschedule_hook( 'wpcd_check_for_scheduled_compose_email_send' );

		// Get all servers.
		$server_args = array(
			'post_type'      => 'wpcd_app_server',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$server_ids  = get_posts( $server_args );

		// Get all apps.
		$app_args = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$app_ids  = get_posts( $app_args );

		$server_app_ids = array_merge( $server_ids, $app_ids );

		// Set the cron for servers and apps.
		if ( ! empty( $server_app_ids ) ) {
			foreach ( $server_app_ids as $key => $value ) {
				$scheduled_enabled = get_post_meta( $value, 'wpcd_compose_email_schedule_email_enable', true );
				$scheduled_time    = get_post_meta( $value, 'wpcd_compose_email_schedule_email_datetime', true );
				if ( 1 === (int) $scheduled_enabled ) {
					if ( strtotime( $scheduled_time ) > time() ) {
						$schedule_args = array( $value );
						wp_clear_scheduled_hook( 'wpcd_check_for_scheduled_compose_email_send', $schedule_args );
						// Set new crons for compose email.
						wp_schedule_event( time(), 'every_two_minute', 'wpcd_check_for_scheduled_compose_email_send', $schedule_args );
					}
				}
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
				self::wpcd_scheduled_compose_email_clear_scheduled_events();
				restore_current_blog();
			}
		} else {
			self::wpcd_scheduled_compose_email_clear_scheduled_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function wpcd_scheduled_compose_email_clear_scheduled_events() {
		wp_unschedule_hook( 'wpcd_check_for_scheduled_compose_email_send' );
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
	public function wpcd_scheduled_compose_email_schedule_events_for_new_site( $new_site, $args ) {

		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {

			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::wpcd_scheduled_compose_email_schedule_events();
			restore_current_blog();
		}

	}

	/**
	 * Display the sent email details popup when click on view button
	 */
	public function wpcd_sent_email_details_popup() {
		// nonce check.
		check_ajax_referer( 'wpcd-sent-email-details-display', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id.
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		// Permission check - user access.
		$this->wpcd_email_user_check_server_app_permission( get_current_user_id(), $post_id );

		ob_start();
		require wpcd_path . 'includes/templates/sent_email_details_popup.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

		exit;
	}

	/**
	 * Delete entry of sent emails.
	 */
	public function wpcd_sent_email_entry_delete() {
		global $wpdb;
		// Nonce check.
		check_ajax_referer( 'wpcd-sent-email-entry-delete', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		$entry_id = filter_input( INPUT_POST, 'entry_id', FILTER_SANITIZE_NUMBER_INT );

		// Server id or app id.
		$parent_id = filter_input( INPUT_POST, 'parent_id', FILTER_SANITIZE_NUMBER_INT );

		// Permission check - user access.
		$this->wpcd_email_user_check_server_app_permission( get_current_user_id(), $parent_id );

		if ( '0' === (string) $entry_id ) {
			$email_args = array( '' );
		} else {
			$email_args = array( 'p' => $entry_id );
		}

		// Check entry_id in wpcd_sent_emails.
		$sent_email_args = array(
			'post_type'   => 'wpcd_sent_emails',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => 'wpcd_sent_email_parent_id',
					'value'   => $parent_id,
					'compare' => '=',
				),
			),
		);

		$entry_found = get_posts( $sent_email_args );

		if ( ! empty( $entry_found ) ) {

			if ( '0' === (string) $entry_id ) {
				// Delete all entries.
				$delete_query = "DELETE p, pm FROM wp_posts p INNER JOIN wp_postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'wpcd_sent_emails' AND pm.meta_key = 'wpcd_sent_email_parent_id' AND pm.meta_value = '" . $parent_id . "'";

				$deleted = $wpdb->query( $delete_query );
			} else {
				// Delete entry.
				$deleted = wp_delete_post( $entry_id, true );
			}

			if ( $deleted ) {
				$msg = array( 'msg' => __( 'Successfully deleted.', 'wpcd' ) );
				wp_send_json_success( $msg );
			} else {
				$msg = array( 'msg' => __( 'Error in deletion.', 'wpcd' ) );
				wp_send_json_success( $msg );
			}
		} else {
			$msg = array( 'msg' => __( 'Entry not found in database.', 'wpcd' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}


	/**
	 * Sent email list with pagination.
	 */
	public function wpcd_sent_emails_list_pagination() {
		global $wpdb;
		// Nonce check.
		check_ajax_referer( 'wpcd-sent-email-list-pagination', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id.
		$parent_id = filter_input( INPUT_POST, 'parent_id', FILTER_SANITIZE_NUMBER_INT );
		$page      = filter_input( INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT );

		// Permission check - user access.
		$this->wpcd_email_user_check_server_app_permission( get_current_user_id(), $parent_id );

		$cur_page = $page;
		$page    -= 1;
		// Set the number of results to display.
		$per_page     = 10;
		$previous_btn = true;
		$next_btn     = true;
		$first_btn    = true;
		$last_btn     = true;
		$start        = $page * $per_page;

		// Get total sent email entries.
		$all_sent_emails   = array(
			'post_type'   => 'wpcd_sent_emails',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => 'wpcd_sent_email_parent_id',
					'value'   => $parent_id,
					'compare' => '=',
				),
			),
		);
		$count_sent_emails = get_posts( $all_sent_emails );
		$count             = count( $count_sent_emails );

		// Check and get all the sent emails either for server or site.
		$sent_emails_args = array(
			'post_type'      => 'wpcd_sent_emails',
			'post_status'    => 'private',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'posts_per_page' => $per_page,
			'offset'         => $start,
			'meta_query'     => array(
				array(
					'key'     => 'wpcd_sent_email_parent_id',
					'value'   => $parent_id,
					'compare' => '=',
				),
			),
		);
		$get_sent_emails  = get_posts( $sent_emails_args );

		$html = '';

		$html .= '<div class="wpcd_sent_email_list_sec">
					<table cellspacing="5" cellpadding="5px" class="wpcd_sent_email_list_table">
					<tr>
						<th>' . __( 'Email Address', 'wpcd' ) . '</th>
						<th>' . __( 'Subject', 'wpcd' ) . '</th>
						<th>' . __( 'Body', 'wpcd' ) . '</th>
						<th>' . __( 'Date', 'wpcd' ) . '</th>
						<th>' . __( 'Delete', 'wpcd' ) . '</th>
					</tr>';

		if ( ! empty( $get_sent_emails ) ) {
			foreach ( $get_sent_emails as $key => $value ) {
				$entry_id = $value->ID;
				$date     = $value->post_date;

				$parent_id     = get_post_meta( $entry_id, 'wpcd_sent_email_parent_id', true );
				$email_address = get_post_meta( $entry_id, 'wpcd_sent_email_email_address', true );
				$subject       = get_post_meta( $entry_id, 'wpcd_sent_email_email_subject', true );
				$body          = get_post_meta( $entry_id, 'wpcd_sent_email_email_body', true );

				$html .= '<tr>
							<td>' . esc_html( $email_address ) . '</td>
							<td>' . esc_html( $subject ) . '</td>
							<td><a href="javascript:void(0)" class="wpcd_view_email_details" data-nonce="' . wp_create_nonce( 'wpcd-sent-email-details-display' ) . '" data-action="wpcd_sent_email_details_popup" data-post_id="' . $entry_id . '">' . __( 'View', 'wpcd' ) . '</a></td>
							<td>' . esc_html( $date ) . '</td>
							<td><a href="javascript:void(0)" class="wpcd_delete_sent_entry" data-parent_id="' . esc_attr( $parent_id ) . '" data-entry_id="' . esc_attr( $entry_id ) . '" data-action="wpcd_sent_email_entry_delete" data-nonce="' . wp_create_nonce( 'wpcd-sent-email-entry-delete' ) . '" >' . __( 'Delete', 'wpcd' ) . '</a></td>
						</tr>';
			}
		} else {
			$html .= '<tr><td colspan="6">' . __( 'No sent emails found.', 'wpcd' ) . '</td></tr>';
		}

			$html .= '</table>
				</div>';

		// This is where the magic happens.
		$no_of_paginations = ceil( $count / $per_page );

		if ( $cur_page >= 7 ) {
			$start_loop = $cur_page - 3;
			if ( $no_of_paginations > $cur_page + 3 ) {
				$end_loop = $cur_page + 3;
			} elseif ( $cur_page <= $no_of_paginations && $cur_page > $no_of_paginations - 6 ) {
				$start_loop = $no_of_paginations - 6;
				$end_loop   = $no_of_paginations;
			} else {
				$end_loop = $no_of_paginations;
			}
		} else {
			$start_loop = 1;
			if ( $no_of_paginations > 7 ) {
				$end_loop = 7;
			} else {
				$end_loop = $no_of_paginations;
			}
		}

		// Pagination Buttons logic.
		$pag_container = "
        <div class='wpcd-universal-pagination'>
            <ul>";

		if ( $first_btn && $cur_page > 1 ) {
			$pag_container .= "<li p='1' class='active'>First</li>";
		} elseif ( $first_btn ) {
			$pag_container .= "<li p='1' class='inactive'>First</li>";
		}

		if ( $previous_btn && $cur_page > 1 ) {
			$pre            = $cur_page - 1;
			$pag_container .= "<li p='$pre' class='active'>Previous</li>";
		} elseif ( $previous_btn ) {
			$pag_container .= "<li class='inactive'>Previous</li>";
		}
		for ( $i = $start_loop; $i <= $end_loop; $i++ ) {
			if ( (int) $cur_page === (int) $i ) {
				$pag_container .= "<li p='$i' class = 'selected' >{$i}</li>";
			} else {
				$pag_container .= "<li p='$i' class='active'>{$i}</li>";
			}
		}

		if ( $next_btn && $cur_page < $no_of_paginations ) {
			$nex            = $cur_page + 1;
			$pag_container .= "<li p='$nex' class='active'>Next</li>";
		} elseif ( $next_btn ) {
			$pag_container .= "<li class='inactive'>Next</li>";
		}

		if ( $last_btn && $cur_page < $no_of_paginations ) {
			$pag_container .= "<li p='$no_of_paginations' class='active'>Last</li>";
		} elseif ( $last_btn ) {
			$pag_container .= "<li p='$no_of_paginations' class='inactive'>Last</li>";
		}

		$pag_container = $pag_container . '
            </ul>
        </div>';

		// We echo the final output.
		echo '<div class = "wpcd-pagination-content">' . $html . '</div>' .
		'<div class = "wpcd-pagination-nav">' . $pag_container . '</div>';

		exit;
	}

	/**
	 * Check the user permission in ajax call.
	 *
	 * @param int $user_id user id.
	 * @param int $post_id post id.
	 */
	public function wpcd_email_user_check_server_app_permission( $user_id, $post_id ) {
		if ( get_post_type( $post_id ) === 'wpcd_app_server' ) {
			if ( ! wpcd_user_can( $user_id, 'view_server', $post_id ) ) {
				$error_msg = array( 'msg' => __( 'You are not allowed to perform this action.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}
		} else {
			if ( ! wpcd_user_can( $user_id, 'view_app', $post_id ) ) {
				$error_msg = array( 'msg' => __( 'You are not allowed to perform this action.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}
		}
	}

	/**
	 * Add new bulk options in server list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function wpcd_add_new_bulk_actions_server( $bulk_array ) {
		// Remove bulk trash option.
		// @TODO: Why is this here instead of in the SERVER POST class?  What does this have to do with emails?
		$disable_bulk_delete = (int) wpcd_get_option( 'wordpress_app_disable_bulk_delete_on_full_server_list' );
		if ( 1 === $disable_bulk_delete ) {
			unset( $bulk_array['trash'] );
		}
		$bulk_array['wpcd_compose_send_email'] = __( 'Send Email', 'wpcd' );
		return $bulk_array;
	}

	/**
	 * Add new bulk options in app list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function wpcd_add_new_bulk_actions_app( $bulk_array ) {
		$bulk_array['wpcd_compose_send_email'] = __( 'Send Email', 'wpcd' );
		return $bulk_array;
	}

	/**
	 * Handle bulk action for server and app.
	 *
	 * @param string $redirect_url redirect url.
	 * @param string $action action.
	 * @param array  $post_ids all post ids.
	 */
	public function wpcd_bulk_action_handler_server_app( $redirect_url, $action, $post_ids ) {
		// Let's remove query args first.
		$redirect_url = remove_query_arg( array( 'wpcd_sent_compose_email' ), $redirect_url );
		// Do something for "Compose & Send Email" bulk action.
		if ( 'wpcd_compose_send_email' === $action ) {
			if ( ! empty( $post_ids ) ) {

				// get current user details.
				$author_id  = get_current_user_id();
				$usermeta   = get_user_by( 'id', $author_id );
				$user_login = $usermeta->data->user_login;
				$post_title = 'Submitted by ' . $user_login;

				if ( get_post_type( $post_ids[0] ) === 'wpcd_app_server' ) {
					$batch_id = wp_insert_post(
						array(
							'post_type'   => 'wpcd_server_batch',
							'post_status' => 'private',
							'post_title'  => $post_title,
							'post_author' => $author_id,
						)
					);
				}

				if ( get_post_type( $post_ids[0] ) === 'wpcd_app' ) {
					$batch_id = wp_insert_post(
						array(
							'post_type'   => 'wpcd_app_batch',
							'post_status' => 'private',
							'post_title'  => $post_title,
							'post_author' => $author_id,
						)
					);
				}

				if ( ! is_wp_error( $batch_id ) && ! empty( $batch_id ) ) {

					// Update the all bulk server/app ids.
					$all_bulk_ids = implode( ', ', $post_ids );
					update_post_meta( $batch_id, 'wpcd_bulk_server_app_ids', $all_bulk_ids );

					$batch_url = admin_url( 'post.php?post=' . $batch_id . '&action=edit' );
					wp_safe_redirect( $batch_url );
					exit;
				}
			}
		}

		return $redirect_url;
	}


	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * print column value.
	 */
	public function server_app_batches_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_bulk_action_ids':
				$all_bulk_ids = get_post_meta( $post_id, 'wpcd_bulk_server_app_ids', true );
				$value        = $this->wpcd_selected_bulk_ids_display_name( $all_bulk_ids );
				break;

			default:
				break;
		}

		echo esc_html( $value );
	}

	/**
	 * Add table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function server_app_batches_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['wpcd_bulk_action_ids'] = __( 'Bulk Action Ids', 'wpcd' );

		return $defaults;
	}

	/**
	 * Remove author box from server and app batches.
	 */
	public function wpcd_batch_posttypes_remove_author_box() {
		remove_meta_box( 'authordiv', 'wpcd_server_batch', 'normal' );
		remove_meta_box( 'authordiv', 'wpcd_app_batch', 'normal' );
	}

	/**
	 * Move author box to side in publish box for server and app batches.
	 */
	public function wpcd_batch_posttypes_author_in_publish() {
		global $post_ID;
		$post = get_post( $post_ID );

		if ( 'wpcd_server_batch' === $post->post_type || 'wpcd_app_batch' === $post->post_type ) {
			echo '<div class="misc-pub-section wpcd-author-sec">' . esc_html( __( 'Author:', 'wpcd' ) );
			post_author_meta_box( $post );
			echo '</div>';
		}
	}

	/**
	 * Display the name of selected bulk ids from server or app.
	 *
	 * @param string $all_bulk_ids all_bulk_ids.
	 *
	 * @return string
	 */
	public function wpcd_selected_bulk_ids_display_name( $all_bulk_ids ) {
		$server_app_name = '';
		$bulk_ids_arr    = explode( ', ', $all_bulk_ids );
		if ( ! empty( $bulk_ids_arr ) ) {
			foreach ( $bulk_ids_arr as $bulk_key => $bulk_value ) {
				if ( get_post_type( $bulk_value ) === 'wpcd_app_server' ) {
					$server_app_name .= WPCD_SERVER()->get_server_name( $bulk_value ) . ', ';
				} else {
					$site_post = get_post( $bulk_value );
					if ( $site_post ) {
						$server_app_name .= $site_post->post_title . ', ';
					}
				}
			}
		}
		$server_app_name = rtrim( $server_app_name, ', ' );
		return $server_app_name;
	}


	/**
	 * Gets the HTML for display the selected server or app ids in bulk details.
	 *
	 * @param int $post_id bulk action id.
	 *
	 * @return string
	 */
	public function display_the_selected_server_app_bulk_ids( $post_id ) {

		$all_bulk_ids     = get_post_meta( $post_id, 'wpcd_bulk_server_app_ids', true );
		$server_app_names = $this->wpcd_selected_bulk_ids_display_name( $all_bulk_ids );

		$html = '';

		$html .= '<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="wpcd_email_addresses_first_name">' . __( 'Selected Bulk Ids', 'wpcd' ) . '</label>
					</div>
					<div class="rwmb-input">' . $server_app_names . '</div>
				</div>';

		return $html;
	}
}
