<?php
/**
 * Stable Diffusion App
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_STABLEDIFF_APP.
 */
class WPCD_STABLEDIFF_APP extends WPCD_APP {

	/**
	 * Holds a reference to this class.
	 *
	 * @var $instance instance.
	 */
	private static $instance;

	/**
	 * Holds a list of actions appropriate for the stablediff app.
	 *
	 * @var $_actions actions.
	 */
	private static $_actions;

	/**
	 * Holds a reference to a list of cloud providers.
	 *
	 * @var $providers providers.
	 */
	private static $providers;


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
	 * WPCD_STABLEDIFF_APP constructor.
	 */
	public function __construct() {

		parent::__construct();

		// Set app name.
		$this->set_app_name( 'stablediff' );
		$this->set_app_description( 'Stable Diffusion 1.4 Server' );

		// Register an app id for this app with WPCD.
		WPCD()->set_app_id( array( $this->get_app_name() => $this->get_app_description() ) );

		// Set folder where scripts are located.
		$this->set_scripts_folder( dirname( __FILE__ ) . '/scripts/' );
		$this->set_scripts_folder_relative( 'includes/core/apps/stable-diffusion/scripts/' );

		// Instantiate some variables.
		$this->set_actions();

		// setup WordPress hooks.
		$this->hooks();

		// Global for backwards compatibility.
		$GLOBALS['wpcd_app_stablediff'] = $this;

	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {
		add_action( 'init', array( &$this, 'init' ), 1 );

		add_action( 'wpcd_app_action', array( &$this, 'do_instance_action' ), 10, 4 );

		// @TODO: Need to convert these to Ultimate Member hooks.
		add_filter( 'spmm_account_page_default_tabs_hook', array( &$this, 'account_tabs' ), 100 );
		add_filter( 'spmm_account_content_hook_stablediff', array( &$this, 'account_stablediff_tab_content' ), 10, 2 );

		add_action( 'wp_ajax_wpcd_stablediff', array( &$this, 'ajax' ) );

		add_action( 'woocommerce_account_downloads_endpoint', array( &$this, 'account_downloads_tab_content' ) ); // Add content to account tab 'Downloads' to let users know where to find downloads.

		add_filter( 'wpcd_cloud_provider_run_cmd', array( &$this, 'get_run_cmd_for_cloud_provider' ), 10, 2 );  // Get script file contents to run for servers that are being provisioned...

		/* Hooks and filters for screens in wp-admin */
		add_filter( 'wpcd_app_admin_list_summary_column', array( &$this, 'app_admin_list_summary_column' ), 10, 2 );  // Show some app details in the wp-admin list of apps.
		add_action( 'add_meta_boxes_wpcd_app', array( $this, 'app_admin_add_meta_boxes' ) );    // Meta box display callback.
		add_action( 'save_post', array( $this, 'app_admin_save_meta_values' ), 10, 2 );         // Save Meta Values.
		add_filter( 'wpcd_app_server_admin_list_local_status_column', array( &$this, 'app_server_admin_list_local_status_column' ), 10, 2 );  // Show the server status.

		// Add a state called "StableDiff" to the app when its shown on the app list.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 20, 2 );

		/* Register shortcodes */
		add_shortcode( 'wpcd_app_stablediff_instances', array( &$this, 'app_stablediff_shortcode' ) );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'stablediff_schedule_events_for_new_site' ), 10, 2 );

		// Handle script replacement tokens when called from function turn_script_into_command.
		add_filter( "wpcd_script_placeholders_{$this->get_app_name()}", array( $this, 'script_placeholders' ), 10, 6 );

		/* Pending Logs Background Task: Request Image. */
		add_action( 'wpcd_pending_log_stablediff_request_image', array( $this, 'handle_task_request_image' ), 10, 3 );

		// Push commands & callbacks.
		add_action( "wpcd_{$this->get_app_name()}_command_install_stable_diff_progress-report", array( &$this, 'callback_install_server_status' ), 10, 4 ); // For getting status of server as it's being deployed.
		add_action( "wpcd_{$this->get_app_name()}_command_stablediff_request_image_progress-report", array( &$this, 'callback_request_image' ), 10, 4 ); // For getting progress report when user requests image.

