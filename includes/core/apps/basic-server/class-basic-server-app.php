<?php
/**
 * Basic Server App
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_BASIC_SERVER_APP
 */
class WPCD_BASIC_SERVER_APP extends WPCD_APP {

	/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance
	 */
	private static $instance;

	/**
	 * Holds a list of actions appropriate for the basic server app.
	 *
	 * @var $_actions actions.
	 */
	private static $_actions;

	/**
	 * Static function that can initialize the class
	 * and return an instance of itself.
	 *
	 * @TODO: This just seems to duplicate the constructor
	 * and is probably only needed when called by
	 * SPMM()->set_class()
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_BASIC_SERVER_APP constructor.
	 */
	public function __construct() {
		// Set app name.
		$this->set_app_name( 'basic-server' );
		$this->set_app_description( 'Basic Linux Server' );

		// Register an app id for this app with WPCD...
		WPCD()->set_app_id( array( $this->get_app_name() => $this->get_app_description() ) );

		// Set folder where scripts are located.
		$this->set_scripts_folder( dirname( __FILE__ ) . '/scripts/' );
		$this->set_scripts_folder_relative( 'includes/core/apps/basic-server/scripts/' );

		// Instantiate some variables.
		$this->set_actions();

		// setup WordPress hooks.
		$this->hooks();

		/* Make sure that we show the server sizes on the provider settings screen - by default they are turned off in settings. */
		add_filter(
			'wpcd_show_server_sizes_in_settings',
			function() {
				return true;
			}
		);

		// Global for backwards compatibility.
		$GLOBALS['wpcd_app_basic_server'] = $this;

	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {
		add_action( 'init', array( &$this, 'init' ), 1 );

		add_action( 'wpcd_basic_server_app_action', array( &$this, 'do_instance_action' ), 10, 3 );

		add_filter( 'spmm_account_page_default_tabs_hook', array( &$this, 'account_tabs' ), 100 );
		add_filter( 'spmm_account_content_hook_vpn', array( &$this, 'account_basic_server_tab_content' ), 10, 2 );

		add_action( 'wp_ajax_wpcd_basic_server', array( &$this, 'ajax' ) );

		add_filter( 'wpcd_cloud_provider_run_cmd', array( &$this, 'get_run_cmd_for_cloud_provider' ), 10, 2 );  // Get script file contents to run for servers that are being provisioned...

		/* Hooks and filters for screens in wp-admin */
		add_filter( 'wpcd_app_admin_list_summary_column', array( &$this, 'app_admin_list_summary_column' ), 10, 2 );  // Show some app details in the wp-admin list of apps.
		add_action( 'add_meta_boxes_wpcd_app', array( $this, 'app_admin_add_meta_boxes' ) );    // Meta box display callback.
		add_action( 'save_post', array( $this, 'app_admin_save_meta_values' ), 10, 2 );         // Save Meta Values.
		add_filter( 'wpcd_app_server_admin_list_local_status_column', array( &$this, 'app_server_admin_list_local_status_column' ), 10, 2 );  // Show the server status.

		// Add a WP state called "BASIC Server" to the app when its shown on the app list.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 20, 2 );

		/* Register shortcodes */
		add_shortcode( 'wpcd_app_basic_server_instances', array( &$this, 'app_basic_server_shortcode' ) );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'basic_server_schedule_events_for_new_site' ), 10, 2 );
	}

	/**
	 * Instantiate the set of actions that are allowed.
	 */
	public function set_actions() {
		self::$_actions = array(
			'relocate'     => __( 'Relocate', 'wpcd' ),
			'reinstall'    => __( 'Reinstall', 'wpcd' ),
			'reboot'       => __( 'Reboot', 'wpcd' ),
			'off'          => __( 'Power Off', 'wpcd' ),
			'on'           => __( 'Power On', 'wpcd' ),
			'instructions' => __( 'Help', 'wpcd' ),
		);
	}

	/**
	 * Single entry point for all AJAX actions.
	 *
	 * Data sent back from the browser:
	 *  $_POST['basic_server_additional']:  any data for the action such as the name when adding or removing users
	 *      Format:
	 *          Array
	 *          (
	 *              [name] => john
	 *          )
	 *
	 * $_POST['basic_server_id']: The post id of the SERVER CPT
	 *
	 * $_POST['basic_server_app_id']: The post id of the APP CPT.
	 *
	 * $_POST['basic_server_action']: The action to perform
	 */
	public function ajax() {
		check_ajax_admin_nonce( 'wpcd_basic_server' );

		/* Extract out any additional parameters that might have been passed from the browser */
		$additional = array();
		if ( isset( $_POST['basic_server_additional'] ) ) {
			$additional = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['basic_server_additional'] ) ) );
		}

		/* Run the action */
		$result = $this->do_instance_action( sanitize_text_field( $_POST['basic_server_id'] ), sanitize_text_field( $_POST['basic_server_action'] ), $additional );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'msg' => $result->get_error_code() ) );
		} else if ( empty( $result ) ) {
			wp_send_json_error();
		}

		wp_send_json_success( array( 'result' => $result ) );
	}

	/**
	 * Add tab to the SPMM  account page
	 *
	 * @param array $tabs tabs.
	 *
	 * @return mixed
	 */
	public function account_tabs( $tabs ) {
		$tabs[399]['basic_server'] = array(
			'icon'        => 'spmm-faicon-desktop',
			'title'       => __( 'Server Instances', 'wpcd' ),
			'custom'      => true,
			'show_button' => false,
		);

		return $tabs;
	}

	/**
	 * Add content to SPMM account tab
	 *
	 * @param string $output output.
	 * @param array  $shortcode_args shortcode args.
	 *
	 * @return string
	 */
	public function account_basic_server_tab_content( $output, $shortcode_args ) {
		$instances = $this->get_instances_for_display();
		if ( empty( $instances ) ) {
			$instances = '<p>' . __( 'No instances found', 'wpcd' ) . '</p>';
			$instances = $this->add_promo_link( 2, $instances );
		}
		$output = '<div class="wpcd-basic-server-grid">' . $instances . '</div>';
		return $output;
	}

	/**
	 * Add shortcode.
	 */
	public function app_basic_server_shortcode() {
		$instances = $this->get_instances_for_display();
		if ( empty( $instances ) ) {
			$instances = '<p>' . __( 'No instances found', 'wpcd' ) . '</p>';
			$instances = $this->add_promo_link( 2, $instances );
		}
		$output = '<div class="wpcd-basic-server-grid">' . $instances . '</div>';
		return $output;
	}

	/**
	 * Return a requested provider object
	 *
	 * @param string $provider name of provider.
	 *
	 * @return VPN_API_Provider_{provider}()
	 */
	public function api( $provider ) {

		return WPCD()->get_provider_api( $provider );

	}

	/**
	 * Return an instance of self.
	 *
	 * @return WPCD_BASIC_SERVER_APP
	 */
	public function get_this() {
		return $this;
	}

	/**
	 * SSH function
	 *
	 * @return BASIC_SERVER_SSH()
	 */
	public function ssh() {
		if ( empty( WPCD()->classes['wpcd_basic_server_ssh'] ) ) {
			WPCD()->classes['wpcd_basic_server_ssh'] = new BASIC_SERVER_SSH();
		}
		return WPCD()->classes['wpcd_basic_server_ssh'];
	}

	/**
	 * WooCommerce function
	 *
	 * @return BASIC_SERVER_WooCommerce()
	 */
	public function woocommerce() {
		if ( empty( WPCD()->classes['wpcd_app_basic_server_wc'] ) ) {
			WPCD()->classes['wpcd_app_basic_server_wc'] = new BASIC_SERVER_WooCommerce();
		}
		return WPCD()->classes['wpcd_app_basic_server_wc'];
	}

	/**
	 * Settings function
	 *
	 * @return BASIC_SERVER_APP_SETTINGS()
	 */
	public function settings() {
		if ( empty( WPCD()->classes['wpcd_app_basic_server_settings'] ) ) {
			WPCD()->classes['wpcd_app_basic_server_settings'] = new BASIC_SERVER_APP_SETTINGS();
		}
		return WPCD()->classes['wpcd_app_basic_server_settings'];
	}


	/**
	 * Init function.
	 */
	public function init() {

		// setup needed objects.
		$this->settings();
		$this->woocommerce();
		$this->ssh();

		add_action( 'wpcd_basic_server_deferred_actions', array( $this, 'do_deferred_actions' ), 10 );
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
				self::basic_server_schedule_events();
				restore_current_blog();
			}
		} else {
			self::basic_server_schedule_events();
		}

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function basic_server_schedule_events() {
		// Clear existing cron.
		wp_clear_scheduled_hook( 'wpcd_basic_server_deferred_actions' );
		
		// Schedule cron for setup deferred instance actions schedule.
		if ( ! defined( 'DISABLE_WPCD_CRON' ) ||  ( defined( 'DISABLE_WPCD_CRON' ) && ! DISABLE_WPCD_CRON ) ) {		
			wp_schedule_event( time(), 'every_minute', 'wpcd_basic_server_deferred_actions' );
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
				self::basic_server_clear_scheduled_events();
				restore_current_blog();
			}
		} else {
			self::basic_server_clear_scheduled_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function basic_server_clear_scheduled_events() {
		wp_clear_scheduled_hook( 'wpcd_basic_server_deferred_actions' );
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
	public function basic_server_schedule_events_for_new_site( $new_site, $args ) {

		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {
			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::basic_server_schedule_events();
			restore_current_blog();
		}

	}

	/**
	 * Perform all deferred actions that need multiple steps to perform.
	 *
	 * @TODO: Update this header to list examples and parameters and expected inputs.
	 *
	 * Also, OOPS, this might compete with other APPS?  We might need to use different keys here...
	 */
	public function do_deferred_actions() {

		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app_server',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => 'wpcd_server_basic-server_action_status',
						'value' => 'in-progress',
					),
				),
				'fields'      => 'ids',
			)
		);

		if ( $posts ) {
			foreach ( $posts as $id ) {
				$action = get_post_meta( $id, 'wpcd_server_basic-server_action', true );
				do_action( 'wpcd_log_error', "calling deferred action $action for $id", 'debug', __FILE__, __LINE__ );
				do_action( 'wpcd_basic_server_app_action', $id, $action );
			}
		}

		set_transient( 'wpcd_do_deferred_actions_for_basic_server_is_active', 1, wpcd_get_long_running_command_timeout() * MINUTE_IN_SECONDS );
	}

	/**
	 * Implement empty method from parent class that adds an app to a server.
	 *
	 * @param array $instance Array of elements that contain information about the server.
	 *
	 * @return array Array of elements that contain information about the server AND the app.
	 */
	public function add_app( &$instance ) {

		$post_id = $instance['post_id']; // extract the server cpt postid from the instance reference.

		/* Loop through the $instance array and add certain elements to the server cpt record */
		foreach ( $instance as $key => $value ) {

			if ( in_array( $key, array( 'init' ), true ) ) {
				continue;
			}

			/* If we're here, then this is a field that's for the server record. */
			update_post_meta( $post_id, 'wpcd_server_' . $key, $value );
		}

		// Restructure the server instance array to add the app data that is going into the wpcd_app CPT.

		/* In this basic server app, there is no real data that will be added though. */
		if ( ! isset( $instance['apps'] ) ) {
			$instance['apps'] = array();
		}

		/*** Normally we would add a record to the apps post type here but since this is a basic server, no apps are being installed. */

		// Schedule after-server-create commands (commands to run after the server has been instantiated for the first time).
		update_post_meta( $post_id, 'wpcd_server_basic-server_action', 'after-server-create-commands' );
		/* update_post_meta( $post_id, 'wpcd_server_after_create_action_app_id', $app_post_id ); */ // No app so no app_post_id var.
		update_post_meta( $post_id, 'wpcd_server_basic-server_action_status', 'in-progress' );
		WPCD_SERVER()->add_deferred_action_history( $post_id, $this->get_app_name() );
		if ( isset( $instance['init'] ) && true === $instance['init'] ) {
			update_post_meta( $post_id, 'wpcd_server_init', '1' );
		}

		return $instance;

	}

	/**
	 * Runs commands after server is created
	 *
	 * If this function is being called, the assumption is that the
	 * server is in an "active" state, ready for commands.
	 *
	 * @param array $instance Array of attributes for the custom post type.
	 */
	private function run_after_server_create_commands( $instance ) {

		/* If we're here, server is up and running, which means we have an IP address. Make sure it gets added to the server record! */
		if ( $instance['post_id'] && isset( $instance['ip'] ) && $instance['ip'] ) {
			WPCD_SERVER()->add_ipv4_address( $instance['post_id'], $instance['ip'] );
		}
		if ( $instance['post_id'] && isset( $instance['ipv6'] ) && $instance['ipv6'] ) {
			WPCD_SERVER()->add_ipv6_address( $instance['post_id'], $instance['ipv6'] );
		}

		do_action( 'wpcd_log_error', 'attempting to run after server create commands for ' . print_r( $instance, true ), 'debug', __FILE__, __LINE__, $instance );

		$run_cmd = $this->get_after_server_create_commands( $instance );

		if ( ! empty( $run_cmd ) ) {
			$run_cmd = str_replace( ' - ', '', $run_cmd );
			$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
			if ( is_wp_error( $result ) ) {
				return false;
			} else {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sends email to the user.
	 *
	 * @param array $instance Array of attributes for the custom post type.
	 */
	private function send_email( $instance ) {
		do_action( 'wpcd_log_error', 'sending email for ' . print_r( $instance, true ), 'debug', __FILE__, __LINE__, $instance );

		$server_post_id = $instance['post_id'];

		// Send email if we have a valid app post id.
		if ( ! empty( $server_post_id ) ) {

			$summary = $this->get_server_instance_summary( $server_post_id );

			if ( ! empty( $summary ) ) {
				$wc_order = wc_get_order( $instance['wc_order_id'] );
				$email    = $wc_order->get_billing_email();

				wp_mail(
					$email,
					__( 'Your new server is ready', 'wpcd' ),
					$summary,
					array( 'Content-Type: text/html; charset=UTF-8' )
				);
			}
		} else {

			do_action( 'wpcd_log_error', 'cannot send email because application cpt id is missing. Server instance id is: ' . print_r( $instance, true ), 'debug', __FILE__, __LINE__, $instance );

		}
	}

	/**
	 * Gets the details summary of the instance for emails and instructions popup.
	 *
	 * @param int  $app_server_id Post ID of the VPN app post/record.
	 * @param bool $email Is this for email?.
	 *
	 * @TODO: break this up into two pieces - one for the server called on the server class and one for this app to get this app details.
	 *
	 * @return string
	 */
	private function get_server_instance_summary( $app_server_id, $email = true ) {

		// Get the server post to match the current app...
		$server_post = $this->get_server_by_server_id( $app_server_id );

		// Get provider from server record.
		$provider        = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );
		$basic_server_id = get_post_meta( $server_post->ID, 'wpcd_server_provider_instance_id', true );
		$details         = WPCD()->get_provider_api( $provider )->call( 'details', array( 'id' => $basic_server_id ) );

		// Get server size from server record.
		$size     = get_post_meta( $server_post->ID, 'wpcd_server_size', true );
		$size     = WPCD()->classes['wpcd_app_basic_server_wc']::$sizes[ strval( $size ) ];
		$region   = get_post_meta( $server_post->ID, 'wpcd_server_region', true );
		$provider = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );

		$template = file_get_contents( dirname( __FILE__ ) . '/templates/' . ( $email ? 'email' : 'popup' ) . '.html' );
		return str_replace(
			array( '$NAME', '$PROVIDER', '$IP', '$SIZE', '$URL' ),
			array(
				get_post_meta( $server_post->ID, 'wpcd_server_name', true ),
				self::$providers[ $provider ],
				$details['ip'],
				$size,
				site_url( 'account' ),
			),
			$template
		);
	}

	/**
	 * Performs an action on a server instance.
	 *
	 * @param int    $server_post_id Post ID.
	 * @param string $action Action to perform.
	 * @param array  $additional Additional data that may be required for the action.
	 *
	 * @return mixed
	 */
	public function do_instance_action( $server_post_id, $action, $additional = array() ) {

		// Bail if the post type is not a server.
		if ( get_post_type( $server_post_id ) !== 'wpcd_app_server' || empty( $action ) ) {
			return;
		}

		// Bail if the server type is not a basic server.
		if ( 'basic-server' !== $this->get_server_type( $server_post_id ) ) {
			return;
		}

		$attributes = array(
			'post_id' => $server_post_id,
		);

		/* Get data from server post */
		$all_meta = get_post_meta( $server_post_id );
		foreach ( $all_meta as $key => $value ) {
			if ( 'wpcd_server_app_post_id' == $key ) {
				continue;  // this key, if present, should not be added to the array since it shouldn't even be in the server cpt in the first place. But it might get there accidentally on certain operations.
			}

			if ( strpos( $key, 'wpcd_server_' ) === 0 ) {
				$value = wpcd_maybe_unserialize( $value );
				$attributes[ str_replace( 'wpcd_server_', '', $key ) ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}
		}

		$current_status = get_post_meta( $server_post_id, 'wpcd_server_basic-server_action_status', true );
		if ( empty( $current_status ) ) {
			$current_status = '';
		}

		do_action( 'wpcd_log_error', "performing $action for $server_post_id on $current_status with " . print_r( $attributes, true ) . ', additional ' . print_r( $additional, true ), 'debug', __FILE__, __LINE__ );

		delete_post_meta( $server_post_id, 'wpcd_server_error' );
		WPCD_SERVER()->add_deferred_action_history( $server_post_id, $this->get_app_name() );

		$details = WPCD()->get_provider_api( $attributes['provider'] )->call( 'details', array( 'id' => $attributes['provider_instance_id'] ) );
		// problem fetching details. Maybe instance was deleted?
		// except for delete, bail!
		if ( is_wp_error( $details ) && 'delete' !== $action ) {
			do_action( 'wpcd_log_error', 'Unable to find instance on ' . $attributes['provider'] . ". Aborting action $action.", 'warn', __FILE__, __LINE__ );
			return $details;
		}

		$result = true;
		switch ( $action ) {
			case 'after-server-create-commands':
				$state = $details['status'];
				// run commands only when the server is 'active'.
				if ( 'active' === $state ) {
					// Merge post ids and server details into a single array.
					$attributes = array_merge( $attributes, $details );

					if ( true == $this->run_after_server_create_commands( $attributes ) ) {
						// schedule emails to be sent..
						update_post_meta( $attributes['post_id'], 'wpcd_server_basic-server_action', 'email' );
						WPCD_SERVER()->add_deferred_action_history( $attributes['post_id'], $this->get_app_name() );
					}
				}
				break;
			case 'email':
				$state = $details['status'];
				// send email only when 'active'.
				if ( 'active' === $state ) {
					// Deleting these three items means that sending this email is the last thing in the deferred action sequence and no more deferred actions will occur for this server.
					delete_post_meta( $attributes['post_id'], 'wpcd_server_basic-server_action_status' );
					delete_post_meta( $attributes['post_id'], 'wpcd_server_basic-server_action' );
					delete_post_meta( $attributes['post_id'], 'wpcd_server_basic-server_init', '1' );  // This one is only going to be present on a NEW server but should not be there for relocations and reinstalls.
					$attributes = array_merge( $attributes, $details );
					$this->send_email( $attributes );
					delete_post_meta( $attributes['post_id'], 'wpcd_server_basic-server_action_email_app_id' ); // must be done AFTER the email is processed since it needs the app id.
					WPCD_SERVER()->add_deferred_action_history( $attributes['post_id'], $this->get_app_name() );
				}
				break;
			case 'relocate':
				// this is like a new create with a new region.
				$new_attributes                   = $attributes;
				$new_attributes['provider']       = $additional['provider'];
				$new_attributes['region']         = $additional['region'];
				$new_attributes['parent_post_id'] = $server_post_id;
				unset( $new_attributes['post_id'] );

				$new_instance = WPCD_SERVER()->relocate_server( $attributes, $new_attributes );

				$new_instance = $this->add_app( $new_instance );  // add application to server and update the new instance record with the data it needs.

				break;
			case 'reinstall':
				// this is like a new create with the same region as before.
				$new_attributes                   = $attributes;
				$new_attributes['parent_post_id'] = $server_post_id;
				unset( $new_attributes['post_id'] );

				$new_instance = WPCD_SERVER()->reinstall_server( $attributes, $new_attributes );

				$new_instance = $this->add_app( $new_instance );  // add application to server and update the new instance record with the data it needs.

				break;
			case 'reboot':
				$result = WPCD_SERVER()->reboot_server( $attributes );
				break;
			case 'off':
				$result = WPCD_SERVER()->turn_off_server( $attributes );
				break;
			case 'on':
				$result = WPCD_SERVER()->turn_on_server( $attributes );
				break;
			case 'delete':
				$result = WPCD_SERVER()->delete_server( $attributes );

				break;
			case 'details':
				// already called.
				break;
		}
		return $result;
	}

	/**
	 * Returns the list of instances to display.
	 *
	 * Unlike the VPN app, this is only displaying
	 * server instances, NOT app instances.
	 *
	 * @return string
	 */
	private function get_instances_for_display() {

		// Get a list of servers that the user has...
		$app_servers = $this->get_servers_by_user_id( get_current_user_id() );  // get_servers_by_user_id is a function in the ancestor class.

		if ( is_countable( $app_posts ) ) {
			$app_posts_cnt = count( $app_posts );
		} else {
			$app_posts_cnt = 0;
		}
		do_action( 'wpcd_log_error', 'Got ' . $app_posts_cnt . ' server instances for user = ' . get_current_user_id(), 'debug', __FILE__, __LINE__ );

		/* Get a list of regions and providers - need this to build dropdowns and such */

		/* @TODO: Shouldn't this be extracted out to its own set of functions - its called multiple times I think. */
		$provider_regions = array();
		$clouds           = WPCD()->get_active_providers();
		$regions          = array();
		$providers        = array();
		foreach ( $clouds as $provider => $name ) {
			$locs = WPCD()->get_provider_api( $provider )->call( 'regions' );

			// if api key not provided or an error occurs, bail!
			if ( ! $locs || is_wp_error( $locs ) ) {
				continue;
			}
			if ( empty( $regions ) ) {
				$regions = $locs;
			}
			$providers[ $provider ] = $name;
			$locations              = array();
			foreach ( $locs as $slug => $loc ) {
				$locations[] = array(
					'slug' => $slug,
					'name' => $loc,
				);
			}
			$provider_regions[ $provider ] = $locations;
		}
		/* End get a list of regions and providers */

		wp_register_script( 'wpcd-basic-server-magnific', wpcd_url . 'assets/js/jquery.magnific-popup.min.js', array( 'jquery' ), wpcd_scripts_version, true );
		wp_enqueue_script( 'wpcd-basic-server', wpcd_url . 'includes/core/apps/basic-server/assets/js/wpcd-basic-server.js', array( 'wpcd-basic-server-magnific', 'wp-util' ), wpcd_scripts_version, true );
		wp_localize_script(
			'wpcd-basic-server',
			'attributes',
			array(
				'nonce'            => wp_create_nonce( 'wpcd_basic_server' ),
				'provider_regions' => $provider_regions,
			)
		);
		wp_register_style( 'wpcd-basic-server-magnific', wpcd_url . 'assets/css/magnific-popup.css', array(), wpcd_scripts_version );
		wp_enqueue_style( 'wpcd-basic-server', wpcd_url . 'includes/core/apps/basic-server/assets/css/wpcd-basic-server.css', array( 'wpcd-basic-server-magnific' ), wpcd_scripts_version );
		wp_enqueue_style( 'wpcd-basic-server-fonts', wpcd_url . 'assets/fonts/spinupvpnwebsite.css', array(), wpcd_scripts_version );

		$output = '<div class="wpcd-basic-server-instances-list">';
		foreach ( $app_servers as $app_server ) {

			// Get the server post to match the current app...
			$server_post = $this->get_server_by_server_id( $app_server->ID );

			// Get some data from the server post.
			$provider        = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );
			$basic_server_id = get_post_meta( $server_post->ID, 'wpcd_server_provider_instance_id', true );
			$region          = get_post_meta( $server_post->ID, 'wpcd_server_region', true );

			// Get regions from the provider.
			if ( ! empty( WPCD()->get_provider_api( $provider ) ) ) {
				$regions = WPCD()->get_provider_api( $provider )->call( 'regions' );
			} else {
				$regions = null;
			}
			if ( ! empty( $regions ) ) {
				$display_region = $regions[ $region ];
			} else {
				$display_region = __( 'missing', 'wpcd' );
			}

			// Get data about the server instance.
			$actions = array();
			$details = null;
			if ( ! empty( WPCD()->get_provider_api( $provider ) ) ) {
				$details = WPCD()->get_provider_api( $provider )->call( 'details', array( 'id' => $basic_server_id ) );
			}
			// problem fetching details. Maybe instance was deleted?
			if ( is_wp_error( $details ) || empty( $details ) ) {
				$actions = 'mia';
			} else {
				$actions = $this->get_actions_for_instance( $app_server->ID, $details );
			}

			// Start building display HTML for this particular server/app instance.
			$buttons         = '';
			$html_attributes = array();
			if ( is_array( $actions ) ) {
				foreach ( $actions as $action ) {

					// Some actions require a 'wrapping' div to help the breaks for css-grid.
					if ( 'reboot' === $action || 'relocate' === $action ) {
						$buttons = $buttons . '<div class="wpcd-basic-server-instance-multi-button-block-wrap">';  // this should be matched later with a footer div.
					}

					$help_tip       = ''; // text that will go underneath each button...
					$foot_break     = false;  // whether or not to insert a footer div after the block.
					$buttons       .= '<div class="wpcd-basic-server-instance-button-block">'; // opening div for button action block.
					$btn_icon_class = '';  /* classname to render icon before text on some buttons */
					switch ( $action ) {
						case 'off':
							$btn_icon_class = '<span class="icon-spvpnpower_off"></span>';
							$help_tip       = __( 'Power off the server. No one will be able to connect until you power it back on.', 'wpcd' );
							break;
						case 'on':
							$help_tip = __( 'Turn on the server.  After clicking this, wait a couple of mins to let it spin up before attempting to connect.', 'wpcd' );
							break;
						case 'reboot':
							$btn_icon_class = '<span class="icon-spvpnreboot"></span>';
							$buttons       .= '<div class="wpcd-basic-server-action-head">' . __( 'Reboot/Reinstall/Poweroff', 'wpcd' ) . '</div>'; // Add in the section title text.
							$help_tip       = __( 'Restart the server.  Hey - it is a computer and sometimes you just need to do this.', 'wpcd' );
							break;
						case 'reinstall':
							$btn_icon_class = '<span class="icon-spvpnreinstall"></span>';
							$help_tip       = __( 'Start over.  This will put the server back into a brand new state, removing all users and data. You will then need to reinstall your apps.', 'wpcd' );
							$foot_break     = true;
							break;
						case 'relocate':
							$btn_icon_class = '<span class="icon-spvpnreinstall"></span>';
							$buttons       .= '<div class="wpcd-basic-server-action-head">' . __( 'Move Your Server', 'wpcd' ) . '</div>'; // Add in the section title text.
							$select1        = '<select class="wpcd-basic-server-additional wpcd-basic-server-provider wpcd-basic-server-select" name="provider">';
							foreach ( $providers as $slug => $name ) {
								$select1 .= '<option value="' . $slug . '" ' . selected( $slug, $provider, false ) . '>' . $name . '</option>';
							}
							$select2 = '<select class="wpcd-basic-server-additional wpcd-basic-server-region wpcd-basic-server-select" name="region">';
							foreach ( $provider_regions[ $provider ] as $regions ) {
								if ( $regions['slug'] === $region ) {
									continue;
								}
								$select2 .= '<option value="' . $regions['slug'] . '">' . $regions['name'] . '</option>';
							}
							$select1   .= '</select>';
							$select2   .= '</select>';
							$buttons   .= $select1 . $select2;
							$help_tip   = __( 'Move your server to a different location. All user data will be removed and you will need to reinstall all your applications.', 'wpcd' );
							$foot_break = true;
							break;
						case 'instructions':
							$summary         = $this->get_server_instance_summary( $app_server->ID, false );
							$html_attributes = array( 'href' => '#instructions-' . $server_post->ID );
							$buttons        .= '<div id="instructions-' . $server_post->ID . '" class="wpcd-basic-server-instructions mfp-hide">' . $summary . '</div>';
							$help_tip        = __( 'Some basic help and instructions', 'wpcd' );
							$foot_break      = true;
							break;
					}
					$attributes = '';
					if ( $html_attributes ) {
						foreach ( $html_attributes as $key => $value ) {
							$attributes .= $key . '=' . esc_attr( $value );
						}
					}

					$buttons .= '<button ' . $attributes . ' class="wpcd-basic-server-action-type wpcd-basic-server-action-' . $action . '" data-action="' . $action . '" data-id="' . $server_post->ID . '" data-app-id="' . '' . '">' . $btn_icon_class . ' ' . self::$_actions[ $action ] . '</button>';

					if ( ! empty( $help_tip ) ) {
						$buttons .= '<div class="wpcd-basic-server-action-help-tip">' . $help_tip . '</div>'; // Add in the help text.
					}

					$buttons .= '</div> <!-- closing div for this button action block --> ';  // closing div for this button action block.

					if ( true == $foot_break ) {
						$buttons .= '<div class="wpcd-basic-server-action-foot $action">' . '</div>'; // Add in footer break as necessary - styling will be done in CSS file of course.
						$buttons .= '</div> <!-- close multi-button block wrap -->'; // close up a multi-button block wrap.
					}
				}
			} elseif ( is_string( $actions ) ) {
				switch ( $actions ) {
					case 'in-progress':
						$buttons = __( 'The instance is currently transitioning state. <br />This happens just after a new purchase when a server is starting up or when rebooting or relocating. <br />Please check back in a few minutes. If you continue to see this message after that please contact our support team.', 'wpcd' );
						break;
					case 'errored':
						$buttons = __( 'An error occurred in the VPN server. Please check back in a few minutes. If you continue to see this message after that please contact our support team.', 'wpcd' );
						break;
					case 'new':
						$buttons = __( 'The instance is initializing. Please check back in a few minutes. If you continue to see this message after that please contact our support team.', 'wpcd' );
						break;
					case 'mia':
						$buttons = __( 'The instance is missing in action. Please check back in a few minutes. If you continue to see this message after that please contact our support team.', 'wpcd' );
						break;
				}
			}

			$size = get_post_meta( $server_post->ID, 'wpcd_server_size', true );

			$subscription = wpcd_maybe_unserialize( get_post_meta( $server_post->ID, 'wpcd_server_wc_subscription', true ) );

			/**
			 * These strings are the class names for the icons from our custom icomoon font file -
			 * we're still using the VPN APP FONT FILE for now so the class names have 'vpn' in it.
			 */
			$provider_icon = '<div class="icon-spvpnprovider"><span class="path1"></span><span class="path2"></span></div>';
			$region_icon   = '<div class="icon-spvpnregion"><span class="path1"></span><span class="path2"></span></div>';
			$size_icon     = '<div class="icon-spvpnsize"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></div>';
			$subid_icon    = '<div class="icon-spvpnsubscription_id"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span><span class="path7"></span></div>';
			/* End classnames from icomoon font file */

			$output .= '
				<div class="wpcd-basic-server-instance">
					<div class="wpcd-basic-server-instance-name">' . get_post_meta( $server_post->ID, 'wpcd_server_name', true ) . '</div>
					<div class="wpcd-basic-server-instance-atts">' .
						'<div class="wpcd-basic-server-instance-atts-provider-wrap">' . $provider_icon . '<div class="wpcd-basic-server-instance-atts-provider-label">' . __( 'Provider', 'wpcd' )        . ': ' . '</div>' . $this->get_providers()[$provider] . '</div>
						<div class="wpcd-basic-server-instance-atts-region-wrap">'    . $region_icon   . '<div class="wpcd-basic-server-instance-atts-region-label">'   . __( 'Region', 'wpcd' )          . ': ' . '</div>' . $display_region . '</div>
						<div class="wpcd-basic-server-instance-atts-size-wrap">'      . $size_icon     . '<div class="wpcd-basic-server-instance-atts-size-label">'     . __( 'Size', 'wpcd' )            . ': ' . '</div>' . WPCD()->classes['wpcd_app_basic_server_wc']::$sizes[ strval( $size ) ] . '</div>
						<div class="wpcd-basic-server-instance-atts-subid-wrap">'     . $subid_icon    . '<div class="wpcd-basic-server-instance-atts-sub-label">'      . __( 'Subscription ID', 'wpcd' ) . ': ' . '</div>' . implode( ', ', $subscription ) . '</div>' ;

			$output .= '</div>';
			$output  = $this->add_promo_link( 1, $output );
			$output .= '<div class="wpcd-basic-server-instance-actions">' . $buttons . '</div>
				</div>';
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Determines what actions to show for a Server instance.
	 *
	 * @param int   $id Post ID of the SERVER being handled.
	 * @param array $details Details of the Server instance as per the 'details' API call.
	 *
	 * @return array
	 */
	private function get_actions_for_instance( $id, $details ) {
		/* Note: the order of items in this array is very important for styling purposes so re-arrange at your own risk. */
		$actions = array(
			'instructions',
			'reboot',
			'off',
			'on',
			'reinstall',
			'relocate',
		);

		// problem fetching details. Maybe instance was deleted?
		if ( is_wp_error( $details ) ) {
			return 'mia';
		}

		// Get a server post record based on the app id passed in...
		$server_post = $this->get_server_by_server_id( $id );

		// What's the state of the server?
		$state  = $details['status'];
		$status = get_post_meta( $server_post->ID, 'wpcd_server_basic-server_action_status', true );

		do_action( 'wpcd_log_error', "Determining actions for $id in current state ($state) with action status ($status)", 'debug', __FILE__, __LINE__ );

		if ( in_array( $status, array( 'in-progress', 'errored' ) ) ) {
			// stuck in the middle with you?
			return $status;
		}

		if ( 'off' === $state ) {
			if ( ( $key = array_search( 'off', $actions ) ) !== false ) {
				unset( $actions[ $key ] );
			}
			if ( ( $key = array_search( 'instructions', $actions ) ) !== false ) {
				unset( $actions[ $key ] );
			}
		} elseif ( in_array( $state, array( 'new' ) ) ) {
			// when it's 'new', let it become 'active'.
			return $state;
		} else {
			if ( ( $key = array_search( 'on', $actions ) ) !== false ) {
				unset( $actions[ $key ] );
			}
		}

		return $actions;
	}

	/**
	 * Performs an SSH command on a VPN instance.
	 *
	 * @param string $action Action to perform.
	 * @param array  $attributes Attributes of the VPN instance.
	 * @param array  $additional Additional data that may be required for the action.
	 *
	 * @return mixed
	 *
	 * @TODO / NOTE : None of these items in here makes any sense for a basic server.
	 * But keeping it around as a template for later if we allow the user to
	 * add an app.
	 */
	public function execute_ssh( $action, $attributes, $additional ) {

		$action_int = '';

		switch ( $action ) {
			case 'add-user':
				$action_int = 1;
				break;
			case 'remove-user':
				$action_int = 2;
				break;
		}

		$instance = WPCD()->get_provider_api( $attributes['provider'] )->call( 'details', $attributes );
		$ip       = $instance['ip'];

		$root_user = WPCD()->get_provider_api( $attributes['provider'] )->get_root_user();

		$passwd = wpcd_get_option( 'vpn_' . $attributes['provider'] . '_sshkey_passwd' );
		if ( ! empty( $passwd ) ) {
			$passwd = self::decrypt( $passwd );
		}
		$key = array(
			'passwd' => $passwd,
			'key'    => wpcd_get_option( 'vpn_' . $attributes['provider'] . '_sshkey' ),
		);

		$post_id = $attributes['post_id'];

		$result = null;
		switch ( $action ) {
			case 'add-user':
				$name     = $additional['name'];
				$commands = 'export basic_server_option=' . $action_int . '; export basic_server_client="' . $name . '"; sudo -E bash /root/openvpn-script.sh;';
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user );
				break;
			case 'remove-user':
				$name     = $additional['name'];
				$commands = 'export basic_server_option=' . $action_int . '; export basic_server_client="' . $name . '"; sudo -E bash /root/openvpn-script.sh;';
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user );
				break;
			case 'download-file':
				$name          = $additional['name'];
				$instance_name = get_post_meta( $post_id, 'wpcd_server_name', true );
				$result        = $this->ssh()->download( $ip, '/root/' . $name . '.ovpn', '', $key, $root_user );  // @TODO: This assumues root user which will not work on AWS and other providers who default to a non-root user.
				if ( is_wp_error( $result ) ) {
					echo wp_send_json_error( array( 'msg' => $result->get_error_code() ) );
				}
				echo wp_send_json_success(
					array(
						'contents' => $result,
						'name'     => sprintf(
							'%s-%s',
							$instance_name,
							$additional['name'] . '.ovpn'
						),
					)
				);
				break;
			case 'connected':
				$commands = 'sudo -E grep "CLIENT_LIST" /etc/openvpn/server/openvpn-status.log | grep -v "HEADER" | awk -F "," \'{print $2}\' | tr \'\n\' \',\' ';
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user );
				break;
			case 'disconnect':
				$name     = $additional['name'];
				$commands = '(echo "sudo -E kill \"' . $name . '\""; sleep 1; echo "quit";) | nc localhost 7000';
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user );
				$commands = 'sudo -E grep "CLIENT_LIST" /etc/openvpn/server/openvpn-status.log | grep -v "HEADER" | awk -F "," \'{print $2}\' | tr \'\n\' \',\' ';
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user );
				break;
			case 'generic':
				$commands = $additional['commands'];
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user );
				break;
		}

		do_action( 'wpcd_log_error', 'execute_ssh: result = ' . print_r( $result, true ), 'debug', __FILE__, __LINE__ );

		if ( is_wp_error( $result ) ) {
			do_action( 'wpcd_log_error', print_r( $result, true ), 'error', __FILE__, __LINE__ );
			return $result;
		}

		if ( in_array( $action, array( 'connected', 'disconnect' ) ) ) {
			return $result;
		}

		// if successful.
		switch ( $action ) {
			case 'add-user':
				$this->add_remove_client( $app_post_id, $additional['name'], 'add' );
				break;
			case 'remove-user':
				$this->add_remove_client( $app_post_id, $additional['name'], 'remove' );
				break;
		}

		WPCD_SERVER()->add_action_to_history( $action, $attributes );
		return true;
	}



	/**
	 * Adds the promotion mark up text to the SERVER user account area.
	 *
	 * @param int    $linkid Which promo are we adding?  For now there is only one....
	 *
	 * @param string $markup The existing markup for the accounta area - we'll append our text to this...
	 *
	 * @return string
	 */
	public function add_promo_link( $linkid, $markup ) {

		switch ( $linkid ) {
			case 1:
				$promo_url     = wpcd_get_option( 'basic_server_promo_item01_url' );
				$promo_text    = wpcd_get_option( 'basic_server_promo_item01_text' );
				$button_option = boolval( wpcd_get_option( 'basic_server_promo_item01_button_option' ) );

				if ( ! empty( $promo_url ) ) {
					if ( false == $button_option ) {
						// just a link..
						$markup .= '<div class="wpcd-basic-server-instance-promo wpcd-basic-server-instance-promo-01" >' . '<a href=' . '"' . $promo_url . '"' . '>' . $promo_text . '</a>' . '</div>';
					} else {
						// make it a button...
						$markup .= '<div class="wpcd-basic-server-instance-promo-button wpcd-basic-server-instance-promo-button-01" >' . '<a href=' . '"' . $promo_url . '"' . '>' . $promo_text . '</a>' . '</div>';
					}
				}
				break;
			case 2:
				$promo_url     = wpcd_get_option( 'basic_server_promo_item02_url' );
				$promo_text    = wpcd_get_option( 'basic_server_promo_item02_text' );
				$button_option = boolval( wpcd_get_option( 'basic_server_promo_item02_button_option' ) );

				if ( ! empty( $promo_url ) ) {
					if ( false == $button_option ) {
						// just a link..
						$markup .= '<div class="wpcd-basic-server-instance-promo wpcd-basic-server-instance-promo-02" >' . '<a href=' . '"' . $promo_url . '"' . '>' . $promo_text . '</a>' . '</div>';
					} else {
						// make it a button...
						$markup .= '<div class="wpcd-basic-server-instance-promo-button wpcd-basic-server-instance-promo-button-02" >' . '<a href=' . '"' . $promo_url . '"' . '>' . $promo_text . '</a>' . '</div>';
					}
				}
				break;

		}

		return $markup;

	}

	/**
	 * Get script file contents to run for servers that are being provisioned...
	 *
	 * Generally, the file contents is a combination of three components:
	 *  1. The run commands passed into the cloud provider api.
	 *  2. The main bash script that we want to run upon start up.
	 *  3. Parameters to the bash script.
	 *
	 * Filter Hook: wpcd_cloud_provider_run_cmd
	 *
	 * @param string $run_cmd Existing run command that we are going to modify.  Usually this is blank and we will be overwriting it.
	 * @param array  $attributes Attributes of the server and app being provisioned.
	 *
	 * @return string $run_cmd
	 */
	public function get_run_cmd_for_cloud_provider( $run_cmd, $attributes ) {
		// Since we moved all commands to after the server has been created, this is no longer used.
		// Just leaving it in here in case we need it for some reason later.
		return $run_cmd;
	}

	/**
	 * Get script file contents to run for servers that are being provisioned...
	 *
	 * Generally, the file contents is a combination of three components:
	 *  1. The run commands passed into the cloud provider api.
	 *  2. The main bash script that we want to run upon start up.
	 *  3. Parameters to the bash script.
	 *
	 * Filter Hook: wpcd_cloud_provider_run_cmd
	 *
	 * @param array $attributes Attributes of the server and app being provisioned.
	 *
	 * @return string $run_cmd
	 *
	 * @TODO / NOTE: This does not apply for this basic-server install.
	 * But keeping this around as a template in case we need it later
	 * when we allow the user to add an app.
	 */
	public function get_after_server_create_commands( $attributes ) {

		// Set initial return command - blank.
		$run_cmd = '';

		/* first check that we are handling a basic server app / server - use attributes array */
		$ok_to_run = false;
		if ( isset( $attributes['app_post_id'] ) ) {
			$app_type = $this->get_app_type( $attributes['app_post_id'] );
			if ( $this->get_app_name() == $app_type ) {
				$ok_to_run = true;
			}
		}

		if ( ! $ok_to_run ) {
			if ( isset( $attributes['initial_app_name'] ) ) {
				if ( $this->get_app_name() == $attributes['initial_app_name'] ) {
					$ok_to_run = true;
				}
			}
		}

		if ( ! $ok_to_run ) {
			/* this does not belong to us so just quit now */
			return $run_cmd;
		}
		/* End first check that we are handling a basic server app / server - use attributes array */

		/* What version of the scripts are we getting? */

		/* @TODO: we're probably going to use this code block in other apps so re-factor to place in the ancestor class? */
		$script_version = '';
		if ( isset( $attributes['scripts_version'] ) && ( ! empty( $attributes['scripts_version'] ) ) ) {
			$script_version = $attributes['scripts_version'];
		} else {
			$script_version = wpcd_get_option( 'basic_server_script_version' );
		}
		if ( empty( $script_version ) ) {
			$script_version = 'v1';
		}
		/* End what version of the scripts are we getting */

		/**
		 * Grab the contents of the run commands template - the text in this template is updated to replace certain ##TOKENS##
		 * and then passed into the cloud provider upon start up.  In the case of Digital Ocean, it uses the run_cmd attribute
		 * of the API to pass in these commands.
		 *
		 * We first check to see if the filename exists with the providers prefix.  If not then we try to grab one without the prefix.
		 */
		$startup_run_commands = $this->get_script_file_contents( $this->get_scripts_folder() . $script_version . '/' . $attributes['provider'] . '-after-server-create-run-commands.txt' );
		if ( empty( $startup_run_commands ) ) {
			$startup_run_commands = $this->get_script_file_contents( $this->get_scripts_folder() . $script_version . '/after-server-create-run-commands.txt' );
		}

		/* Construct an array of placeholder tokens for the run command file  */

		/* @NOTE: This isn't really used in this app. */
		$place_holders = array( 'URL-SCRIPT' => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/' . 'install-open-vpn.txt' );

		/* Replace the startup run command contents */
		$startup_run_commands = $this->replace_script_tokens( $startup_run_commands, $place_holders );
		$startup_run_commands = dos2unix_strings( $startup_run_commands ); // make sure that we only have unix line endings...

		return $startup_run_commands;
	}


	/**
	 * Add content to the app summary column that shows up in app admin list
	 *
	 * Filter Hook: wpcd_app_admin_list_summary_column
	 *
	 * @param string $column_data Data to show in the column.
	 * @param int    $post_id Id of app post being displayed.
	 *
	 * @return: string $column_data
	 */
	public function app_admin_list_summary_column( $column_data, $post_id ) {

		/* Bail out if the app being evaluated isn't a vpn app. */
		if ( 'basic-server' <> get_post_meta( $post_id, 'app_type', true ) ) {
			return $column_data;
		}

		/* Put a line break to separate out data section from others if the column already contains data */
		if ( ! empty( $colummn_data ) ) {
			$column_data = $column_data . '<br />';
		}

		/* Add our data element */
		$column_data = $column_data . 'scripts version: ' . get_post_meta( $post_id, 'basic_server_scripts_version', true ) . '<br />';

		return $column_data;
	}

	/**
	 * Show the server status as it resides in the server cpt for this app.
	 *
	 * Filter Hook: wpcd_app_server_admin_list_local_status_column
	 *
	 * @param string $column_data Data to show in the column.
	 * @param int    $post_id Id of app post being displayed.
	 *
	 * @return: string $column_data
	 */
	public function app_server_admin_list_local_status_column( $column_data, $post_id ) {

		$local_status = get_post_meta( $post_id, 'wpcd_server_basic-server_action_status', true );
		if ( empty( $local_status ) ) {
			return $column_data;
		} else {
			return $local_status;
		}

	}


	/**
	 * Add metabox to the app post detail screen in wp-admin.
	 *
	 * @param object $post post.
	 *
	 * Action Hook: add_meta_box.
	 */
	public function app_admin_add_meta_boxes( $post ) {

		/* Only paint metabox when the app-type is a basic server. */
		if ( ! ( 'basic-server' === get_post_meta( $post->ID, 'app_type', true ) ) ) {
			return;
		}

		// Add APP DETAIL meta box into APPS custom post type.
		add_meta_box(
			'basic_server_app_detail',
			__( 'Server', 'wpcd' ),
			array( $this, 'render_basic_server_app_details_meta_box' ),
			'wpcd_app',
			'advanced',
			'high'
		);
	}

	/**
	 * Render the VPN APP detail meta box in the app post detai screen in wp-admin
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_basic_server_app_details_meta_box( $post ) {

		/* Only render data in the metabox when the app-type is a vpn. */
		if ( ! 'basic-server' === get_post_meta( $post->ID, 'app_type', true ) ) {
			return;
		}

		$html = '';

		$wpcd_basic_server_app_scripts_version = get_post_meta( $post->ID, 'basic_server_scripts_version', true );

		ob_start();
		require wpcd_path . 'includes/core/apps/basic-server/templates/basic_server_app_details.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

	}

	/**
	 * Handles saving the meta box in the app post detail screen in wp-admin.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post    Post object.
	 * @return null
	 */
	public function app_admin_save_meta_values( $post_id, $post ) {

		/* Only save metabox data when the app-type is a vpn. */
		if ( ! 'basic-server' === get_post_meta( $post->ID, 'app_type', true ) ) {
			return;
		}

		// Add nonce for security and authentication.
		$nonce_name   = sanitize_text_field( filter_input( INPUT_POST, 'basic_server_meta', FILTER_UNSAFE_RAW ) );
		$nonce_action = 'wpcd_basic_server_app_nonce_meta_action';

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

		/* Get new values */
		$wpcd_basic_server_app_scripts_version = sanitize_text_field( filter_input( INPUT_POST, 'wpcd_basic_serverscripts_version', FILTER_UNSAFE_RAW ) );

		/* Add new values to database */
		update_post_meta( $post_id, 'basic_server_scripts_version', $wpcd_basic_server_app_scripts_version );

	}

	/**
	 * Set the post state display
	 *
	 * Filter Hook: display_post_states
	 *
	 * @param array  $states The current states for the CPT record.
	 * @param object $post The post object.
	 *
	 * @return array $states
	 */
	public function display_post_states( $states, $post ) {

		/* Show the app type on the app list screen */
		if ( 'wpcd_app' === get_post_type( $post ) && 'basic-server' == $this->get_app_type( $post->ID ) ) {
			$states['wpcd-app-desc'] = $this->get_app_description();
		}

		/* Show the server type on the server list screen */
		if ( 'wpcd_app_server' === get_post_type( $post ) && 'basic-server' == $this->get_server_type( $post->ID ) ) {
			$states['wpcd-server-type'] = 'Basic Svr';  // Unfortunately we don't have a server type description function we can call right now so hardcoding the value here.
		}
		return $states;
	}


}