		/* Make sure that we show the server sizes on the provider settings screen - by default they are turned off in settings. */
		add_filter(
			'wpcd_show_server_sizes_in_settings',
			function() {
				return true;
			}
		);

	}

	/**
	 * Create a key-value appear of actions and descriptions.
	 */
	public function set_actions() {
		self::$_actions = array(
			'download-file' => __( 'Download Configuration File', 'wpcd' ),
			'request-image' => __( 'Request Images', 'wpcd' ),
			'relocate'      => __( 'Relocate', 'wpcd' ),
			'reinstall'     => __( 'Reinstall', 'wpcd' ),
			'reboot'        => __( 'Reboot', 'wpcd' ),
			'off'           => __( 'Power Off', 'wpcd' ),
			'on'            => __( 'Power On', 'wpcd' ),
			'remove-user'   => __( 'Remove User', 'wpcd' ),
			'connected'     => __( 'List Clients', 'wpcd' ),
			'instructions'  => __( 'View Instructions', 'wpcd' ),
		);
	}

	/**
	 * Return array of actions and description
	 */
	public function get_actions() {
		return self::$_actions;
	}

	/**
	 * Return the description or label of of an action given an action key.
	 *
	 * @param string $action Action key eg:request-image.
	 *
	 * @return string
	 */
	public function get_action_description( $action ) {

		$actions = $this->get_actions();

		return ! empty( $actions[ $action ] ) ? $actions[ $action ] : false;

	}

	/**
	 * Single entry point for all AJAX actions.
	 *
	 * Data sent back from the browser:
	 *  $_POST['vpn_additional']:  any data for the action such as the name when adding or removing users
	 *      Format:
	 *          Array
	 *          (
	 *              [name] => john
	 *          )
	 *
	 * $_POST['vpn_id']: The post id of the SERVER CPT
	 *
	 * $_POST['vpn_app_id']: The post id of the APP CPT.
	 *
	 * $_POST['vpn_action']: The action to perform
	 */
	public function ajax() {
		check_ajax_admin_nonce( 'wpcd_stablediff_nonce' );

		/* Extract out any additional parameters that might have been passed from the browser */
		$additional = array();
		if ( isset( $_POST['stablediff_additional'] ) ) {
			$additional = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['stablediff_additional'] ) ) );
		}

		/* Run the action */
		$result = $this->do_instance_action( sanitize_text_field( $_POST['stablediff_id'] ), sanitize_text_field( $_POST['stablediff_app_id'] ), sanitize_text_field( $_POST['stablediff_action'] ), $additional );

		/* If error, return to browser and do nothing else. */
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'msg' => $result->get_error_code() ) );
		} elseif ( empty( $result ) ) {
			wp_send_json_error();
		}

		/* Perform after-action stuff based on the action requested and its results after being run */
		switch ( $_POST['stablediff_action'] ) {
			case 'add-user':
				// download the file as soon as the user is added.
				$this->do_instance_action( sanitize_text_field( $_POST['stablediff_id'] ), sanitize_text_field( $_POST['stablediff_app_id'] ), 'download-file', $additional );
				break;
			case 'connected':
			case 'disconnect':
				if ( ! empty( $result ) ) {
					$result = explode( ',', $result );
					$temp   = array_filter( array_map( 'trim', $result ) );
					$result = array();
					foreach ( $temp as $name ) {
						$result[] = array( 'name' => $name );
					}
				}
				break;
		}

		/* If we got here, most things were successful to send result. */
		wp_send_json_success( array( 'result' => $result ) );
	}

	/**
	 * Add tab to account page
	 *
	 * @param array $tabs tabs.
	 *
	 * @return mixed
	 */
	public function account_tabs( $tabs ) {
		$tabs[299]['stablediff'] = array(
			'icon'        => 'spmm-faicon-desktop',
			'title'       => __( 'Stable Diffusion 1.5 Server Instances', 'wpcd' ),
			'custom'      => true,
			'show_button' => false,
		);

		return $tabs;
	}

	/**
	 * Add content to account tab
	 *
	 * @param string $output output.
	 * @param array  $shortcode_args shortcode args.
	 *
	 * @return string
	 */
	public function account_stablediff_tab_content( $output, $shortcode_args ) {
		$instances = $this->get_instances_for_display();
		if ( empty( $instances ) ) {
			$instances = '<p>' . __( 'No instances found', 'wpcd' ) . '</p>';
			$instances = $this->add_promo_link( 2, $instances );
		}
		$output = '<div class="wpcd-stablediff-grid">' . $instances . '</div>';
		return $output;
	}

	/**
	 * Add shortcode
	 */
	public function app_stablediff_shortcode() {
		$instances = $this->get_instances_for_display();
		if ( empty( $instances ) ) {
			$instances = '<p>' . __( 'No instances found', 'wpcd' ) . '</p>';
			$instances = $this->add_promo_link( 2, $instances );
		}
		$output = '<div class="wpcd-stablediff-grid">' . $instances . '</div>';
		return $output;
	}

	/**
	 * Return a requested provider object
	 *
	 * @param string $provider name of provider.
	 *
	 * @return STABLEDIFF_API_Provider_{provider}()
	 */
	public function api( $provider ) {

		return WPCD()->get_provider_api( $provider );

	}

	/**
	 * Return an instance of self.
	 *
	 * @return WPCD_STABLEDIFF_APP
	 */
	public function get_this() {
		return $this;
	}

	/**
	 * SSH function
	 */
	public function ssh() {
		if ( empty( WPCD()->classes['wpcd_stablediff_ssh'] ) ) {
			WPCD()->classes['wpcd_stablediff_ssh'] = new STABLEDIFF_SSH();
		}
		return WPCD()->classes['wpcd_stablediff_ssh'];
	}

	/**
	 * Woocommerce function.
	 */
	public function woocommerce() {
		if ( empty( WPCD()->classes['wpcd_app_stablediff_wc'] ) ) {
			WPCD()->classes['wpcd_app_stablediff_wc'] = new STABLEDIFF_WooCommerce();
		}
		return WPCD()->classes['wpcd_app_stablediff_wc'];
	}

	/**
	 * Settings function.
	 *
	 * @return STABLEDIFF_WooCommerce()
	 */
	public function settings() {
		if ( empty( WPCD()->classes['wpcd_app_stablediff_settings'] ) ) {
			WPCD()->classes['wpcd_app_stablediff_settings'] = new STABLEDIFF_APP_SETTINGS();
		}
		return WPCD()->classes['wpcd_app_stablediff_settings'];
	}


	/**
	 * Init function.
	 */
	public function init() {

		// setup needed objects.
		$this->settings();
		$this->woocommerce();
		$this->ssh();

		add_action( 'wpcd_stablediff_file_watcher', array( $this, 'file_watcher_delete_temp_files' ) );
		add_action( 'wpcd_stablediff_deferred_actions', array( $this, 'do_deferred_actions' ) );

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
				self::stablediff_schedule_events();
				restore_current_blog();
			}
		} else {
			self::stablediff_schedule_events();
		}

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function stablediff_schedule_events() {
		// setup temporary script deletion.
		wp_clear_scheduled_hook( 'wpcd_stablediff_file_watcher' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_stablediff_file_watcher' );

		// setup deferred instance actions schedule.
		wp_clear_scheduled_hook( 'wpcd_stablediff_deferred_actions' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_stablediff_deferred_actions' );
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
				self::stablediff_clear_scheduled_events();
				restore_current_blog();
			}
		} else {
			self::stablediff_clear_scheduled_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function stablediff_clear_scheduled_events() {
		wp_clear_scheduled_hook( 'wpcd_stablediff_file_watcher' );
		wp_clear_scheduled_hook( 'wpcd_stablediff_deferred_actions' );
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
	public function stablediff_schedule_events_for_new_site( $new_site, $args ) {

		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {

			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::stablediff_schedule_events();
			restore_current_blog();
		}

	}

	/**
	 * Perform all deferred actions that need multiple steps to perform.
	 *
	 * @TODO: Update this header to list examples and parameters and expected inputs.
	 */
	public function do_deferred_actions() {

		set_transient( 'wpcd_do_deferred_actions_for_stablediff_is_active', 1, wpcd_get_long_running_command_timeout() * MINUTE_IN_SECONDS );

		do_action( 'wpcd_log_error', 'doing do_deferred_actions', 'debug', __FILE__, __LINE__ );

		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app_server',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => 'wpcd_server_action_status',
						'value' => 'in-progress',
					),
				),
				'fields'      => 'ids',
			)
		);

		if ( $posts ) {
			foreach ( $posts as $id ) {
				$action = get_post_meta( $id, 'wpcd_server_action', true );
				do_action( 'wpcd_log_error', "calling deferred action $action for $id", 'debug', __FILE__, __LINE__ );
				do_action( 'wpcd_app_action', $id, '', $action );
			}
		}

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

			if ( in_array( $key, array( 'clients', 'init', 'client' ), true ) ) {
				continue;
			}

			// Do not add these fields to the server record - they belong to the app record.
			if ( in_array( $key, array( 'max_clients', 'dns', 'protocol', 'port', 'clients', 'scripts_version' ), true ) ) {
				continue;
			}

			/* If we're here, then this is a field that's for the server record. */
			update_post_meta( $post_id, 'wpcd_server_' . $key, $value );
		}

		/* Restructure the server instance array to add the app data that is going into the wpcd_app CPT */
		if ( ! isset( $instance['apps'] ) ) {
			$instance['apps'] = array();
		}

		/**
		 * If 'wc_user_id' is not set in the instance or is blank then use the current logged in user as the post author.
		 * This will help when we're adding servers in wp-admin instead of from the front-end.
		 */
		if ( ( isset( $instance['wc_user_id'] ) && empty( $instance['wc_user_id'] ) ) || ( ! isset( $instance['wc_user_id'] ) ) ) {
			$post_author = get_current_user_id();
		} else {
			$post_author = $instance['wc_user_id'];
		}

		/* Create an app cpt record and add our data fields to it */
		$app_post_id = WPCD_POSTS_APP()->add_app( $this->get_app_name(), $post_id, $post_author );
		if ( ! is_wp_error( $app_post_id ) && ! empty( $app_post_id ) ) {

			/* If we're passed a client element, do some things with it */
			if ( isset( $instance['client'] ) ) {
				$this->add_remove_client( $app_post_id, $instance['client'], 'add' );
			}

			/* add additional app-specific sections to the server array */
			$instance['apps']['app']                = array();
			$instance['apps']['app']['app_post_id'] = $app_post_id;
			$instance['apps']['app']['app_name']    = $this->get_app_name();

			/**
			 * Setup an array of fields and loop through them to add them to the wpcd_app cpt record using the array_map function.
			 * Note that we are passing in the $instance variable to the anonymous function by REFERENCE via a USE parm.
			*/
			$appfields = array( 'max_clients', 'dns', 'protocol', 'port', 'clients', 'scripts_version' );
			$x         = array_map(
				function( $f ) use ( &$instance, $app_post_id ) {
					if ( isset( $instance[ $f ] ) ) {
						update_post_meta( $app_post_id, 'stabldiff_' . $f, $instance[ $f ] );
						$instance['apps']['app'][ 'stablediff_' . $f ] = $instance[ $f ];
					}
				},
				$appfields
			);

		}

		// Schedule after-server-create commands (commands to run after the server has been instantiated for the first time).
		update_post_meta( $post_id, 'wpcd_server_action', 'after-server-create-commands' );
		update_post_meta( $post_id, 'wpcd_server_after_create_action_app_id', $app_post_id );
		update_post_meta( $post_id, 'wpcd_server_action_status', 'in-progress' );
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
	 *
	 * Note: Some of the types of data that can be found in the $instance array is described
	 * at the top of the get_after_server_create_commands function.
	 */
	private function run_after_server_create_commands( $instance ) {

		/* If we're here, server is up and running, which means we have an IP address. Make sure it gets added to the server record! */
		if ( $instance['post_id'] && isset( $instance['ip'] ) && $instance['ip'] ) {
			WPCD_SERVER()->add_ipv4_address( $instance['post_id'], $instance['ip'] );
		}
		if ( $instance['post_id'] && isset( $instance['ipv6'] ) && $instance['ipv6'] ) {
			WPCD_SERVER()->add_ipv6_address( $instance['post_id'], $instance['ipv6'] );
		}

		do_action( 'wpcd_log_error', 'attempting to run after server create commands for ' . print_r( $instance, true ), 'debug', __FILE__, __LINE__ );

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
	 * Setup a background task to submit a request to the server to generate images.
	 *
	 * @param array $attributes An array of attributes with server and app data.
	 * @param array $additional An array of data that we might need - this is usually provided by html forms data.
	 *
	 * Note: Some of the types of data that can be found in the $attributes array is described
	 * at the top of the get_after_server_create_commands function.
	 *
	 * @return boolean|wp_error
	 */
	public function request_image( $attributes, $additional ) {

		$result = true;

		$server_post_id = $attributes['post_id'];

		// Get data about the server.
		$instance = $this->get_server_instance_details( $server_post_id );
		$provider = $instance['provider'];

		// Get provider handle since we'll need to get the root user name.
		$root_user = WPCD()->get_provider_api( $provider )->get_root_user();

		// Generate a folder name.
		$folder_name = wpcd_generate_uuid();

		// Add elements to the additional array that are needed for the bash script.
		$additional['wpcd_user'] = $root_user;

		// Add defaults for stable diffusion command line.
		$additional['AI_PROMPT']     = sanitize_text_field( $additional['image-prompt'] );
		$additional['AI_SAMPLES']    = 2;
		$additional['AI_OUTPUT_DIR'] = 'outputs/wpcd-outputs/' . $folder_name;
		$additional['AI_STEPS']      = 50;
		$additional['AI_WIDTH']      = 512;
		$additional['AI_HEIGHT']     = 512;
		$additional['AI_SEED']       = random_int( 20, 999999 );

		// Add S3 vars so that files can be uploaded to bucket.
		$additional['AWS_ACCESS_KEY_ID']     = wpcd_get_option( 'stablediff_app_aws_access_key' );
		$additional['AWS_SECRET_ACCESS_KEY'] = wpcd_get_option( 'stablediff_app_aws_secret_key' );
		$additional['AWS_DEFAULT_REGION']    = wpcd_get_option( 'stablediff_app_aws_default_region' );
		$additional['AWS_BUCKET']            = wpcd_get_option( 'stablediff_app_aws_bucket' );
		$additional['AWS_FOLDER']            = $folder_name;

		// Add other environment vars needed for bash script.
		$additional['server_id'] = $server_post_id;

		// Generate Callback URL and stick in the $additional array as well.
		$command_name               = 'stablediff_request_image';
		$additional['callback_url'] = $this->get_command_url( $server_post_id, $command_name, 'progress-report' );

		// Create task record here so we can get an id to add to the $additional array.
		$task_id = WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_post_id, 'stablediff_request_image', $server_post_id, array_merge( $instance, $additional ), 'not-ready', $server_post_id, __( 'Waiting To Request Stable Diffusion Images', 'wpcd' ) );

		// Add/update a meta on the server record that points to this latest request (needed for the ui, nothing operational).
		update_post_meta( $server_post_id, 'wpcd_stablediff_last_requested_image_task_id', $task_id );

		// Add the task id to the $additional array.
		$additional['task_id'] = $task_id;

		$run_cmd = $this->turn_script_into_command(
			$attributes,
			'before-run-default-stablediff.txt',
			$additional,
		);

		// Add the run command to the task record.
		$additional['run_cmd']     = $run_cmd;
		$additional['action_hook'] = 'wpcd_pending_log_stablediff_request_image'; // Action hook.
		WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, array_merge( $instance, $additional ), 'ready' );

		return $result;

	}

	/**
	 * Submit a request to the server to generate images.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_pending_log_stablediff_request_image
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Post Id of server on which this action apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function handle_task_request_image( $task_id, $server_id, $args ) {

		// Execute ssh command.
		$run_cmd = $args['run_cmd'];
		if ( ! empty( $run_cmd ) ) {
			$run_cmd = str_replace( ' - ', '', $run_cmd );
			$result  = $this->execute_ssh( 'generic', $args, array( 'commands' => $run_cmd ) );
			if ( is_wp_error( $result ) ) {
				return false;
			} else {
				return true;
			}
		}

		// In most cases we would mark the task complete here.  BUT, we're not doing that in this situation.
		// Instead we are going to wait for a callback that will tell us the task is complete.
		// Then and only then will we mark the task as complete.
	}

	/**
	 * Different scripts needs different placeholders/handling.
	 *
	 * Filter Hook: wpcd_script_placeholders_{$this->get_app_name()}
	 *
	 * @param array  $array              The array of placeholders, usually empty but since this is the first param, its the one returned as the modified value.
	 * @param string $script_name        Script_name.
	 * @param string $script_version     The version of script to be used.
	 * @param array  $instance           Various pieces of data about the server or app being used. It can use the following keys. post_id: the ID of the post.
	 * @param string $command            The command being constructed.
	 * @param array  $additional         An array of any additional data we might need.
	 */
	public function script_placeholders( $array, $script_name, $script_version, $instance, $command, $additional ) {
		$new_array = array();

		$common_array = array(
			'COMMON_PARARMS_URL' => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/params.sh',
		);

		switch ( $script_name ) {
			case 'before-run-default-stablediff.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/run-default-stablediff.txt',
						'SCRIPT_NAME' => 'run-stablediff.sh',
					),
					$common_array,
					$additional
				);
				break;
		}

		return array_merge( $array, $new_array );

	}

	/**
	 * Sends email to the user.
	 *
	 * @param array $instance Array of attributes for the custom post type.
	 */
	private function send_email( $instance ) {

		do_action( 'wpcd_log_error', 'sending email for ' . print_r( $instance, true ), 'debug', __FILE__, __LINE__ );

		// get the app post id from the server instance post.
		$app_post_id = get_post_meta( $instance['post_id'], 'wpcd_server_action_email_app_id', true );

		// Send email if we have a valid app post id.
		if ( ! empty( $app_post_id ) ) {
			$summary = $this->get_app_instance_summary( $app_post_id );

			if ( ! empty( $summary ) ) {

				$wc_order = wc_get_order( $instance['wc_order_id'] );
				$email    = $wc_order->get_billing_email();

				wp_mail(
					$email,
					__( 'New Stable Diffusion V 1.4 instance created', 'wpcd' ),
					$summary,
					array( 'Content-Type: text/html; charset=UTF-8' )
				);
			}
		} else {

			do_action( 'wpcd_log_error', 'cannot send email because application cpt id is missing. Server instance id is: ' . print_r( $instance, true ), 'debug', __FILE__, __LINE__ );

		}
	}

	/**
	 * Executes scripts on the instance.
	 *
	 * @param array $instance Array of attributes for the custom post type.
	 *
	 * @TODO: This is unused right now an can likely be deleted.
	 */
	private function do_scripts( $instance ) {
		// fetch the run_cmd.
		$post_id = $instance['post_id'];
		$run_cmd = get_post_meta( $post_id, 'wpcd_server_run_cmd', true );

		do_action( 'wpcd_log_error', sprintf( 'running scripts (%s) on instance %s', $run_cmd, print_r( $instance, true ) ), 'debug', __FILE__, __LINE__ );

		// replace the beginning space_dash_space with empty string so that these can be run on the command prompt.
		$run_cmd = str_replace( ' - ', '', $run_cmd );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

	}

	/**
	 * Validates the server and returns the server instance details.
	 *
	 * NOTE: This is almost an exact duplicate of the function with the same name
	 * in class-wordpress-app.php. It's only difference is that the function
	 * get_instance_details that it calls doesn't handle custom fields.
	 * If it wasn't for custom fields we could have hand a common function.
	 *
	 * @param int $server_id server id.
	 */
	public function get_server_instance_details( $server_id ) {
		if ( ! $server_id ) {
			return new \WP_Error( __( 'Invalid server ID', 'wpcd' ) );
		}

		// Verify the cpt type.
		$post = get_post( $server_id );
		if ( 'wpcd_app_server' !== $post->post_type ) {
			return new \WP_Error( __( 'Invalid server', 'wpcd' ) );
		}

		$instance = $this->get_instance_details( $server_id );

		return $instance;
	}

	/**
	 * Generates the instances' details that are required by other functions.
	 *
	 * NOTE: This is almost an exact duplicate of the function with the same name
	 * in class-wordpress-app.php. It's only difference is that it doesn't handle
	 * custom fields since this app doesn't have them.
	 * If it wasn't for custom fields we could have hand a common function.
	 *
	 * @param int $id id.
	 */
	public function get_instance_details( $id ) {
		$attributes = array(
			'post_id' => $id,
		);

		/* Get data from server post */
		$all_meta = get_post_meta( $id );
		foreach ( $all_meta as $key => $value ) {
			if ( 'wpcd_server_app_post_id' == $key ) {
				continue;  // this key, if present, should not be added to the array since it shouldn't even be in the server cpt in the first place. But it might get there accidentally on certain operations.
			}

			// Any field that starts with "wpcd_server_" goes into the array.
			if ( strpos( $key, 'wpcd_server_' ) === 0 ) {
				$value = wpcd_maybe_unserialize( $value );
				$attributes[ str_replace( 'wpcd_server_', '', $key ) ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}
		}

		$details = WPCD()->get_provider_api( $attributes['provider'] )->call( 'details', array( 'id' => $attributes['provider_instance_id'] ) );

		// Merge post ids and server details into a single array.
		$attributes = array_merge( $attributes, $details );

		return $attributes;
	}

	/**
	 * Gets the details summary of the instance for emails and instructions popup.
	 *
	 * @param int  $app_post_id Post ID of the StableDiff app post/record.
	 * @param bool $email Is this for email.
	 *
	 * @TODO: break this up into two pieces - one for the server called on the server class and one for this app to get this app details.
	 *
	 * @return string
	 */
	private function get_app_instance_summary( $app_post_id, $email = true ) {

		// get the app post for the passed id.
		$app_post = $this->get_app_by_app_id( $app_post_id );

		// Get the server post to match the current app...
		$server_post = $this->get_server_by_app_id( $app_post->ID );

		// Get provider from server record.
		$provider      = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );
		$stablediff_id = get_post_meta( $server_post->ID, 'wpcd_server_provider_instance_id', true );
		$details       = WPCD()->get_provider_api( $provider )->call( 'details', array( 'id' => $stablediff_id ) );

		// Get protocol from stablediff app record.
		$protocol = get_post_meta( $app_post_id, 'stablediff_protocol', true );
		$port     = get_post_meta( $app_post_id, 'stablediff_port', true );
		$protocol = sprintf( '%s / %d', WPCD()->classes['wpcd_app_stablediff_wc']->protocol[ strval( $protocol ) ], $port );

		// Get server size from server record.
		$size     = get_post_meta( $server_post->ID, 'wpcd_server_size', true );
		$size     = WPCD()->classes['wpcd_app_stablediff_wc']::$sizes[ strval( $size ) ];
		$region   = get_post_meta( $server_post->ID, 'wpcd_server_region', true );
		$provider = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );

		// Get max clients allowed from stablediff app record.
		$max   = get_post_meta( $app_post_id, 'stablediff_max_clients', true );
		$total = wpcd_maybe_unserialize( get_post_meta( $app_post_id, 'stablediff_clients', true ) );
		if ( empty( $total ) ) {
			$total = array();
		} else {
			$total = count( $total );
		}
		$users = sprintf( '%d / %d', $total, $max );

		$template = file_get_contents( dirname( __FILE__ ) . '/templates/' . ( $email ? 'email' : 'popup' ) . '.html' );
		return str_replace(
			array( '$NAME', '$PROVIDER', '$IP', '$PROTOCOL', '$SIZE', '$USERS', '$URL' ),
			array(
				get_post_meta( $server_post->ID, 'wpcd_server_name', true ),
				$this->get_providers()[ $provider ],
				$details['ip'],
				$protocol,
				$size,
				$users,
				site_url( 'account' ),
			),
			$template
		);
	}

	/**
	 * Adds or removes a client.
	 *
	 * @param int    $app_post_id Post ID.
	 * @param string $name Name of client.
	 * @param string $type 'add' or 'remove'.
	 */
	public function add_remove_client( $app_post_id, $name, $type = 'add' ) {
		do_action( 'wpcd_log_error', "trying to $type client with name $name", 'debug', __FILE__, __LINE__ );
		$clients = wpcd_maybe_unserialize( get_post_meta( $app_post_id, 'stablediff_clients', true ) );
		if ( ! $clients ) {
			$clients = array();
		}
		if ( 'add' === $type ) {
			$clients[] = $name;
		} elseif ( count( $clients ) > 0 ) {
			$temp    = $clients;
			$clients = array();
			foreach ( $temp as $client ) {
				if ( $client === $name ) {
					continue;
				}
				$clients[] = $client;
			}
		}
		update_post_meta( $app_post_id, 'stablediff_clients', $clients );
	}

	/**
	 * Performs an action on a Stable Diffusion instance.
	 *
	 * @param int    $server_post_id Post ID.
	 * @param int    $app_post_id app_post_id.
	 * @param string $action Action to perform.
	 * @param array  $additional Additional data that may be required for the action.
	 *
	 * @return mixed
	 */
	public function do_instance_action( $server_post_id, $app_post_id, $action, $additional = array() ) {

		// Bail if the post type is not a server.
		if ( get_post_type( $server_post_id ) !== 'wpcd_app_server' || empty( $action ) ) {
			return;
		}

		// Bail if the server type is not a stable diffusion server.
		if ( 'stablediff' !== $this->get_server_type( $server_post_id ) ) {
			return;
		}

		$attributes = array(
			'post_id'     => $server_post_id,
			'app_post_id' => $app_post_id,
		);

		/* Get data from server post */
		$all_meta = get_post_meta( $server_post_id );
		foreach ( $all_meta as $key => $value ) {
			if ( 'wpcd_server_app_post_id' === $key ) {
				continue;  // this key, if present, should not be added to the array since it shouldn't even be in the server cpt in the first place. But it might get there accidentally on certain operations.
			}

			if ( strpos( $key, 'wpcd_server_' ) === 0 ) {
				$value = wpcd_maybe_unserialize( $value );
				$attributes[ str_replace( 'wpcd_server_', '', $key ) ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}
		}

		/* Get data from app post - not all calls to this function will pass in an app_post_id though so only attempt to get that data if an app_post_id is present. */
		if ( ! empty( $app_post_id ) ) {
			$all_app_meta = get_post_meta( $app_post_id );
			foreach ( $all_app_meta as $key => $value ) {
				if ( strpos( $key, 'stablediff_' ) === 0 ) {
					$value = maybe_unserialize( $value );
					$attributes[ str_replace( 'stablediff_', '', $key ) ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
				}
			}
		}

		$current_status = get_post_meta( $server_post_id, 'wpcd_server_action_status', true );
		if ( empty( $current_status ) ) {
			$current_status = '';
		}

		do_action( 'wpcd_log_error', "performing $action for $server_post_id on $current_status with " . print_r( $attributes, true ) . ', additional ' . print_r( $additional, true ), 'debug', __FILE__, __LINE__ );

		delete_post_meta( $server_post_id, 'wpcd_server_error' );

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

					// We only want to run this once so check to see if there is anything in the 'wpcd_server_action' meta....
					if ( 'ai-warming-up' === (string) get_post_meta( $attributes['post_id'], 'wpcd_server_action', true ) ) {
						break;
					}

					// Grab the app post id from the database because if this action.
					// needed to be repeated after a failure, it is possible that the.
					// only place the app id exists is in the database and not the.
					// attributes array.
					if ( empty( $app_post_id ) ) {
						$app_post_id               = get_post_meta( $attributes['post_id'], 'wpcd_server_after_create_action_app_id', true );
						$attributes['app_post_id'] = $app_post_id;
					}

					// Merge post ids and server details into a single array.
					$attributes = array_merge( $attributes, $details );

					if ( true === $this->run_after_server_create_commands( $attributes ) ) {

						// Mark server so that it knows the first part of the install is completed.
						update_post_meta( $attributes['post_id'], 'wpcd_server_action', 'ai-warming-up' );

						// The function that sends the server ready email will need this later.
						update_post_meta( $attributes['post_id'], 'wpcd_server_action_email_app_id', $app_post_id );

						/**
						 * All other metas that would normally get updated here eg(VPN and BASIC SERVER apps)
						 * are now being updated in the callback_install_server_status function.
						 *
						 * This is because it takes a long time to warm up the server and it's really not
						 * going to be ready until that is complete.
						 *
						 * Then and only then will do a curl/rest callback which will trigger the
						 * callback_install_server_status function to update metas and prepare to send emails.
						 */

					}
				}
				break;
			case 'ai-warming-up':
				// Do nothing.
				break;
			case 'email':
				$state = $details['status'];
				// send email only when server is 'active'.
				if ( 'active' === $state ) {
					// Deleting these three items means that sending this email is the last thing in the deferred action sequence and no more deferred actions will occur for this server.
					delete_post_meta( $attributes['post_id'], 'wpcd_server_action_status' );
					delete_post_meta( $attributes['post_id'], 'wpcd_server_action' );
					delete_post_meta( $attributes['post_id'], 'wpcd_server_init', '1' );  // This one is only going to be present on a NEW server but should not be there for relocations and reinstalls.
					$attributes = array_merge( $attributes, $details );
					$this->send_email( $attributes );
					delete_post_meta( $attributes['post_id'], 'wpcd_server_action_email_app_id' ); // must be done AFTER the email is processed since it needs the app id.
					WPCD_SERVER()->add_deferred_action_history( $attributes['post_id'], $this->get_app_name() );
				}
				break;
			case 'relocate':
				// this is like a new create with a new region.
				$new_attributes                   = $attributes;
				$new_attributes['provider']       = $additional['provider'];
				$new_attributes['region']         = $additional['region'];
				$new_attributes['client']         = 'client1';
				$new_attributes['parent_post_id'] = $server_post_id;
				unset( $new_attributes['post_id'] );
				unset( $new_attributes['clients'] );

				$new_instance = WPCD_SERVER()->relocate_server( $attributes, $new_attributes );

				$new_instance = $this->add_app( $new_instance );  // add application to server and update the new instance record with the data it needs.

				break;
			case 'reinstall':
				// this is like a new create with the same region as before.
				$new_attributes                   = $attributes;
				$new_attributes['client']         = 'client1';
				$new_attributes['parent_post_id'] = $server_post_id;
				unset( $new_attributes['post_id'] );
				unset( $new_attributes['clients'] );

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
			case 'request-image':
				$result = $this->request_image( $attributes, $additional );
				break;
			case 'add-user':
				// fall-through.
			case 'download-file':
				// fall-through.
			case 'connected':
				// fall-through.
			case 'remove-user':
				$result = $this->execute_ssh( $action, $attributes, $additional );
				break;
		}
		return $result;
	}

	/**
	 * Returns the list of instances to display.
	 *
	 * @return string
	 */
	private function get_instances_for_display() {

		// Get a list of apps that the user has...
		$app_posts = $this->get_apps_by_user_id( get_current_user_id() );  // get_apps_by_user_id is a function in the ancestor class.

		do_action( 'wpcd_log_error', 'Got ' . count( $app_posts ) . ' app instances for user = ' . get_current_user_id(), 'debug', __FILE__, __LINE__ );

		// Quit if there's no applications to show.
		if ( ! $app_posts ) {
			return null;
		}

		/* Get a list of regions and providers - need this to build dropdowns and such */

		/* @TODO: Shouldn't this be extracted out to its own set of functions - its called multiple times I think. */
		$provider_regions = array();
		$clouds           = WPCD()->get_active_cloud_providers();
		$regions          = array();
		$providers        = array();
		foreach ( $clouds as $provider => $name ) {
			$locs = WPCD()->get_provider_api( $provider )->call( 'regions' );

			// if api key not provided or an error occurs, bail.
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
		/* End get a list of regions and providers - need this to build dropdowns and such */

		wp_register_script( 'wpcd-stablediff-magnific', wpcd_url . 'assets/js/jquery.magnific-popup.min.js', array( 'jquery' ), wpcd_scripts_version, true );
		wp_enqueue_script( 'wpcd-stablediff', wpcd_url . 'includes/core/apps/stable-diffusion/assets/js/wpcd-stablediff.js', array( 'wpcd-stablediff-magnific', 'wp-util' ), wpcd_scripts_version, true );
		wp_localize_script(
			'wpcd-stablediff',
			'attributes',
			array(
				'nonce'            => wp_create_nonce( 'wpcd_stablediff_nonce' ),
				'provider_regions' => $provider_regions,
			)
		);
		wp_register_style( 'wpcd-stablediff-magnific', wpcd_url . 'assets/css/magnific-popup.css', array(), wpcd_scripts_version );
		wp_enqueue_style( 'wpcd-stablediff', wpcd_url . 'includes/core/apps/stable-diffusion/assets/css/wpcd-stablediff.css', array( 'wpcd-stablediff-magnific' ), wpcd_scripts_version );
		wp_enqueue_style( 'wpcd-stablediff-fonts', wpcd_url . 'includes/core/apps/stable-diffusion/assets/fonts/stablediffwebsite.css', array(), wpcd_scripts_version );

		$output = '<div class="wpcd-stablediff-instances-list">';
		foreach ( $app_posts as $app_post ) {

			// Skip any non-stablediff apps.
			if ( ! ( 'stablediff' === $this->get_app_type( $app_post->ID ) ) ) {
				continue;
			}

			// Get the server post to match the current app...
			$server_post = $this->get_server_by_app_id( $app_post->ID );

			// Get some data from the server post.
			$provider      = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );
			$stablediff_id = get_post_meta( $server_post->ID, 'wpcd_server_provider_instance_id', true );
			$region        = get_post_meta( $server_post->ID, 'wpcd_server_region', true );

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
				$details = WPCD()->get_provider_api( $provider )->call( 'details', array( 'id' => $stablediff_id ) );
			}
			// problem fetching details. Maybe instance was deleted?
			if ( is_wp_error( $details ) || empty( $details ) ) {
				$actions = 'mia';
			} else {
				$actions = $this->get_actions_for_instance( $app_post->ID, $details );
			}

			// Get data from pending logs for any images requested.
			$pending_image_requests = $this->get_pending_images_count( $server_post->ID );

			// Start building display HTML for this particular server/app instance.
			$buttons         = '';
			$html_attributes = array();
			if ( is_array( $actions ) ) {
				foreach ( $actions as $action ) {

					// Some actions require a 'wrapping' div to help the breaks for css-grid.
					if ( in_array( $action, array( 'download-file', 'request-image', 'last-completed-image', 'reboot', 'relocate', 'image-grid-1', 'image-grid-2', 'instructions' ), true ) ) {
						$buttons = $buttons . '<div class="wpcd-stablediff-instance-multi-button-block-wrap">';  // this should be matched later with a footer div.
					}

					// Initialize important vars for this iteration of the loop.
					$help_tip       = ''; // text that will go underneath each button.
					$input_help_tip = ''; // text that will go underneath each input field.
					$foot_break     = false;  // whether or not to insert a footer div after the block.
					$buttons       .= '<div class="wpcd-stablediff-instance-button-block">'; // opening div for button action block.
					$btn_icon_class = '';  /* classname to render icon before text on some buttons */

					switch ( $action ) {
						case 'off':
							$btn_icon_class = '<span class="icon-spstablediffpower_off"></span>';
							$help_tip       = __( 'Power off the server. No one will be able to connect until you power it back on.', 'wpcd' );
							break;
						case 'on':
							$help_tip = __( 'Turn on the server.  After clicking this, wait a couple of mins to let it spin up before attempting to connect.', 'wpcd' );
							break;
						case 'reboot':
							$btn_icon_class = '<span class="icon-spstablediffreboot"></span>';
							$buttons       .= '<div class="wpcd-stablediff-action-head">' . __( 'Reboot/Reinstall/Poweroff', 'wpcd' ) . '</div>'; // Add in the section title text.
							$help_tip       = __( 'Restart the server.  Hey - it is a computer and sometimes you just need to do this.', 'wpcd' );
							break;
						case 'reinstall':
							$btn_icon_class = '<span class="icon-spstablediffreinstall"></span>';
							$help_tip       = __( 'Start over.  This will put the server back into a brand new state, removing all files. Please make sure you download all existing files before using this operation!', 'wpcd' );
							$foot_break     = true;
							break;
						case 'relocate':
							$btn_icon_class = '<span class="icon-spstablediffreinstall"></span>';
							$buttons       .= '<div class="wpcd-stablediff-action-head">' . __( 'Move Your Server', 'wpcd' ) . '</div>'; // Add in the section title text.
							$select1        = '<select class="wpcd-stablediff-additional wpcd-stablediff-provider wpcd-stablediff-select" name="provider">';
							foreach ( $providers as $slug => $name ) {
								$select1 .= '<option value="' . $slug . '" ' . selected( $slug, $provider, false ) . '>' . $name . '</option>';
							}
							$select2 = '<select class="wpcd-stablediff-additional wpcd-stablediff-region wpcd-stablediff-select" name="region">';
							foreach ( $provider_regions[ $provider ] as $regions ) {
								if ( $regions['slug'] === $region ) {
									continue;
								}
								$select2 .= '<option value="' . $regions['slug'] . '">' . $regions['name'] . '</option>';
							}
							$select1   .= '</select>';
							$select2   .= '</select>';
							$buttons   .= $select1 . $select2;
							$help_tip   = __( 'Move your server to a different location. All existing image files will be removed - please make sure you download them before using this option. Please note that not all server sizes are available in all regions. So please consider checking with our support folks before moving your server so we can verify that your size is still available in your target region.', 'wpcd' );
							$foot_break = true;
							break;
						case 'request-image':
							$buttons .= '<div class="wpcd-stablediff-action-head">' . __( 'Request Images', 'wpcd' ) . '</div>'; // Add in the section title text.
							if ( 'off' === (string) $details['status'] ) {
								$buttons .= sprintf( '<p class="wpcd-stablediff-action-error"> %s</p>', __( 'The server is turned off - you cannot request images at this time.  Please use the options in the POWER section below to restart the server.', 'wpcd' ) );
							} else {
								$buttons       .= '<input type="text" name="image-prompt" id="wpcd-stablediff-input-text-request-image" class="wpcd-stablediff-additional wpcd-stablediff-input-text">';
								$input_help_tip = __( 'Describe the image you would like to generate and then use the REQUEST IMAGES button below to submit the request to the server.', 'wpcd' );
								$help_tip       = sprintf( __( 'You have %s image requests pending. Each request will generate four images.', 'wpcd' ), $pending_image_requests );
							}

							break;
						case 'last-image-request':
							$buttons                    .= '<hr />';
							$buttons                    .= '<div class="wpcd-stablediff-action-head">' . __( 'Most Recent Requested Image', 'wpcd' ) . '</div>'; // Add in the section title text.
							$last_requested_image_prompt = __( 'No Valid Image Requests Were Found.', 'wpcd' ); // Default output.

							// Get the task id for the last requested image.
							$last_requested_image_task_id = get_post_meta( $server_post->ID, 'wpcd_stablediff_last_requested_image_task_id', true );
							if ( ! empty( $last_requested_image_task_id ) ) {
								$task_details = WPCD_POSTS_PENDING_TASKS_LOG()->get_pending_task_details_by_id( $last_requested_image_task_id );
								if ( ! empty( $task_details ) && ! empty( $task_details['AI_PROMPT'] ) ) {
									$last_requested_image_prompt = $task_details['AI_PROMPT'];
								}
							}
							$buttons .= '<p class="wpcd-stablediff-action-help-tip">' . $last_requested_image_prompt . '</p>';
							break;
						case 'completed-images-urls':
							// Show the URLS for 10 most recently completed images.

							$buttons .= '<hr />';
							$buttons .= '<div class="wpcd-stablediff-action-head">' . __( 'Download Your Most Recent Generated Images', 'wpcd' ) . '</div>'; // Add in the section title text.
							$msg = __( 'All links expire after 7 days!', 'wpcd' );
							$buttons .= '<p class="wpcd-stablediff-action-help-tip">' . $msg . '</p>';

							// Get list of images from the server meta.
							$images = wpcd_maybe_unserialize( get_post_meta( $server_post->ID, 'wpcd_stablediff_image_urls', true ) );

							if ( ! empty( $images ) && is_array( $images ) && count( $images ) > 0 ) {
								// Get an array iterator that is reversed.
								$reverted_images_iterator = new ArrayIterator( array_reverse( $images ) );
								$cnt                      = 0;
								foreach ( $reverted_images_iterator as $image ) {
									$cnt++;

									$image_url   = $image['signed-url'];
									$image_label = sprintf( __( 'Image generated on: %s', 'wpcd' ), $image['date-generated-gmt'] );

									$buttons .= sprintf( '<a href="%s" target="_blank">%s</a><br />', $image_url, $image_label );
									if ( $cnt >= 10 ) {
										break;
									}
								}
							} else {
								$msg      = __( 'No generated images were found for this server. use the REQUEST IMAGES button above to generate your first one!', 'wpcd' );
								$buttons .= '<p class="wpcd-stablediff-action-help-tip">' . $msg . '</p>';
							}
							$foot_break = true;
							break;
						case 'last-completed-image':
							$buttons .= '<div class="wpcd-stablediff-action-head">' . __( 'Recently Generated Image', 'wpcd' ) . '</div>'; // Add in the section title text.

							// Get list of images from the server meta.
							$image_urls = wpcd_maybe_unserialize( get_post_meta( $server_post->ID, 'wpcd_stablediff_image_urls', true ) );

							if ( ! empty( $image_urls ) && is_array( $image_urls ) && count( $image_urls ) > 0 ) {
								$last_generated_image = end( $image_urls ); // most recent image will be at the bottom of the array.
								$image_to_show        = $last_generated_image['signed-url'];

								$buttons .= sprintf( '<img class="wpcd-stablediff-generated-img" src=%s />', $image_to_show );

								$prompt   = $last_generated_image['prompt'];
								$buttons .= '<p class="wpcd-stablediff-action-help-tip">' . $prompt . '</p>';

							} else {
								$msg      = __( 'No images have been generated recently.', 'wpcd' );
								$buttons .= '<p class="wpcd-stablediff-action-help-tip">' . $msg . '</p>';
							}

							$foot_break = true;

							break;

						case 'image-grid-1':
							// Show the four most recent images in a grid of thumbnails.

							$buttons .= '<div class="wpcd-stablediff-action-head">' . __( 'Recent Images Grid #1', 'wpcd' ) . '</div>'; // Add in the section title text.

							// Get list of images from the server meta.
							$images = wpcd_maybe_unserialize( get_post_meta( $server_post->ID, 'wpcd_stablediff_image_urls', true ) );

							if ( ! empty( $images ) && is_array( $images ) && count( $images ) > 0 ) {

								// Add div wrapper for grid.
								$buttons .= '<div class="wpcd-stablediff-image-grid-wrapper">';

								// Get an array iterator that is reversed.
								$reverted_images_iterator = new ArrayIterator( array_reverse( $images ) );
								$cnt                      = 0;
								foreach ( $reverted_images_iterator as $image ) {
									$cnt++;

									$image_url = $image['signed-url'];
									$prompt    = $image['prompt'];
									$buttons  .= sprintf( '<div class="wpcd-stablediff-image-thumbnail"><img class="wpcd-stablediff-generated-img" alt="%s" src=%s /></div>', $prompt, $image_url );

									// Only images 1-4 should be shown.
									if ( $cnt >= 4 ) {
										break;
									}
								}

								// Close div wrapper
								$buttons .= '</div>';
							} else {
								$msg      = __( 'No images are available for this grid. Start generating images using the REQUEST IMAGES button above.', 'wpcd' );
								$buttons .= '<p class="wpcd-stablediff-action-help-tip">' . $msg . '</p>';
							}
							$foot_break = true;
							break;
						case 'image-grid-2':
							// Show the second four most recent images in a grid of thumbnails.

							$buttons .= '<div class="wpcd-stablediff-action-head">' . __( 'Recent Images Grid #2', 'wpcd' ) . '</div>'; // Add in the section title text.

							// Get list of images from the server meta.
							$images = wpcd_maybe_unserialize( get_post_meta( $server_post->ID, 'wpcd_stablediff_image_urls', true ) );

							if ( ! empty( $images ) && is_array( $images ) && count( $images ) > 4 ) {

								// Add div wrapper for grid.
								$buttons .= '<div class="wpcd-stablediff-image-grid-wrapper">';

								// Get an array iterator that is reversed.
								$reverted_images_iterator = new ArrayIterator( array_reverse( $images ) );
								$cnt                      = 0;
								foreach ( $reverted_images_iterator as $image ) {
									$cnt++;

									// Skip the first 4 images
									if ( $cnt <= 4 ) {
										continue;
									}

									$image_url = $image['signed-url'];
									$prompt    = $image['prompt'];
									$buttons  .= sprintf( '<div class="wpcd-stablediff-image-thumbnail"><img class="wpcd-stablediff-generated-img" alt="%s" src=%s /></div>', $prompt, $image_url );

									// Only images 5-8 should be shown.
									if ( $cnt >= 8 ) {
										break;
									}
								}

								// Close div wrapper
								$buttons .= '</div>';
							} else {
								$msg      = __( 'No images are available for this grid. Generate more images using the REQUEST IMAGES button above.', 'wpcd' );
								$buttons .= '<p class="wpcd-stablediff-action-help-tip">' . $msg . '</p>';
							}
							$foot_break = true;
							break;
						case 'instructions':
							$buttons        .= '<div class="wpcd-stablediff-action-head">' . __( 'Help & Support', 'wpcd' ) . '</div>'; // Add in the section title text.
							$summary         = $this->get_app_instance_summary( $app_post->ID, false );
							$html_attributes = array( 'href' => '#instructions-' . $server_post->ID );
							$buttons        .= '<div id="instructions-' . $server_post->ID . '" class="wpcd-stablediff-instructions mfp-hide">' . $summary . '</div>';
							$help_tip        = __( 'Click here to get help on how to best use your new Stable Diffusion server.', 'wpcd' );
							$foot_break      = true;
							break;
					}
					$attributes = '';
					if ( $html_attributes ) {
						foreach ( $html_attributes as $key => $value ) {
							$attributes .= $key . '=' . esc_attr( $value );
						}
					}

					// Add help tip below the input fields.
					if ( ! empty( $input_help_tip ) ) {
						$buttons .= '<div class="wpcd-stablediff-action-help-tip">' . $input_help_tip . '</div>'; // Add in the help text that goes underneath the input fields.
					}

					// Add an actual button for most things (but not all).
					if ( ! in_array( $action, array( 'last-completed-image', 'last-image-request', 'completed-images-urls', 'image-grid-1', 'image-grid-2' ), true ) ) {

						if ( 'off' === (string) $details['status'] && 'request-image' === $action ) {
							// Do nothing - we dont' want to addthe request image button if the server is turned off.
						} else {
							$buttons .= '<button ' . $attributes . ' class="wpcd-stablediff-action-type wpcd-stablediff-action-' . $action . '" data-action="' . $action . '" data-id="' . $server_post->ID . '" data-app-id="' . $app_post->ID . '">' . $btn_icon_class . ' ' . $this->get_action_description( $action ) . '</button>';

							// Add help tip below the buttons.
							if ( ! empty( $help_tip ) ) {
								$buttons .= '<div class="wpcd-stablediff-action-help-tip">' . $help_tip . '</div>'; // Add in the help text.
							}
						}
					}

					$buttons .= '</div> <!-- closing div for this button action block --> ';  // closing div for this button action block.

					if ( true === $foot_break ) {
						$buttons .= '<div class="wpcd-stablediff-action-foot $action">' . '</div>'; // Add in footer break as necessary - styling will be done in CSS file of course.
						$buttons .= '</div> <!-- close multi-button block wrap -->'; // close up a multi-button block wrap.
					}
				}
			} elseif ( is_string( $actions ) ) {
				switch ( $actions ) {
					case 'ai-warming-up':
						$buttons .= '<p class="wpcd-stablediff-action-warning">' . __( 'We are currently warming up the Stable Diffusion AI engine for this server - this can take 20 minutes or more. We will send you an email when this is complete.', 'wpcd' ) . '</p>';
						break;
					case 'in-progress':
						$buttons .= '<p class="wpcd-stablediff-action-warning">' . __( 'The instance is currently transitioning state. <br />This happens just after a new purchase when a server is starting up or when rebooting or relocating. <br />Please check back in a few minutes - it can take as long as 20 minutes to deploy a new server. If you continue to see this message after that please contact our support team.', 'wpcd' ) . '</p>';
						break;
					case 'errored':
						$buttons .= '<p class="wpcd-stablediff-action-error">' . __( 'An error occurred in the Stable Diffusion server. Please check back in a few minutes. If you continue to see this message after that please contact our support team.', 'wpcd' ) . '</p>';
						break;
					case 'new':
						$buttons .= '<p class="wpcd-stablediff-action-warning">' . __( 'The instance is initializing. Please check back in a few minutes. If you continue to see this message after that please contact our support team.', 'wpcd' ) . '</p>';
						break;
					case 'mia':
						$buttons .= '<p class="wpcd-stablediff-action-error">' . __( 'The instance is missing in action. Please check back in a few minutes. If you continue to see this message after that please contact our support team.', 'wpcd' ) . '</p>';
						break;
				}
			}

			// Get some basic data about the server.
			$ipv4 = WPCD_SERVER()->get_ipv4_address( $server_post->ID );
			$size = get_post_meta( $server_post->ID, 'wpcd_server_size', true );

			$max   = get_post_meta( $app_post->ID, 'stablediff_max_clients', true );
			$total = wpcd_maybe_unserialize( get_post_meta( $app_post->ID, 'stablediff_clients', true ) );
			if ( empty( $total ) ) {
				$total = array();
			}

			// Get subscription id from server cpt.
			$subscription = wpcd_maybe_unserialize( get_post_meta( $server_post->ID, 'wpcd_server_wc_subscription', true ) );

			// Make $subscription var an array for use later.
			if ( ! is_array( $subscription ) ) {
				$subscription_array = array( $subscription );
			} else {
				$subscription_array = $subscription;
			}

			/* These strings are the class names for the icons from our custom icomoon font file */
			$provider_icon = '<div class="icon-spstablediffprovider"><span class="path1"></span><span class="path2"></span></div>';
			$region_icon   = '<div class="icon-spstablediffregion"><span class="path1"></span><span class="path2"></span></div>';
			$size_icon     = '<div class="icon-spstablediffsize"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></div>';
			$ip_icon       = '<div class="icon-spstablediffip"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></div>';
			$users_icon    = '<div class="icon-spstablediffusers"><span class="path1"></span><span class="path2"></span></div>';
			$subid_icon    = '<div class="icon-spstablediffsubscription_id"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span><span class="path7"></span></div>';
			/* End classnames from icomoon font file */

			$output .= '
				<div class="wpcd-stablediff-instance">
					<div class="wpcd-stablediff-instance-name">' . get_post_meta( $server_post->ID, 'wpcd_server_name', true ) . '</div>

					<div class="wpcd-stablediff-instance-atts">' .
						'<div class="wpcd-stablediff-instance-atts-provider-wrap">' . $provider_icon . '<div class="wpcd-stablediff-instance-atts-provider-label">' . __( 'Provider', 'wpcd' ) . ': ' . '</div>' . $this->get_providers()[ $provider ] . '</div>
						<div class="wpcd-stablediff-instance-atts-region-wrap">' . $region_icon . '<div class="wpcd-stablediff-instance-atts-region-label">' . __( 'Region', 'wpcd' ) . ': ' . '</div>' . $display_region . '</div>
						<div class="wpcd-stablediff-instance-atts-size-wrap">' . $size_icon . '<div class="wpcd-stablediff-instance-atts-size-label">' . __( 'Size', 'wpcd' ) . ': ' . '</div>' . WPCD()->classes['wpcd_app_stablediff_wc']::$sizes[ strval( $size ) ] . '</div>
						<div class="wpcd-stablediff-instance-atts-ip-wrap">' . $ip_icon . '<div class="wpcd-stablediff-instance-atts-ip-label">' . __( 'IPv4', 'wpcd' ) . ': ' . '</div>' . $ipv4 . '</div>
						<div class="wpcd-stablediff-instance-atts-users-wrap">' . $users_icon . '<div class="wpcd-stablediff-instance-atts-users-label">' . __( 'Image Requests Pending', 'wpcd' ) . ': ' . '</div>' . sprintf( '%d', $pending_image_requests ) . '</div>
						<div class="wpcd-stablediff-instance-atts-subid-wrap">' . $subid_icon . '<div class="wpcd-stablediff-instance-atts-sub-label">' . __( 'Subscription ID', 'wpcd' ) . ': ' . '</div>' . implode( ', ', $subscription_array ) . '</div>';

			$output .= '</div>';
			$output  = $this->add_promo_link( 1, $output );
			$output .= '<div class="wpcd-stablediff-instance-actions">' . $buttons . '</div>
				</div>';
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Determines what actions to show for a Stable Diffusion instance.
	 *
	 * @param int   $id Post ID of the app being handled.
	 * @param array $details Details of the Stable Diffusion instance as per the 'details' API call.
	 *
	 * @return array
	 */
	private function get_actions_for_instance( $id, $details ) {
		/* Note: the order of items in this array is very important for styling purposes so re-arrange at your own risk. */
		$actions = array(
			'request-image',
			'last-image-request',
			'completed-images-urls',
			'last-completed-image',
			'image-grid-1',
			'image-grid-2',
			'reboot',
			'off',
			'on',
			'reinstall',
			'relocate',
			'instructions',
		);

		// problem fetching details. Maybe instance was deleted?
		if ( is_wp_error( $details ) ) {
			return 'mia';
		}

		// Get a server post record based on the app id passed in...
		$server_post = $this->get_server_by_app_id( $id );

		// What's the state of the server?
		$state = $details['status']; // This is the provider state of the server, in particular whether the server is on, off or in some other power mode.

		// What is the installation or other activity status of the server?  If this is filled in it means something is running on the server.
		$status = get_post_meta( $server_post->ID, 'wpcd_server_action_status', true );

		// For stable diffusion servers, if the server is being newly installed, relocated or rebuilt, we have a warming up action.  Are we in that state?
		if ( 'ai-warming-up' === (string) get_post_meta( $server_post->ID, 'wpcd_server_action', true ) ) {
			$status = get_post_meta( $server_post->ID, 'wpcd_server_action', true );
		}

		do_action( 'wpcd_log_error', "Determining actions for $id in current state ($state) with action status ($status)", 'debug', __FILE__, __LINE__ );

		if ( in_array( $status, array( 'in-progress', 'errored', 'ai-warming-up' ) ) ) {
			// stuck in the middle with you?
			return $status;
		}

		if ( 'off' === $state ) {
			if ( ( $key = array_search( 'off', $actions ) ) !== false ) {
				unset( $actions[ $key ] );
			}
			if ( ( $key = array_search( 'add-user', $actions ) ) !== false ) {
				unset( $actions[ $key ] );
			}
			if ( ( $key = array_search( 'remove-user', $actions ) ) !== false ) {
				unset( $actions[ $key ] );
			}
			if ( ( $key = array_search( 'download-file', $actions ) ) !== false ) {
				unset( $actions[ $key ] );
			}
			if ( ( $key = array_search( 'connected', $actions ) ) !== false ) {
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
	 * Performs an SSH command on a Stable Diffusion instance.
	 *
	 * @param string $action Action to perform.
	 * @param array  $attributes Attributes of the Stable Diffusion instance.
	 * @param array  $additional Additional data that may be required for the action.
	 *
	 * @return mixed
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
		if ( ! empty( $attributes['app_post_id'] ) ) {
			$app_post_id = $attributes['app_post_id'];
		} else {
			$app_post_id = '';
		}

		$result = null;
		switch ( $action ) {
			case 'add-user':
				$name     = $additional['name'];
				$commands = 'export stablediff_option=' . $action_int . '; export stablediff_client="' . $name . '"; sudo -E bash ~/openvpn-script.sh;';
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user );
				break;
			case 'remove-user':
				$name     = $additional['name'];
				$commands = 'export stablediff_option=' . $action_int . '; export stablediff_client="' . $name . '"; sudo -E bash ~/openvpn-script.sh;';
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user );
				break;
			case 'download-file':
				$name          = $additional['name'];
				$instance_name = get_post_meta( $post_id, 'wpcd_server_name', true );
				$result        = $this->ssh()->download( $ip, '~/' . $name . '.ovpn', '', $key, $root_user );  // @TODO: This assumues root user which will not work on AWS and other providers who default to a non-root user.
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

		// if successful.
		switch ( $action ) {
			case 'add-user':
				$this->add_remove_client( $app_post_id, $additional['name'], 'add' );
				break;
			case 'remove-user':
				$this->add_remove_client( $app_post_id, $additional['name'], 'remove' );
				break;
		}

		// comamnds that do not get added to history.
		if ( in_array( $action, array( 'connected', 'disconnect', 'generic' ) ) ) {
			return $result;
		}

		WPCD_SERVER()->add_action_to_history( $action, $attributes );
		return true;
	}



	/**
	 * Adds the promotion mark up text to the Stable Diffusion user account area.
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
				$promo_url     = wpcd_get_option( 'stablediff_promo_item01_url' );
				$promo_text    = wpcd_get_option( 'stablediff_promo_item01_text' );
				$button_option = boolval( wpcd_get_option( 'stablediff_promo_item01_button_option' ) );

				if ( ! empty( $promo_url ) ) {
					if ( false == $button_option ) {
						// just a link..
						$markup .= '<div class="wpcd-stablediff-instance-promo wpcd-stablediff-instance-promo-01" >' . '<a href=' . '"' . $promo_url . '"' . '>' . $promo_text . '</a>' . '</div>';
					} else {
						// make it a button...
						$markup .= '<div class="wpcd-stablediff-instance-promo-button wpcd-stablediff-instance-promo-button-01" >' . '<a href=' . '"' . $promo_url . '"' . '>' . $promo_text . '</a>' . '</div>';
					}
				}
				break;
			case 2:
				$promo_url     = wpcd_get_option( 'stablediff_promo_item02_url' );
				$promo_text    = wpcd_get_option( 'stablediff_promo_item02_text' );
				$button_option = boolval( wpcd_get_option( 'stablediff_promo_item02_button_option' ) );

				if ( ! empty( $promo_url ) ) {
					if ( false == $button_option ) {
						// just a link..
						$markup .= '<div class="wpcd-stablediff-instance-promo wpcd-stablediff-instance-promo-02" >' . '<a href=' . '"' . $promo_url . '"' . '>' . $promo_text . '</a>' . '</div>';
					} else {
						// make it a button...
						$markup .= '<div class="wpcd-vstablediffpn-instance-promo-button wpcd-stablediff-instance-promo-button-02" >' . '<a href=' . '"' . $promo_url . '"' . '>' . $promo_text . '</a>' . '</div>';
					}
				}
				break;

		}

		return $markup;

	}

	/**
	 * Add content to account tab 'Downloads' to let users know where to find downloads.
	 * This content only shows up if a HELP url is set in settings.
	 *
	 * Action Hook: woocommerce_account_downloads_endpoint
	 */
	public function account_downloads_tab_content() {
		$acct_url = wpcd_get_option( 'stablediff_general_help_url' );

		if ( ! empty( $acct_url ) ) {
			$output_str  = '<div class = "wpcd-stablediff-acct-downloads-text-wrap">';
			$output_str .= '<h3 class = "wpcd-stablediff-acct-downloads-text-header">' . __( 'Are you looking for the Stable Diffusion applications for your device?', 'wpcd' ) . '</h3>';
			$output_str .= '<p class = "wpcd-stablediff-acct-downloads-text">' . __( 'To connect to your server you need to download the OPENVPN CLIENT application for your device. Check out our <a href="%s">help pages</a> for more information on how to download these applications and connect to your server.  <br /><br />There you will find instructions for iOS, Android, Windows 10 and more.', 'wpcd' ) . '</p>';
			$output_str .= '</div>';

			// Make sure developers can change the message.
			$output_str = apply_filters( 'wpcd-stablediff-acct-downloads-text', $output_str );

			// Send it to the screen.
			echo sprintf( $output_str, $acct_url );
		}

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
	 * @param array $attributes Attributes of the server and app being provisioned.
	 *
	 *  $atttributes:
	 *  [post_id] => 72850
	 *  [app_post_id] => 72851
	 *  [plugin_initial_version] => 4.24.0
	 *  [plugin_updated_version] => 4.24.0
	 *  [initial_app_name] => stablediff
	 *  [region] => us-west-1
	 *  [size] => small
	 *  [name] => rhonda-hahn-2022-09-14-023838-72849-1
	 *  [wc_order_id] => 72848
	 *  [wc_subscription] => a:1:{i:0;i:72849;}
	 *  [wc_user_id] => 6
	 *  [provider] => 72055-stablediff
	 *  [provider_instance_id] => i-056cb59c0ecb3c3e8
	 *  [server_name] => rhonda-hahn-2022-09-14-023838-72849-1
	 *  [created] => 2022-09-13 21:38:40
	 *  [actions] => a:1:{s:7:"created";i:1663123120;}
	 *  [action] => after-server-create-commands
	 *  [after_create_action_app_id] => 72851
	 *  [action_status] => in-progress
	 *  [last_deferred_action_source] => a:1:{s:11:" 1663123120";s:10:"stablediff";}
	 *  [init] => 1
	 *  [ipv4] => 18.144.11.171
	 *  [status] => active
	 *  [action_id] =>
	 *  [os] => ami-08948efa38f6c51f0
	 *  [ip] => 18.144.11.17.
	 *
	 * @return string $run_cmd
	 */
	public function get_after_server_create_commands( $attributes ) {

		// Setup return variable.
		$run_cmd = '';

		/* first check that we are handling a Stable Diffusion app / server - use attributes array */
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
		/* End first check that we are handling a Stable Diffusion app / server - use attributes array */

		/* Good to go - now get some data from our app post and stick it in the attributes array */
		$attributes['port']            = get_post_meta( $attributes['app_post_id'], 'stablediff_port', true );
		$attributes['protocol']        = get_post_meta( $attributes['app_post_id'], 'stablediff_protocol', true );
		$attributes['dns']             = get_post_meta( $attributes['app_post_id'], 'stablediff_dns', true );
		$attributes['clients']         = wpcd_maybe_unserialize( get_post_meta( $attributes['app_post_id'], 'stablediff_dns', true ) );
		$attributes['max_clients']     = get_post_meta( $attributes['app_post_id'], 'stablediff_max_clients', true );
		$attributes['scripts_version'] = get_post_meta( $attributes['app_post_id'], 'stablediff_scripts_version', true );

		/* And, since this is the start up script, need to make sure that include a couple of items */
		$attributes['option'] = 1;  // Tells the script to add a user.
		$attributes['client'] = 'client1';  // Name of client to add...

		/* Calculate Callback URL and stick that into the array as well */
		$command_name               = 'install_stable_diff';
		$attributes['callback_url'] = $this->get_command_url( $attributes['post_id'], $command_name, 'progress-report' );

		/* Root user name. */
		$attributes['WPCD_USER'] = WPCD()->get_provider_api( $attributes['provider'] )->get_root_user();

		/* What version of the scripts are we getting? */

		/* @TODO: we're probably going to use this code block in other apps so re-factor to place in the ancestor class? */
		$script_version = '';
		if ( isset( $attributes['scripts_version'] ) && ( ! empty( $attributes['scripts_version'] ) ) ) {
			$script_version = $attributes['scripts_version'];
		} else {
			$script_version = wpcd_get_option( 'stablediff_script_version' );
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

		/* grab the contents of the parameter file template - this file contains export vars so that the bash script can be executed without human input */
		$parameter_file_contents = $this->get_script_file_contents( $this->get_scripts_folder() . $script_version . '/params.sh' );

		/* Calculate the temporary parameters file name */
		$parameter_file = 'stablediff-install-startup-parameters-' . sprintf( '%s-%s', $attributes['wc_order_id'], $attributes['region'] ) . '.txt';

		/* Construct an array of placeholder tokens for the run command file  */
		$place_holder_1 = array( 'URL-SCRIPT' => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/' . 'install-stablediff.txt' );
		$place_holder_2 = array( 'URL-SCRIPT-PARAMS' => $this->get_script_temp_path_uri() . '/' . $parameter_file );
		$place_holders  = array_merge( $place_holder_1, $place_holder_2 );

		/* Replace the startup run command contents */
		$startup_run_commands = $this->replace_script_tokens( $startup_run_commands, $place_holders );
		$startup_run_commands = dos2unix_strings( $startup_run_commands ); // make sure that we only have unix line endings...

		/* Replace the parameter file contents with data from the $attributes array */
		$parameter_file_contents = $this->replace_script_tokens( $parameter_file_contents, $attributes );

		/* Put the parameter file into the temp folder... */
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->put_contents(
			trailingslashit( $this->get_script_temp_path() ) . $parameter_file,
			$parameter_file_contents,
			FS_CHMOD_FILE
		);

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

		/* Bail out if the app being evaluated isn't a Stable Diffusion app. */
		if ( 'stablediff' <> get_post_meta( $post_id, 'app_type', true ) ) {
			return $column_data;
		}

		/* Put a line break to separate out data section from others if the column already contains data */
		if ( ! empty( $colummn_data ) ) {
			$column_data = $column_data . '<br />';
		}

		/* Add our data element */
		$column_data = $column_data . 'port: ' . get_post_meta( $post_id, 'stablediff_port', true ) . '<br />';
		$column_data = $column_data . 'protocol: ' . $this->get_protocols()[ strval( get_post_meta( $post_id, 'stablediff_protocol', true ) ) ] . '<br />';
		$column_data = $column_data . 'dns: ' . $this->get_dns_providers()[ strval( get_post_meta( $post_id, 'stablediff_dns', true ) ) ] . '<br />';
		$column_data = $column_data . 'max clients: ' . get_post_meta( $post_id, 'stablediff_max_clients', true );

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

		$local_status = get_post_meta( $post_id, 'wpcd_server_action_status', true );

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

		/* Only paint metabox when the app-type is a Stable Diffusion server. */
		if ( ! ( 'stablediff' === get_post_meta( $post->ID, 'app_type', true ) ) ) {
			return;
		}

		// Add APP DETAIL meta box into APPS custom post type.
		add_meta_box(
			'stablediff_app_detail',
			__( 'Stable Diffusion', 'wpcd' ),
			array( $this, 'render_stablediff_app_details_meta_box' ),
			'wpcd_app',
			'advanced',
			'high'
		);
	}

	/**
	 * Render the STABLE DIFFUSION APP detail meta box in the app post detai screen in wp-admin
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box.
	 */
	public function render_stablediff_app_details_meta_box( $post ) {

		/* Only render data in the metabox when the app-type is a stable diffusion app. */
		if ( ! ( 'stablediff' === get_post_meta( $post->ID, 'app_type', true ) ) ) {
			return;
		}

		$html = '';

		$wpcd_stablediff_app_dns             = get_post_meta( $post->ID, 'stablediff_dns', true );
		$wpcd_stablediff_app_port            = get_post_meta( $post->ID, 'stablediff_port', true );
		$wpcd_stablediff_app_protocol        = get_post_meta( $post->ID, 'stablediff_protocol', true );
		$wpcd_stablediff_app_max_clients     = get_post_meta( $post->ID, 'stablediff_max_clients', true );
		$wpcd_stablediff_app_scripts_version = get_post_meta( $post->ID, 'stablediff_scripts_version', true );
		$wpcd_stablediff_app_clients         = wpcd_maybe_unserialize( get_post_meta( $post->ID, 'stablediff_clients', true ) );

		// Get Stable Diffusion clients array and convert into "," separated string.
		if ( ! empty( $wpcd_stablediff_app_clients ) && is_array( $wpcd_stablediff_app_clients ) ) {
			$wpcd_stablediff_app_clients = implode( ',', $wpcd_stablediff_app_clients );
		}

		ob_start();
		require wpcd_path . 'includes/core/apps/stable-diffusion/templates/stablediff_app_details.php';
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

		/* Only save metabox data when the app-type is a stable diffusion app. */
		if ( ! ( 'stablediff' === get_post_meta( $post->ID, 'app_type', true ) ) ) {
			return;
		}

		// Add nonce for security and authentication.
		$nonce_name   = sanitize_text_field( filter_input( INPUT_POST, 'stablediff_meta', FILTER_UNSAFE_RAW ) );
		$nonce_action = 'wpcd_stablediff_app_nonce_meta_action';

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
		$wpcd_stablediff_app_dns             = sanitize_text_field( filter_input( INPUT_POST, 'wpcd_stablediff_dns', FILTER_UNSAFE_RAW ) );
		$wpcd_stablediff_app_protocol        = sanitize_text_field( filter_input( INPUT_POST, 'wpcd_stablediff_protocol', FILTER_UNSAFE_RAW ) );
		$wpcd_stablediff_app_port            = sanitize_text_field( filter_input( INPUT_POST, 'wpcd_stablediff_port', FILTER_UNSAFE_RAW ) );
		$wpcd_stablediff_app_clients         = sanitize_text_field( filter_input( INPUT_POST, 'wpcd_stablediff_clients', FILTER_UNSAFE_RAW ) );
		$wpcd_stablediff_app_scripts_version = sanitize_text_field( filter_input( INPUT_POST, 'wpcd_stablediff_scripts_version', FILTER_UNSAFE_RAW ) );
		$wpcd_stablediff_app_max_clients     = filter_input( INPUT_POST, 'wpcd_stablediff_max_clients', FILTER_SANITIZE_NUMBER_INT );

		/* Add new values to database */
		update_post_meta( $post_id, 'stablediff_dns', $wpcd_stablediff_app_dns );
		update_post_meta( $post_id, 'stablediff_protocol', $wpcd_stablediff_app_protocol );
		update_post_meta( $post_id, 'stablediff_port', $wpcd_stablediff_app_port );
		update_post_meta( $post_id, 'stablediff_scripts_version', $wpcd_stablediff_app_scripts_version );
		update_post_meta( $post_id, 'stablediff_max_clients', $wpcd_stablediff_app_max_clients );

		// save stable diffusion clients as array.
		if ( ! empty( $wpcd_stablediff_app_clients ) ) {
			$wpcd_stablediff_app_clients = explode( ',', $wpcd_stablediff_app_clients );
		}
		update_post_meta( $post_id, 'stablediff_clients', $wpcd_stablediff_app_clients );

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
		if ( 'wpcd_app' === get_post_type( $post ) && 'stablediff' == $this->get_app_type( $post->ID ) ) {
			$states['wpcd-app-desc'] = $this->get_app_description();
		}

		/* Show the server type on the server list screen */
		if ( 'wpcd_app_server' === get_post_type( $post ) && 'stablediff' == $this->get_server_type( $post->ID ) ) {
			$states['wpcd-server-type'] = 'Stable Diffusion';  // Unfortunately we don't have a server type description function we can call right now so hardcoding the value here.
		}
		return $states;
	}


	/**
	 * Return an array of dns providers.
	 */
	public function get_dns_providers() {
		return array(
			'1' => 'Default',
			'2' => '1.1.1.1',
			'3' => 'Google',
			'4' => 'OpenDNS',
			'5' => 'Verisign',
		);
	}

	/**
	 * Return an array of protocols.
	 */
	public function get_protocols() {
		return array(
			'1' => 'UDP',
			'2' => 'TCP',
		);
	}

	/**
	 * Get a list of pending log image request posts that have not been completed yet.
	 *
	 * @param string $server_id The server id for which we're making this request.
	 *
	 * @return array|bool|object|wp_error
	 */
	public function get_pending_images( $server_id ) {

		$image_requests_ready      = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'ready', 'stablediff_request_image' );
		$image_requests_not_ready  = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'not-ready', 'stablediff_request_image' );
		$image_requests_in_process = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'stablediff_request_image' );

		return array_merge( $image_requests_ready, $image_requests_not_ready, $image_requests_in_process );

	}

	/**
	 * Get a count of pending log image request posts that have not been completed yet.
	 *
	 * @param string $server_id The server id for which we're making this request.
	 *
	 * @return array|bool|object|wp_error
	 */
	public function get_pending_images_count( $server_id ) {

		$pending_images = $this->get_pending_images( $server_id );

		return count( $pending_images );

	}

	/**
	 * Handles server status during server deployment process.
	 *
	 * This function is called as the server is being prepared.  The server bash script makes periodic callbacks/restapi calls
	 * that trigger this function.
	 * It passes one queryparm called 'state' with one of three values.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_command_install_stable_diff_progress-report || wpcd_stablediff_command_install_stable_diff_progress-report
	 *
	 * @param int    $id server post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void|boolean.
	 */
	public function callback_install_server_status( $id, $command_id, $name, $status ) {

		// Set variable to status if not set in the parameters. In this case it will default to "completed" - will be used in action hooks later.
		if ( empty( $status ) ) {
			$status = 'completed';
		}

		// set variable to name, in this case it is always "install_stable_diff" - will be used in action hooks later.
		$name = 'install_stable_diff';

		// Get server state.  It should be 'starting_warmup', 'warmup_complete', 'done'.
		$server_state = sanitize_text_field( filter_input( INPUT_GET, 'state', FILTER_UNSAFE_RAW ) );

		if ( ! in_array( $server_state, array( 'starting_warmup', 'warmup_complete', 'done' ), true ) ) {
			// Invalid server_state given so log error and return.
			do_action( 'wpcd_log_error', 'An invalid callback request was received for function callback_install_server_status - received server id ' . (string) $id, 'security', __FILE__, __LINE__ );
			return false;
		}

		// Update server metas based on server state.
		if ( 'wpcd_app_server' === get_post_type( $id ) ) {

			switch ( $server_state ) {
				case 'starting_warmup':
					WPCD_SERVER()->add_deferred_action_history( $id, $server_state );
					do_action( 'wpcd_log_notification', $id, 'notice', __( 'The stable diffusion server is about to begin the warmup process.', 'wpcd' ), 'server-prepare', null );
					break;
				case 'warmup_complete':
					do_action( 'wpcd_log_notification', $id, 'notice', __( 'The stable diffusion server has completed the warmup process.', 'wpcd' ), 'server-prepare', null );
					WPCD_SERVER()->add_deferred_action_history( $id, $server_state );
					break;
				case 'done':
					do_action( 'wpcd_log_notification', $id, 'notice', __( 'The stable diffusion server is ready.', 'wpcd' ), 'server-prepare', null );

					// Delete certain metas from the database since it is no longer necessary.
					delete_post_meta( $id, 'wpcd_server_after_create_action_app_id' );

					// Schedule emails to be sent..
					update_post_meta( $id, 'wpcd_server_action', 'email' );

					// Add to history.
					WPCD_SERVER()->add_deferred_action_history( $id, $server_state );
					break;
			}
		} else {
			do_action( 'wpcd_log_error', 'Data received for server that does not exist - received server id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );
		}

	}

	/**
	 * Handles status after request for image is submitted to the server..
	 *
	 * This function is called as the server generates images.  The server bash script makes periodic callbacks/restapi calls
	 * that trigger this function.
	 * It passes two queryparms called 'state' and taskid.
	 *
	 * It will be called multiple times.  It will be called when things are completed as well as once for each image uploaded to S3.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_command_stablediff_request_image_progress-report || wpcd_stablediff_command_stablediff_request_image_progress-report
	 *
	 * @param int    $id server post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void|boolean.
	 */
	public function callback_request_image( $id, $command_id, $name, $status ) {

		// Set variable to status if not set in the parameters. In this case it will default to "completed" - will be used in action hooks later.
		if ( empty( $status ) ) {
			$status = 'completed';
		}

		// set variable to name, in this case it is always "install_stable_diff" - will be used in action hooks later.
		$name = 'stablediff_request_image';

		// Get command state.  It should be 'in_progress', 'done'.
		$request_state = sanitize_text_field( filter_input( INPUT_GET, 'state', FILTER_UNSAFE_RAW ) );

		if ( ! in_array( $request_state, array( 'in_progress', 'done' ), true ) ) {
			// Invalid request_state given so log error and return.
			do_action( 'wpcd_log_error', 'An invalid callback request was received for function callback_request_image - received server id ' . (string) $id, 'security', __FILE__, __LINE__ );
			return false;
		}

		// Get the task id.  If we didn't get one, say bye-bye.
		$task_id = sanitize_text_field( filter_input( INPUT_GET, 'taskid', FILTER_SANITIZE_NUMBER_INT ) );
		if ( empty( $task_id ) ) {
			do_action( 'wpcd_log_error', 'No task id was provided in function callback_request_image - received server id ' . (string) $id, 'security', __FILE__, __LINE__ );
			return false;
		}

		// Do we have a signed URL?  If so we need to add it to the server record.
		$signed_url64 = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'awssignedurl64', FILTER_UNSAFE_RAW ) ) ); // The aws signed url encoded in base64 to avoid complications with ampersands.
		if ( ! empty( $signed_url64 ) ) {

			$signed_url = base64_decode( $signed_url64, true ); // decode the url.

			// Get the folder name that we uploaded files into.
			$aws_folder = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'folder', FILTER_UNSAFE_RAW ) ) );

			// What's the file name?
			$aws_file = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'file', FILTER_UNSAFE_RAW ) ) );

			// What was the prompt used?
			$ai_prompt_64 = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'aiprompt64', FILTER_UNSAFE_RAW ) ) );
			$ai_prompt    = base64_decode( $ai_prompt_64, true );

			// Add it to the server record.
			$all_image_urls = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_stablediff_image_urls', true ) );
			if ( empty( $all_image_urls ) ) {
				$all_image_urls = array();
			}

			$all_image_urls[ $aws_folder . '-' . $aws_file ] = array(
				'signed-url'         => $signed_url,
				'taskid'             => $task_id,
				'filename'           => $aws_file,
				'prompt'             => $ai_prompt,
				'date-generated'     => time(),
				'date-generated-gmt' => gmdate( 'Y-m-d H:i:s' ),
			);

			update_post_meta( $id, 'wpcd_stablediff_image_urls', $all_image_urls );

		}

		// Update task to done.
		if ( 'done' === $request_state ) {

			// Maybe send email?

			// Update task record to flag it as done.
			WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, false, 'complete' );
		}

	}

}
