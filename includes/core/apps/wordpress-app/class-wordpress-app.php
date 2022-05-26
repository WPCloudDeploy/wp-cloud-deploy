<?php
/**
 * WordPress App WPCD_WORDPRESS_APP.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_APP
 */
class WPCD_WORDPRESS_APP extends WPCD_APP {

	/* Include traits */
	use wpcd_wpapp_after_prepare_server;
	use wpcd_wpapp_tabs_security;
	use wpcd_wpapp_metaboxes_app;
	use wpcd_wpapp_metaboxes_server;
	use wpcd_wpapp_commands_and_logs;
	use wpcd_wpapp_push_commands;
	use wpcd_wpapp_admin_column_data;
	use wpcd_wpapp_backup_functions;
	use wpcd_wpapp_upgrade_functions;
	use wpcd_wpapp_woocommerce_support;

	/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance.
	 */
	private static $instance;

	/**
	 * Holds singletons of the REST API controllers
	 *
	 * @var array
	 */
	protected array $rest_controllers = array();


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
	 * WPCD_WORDPRESS_APP constructor.
	 */
	public function __construct() {

		parent::__construct();
		// Set app name.
		$this->set_app_name( 'wordpress-app' );
		$this->set_app_description( 'WordPress' );

		// Register an app id for this app with WPCD...
		WPCD()->set_app_id( array( $this->get_app_name() => $this->get_app_description() ) );

		// Set folder where scripts are located.
		$this->set_scripts_folder( dirname( __FILE__ ) . '/scripts/' );
		$this->set_scripts_folder_relative( "includes/core/apps/{$this->get_app_name()}/scripts/" );

		add_action( 'init', array( &$this, 'init' ), 1 );

		// Global for backwards compatibility.
		$GLOBALS[ "wpcd_app_{$this->get_app_name()}" ] = $this;

	}


	/**
	 * Init Function.
	 */
	public function init() {

		// setup needed objects.
		$this->settings();
		$this->ssh();

		// run upgrades if necessary.
		$this->upgrade();

		// setup WordPress hooks.
		$this->hooks();

	}


	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {
		$this->include_tabs();
		$this->include_tabs_server();

		// Enable the REST API if the settings allow for it.
		if ( true === (bool) wpcd_get_early_option( 'wordpress_app_rest_api_enable' ) ) {
			$this->include_rest_api();
		}

		// Make sure WordPress loads up our css and js scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'wpapp_enqueue_scripts' ), 10, 1 );

		// Show any admin notices related to upgrades.
		add_action( 'admin_notices', array( $this, 'wpapp_upgrades_admin_notice' ) );

		// Actions send from the front-end when installing servers and sites.
		add_action( "wpcd_server_{$this->get_app_name()}_action", array( &$this, 'do_instance_action' ), 10, 3 );
		add_action( "wpcd_app_{$this->get_app_name()}_action", array( &$this, 'do_app_action' ), 10, 3 );

		add_filter( "wpcd_command_{$this->get_app_name()}_logs_done", array( &$this, 'get_logs_done' ), 10, 4 );
		add_filter( "wpcd_command_{$this->get_app_name()}_logs_intermed", array( &$this, 'get_logs_intermed' ), 10, 4 );
		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( &$this, 'command_completed' ), 10, 2 );
		add_filter( 'wpcd_server_script_args', array( $this, 'add_script_args_server' ), 10, 2 );
		add_filter( 'wpcd_app_script_args', array( $this, 'add_script_args_app' ), 10, 2 );
		add_filter( 'wpcd_actions', array( $this, 'add_post_actions' ), 10, 2 );
		add_filter( "wpcd_script_placeholders_{$this->get_app_name()}", array( $this, 'script_placeholders' ), 10, 6 );
		add_filter( 'wpcd_app_server_admin_list_local_status_column', array( &$this, 'app_server_admin_list_local_status_column' ), 10, 2 );  // Show the server status.
		add_filter( 'wpcd_app_server_admin_list_local_status_column', array( &$this, 'app_server_admin_list_upgrade_status' ), 11, 2 );  // Show the upgrade status in the local status column - function located in trait file upgrade.php.
		add_filter( 'wpcd_app_admin_list_summary_column', array( &$this, 'app_admin_list_upgrade_status' ), 11, 2 );  // Show the upgrade status in the TITLE column of the app list - function located in trait file upgrade.php.

		// Push commands and callbacks from servers.
		add_action( "wpcd_{$this->get_app_name()}_command_server_status_completed", array( &$this, 'push_command_server_status_completed' ), 10, 4 );  // When a server sends us it's daily status report, part 1 - see bash script #24.
		add_action( "wpcd_{$this->get_app_name()}_command_sites_status_completed", array( &$this, 'push_command_sites_status_completed' ), 10, 4 );  // When a server sends us it's daily status report, part 2 - see bash script #24.
		add_action( "wpcd_{$this->get_app_name()}_command_maldet_scan_completed", array( &$this, 'push_command_maldet_scan_completed' ), 10, 4 );  // When a server sends us a report of maldet scan results - see bash script #26.
		add_action( "wpcd_{$this->get_app_name()}_command_server_restart_completed", array( &$this, 'push_command_server_restart_completed' ), 10, 4 );  // When a server sends us a report of restart or shutdown.
		add_action( "wpcd_{$this->get_app_name()}_command_monit_log_completed", array( &$this, 'push_command_monit_log_completed' ), 10, 4 );  // When a server sends us a monit alert or report.
		add_action( "wpcd_{$this->get_app_name()}_command_start_domain_backup_completed", array( &$this, 'push_command_domain_backup_v1_started' ), 10, 4 );  // When a server sends us a notification telling us a scheduled backup was started for a domain.
		add_action( "wpcd_{$this->get_app_name()}_command_end_domain_backup_completed", array( &$this, 'push_command_domain_backup_v1_completed' ), 10, 4 );  // When a server sends us a notification telling us a scheduled backup was completed for a domain.
		add_action( "wpcd_{$this->get_app_name()}_command_server_config_backup_completed", array( &$this, 'push_command_server_config_backup' ), 10, 4 );  // When a server sends us a notification telling us a backup of the server configuration has started or ended.
		add_action( "wpcd_{$this->get_app_name()}_command_test_rest_api_completed", array( &$this, 'push_command_test_rest_api_completed' ), 10, 4 );  // When a server sends us a test notification (initiated from the TOOLS tab on a server screen).

		// Push commands and callbacks from sites.
		add_action( "wpcd_{$this->get_app_name()}_command_schedule_site_sync_completed", array( &$this, 'push_command_schedule_site_sync' ), 10, 4 );  // When a scheduled site sync has started or ended.

		// After server prepare action hooks.
		$this->wpcd_after_server_prepare_action_hooks();

		// When we're querying to find out the status of a server.
		add_filter( 'wpcd_is_server_available_for_commands', array( &$this, 'wpcd_is_server_available_for_commands' ), 10, 2 );

		// When an app cleanup script is being run.
		add_action( 'wpcd_cleanup_app_after', array( $this, 'wpcd_cleanup_app_after' ), 10, 1 );

		// When a server cleanup script is being run.
		add_action( 'wpcd_cleanup_server_after', array( $this, 'wpcd_cleanup_server_after' ), 10, 1 );

		// When WP has been installed, add temp domain to DNS if configured as well as perform other actions after the site is successfully installed.
		add_action( 'wpcd_command_wordpress-app_completed_after_cleanup', array( $this, 'wpcd_wpapp_install_complete' ), 10, 4 );

		// Ajax Hooks.
		add_action( "wp_ajax_wpcd_{$this->get_app_name()}", array( &$this, 'ajax_server' ) );       // For ajax calls dealing with servers in wp-admin.
		add_action( "wp_ajax_wpcd_app_{$this->get_app_name()}", array( &$this, 'ajax_app' ) );      // for ajax calls dealing with apps in wp-admin.
		if ( wpcd_is_woocommerce_activated() ) {
			add_action( 'wp_ajax_wpcd_wpapp_frontend', array( &$this, 'ajax_wpapp_frontend' ) );    // for ajax calls from the front-end - code in trait files.
		}

		// Add welcome message to the settings screen.
		add_filter( 'wpcd_general_settings_after_welcome_message', array( $this, 'welcome_message_settings' ), 10, 1 );

		// Add some additional instructions to the "no application servers found" message.
		add_filter( 'wpcd_no_app_servers_found_msg', array( $this, 'no_app_servers_found_msg' ), 10, 1 );

		// Add a state called "WordPress" to the app when its shown on the app list.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 20, 2 );

		// Background actions for SERVER.
		add_action( 'wpcd_wordpress_deferred_actions_for_server', array( $this, 'do_deferred_actions_for_server' ), 10 );

		// Background actions for APPS.
		add_action( 'wpcd_wordpress_deferred_actions_for_apps', array( $this, 'do_deferred_actions_for_app' ), 10 );

		// Delete temp log files.
		add_action( 'wpcd_wordpress_file_watcher', array( $this, 'file_watcher_delete_temp_files' ) );

		/* Do not allow WooCommerce to redirect to their account page  */
		add_filter( 'woocommerce_prevent_admin_access', array( $this, 'wc_subscriber_admin_access' ), 20, 1 );

		/*********************************************
		* Hooks and filters for screens in wp-admin
		*/

		// Filter hook to add new columns to the APP list.
		add_filter( 'manage_wpcd_app_posts_columns', array( $this, 'app_posts_app_table_head' ), 10, 1 );

		// Action hook to add values in new columns in the APP list.
		add_action( 'manage_wpcd_app_posts_custom_column', array( $this, 'app_posts_app_table_content' ), 10, 2 );

		// Filter hook to add new columns to the SERVER list.
		add_filter( 'manage_wpcd_app_server_posts_columns', array( $this, 'app_server_table_head' ), 10, 1 );

		// Show some app details in the wp-admin list of apps.
		add_filter( 'wpcd_app_admin_list_summary_column', array( &$this, 'app_admin_list_summary_column' ), 10, 2 );

		// Show some app details about the health of the app the wp-admin list of apps.
		add_filter( 'wpcd_app_admin_list_app_health_column', array( &$this, 'app_admin_list_health_column' ), 10, 2 );

		// Add the INSTALL WordPress button to the server list.
		add_filter( 'wpcd_app_server_table_content', array( &$this, 'app_server_table_content' ), 10, 3 );

		// Filter hook to add a REMOVE SITE link to the hover action on an app.
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );

		// Meta box display callback.
		add_action( 'add_meta_boxes_wpcd_app', array( $this, 'app_admin_add_meta_boxes' ) );

		// Save Meta Values.
		add_action( 'save_post', array( $this, 'app_admin_save_meta_values' ), 10, 2 );

		// Add Metabox.IO metaboxes for the WordPress app into the APP details CPT screen.
		add_filter( "wpcd_app_{$this->get_app_name()}_metaboxes", array( $this, 'add_meta_boxes' ), 10, 1 );

		// Add Metabox.IO metaboxes for the SERVER CPT into the server details CPT screen.
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_server_metaboxes' ), 10, 1 ); // Register application metabox stub with filter. Note that this is a METABOX.IO filter, not a core WP filter.
		add_filter( "wpcd_server_{$this->get_app_name()}_metaboxes", array( $this, 'add_meta_boxes_server' ), 10, 1 );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpapp_schedule_events_for_new_site' ), 10, 2 );

		// Action hook to set transient if directory is readable and .txt files are accessible.
		add_action( 'admin_init', array( $this, 'wpapp_admin_init' ) );

		// Action hook to handle ajax request to set transient if user closed the readable notice check.
		add_action( 'wp_ajax_set_readable_check', array( $this, 'set_readable_check' ) );

		// Action hook to handle ajax request to set transient if user clicked the "check again" option in the "readable check" notice.
		add_action( 'wp_ajax_readable_check_again', array( $this, 'readable_check_again' ) );

		// Action hook to extend admin filter options.
		add_action( 'restrict_manage_posts', array( $this, 'wpapp_wpcd_app_table_filtering' ) );

		// Filter hook to filter app listing on custom meta data.
		add_filter( 'parse_query', array( $this, 'wpapp_wpcd_app_parse_query' ), 10, 1 );

		// Action hook to handle ajax request to set transient if user closed the notice for cron check.
		add_action( 'wp_ajax_set_cron_check', array( $this, 'set_cron_check' ) );
	}

	/**
	 * Setup some action hooks to do things after a server has been created.
	 * The hook functions themselves are in a trait file  after-prepare-server.php
	 */
	public function wpcd_after_server_prepare_action_hooks() {
		/* Add tasks to pending logs after a server is created. */
		add_action( 'wpcd_command_wordpress-app_prepare_server_completed', array( $this, 'wpcd_wpapp_core_prepare_server_completed' ), 10, 2 );

		/* Pending Logs Background Task: Trigger installation of backup scripts on the server */
		add_action( 'wpcd_core_after_server_prepare_install_server_backups', array( $this, 'wpcd_core_install_server_backups' ), 10, 3 );

		/*  Install Backups: Handle backup script installation success or failure */
		add_action( 'wpcd_server_wordpress-app_server_auto_backup_action_all_sites_successful', array( $this, 'wpcd_core_install_server_handle_backup_script_install_success' ), 10, 3 );
		add_action( 'wpcd_server_wordpress-app_server_auto_backup_action_all_sites_failed', array( $this, 'wpcd_core_install_server_handle_backup_script_install_failed' ), 10, 3 );

		/* Pending Logs Background Task: Trigger installation of server configuration backups on the server */
		add_action( 'wpcd_core_after_server_prepare_install_server_configuration_backups', array( $this, 'wpcd_core_install_server_configuration_backups' ), 10, 3 );

		/*  Install Server Configuration Backups: Handle backup script installation success or failure */
		add_action( 'wpcd_server_wordpress-app_toggle_server_configuration_backups_success', array( $this, 'wpcd_core_install_server_handle_config_backup_install_success' ), 10, 3 );
		add_action( 'wpcd_server_wordpress-app_toggle_server_configuration_backups_failed', array( $this, 'wpcd_core_install_server_handle_config_backup_install_failed' ), 10, 3 );

		/* Pending Logs Background Task: Run callback for the first time on a server after they're installed. */
		add_action( 'wpcd_core_after_server_prepare_run_server_callbacks', array( $this, 'wpcd_core_run_server_callbacks' ), 10, 3 );

	}

	/**
	 * Include the files corresponding to the tabs.
	 */
	private function include_tabs() {

		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/tabs.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/general.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/backup.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/ssl.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/cache.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/sftp.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/staging.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/clone-site.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/copy-to-existing-site.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/site-sync.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/crons.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/php-options.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/change-domain.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/misc.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/tweaks.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/tools.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/theme-and-plugin-updates.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/phpmyadmin.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/6g_firewall.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/7g_firewall.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/statistics.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/logs.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/wp-site-users.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/wpconfig-options.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/redirect-rules.php';

		if ( defined( 'WPCD_SHOW_SITE_USERS_TAB' ) && WPCD_SHOW_SITE_USERS_TAB ) {
			require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/site-system-users.php';
		}

		/**
		 * Need to add new tabs or add data to existing tabs from an add-on?
		 * Then this action hook MUST be used! Otherwise, weird
		 * stuff will happen and you will not know why!
		 */
		do_action( 'wpcd_wpapp_include_app_tabs' );

	}


	/**
	 * Include the files corresponding to the tabs for the server CPT.
	 */
	private function include_tabs_server() {

		// No upgrades needed - show every thing.
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/general.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/services.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/callbacks.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/ufw_firewall.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/backup.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/monit.php';

		// Possibly add required files for ssh console.
		$option_hide_ssh_console = get_option( 'wpcd_wpapp_ssh_console_hide' );
		if ( ! empty( $option_hide_ssh_console ) && true == boolval( $option_hide_ssh_console ) ) {
			// ssh console tab should be hidden unless a wp-config option forces it to be shown - so check for that here.
			if ( defined( 'WPCD_HIDE_SSH_CONSOLE' ) && ! WPCD_HIDE_SSH_CONSOLE ) {
				require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/ssh_console.php';
			}
		} else {
			// possibly show the console unless a wp-config option forces it to be hidden so check for that here.
			if ( ! defined( 'WPCD_HIDE_SSH_CONSOLE' ) || ( defined( 'WPCD_HIDE_SSH_CONSOLE' ) && ! WPCD_HIDE_SSH_CONSOLE ) ) {
				require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/ssh_console.php';
			}
		}

		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/statistics.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/tools.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/tweaks.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/upgrade.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/sites.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/power.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/users.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/logs.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/fail2ban.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/sshkeys.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/monitorix.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/goaccess.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/resize.php';

		/**
		 * Need to add new tabs or add data to existing tabs from an add-on?
		 * Then this action hook MUST be used! Otherwise, weird
		 * stuff will happen and you will not know why!
		 */
		do_action( 'wpcd_wpapp_include_server_tabs' );

	}

	/**
	 * Include the files for the REST API and instantiate the controllers
	 */
	private function include_rest_api() {
		require_once wpcd_path . 'includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-base.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-servers.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-sites.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-tasks.php';

		// Function specific endpoints for sites - so that the main sites controller file does not get large and unwieldly.
		require_once wpcd_path . 'includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-sites-change-domain.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/rest-api/class-wpcd-rest-api-controller-sites-clone-site.php';

		/**
		 * Need to add new REST API controllers from an add-on?
		 * Then this action hook MUST be used! Otherwise, weird
		 * stuff will happen and you will not know why!
		 *
		 * Third parties that need to add a plugin to extend
		 * the rest api by adding new controllers should
		 * ensure that the filter a few lines below is used
		 * to add the new controller to the array so that
		 * they can be instantiated.
		 */
		do_action( 'wpcd_wpapp_include_rest_api' );

		// List of controllers to instantiate.
		// This list should be added to by other plugins adding their own rest api controllers.
		$controllers = apply_filters(
			"wpcd_app_{$this->get_app_name()}_rest_api_controller_list",
			array(
				WPCD_REST_API_Controller_Servers::class,
				WPCD_REST_API_Controller_Sites::class,
				WPCD_REST_API_Controller_Sites_Change_Domain::class,
				WPCD_REST_API_Controller_Sites_Clone_Site::class,
				WPCD_REST_API_Controller_Tasks::class,
			)
		);

		// Loop through list and instantiate.
		foreach ( $controllers as $controller_class ) {
			$controller                                        = new $controller_class();
			$this->rest_controllers[ $controller->get_name() ] = $controller;
		}

	}


	/**
	 * Get general fields for metaboxes on app cpt screen
	 *
	 * @param array $fields fields.
	 * @param int   $app_id is the post id of the app record we're on.
	 *
	 * @return array new array of fields.
	 *
	 * @TODO: strip out inline styles and move to admin stylesheet?
	 */
	private function get_general_fields( array $fields, $app_id ) {
		$fields[] = array(
			'name'    => __( 'Domain', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => $this->get_domain_name( $app_id ),
			'columns' => 'left' === $this->get_tab_style() ? 4 : 4,
			'class'   => 'left' === $this->get_tab_style() ? 'wpcd_site_details_top_row wpcd_site_details_top_row_domain wpcd_site_details_top_row_domain_left' : 'wpcd_site_details_top_row wpcd_site_details_top_row_domain',
		);
		$fields[] = array(
			'name'    => __( 'IP', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => $this->get_all_ip_addresses_for_display( $app_id ),
			'columns' => 'left' === $this->get_tab_style() ? 2 : 2,
			'class'   => 'wpcd_site_details_top_row',
		);
		$fields[] = array(
			'name'    => __( 'Admin', 'wpcd' ),
			'id'      => 'wpcd_app_action_site-detail-header-view-admin',
			'type'    => 'button',
			'std'     => $this->get_formatted_wpadmin_link( $app_id ),
			'columns' => 'left' === $this->get_tab_style() ? 2 : 2,
			'class'   => 'wpcd_site_details_top_row',
		);
		$fields[] = array(
			'name'    => __( 'Front-end', 'wpcd' ),
			'id'      => 'wpcd_app_action_site-detail-header-view-site',
			'type'    => 'button',
			'std'     => $this->get_formatted_site_link( $app_id ),
			'columns' => 'left' === $this->get_tab_style() ? 2 : 2,
			'class'   => 'wpcd_site_details_top_row',
		);

		$server_post_id = get_post_meta( $app_id, 'parent_post_id', true );
		$url            = admin_url( 'edit.php?post_type=wpcd_app&server_id=' . (string) $server_post_id );
		$apps_on_server = sprintf( '<a href="%s" target="_blank">%s</a>', $url, __( 'View App List', 'wpcd' ) );

		$fields[] = array(
			'name'    => __( 'Apps on Server', 'wpcd' ),
			'id'      => 'wpcd_app_action_site-detail-header-view-apps',
			'type'    => 'button',
			'std'     => $apps_on_server,
			'columns' => 'left' === $this->get_tab_style() ? 2 : 2,
			'class'   => 'wpcd_site_details_top_row',
		);

		// Does the server for this app need an upgrade?
		$upgrade_needed = $this->app_admin_list_upgrade_status( '', $app_id );  // This function is located in traits/traits-for-class-wordpress-app/upgrade.php.
		if ( ! empty( $upgrade_needed ) ) {

			$fields[] = array(
				'type' => 'divider',
			);

			$fields[] = array(
				'name'  => __( 'Upgrade Notice', 'wpcd' ),
				'type'  => 'custom_html',
				'std'   => $upgrade_needed,
				'class' => 'wpcd_site_details_top_row',
			);
		}

		return $fields;
	}

	/**
	 * Get general fields for metaboxes on server cpt screen
	 *
	 * @param array $fields fields.
	 * @param int   $id is the post id of the app record we're on.
	 *
	 * @return array new array of fields
	 *
	 * @TODO: strip out inline styles and move to admin stylesheet?
	 */
	private function get_general_fields_server( array $fields, $id ) {

		$fields['general-welcome-top-col_1'] = array(
			'name'    => __( 'Server Name', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'wpcd_server_name', true ),
			'columns' => 3,
			'class'   => 'wpcd_server_details_top_row',
		);

		$fields['general-welcome-top-col_2'] = array(
			'name'    => __( 'IP', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => WPCD_SERVER()->get_all_ip_addresses_for_display( $id ),
			'columns' => 3,
			'class'   => 'wpcd_server_details_top_row',
		);

		$fields['general-welcome-top-col_3'] = array(
			'name'    => __( 'Provider', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => WPCD()->wpcd_get_cloud_provider_desc( get_post_meta( $id, 'wpcd_server_provider', true ) ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row',
		);

		$fields['general-welcome-top-col_4'] = array(
			'name'    => __( 'Region', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'wpcd_server_region', true ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row',
		);
	
		if( is_admin() ) {
			$apps_url    =  admin_url( 'edit.php?post_type=wpcd_app&server_id=' . $id );
		} else {
			$apps_url = get_permalink( WPCD_WORDPRESS_APP_PUBLIC::get_apps_list_page_id() ) . '?server_id=' . (string) $id;
		}

		$fields['general-welcome-top-col_5'] = array(
			'name'    => __( 'Apps', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => sprintf( '<a href="%s" target="_blank">%d</a>', esc_url( $apps_url ), WPCD_SERVER()->get_app_count( $id ) ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row',
		);

		// Does this server need an upgrade?
		$upgrade_needed = $this->app_server_admin_list_upgrade_status( '', $id );  // This function is located in traits/traits-for-class-wordpress-app/upgrade.php.
		if ( ! empty( $upgrade_needed ) ) {

			$fields[] = array(
				'type' => 'divider',
			);

			$fields[] = array(
				'name'  => __( 'Upgrade Notice', 'wpcd' ),
				'type'  => 'custom_html',
				'std'   => $upgrade_needed,
				'class' => 'wpcd_server_details_top_row',
			);
		}

		return $fields;
	}


	/**
	 * Get the domain name used for a wp app instance
	 *
	 * @param int $app_id post id of app record.
	 *
	 * @return string the domain name.
	 */
	public function get_domain_name( $app_id ) {
		return get_post_meta( $app_id, 'wpapp_domain', true );
	}

	/**
	 * Returns an app ID using the postid of a server and the domain name.
	 *
	 * @param int    $server_id  The server for which to locate the app post.
	 * @param string $domain The domain for which to locate the app post on the server.
	 *
	 * @return int|boolean app post id or false or error message
	 */
	public function get_app_id_by_server_id_and_domain( $server_id, $domain ) {

		// If the server id is not for a server post, return immediately with an error.
		if ( 'wpcd_app_server' !== get_post_type( $server_id ) ) {
			return false;
		}

		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => 'parent_post_id',
						'value' => $server_id,
					),
					array(
						'key'   => 'wpapp_domain',
						'value' => $domain,
					),
				),
			),
		);

		if ( $posts ) {
			return $posts[0]->ID;
		} else {
			return false;
		}

	}

	/**
	 * Returns all the app posts for a particular a domain name.
	 *
	 * Usually, only one post exists for a domain.
	 * But if a domain is pushed to another server, multiple posts might exist.
	 *
	 * @param string $domain The domain for which to locate the app post on the server.
	 *
	 * @return array|boolean|string array of posts or false or error message
	 */
	public function get_apps_by_domain( $domain ) {

		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => 'wpapp_domain',
						'value' => $domain,
					),
				),
			),
		);

		if ( $posts ) {
			return $posts;
		} else {
			return false;
		}

	}


	/**
	 * Get the status of ssl stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return boolean
	 */
	public function get_site_local_ssl_status( $app_id ) {

		if ( 'on' === get_post_meta( $app_id, 'wpapp_ssl_status', true ) ) {
			return true;
		} else {
			return false;
		}

	}


	/**
	 * Get a formatted link to wp_admin area
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return string
	 */
	public function get_formatted_wpadmin_link( $app_id ) {

		// get ssl status first.
		$ssl = $this->get_site_local_ssl_status( $app_id );

		// get domain name.
		$domain = $this->get_domain_name( $app_id );

		if ( true == $ssl ) {
			$url_wpadmin = 'https://' . $domain . '/wp-admin';
		} else {
			$url_wpadmin = 'http://' . $domain . '/wp-admin';
		}

		return sprintf( '<a href = "%s" target="_blank">' . __( 'Login to admin area', 'wpcd' ) . '</a>', $url_wpadmin );

	}

	/**
	 * Returns a boolean true/false if PHP 80 is supposed to be installed.
	 *
	 * @param int $server_id ID of server being interrogated...
	 *
	 * @return boolean
	 */
	public function is_php_80_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '4.2.4' ) > -1 ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Returns a boolean true/false if PHP 81 is supposed to be installed.
	 *
	 * @param int $server_id ID of server being interrogated...
	 *
	 * @return boolean
	 */
	public function is_php_81_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '4.12.1' ) > -1 ) {
			// Versions of the plugin after 4.12.1 automatically install PHP 8.1.
			return true;
		} else {
			// See if it was manually installed via an upgrade process - which would leave a meta field value behind on the server CPT record.
			$is_php81_installed = (bool) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_php81_installed', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( true === $is_php81_installed ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if a particular PHP version is active.
	 * Version 4.16 and later of WPCD deactivated earlier versions of PHP
	 * by default.  Only if the user explicitly activated it was it enabled.
	 *
	 * @param int    $server_id ID of server being interrogated...
	 * @param string $php_version PHP version - eg: php56, php71, php72, php73, php74, php81, php82 etc.
	 *
	 * @return boolean
	 */
	public function is_php_version_active( $server_id, $php_version ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		$return = true;  // assume true for active.
		if ( version_compare( $initial_plugin_version, '4.15.0' ) > -1 ) {
			// Versions of WPCD later than 4.15 deactivated PHP versions 5.6, 7.1, 7.2 and 7.3.
			switch ( $php_version ) {
				case 'php56':
				case 'php70':
				case 'php71':
				case 'php72':
				case 'php73':
					$return = false;
					break;
			}
		} else {
			// Versions of WPCD prior to 4.15 activated almost all php versions.  Except PHP 8.0 and 8.1 are special cases because of the timing of when these were added to servers.
			switch ( $php_version ) {
				case 'php80':
					if ( ! $this->is_php_80_installed( $server_id ) ) {
						$return = false;
					}
					break;
				case 'php81':
					if ( ! $this->is_php_81_installed( $server_id ) ) {
						$return = false;
					}
					break;
			}
		}

		// Check for metas - this overrides defaults from above.
		$current_php_activation_state = wpcd_maybe_unserialize( get_post_meta( $server_id, 'wpcd_wpapp_php_activation_state', true ) );
		if ( is_array( $current_php_activation_state ) ) {
			if ( ! empty( $current_php_activation_state[ $php_version ] ) ) {
				if ( 'disabled' === $current_php_activation_state[ $php_version ] ) {
					$return = false;
				}
				if ( 'enabled' === $current_php_activation_state[ $php_version ] ) {
					$return = true;
				}
			}
		}

		return $return;
	}

	/**
	 * Returns a boolean true/false if the 7G V 1.5 Firewall Rules is installed.
	 *
	 * @param int $server_id ID of server being interrogated...
	 *
	 * @return boolean
	 */
	public function is_7g15_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '4.12.1' ) > -1 ) {
			// Versions of the plugin after 4.12.1 automatically install 7g V 1.5.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_7g_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 1.5 ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if wpcli 2.5 is installed.
	 *
	 * @param int $server_id ID of server being interrogated...
	 *
	 * @return boolean
	 */
	public function is_wpcli25_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '4.12.1' ) > -1 ) {
			// Versions of the plugin after 4.12.1 automatically install wpcli 2.5.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_wpcli_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 2.5 ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if wpcli 2.6 is installed.
	 *
	 * @param int $server_id ID of server being interrogated...
	 *
	 * @return boolean
	 */
	public function is_wpcli26_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '4.14.2' ) > -1 ) {
			// Versions of the plugin after 4.14.2 automatically install wpcli 2.6.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_wpcli_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 2.6 ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if the PHP Module INTL is supposed to be installed on the server.
	 *
	 * @param int $server_id ID of server being interrogated...
	 *
	 * @return boolean
	 */
	public function is_php_intl_module_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '4.16.2' ) > -1 ) {
			// Versions of the plugin after 4.16.2 automatically install the PHP INTL module on all new servers.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (bool) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_phpintl_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			return $it_is_installed;
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if the server is a 4.6.0 or later server or was upgraded to that version.
	 *
	 * @param int $server_id ID of server being interrogated...
	 *
	 * @return boolean
	 */
	public function is_460_or_later( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '4.6.0' ) > -1 ) {
			return true;
		}

		$last_upgrade_done = $this->get_server_meta_by_app_id( $server_id, 'wpcd_last_upgrade_done', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( (int) $last_upgrade_done >= 460 ) {
			return true;
		}

		return false;

	}

	/**
	 * Returns a boolean true/false if http2 is enabled or disabled on a site
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean
	 */
	public function is_http2_enabled( $app_id ) {

		if ( 'on' === $this->http2_status( $app_id ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Returns on/off status of http2 for the site being interrogated.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string 'on' or 'off'
	 */
	public function http2_status( $app_id ) {

		$http2_status = get_post_meta( $app_id, 'wpapp_ssl_http2_status', true );

		if ( empty( $http2_status ) ) {
			$http2_status = 'off';
		}

		return $http2_status;

	}

	/**
	 * Returns whether a site is enabled or disabled.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean true/false
	 */
	public function is_site_enabled( $app_id ) {

		if ( 'off' === $this->site_status( $app_id ) ) {
			return false;
		} else {
			return true;
		}

	}

	/**
	 * Returns an indicator whether the site is enabled or disabled.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string 'on' or 'off'
	 */
	public function site_status( $app_id ) {

		$current_status = get_post_meta( $app_id, 'wpapp_site_status', true );

		if ( empty( $current_status ) ) {
			$current_status = 'on';
		}

		return $current_status;

	}

	/**
	 * Returns whether a site is a staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean
	 */
	public function is_staging_site( $app_id ) {

		return WPCD()->is_staging_site( $app_id );

	}

	/**
	 * Returns the domain of the associated staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string The staging domain name if it exists.
	 */
	public function get_companion_staging_site_domain( $app_id ) {

		$staging_domain = (string) get_post_meta( $app_id, 'wpapp_staging_domain', true );

		return $staging_domain;

	}

	/**
	 * Returns the post id of the domain of the associated staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return int The post id of the staging domain if it exists.
	 */
	public function get_companion_staging_site_id( $app_id ) {

		$staging_site_id = (int) get_post_meta( $app_id, 'wpapp_staging_domain_id', true );

		return $staging_site_id;

	}

	/**
	 * Returns the domain of the associated live site for a staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string The live domain name if it exists.
	 */
	public function get_live_domain_for_staging_site( $app_id ) {

		$live_domain = (string) get_post_meta( $app_id, 'wpapp_cloned_from', true );

		return $live_domain;

	}

	/**
	 * Returns the post id of the live domain of associated with a staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return int The post id for the live site related to the staging site.
	 */
	public function get_live_id_for_staging_site( $app_id ) {

		$live_id = (int) get_post_meta( $app_id, 'wpapp_cloned_from_id', true );

		return $live_id;

	}


	/**
	 * Get a formatted link to the front-end of the site
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return string
	 */
	public function get_formatted_site_link( $app_id ) {

		// get ssl status first.
		$ssl = $this->get_site_local_ssl_status( $app_id );

		// get domain name.
		$domain = $this->get_domain_name( $app_id );

		if ( true == $ssl ) {
			$url_site = 'https://' . $domain;
		} else {
			$url_site = 'http://' . $domain;
		}

		return sprintf( '<a href = "%s" target="_blank">' . __( 'View site', 'wpcd' ) . '</a>', $url_site );

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
	 * Set the domain name used for a wp app instance.
	 * This can be used when the user changes their domain.
	 *
	 * @param int    $app_id post id of app record.
	 * @param string $new_domain new domain to update the record with.
	 *
	 * @return int|bool the result of the update_post_meta call - see https://developer.wordpress.org/reference/functions/update_post_meta/
	 */
	public function set_domain_name( $app_id, $new_domain ) {
		return update_post_meta( $app_id, 'wpapp_domain', $new_domain );
	}


	/**
	 * Add additional parameters to the localized script sent to the APPS cpt screen.
	 *
	 * These parameters are intended to be common to all JS scripts for the apps screen.
	 * Unique parameters per script should probably not be added here.
	 *
	 * Filter hook: wpcd_app_script_args
	 *
	 * @param array  $args args.
	 * @param string $script script.
	 *
	 * These values will be used in Javascript as part of the 'params' array.
	 * Example, action will be referenced as params.action.
	 */
	public function add_script_args_app( $args, $script ) {
		$args['action']                  = "wpcd_app_{$this->get_app_name()}";
		$args['redirect']                = admin_url( '/edit.php?post_type=wpcd_app' );
		$args['refresh_seconds']         = 30;  // No point in trying to go too low since the cron process that collects and stores the logs only run once per minute.
		$args['l10n']                    = array(
			'done'                     => __( 'The command has completed.', 'wpcd' ),
			'checking_for_logs'        => __( 'Checking server for updated progress information...', 'wpcd' ),
			'no_progress_data_in_logs' => __( 'Last progress check resulted in no new data. The operation seems to still be in progress. However, if you see this error more than 10 times or so, it might be because the connection to the WordPress server has been severed.', 'wpcd' ),
		);
		$args['user_can_manage_servers'] = current_user_can( 'wpcd_manage_servers' );
		return $args;
	}


	/**
	 * Add additional parameters to the localized script sent to the SERVER cpt screen.
	 *
	 * These parameters are intended to be common to all JS scripts for the SERVER screen.
	 * Unique parameters per script should probably not be added here.
	 *
	 * @param array  $args args.
	 * @param string $script script.
	 *
	 * These values will be used in Javascript as part of the 'params' array.
	 * Example, action will be referenced as params.action.
	 */
	public function add_script_args_server( $args, $script ) {
		$this->add_provider_support();
		$args['action']                     = "wpcd_{$this->get_app_name()}";
		$args['refresh_seconds']            = 5;
		$args['l10n']                       = array(
			'add_new' => __( 'Deploy A New WordPress Server', 'wpcd' ),
			'done'    => __( 'The command has completed.', 'wpcd' ),
		);
		$args['user_can_provision_servers'] = apply_filters( "wpcd_{$this->get_app_name()}_show_deploy_server_button", current_user_can( 'wpcd_provision_servers' ) );  // Filter: wpcd_wordpress-app_show_deploy_server_button.
		return $args;
	}


	/**
	 * Single entry point for all ajax actions for app.
	 */
	public function ajax_app() {

		/* Check nonce */
		check_ajax_referer( 'wpcd-app', 'nonce' );

		/* Get action requested */
		$action = filter_input( INPUT_POST, '_action', FILTER_SANITIZE_STRING );
		if ( empty( $action ) ) {
			$action = filter_input( INPUT_GET, '_action', FILTER_SANITIZE_STRING );
		}

		/* Get app id */
		$id = filter_input( INPUT_POST, 'id', FILTER_VALIDATE_INT );

		/* Make sure user can at least view the app */
		if ( ! $this->wpcd_user_can_view_wp_app( $id ) ) {
			echo wp_send_json_error( array( 'msg' => __( 'You are not allowed to perform this operation on this app.', 'wpcd' ) ) );
			wp_die();
		}

		/* Initialize var that will be sent back to js requester. */
		$result = null;
		$msg    = null;

		switch ( $action ) {
			case 'show-log-console':
				include wpcd_path . 'includes/core/apps/wordpress-app/templates/show-log-console-bare.php';
				wp_die();
				break;
			case 'fetch-logs-from-db':
				// fetch logs only from the db.
				if ( ! isset( $_POST['params'] ) ) {
					break;
				}
				$id   = sanitize_text_field( $_POST['params']['id'] );
				$name = sanitize_text_field( $_POST['params']['name'] );
				$old  = sanitize_text_field( $_POST['params']['old'] );

				// do not make this === as we are sending a boolean.
				$done = ( $old && $old == 'true' ) || $this->is_command_done( $id, $name );

				$logs = $this->get_app_command_logs( $id, $name );
				// when the logs are unavailable.
				if ( is_wp_error( $logs ) ) {
					break;
				}
				$result = array(
					'logs' => nl2br( $logs ),
					'done' => $done,
				);
				break;
			default:
				$result = apply_filters( "wpcd_app_{$this->get_app_name()}_tab_action", $result, $action, $id );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'msg' => $result->get_error_code() ) );
		}

		wp_send_json_success(
			array(
				'msg'    => $msg,
				'result' => $result,
			)
		);
	}


	/**
	 * Validates the server and returns the server instance details.
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
	 * Validates the app and returns the server instance details.
	 *
	 * @param int $id id.
	 */
	public function get_app_instance_details( $id ) {
		if ( ! $id ) {
			return new \WP_Error( __( 'Invalid site ID', 'wpcd' ) );
		}

		// Verify the app type.
		$post = get_post( $id );
		if ( 'wpcd_app' !== $post->post_type || $this->get_app_name() !== get_post_meta( $id, 'app_type', true ) ) {
			return new \WP_Error( __( 'Invalid app', 'wpcd' ) );
		}

		// @TODO: replace the get_post_meta call below with this line for standardization purposes: $this->get_server_id_by_app_id( $id ) ;
		$server_id = get_post_meta( $id, 'parent_post_id', true );
		if ( ! $server_id ) {
			return new \WP_Error( __( 'No server found', 'wpcd' ) );
		}

		$instance = $this->get_instance_details( $server_id );

		return $instance;
	}


	/**
	 * This interprets the ssh result and reduces to a boolean value to indicate if a command succeeded or failed.
	 *
	 * @param string $result result.
	 * @param string $command command.
	 * @param string $action action.
	 */
	public function is_ssh_successful( $result, $command, $action = '' ) {
		switch ( $command ) {
			case 'disable_remove_site.txt':
				$return =
				( strpos( $result, ' has been ' ) !== false )
				||
				( strpos( $result, ' local backups have been ' ) !== false );
				break;
			case 'manage_https.txt':
				$return =
				( strpos( $result, 'SSL has been ' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false )
				||
				( strpos( $result, 'http2 is already enabled for domain' ) !== false )
				||
				( strpos( $result, 'http2 enabled for domain' ) !== false )
				||
				( strpos( $result, 'http2 disabled for domain' ) !== false )
				||
				( strpos( $result, 'http2 is already disabled for domain' ) !== false );
				break;
			case 'add_remove_sftp.txt':
				$return =
				( 'sftp-add-user' === $action && strpos( $result, 'Added SFTP user ' ) !== false )
				||
				( 'sftp-remove-user' === $action && strpos( $result, 'Removed SFTP user ' ) !== false )
				||
				( 'sftp-change-password' === $action && strpos( $result, 'Password changed for ' ) !== false )
				||
				( 'sftp-remove-key' === $action && strpos( $result, 'Public key removed for ' ) !== false )
				||
				( 'sftp-remove-password' === $action && strpos( $result, 'Password removed for ' ) !== false )
				||
				( 'sftp-set-key' === $action && strpos( $result, 'Public key set for ' ) !== false );
				break;
			case 'manage_site_users.txt':
				$return =
				( 'site-user-change-password' === $action && strpos( $result, 'Password changed for ' ) !== false )
				||
				( 'site-user-remove-key' === $action && strpos( $result, 'Public key removed for ' ) !== false )
				||
				( 'site-user-remove-password' === $action && strpos( $result, 'Password removed for ' ) !== false )
				||
				( 'site-user-set-key' === $action && strpos( $result, 'Public key set for ' ) !== false );
				break;
			case 'basic_auth_misc.txt':
				$return =
				( strpos( $result, 'Basic authentication disabled for' ) !== false )
				||
				( strpos( $result, 'Basic authentication enabled for' ) !== false );
				break;
			case 'basic_auth_wplogin_misc.txt':
				$return =
				( strpos( $result, 'Basic authentication disabled for' ) !== false )
				||
				( strpos( $result, 'Basic authentication enabled for' ) !== false );
				break;
			case 'toggle_https_misc.txt':
				$return =
				( strpos( $result, 'HTTPS redirect disabled for' ) !== false )
				||
				( strpos( $result, 'HTTPS redirect enabled for' ) !== false )
				||
				( strpos( $result, 'SSL redirection is already disabled for' ) !== false );
				break;
			case 'toggle_wp_linux_cron_misc.txt':
				$return =
				( strpos( $result, 'System cron enabled for' ) !== false )
				||
				( strpos( $result, 'System cron disabled for' ) !== false );
				break;
			case 'toggle_password_auth_misc.txt':
				$return =
				( strpos( $result, 'SSH password auth has been enabled for user' ) !== false )
				||
				( strpos( $result, 'SSH password auth has been disabled for user' ) !== false );
				break;
			case 'change_php_version_misc.txt':
				$return = strpos( $result, 'PHP version changed to' ) !== false;
				break;
			case 'change_php_option_misc.txt':
				$return = strpos( $result, 'Successfully changed PHP value' ) !== false;
				break;
			case 'toggle_php_active_misc.txt':
				$return =
				( strpos( $result, 'has been disabled' ) !== false )
				||
				( strpos( $result, 'already disabled' ) !== false )
				||
				( strpos( $result, 'has been enabled' ) !== false )
				||
				( strpos( $result, 'already enabled' ) !== false );
				break;
			case 'backup_restore.txt':
				$return =
				( strpos( $result, 'Backup has been completed!' ) !== false )
				||
				( strpos( $result, 'has been restored' ) !== false );
				break;
			case 'backup_restore_schedule.txt':
				$return =
				( strpos( $result, 'Backup job configured!' ) !== false )
				||
				( strpos( $result, 'Backup job removed!' ) !== false )
				||
				( strpos( $result, 'Full backup job configured!' ) !== false )
				||
				( strpos( $result, 'Full backup job removed!' ) !== false );
				break;
			case 'backup_restore_save_credentials.txt':
				$return = ( strpos( $result, 'AWS credentials have been saved' ) !== false );
				break;
			case 'change_domain_quick.txt':
				$return = ( strpos( $result, 'changed to' ) !== false );
				break;
			case 'change_domain_full.txt':
				$return =
				( strpos( $result, 'changed to' ) !== false )
				||
				( strpos( $result, 'Dry run completed' ) !== false );
				break;
			case 'clone_site.txt':
				$return = ( strpos( $result, 'has been cloned' ) !== false );
				break;
			case 'manage_phpmyadmin.txt':
				$return =
				( strpos( $result, 'phpMyAdmin installed for' ) !== false )
				||
				( strpos( $result, 'phpMyAdmin updated for' ) !== false )
				||
				( strpos( $result, 'Access credentials have been updated' ) !== false )
				||
				( strpos( $result, 'phpMyAdmin has been removed for' ) !== false );
				break;
			case '6g_firewall.txt':
				$return =
				( strpos( $result, 'Enabled 6G Firewall' ) !== false )
				||
				( strpos( $result, 'Disabled 6G Firewall' ) !== false );
				break;
			case '7g_firewall.txt':
				$return =
				( strpos( $result, 'Enabled 7G Firewall' ) !== false )
				||
				( strpos( $result, 'Disabled 7G Firewall' ) !== false );
				break;
			case 'manage_nginx_pagecache.txt':
				$return =
				( strpos( $result, 'WordPress Cache has been enabled' ) !== false )
				||
				( strpos( $result, 'WordPress Cache has been disabled' ) !== false )
				||
				( strpos( $result, 'WordPress Cache has been cleared' ) !== false );
				break;
			case 'toggle_wp_debug.txt':
				$return =
				( strpos( $result, 'WordPress debug flags enabled' ) !== false )
				||
				( strpos( $result, 'WordPress debug flags disabled' ) !== false );
				break;
			case 'multisite.txt':
				$return =
				( strpos( $result, 'WordPress Multisite has been enabled for' ) !== false )
				||
				( strpos( $result, 'configuration has been set up' ) !== false )
				||
				( strpos( $result, 'has been deregistered' ) !== false )
				||
				( strpos( $result, 'SSL enabled for' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false )
				||
				( strpos( $result, 'HTTPS disabled for' ) !== false );
				break;
			case 'multisite_wildcard_ssl.txt':
				$return =
				( strpos( $result, 'Wildcard HTTPS has been configured for' ) !== false )
				||
				( strpos( $result, 'HTTPS disabled for' ) !== false );
				break;
			case 'site_sync_origin_setup.txt':
				$return =
				( strpos( $result, 'Authentication is already set up' ) !== false )
				||
				( strpos( $result, 'Authentication has been set up' ) !== false );
				break;
			case 'site_sync_destination_setup.txt':
				$return =
				( strpos( $result, 'Setup has been completed' ) !== false );
				break;
			case 'site_sync.txt':
				$return =
				( strpos( $result, 'Site Sync Completed Successfully' ) !== false )
				||
				( strpos( $result, 'Site sync has been scheduled' ) !== false );
				break;
			case 'site_sync_unschedule.txt':
				$return =
				( strpos( $result, 'Site sync job removed' ) !== false )
				||
				( strpos( $result, 'Schedule Site Sync For This Site Disabled' ) !== false )
				||
				( strpos( $result, 'No such job configured with given domain and destination ip' ) !== false )
				||
				( strpos( $result, 'No syncing job is configured as cron' ) !== false );
				break;
			case 'enable_disable_php_functions.txt':
				$return =
				( strpos( $result, 'has been enabled' ) !== false )
				||
				( strpos( $result, 'has been disabled' ) !== false );
				break;
			case 'reset_site_permissions.txt':
				$return =
				( strpos( $result, 'Permissions have been reset for' ) !== false );
				break;
			case 'server_redirect.txt':
				// Even though this name has "server" in it, it's mostly a site-level item.
				$return =
				( strpos( $result, 'Redirect rule added' ) !== false )
				||
				( strpos( $result, 'Redirect rule has been removed' ) !== false )
				||
				( strpos( $result, 'All Rewrite rules have been removed' ) !== false );
				break;
			case 'nginx_options.txt':
				// This one is a mix of server and site level items - mostly site level items.
				$return =
				( strpos( $result, 'already enabled' ) !== false )
				||
				( strpos( $result, 'already disabled' ) !== false )
				||
				( strpos( $result, 'Success!' ) !== false );
				break;
			case 'php_workers.txt':
				$return =
				( strpos( $result, 'PHP Workers Updated' ) !== false );
				break;
			case 'fail2ban_site.txt':
				// There is also a fail2ban section in the servers section below!
				$return =
				( strpos( $result, 'Fail2ban plugin has been installed for' ) !== false )
				||
				( strpos( $result, 'Fail2ban Plugin has been removed from' ) !== false );
				break;
			case 'reliable_updates.txt':
				$return =
				( strpos( $result, 'Updates are complete' ) !== false );
				break;
			case 'copy_site_to_existing_site.txt':
				$return =
				( strpos( $result, 'Copy to existing site is complete' ) !== false );
				break;
			case 'change_file_upload_size.txt':
				$return =
				( strpos( $result, 'File upload limits have been changed for' ) !== false );
				break;
			case 'update_wp_site_option.txt':
				$return =
				( strpos( $result, 'Updated Option Value' ) !== false );
				break;
			case 'change_wp_credentials.txt':
				$return =
				( strpos( $result, 'Updated credentials for user' ) !== false );
				break;
			case 'add_wp_user.txt':
				$return =
				( strpos( $result, 'Added user' ) !== false );
				break;
			case 'update_wp_config_option.txt':
				$return =
				( strpos( $result, 'Updated WPConfig Option Value' ) !== false );
				break;

			/**************************************************************
			* The items below this are SERVER items, not APP items        *
			*/
			case 'backup_restore_delete_and_prune_server.txt':
				$return =
				( strpos( $result, 'All backups have been deleted' ) !== false )
				||
				( strpos( $result, 'All backups older than' ) !== false );
				break;
			case 'install_memcached.txt':
				$return =
				( strpos( $result, 'Memcached has been installed' ) !== false )
				||
				( strpos( $result, 'Memcached is already installed' ) !== false );
				break;
			case 'manage_memcached.txt':
				$return =
				( strpos( $result, 'Memcached server has been restarted' ) !== false )
				||
				( strpos( $result, 'Memcached cache has been cleared' ) !== false )
				||
				( strpos( $result, 'Memcached has been enabled' ) !== false )
				||
				( strpos( $result, 'Memcached has been disabled' ) !== false )
				||
				( strpos( $result, 'Memcached has been removed from the system' ) !== false );
				break;
			case 'install_redis.txt':
				$return =
				( strpos( $result, 'Redis has been installed' ) !== false )
				||
				( strpos( $result, 'Redis is already installed' ) !== false );
				break;
			case 'manage_redis.txt':
				$return =
				( strpos( $result, 'Redis server has been restarted' ) !== false )
				||
				( strpos( $result, 'Redis cache has been cleared' ) !== false )
				||
				( strpos( $result, 'Redis has been enabled' ) !== false )
				||
				( strpos( $result, 'Redis has been disabled' ) !== false )
				||
				( strpos( $result, 'Redis has been removed from the system' ) !== false );
				break;
			case 'add_wp_admin.txt':
				$return =
				( strpos( $result, 'added as an administrator to' ) !== false );
				break;
			case 'restart_php_service.txt':
				$return =
				( strpos( $result, 'PHP service has restarted for version' ) !== false );
				break;
			case 'toggle_edd_nginx_rules.txt':
				$return =
				( strpos( $result, 'Easy Digital Downloads NGINX directives enabled for' ) !== false )
				||
				( strpos( $result, 'Easy Digital Downloads NGINX directives disabled for' ) !== false );
				break;
			case 'email_gateway.txt':
				$return =
				( strpos( $result, 'The email gateway has now been configured' ) !== false )
				||
				( strpos( $result, 'Test email has been sent' ) !== false )
				||
				( strpos( $result, 'Email gateway successfully removed' ) !== false );
				break;
			case 'run_upgrades_290.txt':
			case 'run_upgrades_460.txt':
			case 'run_upgrades_461.txt':
			case 'run_upgrades_462.txt':
				$return =
				( strpos( $result, 'upgrade completed' ) !== false )
				||
				( strpos( $result, 'Upgrade Completed' ) !== false )
				||
				( strpos( $result, '7G Firewall is already installed' ) !== false );
				break;
			case 'run_upgrade_install_php_81.txt':
				$return = ( strpos( $result, 'PHP 8.1 has been installed' ) !== false );
				break;
			case 'run_upgrade_7g.txt':
				$return = ( strpos( $result, 'The 7G Firewall has been upgraded' ) !== false );
				break;
			case 'run_upgrade_wpcli.txt':
				$return = ( strpos( $result, 'WPCLI has been upgraded' ) !== false );
				break;
			case 'run_upgrade_install_php_intl.txt':
				$return = ( strpos( $result, 'PHP intl module has been installed' ) !== false );
				break;
			case 'server_status_callback.txt':
				$return =
				( strpos( $result, 'Server status job configured' ) !== false )
				||
				( strpos( $result, 'Server status job removed' ) !== false )
				||
				( strpos( $result, 'Server status job scheduled successfully' ) !== false )
				||
				( strpos( $result, 'Server status job executed successfully' ) !== false );
				break;
			case 'maldet.txt':
				$return =
				( strpos( $result, 'Maldet has been installed' ) !== false )
				||
				( strpos( $result, 'LMD is already installed!' ) !== false )
				||
				( strpos( $result, 'clamscan and LMD uninstalled' ) !== false )
				||
				( strpos( $result, 'Clamscan database has been updated' ) !== false )
				||
				( strpos( $result, 'Malware Detection has been updated' ) !== false )
				||
				( strpos( $result, 'Scanning has been completed' ) !== false )
				||
				( strpos( $result, 'Cron has been disabled' ) !== false )
				||
				( strpos( $result, 'Cron has been enabled' ) !== false )
				||
				( strpos( $result, 'Malware data has been purged' ) !== false )
				||
				( strpos( $result, 'Malware services have been restarted' ) !== false );
				break;
			case 'server_restart_callback.txt':
				$return =
				( strpos( $result, 'Server restart callback job configured' ) !== false )
				||
				( strpos( $result, 'Server restart callback job removed' ) !== false )
				||
				( strpos( $result, 'Server restart callback job executed successfully' ) !== false );
				break;
			case 'monitorix.txt':
				$return =
				( strpos( $result, 'Monitorix has been installed' ) !== false )
				||
				( strpos( $result, 'Monitorix has been removed' ) !== false )
				||
				( strpos( $result, 'Monitorix has been updated' ) !== false )
				||
				( strpos( $result, 'has been enabled for' ) !== false )
				||
				( strpos( $result, 'has been disabled for' ) !== false )
				||
				( strpos( $result, 'SSL has been enabled for' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false )
				||
				( strpos( $result, 'SSL has been disabled for' ) !== false );
				break;
			case 'netdata_install.txt':
				$return =
				( strpos( $result, 'Netdata has been installed' ) !== false )
				||
				( strpos( $result, 'Netdata is already installed' ) !== false );
				break;
			case 'netdata.txt':
				$return =
				( strpos( $result, 'Netdata has been installed' ) !== false )
				||
				( strpos( $result, 'Netdata has been removed' ) !== false )
				||
				( strpos( $result, 'Netdata has been updated' ) !== false )
				||
				( strpos( $result, 'Basic Auth has been enabled for' ) !== false )
				||
				( strpos( $result, 'Basic Auth already enabled' ) !== false )
				||
				( strpos( $result, 'Basic Auth has been disabled' ) !== false )
				||
				( strpos( $result, 'Basic Auth has been updated' ) !== false )
				||
				( strpos( $result, 'SSL has been enabled for' ) !== false )
				||
				( strpos( $result, 'SSL was not enabled for netdata so nothing to disable' ) !== false )
				||
				( strpos( $result, 'SSL has been disabled for' ) !== false )
				||
				( strpos( $result, 'Registry already enabled ' ) !== false )
				||
				( strpos( $result, 'Registry enabled to' ) !== false )
				||
				( strpos( $result, 'Registry already pointed to ' ) !== false )
				||
				( strpos( $result, 'Registry pointed to' ) !== false );
				break;
			case 'monit.txt':
				$return =
				( strpos( $result, 'Monit has been installed' ) !== false )
				||
				( strpos( $result, 'Monit has been removed' ) !== false )
				||
				( strpos( $result, 'Monit has been updated' ) !== false )
				||
				( strpos( $result, 'has been enabled' ) !== false )
				||
				( strpos( $result, 'has been disabled' ) !== false )
				||
				( strpos( $result, 'SSL has been enabled for' ) !== false )
				||
				( strpos( $result, 'SSL has been disabled for' ) !== false )
				||
				( strpos( $result, 'SSL is already disabled for' ) !== false )
				||
				( strpos( $result, 'Monit email settings updated' ) !== false )
				||
				( strpos( $result, 'All monitors enabled' ) !== false )
				||
				( strpos( $result, 'All monitors disabled' ) !== false )
				||
				( strpos( $result, 'Callbacks have been enabled' ) !== false )
				||
				( strpos( $result, 'Callbacks have been disabled' ) !== false )
				||
				( strpos( $result, 'Monit has been activated' ) !== false )
				||
				( strpos( $result, 'Monit has been temporarily deactivated' ) !== false );
				break;
			case 'schedule_server_reboot.txt':
				$return =
				( strpos( $result, 'The server reboot has been scheduled' ) !== false );
				break;
			case 'backup_config_files.txt':
				$return =
				( strpos( $result, 'Backup cron job has been configured' ) !== false )
				||
				( strpos( $result, 'Cron for conf backup has been removed' ) !== false )
				||
				( strpos( $result, 'Backup files have been removed' ) !== false );
				break;
			case 'goaccess.txt':
				$return =
				( strpos( $result, 'goaccess is already installed' ) !== false )
				||
				( strpos( $result, 'Goaccess has been installed' ) !== false )
				||
				( strpos( $result, 'goaccess has been removed' ) !== false )
				||
				( strpos( $result, 'Goaccess has been disabled' ) !== false )
				||
				( strpos( $result, 'goaccess has been enabled' ) !== false )
				||
				( strpos( $result, 'SSL Already Enabled' ) !== false )
				||
				( strpos( $result, 'SSL has been enabled for' ) !== false )
				||
				( strpos( $result, 'SSL has been disabled' ) !== false )
				||
				( strpos( $result, 'SSL Not enabled for' ) !== false )
				||
				( strpos( $result, 'Basic Auth already enabled' ) !== false )
				||
				( strpos( $result, 'Basic auth has been enabled' ) !== false )
				||
				( strpos( $result, 'Basic Auth already disabled' ) !== false )
				||
				( strpos( $result, 'Auth has been updated' ) !== false )
				||
				( strpos( $result, 'whitelisted' ) !== false )
				||
				( strpos( $result, 'removed from whitelist' ) !== false )
				||
				( strpos( $result, 'is not whitelisted' ) !== false )
				||
				( strpos( $result, 'All whiteslited ips has been removed' ) !== false );
				break;
			case 'fail2ban.txt':
				$return =
				( strpos( $result, 'Fail2ban installation complete' ) !== false )
				||
				( strpos( $result, 'fail2ban has been removed' ) !== false )
				||
				( strpos( $result, 'fail2ban has been purged' ) !== false )
				||
				( strpos( $result, 'Fail2ban parameters have been successfully updated' ) !== false )
				||
				( strpos( $result, 'Protocol has been added' ) !== false )
				||
				( strpos( $result, 'The specified protocol has been removed' ) !== false )
				||
				( strpos( $result, 'The protocol was not enabled and therefore could not be removed' ) !== false )
				||
				( strpos( $result, 'Fail2ban parameters have been successfully updated' ) !== false )
				||
				( strpos( $result, 'Fail2ban software has been successfully updated' ) !== false )
				||
				( strpos( $result, 'has been unbanned' ) !== false )
				||
				( strpos( $result, 'has been banned' ) !== false );
				break;
			case 'server_update.txt':
				$return =
				( strpos( $result, 'Updates have been scheduled to run via cron' ) !== false )
				||
				( strpos( $result, 'Security Updates have been scheduled to run via cron' ) !== false );
				break;

			/**************************************************************
			* The items below this are SERVER SYNC items, not APP items   *
			*/
			case 'server_sync_origin_setup.txt':
				$return =
				( strpos( $result, 'Setup has been finished for this server. But you are not done yet' ) !== false );
				break;
			case 'server_sync_destination_setup.txt':
				$return =
				( strpos( $result, 'Setup has been completed!' ) !== false );
				break;
			case 'server_sync_manage.txt':
				$return =
				( strpos( $result, 'The syncronization job has been started' ) !== false )
				||
				( strpos( $result, 'The scheduled sync job has been disabled' ) !== false )
				||
				( strpos( $result, 'The scheduled sync job has been re-enabled' ) !== false )
				||
				( strpos( $result, 'The sync service has been permanently removed' ) !== false );
				break;

		}

		/* Sometimes we get a false positive so check for some things that might indicate a generic failure. */
		if ( $return ) {
			$return = $return
				&&
				( strpos( $result, 'dpkg was interrupted, you must manually run' ) === false )
				&&
				( strpos( $result, 'Installation of required packages failed' ) === false );
		}
		if ( $return && ( false === boolval( wpcd_get_option( 'wordpress_app_ignore_journalctl_xe' ) ) ) ) {
			$return = $return
				&&
				( strpos( $result, 'journalctl -xe' ) === false );
		}

		return apply_filters( 'wpcd_is_ssh_successful', $return, $result, $command, $action, $this->get_app_name() );

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
	 * @param array  $additional         An array of any additional data we might need. It can use the following keys (non-exhaustive list):
	 *    command: The command to use (a script may have multiple commands)
	 *    domain: The domain of the site
	 *    user: The user to action.
	 *    email: The email to use.
	 *    public_key: The path to the public key
	 *    password: The password of the user.
	 */
	public function script_placeholders( $array, $script_name, $script_version, $instance, $command, $additional ) {
		$new_array    = array();
		$common_array = array(
			'SCRIPT_COMMON_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/9999-common-functions.txt',
			'SCRIPT_COMMON_NAME' => '9999-common-functions.sh',
		);
		switch ( $script_name ) {
			case 'after-server-create-run-commands.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'           => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/01-prepare_server.txt',
						'SCRIPT_NAME'          => '01-prepare_server.sh',
						'SCRIPT_LOGS'          => "{$this->get_app_name()}_prepare_server",
						'CALLBACK_URL'         => $this->get_command_url( $instance['post_id'], 'prepare_server', 'completed' ),
						'LONG_COMMAND_TIMEOUT' => wpcd_get_long_running_command_timeout(),
					),
					$common_array,
					$additional
				);
				break;
			case 'install_wordpress_site.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/02-install_wordpress_site.txt',
						'SCRIPT_NAME'  => '02-install_wordpress_site.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['post_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'disable_remove_site.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/03-disable-remove-site.txt',
						'SCRIPT_NAME' => '03-disable-remove-site.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_https.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/04-manage_https.txt',
						'SCRIPT_NAME' => '04-manage_https.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'add_remove_sftp.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/06-add_remove_sftp.txt',
						'SCRIPT_NAME' => '06-add_remove_sftp.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_site_users.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/11-manage_site_users.txt',
						'SCRIPT_NAME' => '11-manage_site_users.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'backup_restore.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME'  => '08-backup.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'backup_restore_delete_and_prune.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME'  => '08-backup.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'backup_restore_schedule.txt':
			case 'backup_restore_save_credentials.txt':
			case 'backup_restore_refresh_backup_list.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME' => '08-backup.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'basic_auth_misc.txt':
			case 'basic_auth_wplogin_misc.txt':
			case 'toggle_https_misc.txt':
			case 'toggle_wp_linux_cron_misc.txt':
			case 'change_php_version_misc.txt':
			case 'get_diskspace_used_misc.txt':
			case 'change_php_option_misc.txt':
			case 'toggle_wp_debug.txt':
			case 'restart_php_service.txt':
			case 'toggle_password_auth_misc.txt':
			case 'toggle_php_active_misc.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/10-misc.txt',
						'SCRIPT_NAME' => '10-misc.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'change_domain_quick.txt':
			case 'change_domain_full.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/05-change_domain.txt',
						'SCRIPT_NAME'  => '05-change_domain.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'search_and_replace_db.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/31-search_and_replace_db.txt',
						'SCRIPT_NAME'  => '31-search_and_replace_db.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'clone_site.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/09-clone_site.txt',
						'SCRIPT_NAME'  => '09-clone_site.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_phpmyadmin.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/07-phpmyadmin.txt',
						'SCRIPT_NAME'  => '07-phpmyadmin.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case '6g_firewall.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/15-6g_firewall.txt',
						'SCRIPT_NAME' => '15-6g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case '7g_firewall.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/40-7g_firewall.txt',
						'SCRIPT_NAME' => '40-7g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_nginx_pagecache.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/18-wp_cache.txt',
						'SCRIPT_NAME' => '18-wp_cache.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'add_wp_admin.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/10-misc.txt',
						'SCRIPT_NAME' => '10-misc.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'toggle_edd_nginx_rules.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/17-plugin_tweaks.txt',
						'SCRIPT_NAME' => '17-plugin_tweaks.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'multisite.txt':
			case 'multisite_wildcard_ssl.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/13-multisite.txt',
						'SCRIPT_NAME' => '13-multisite.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'site_sync_origin_setup.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/81-origin-site-sync.txt',
						'SCRIPT_NAME' => '81-origin-site-sync.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'site_sync_destination_setup.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/82-destination-site-sync.txt',
						'SCRIPT_NAME' => '82-destination-site-sync.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'site_sync.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/81-origin-site-sync.txt',
						'SCRIPT_NAME'  => '81-origin-site-sync.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'site_sync_unschedule.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/81-origin-site-sync.txt',
						'SCRIPT_NAME' => '81-origin-site-sync.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'enable_disable_php_functions.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/22-enable_disable_php_functions.txt',
						'SCRIPT_NAME' => '22-enable_disable_php_functions.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'reset_site_permissions.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/10-misc.txt',
						'SCRIPT_NAME' => '10-misc.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_redirect.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/33-server_redirect.txt',
						'SCRIPT_NAME' => '33-server_redirect.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'nginx_options.txt':
				// This one is a mix of server and site level items - mostly site level items.
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/34-nginx_options.txt',
						'SCRIPT_NAME' => '34-nginx_options.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'php_workers.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/39-php_workers.txt',
						'SCRIPT_NAME' => '39-php_workers.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'fail2ban_site.txt':
				// There is also a fail2ban section in the servers section below!
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/23-fail2ban.txt',
						'SCRIPT_NAME' => '23-fail2ban.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'reliable_updates.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'         => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/50-reliable_updates.txt',
						'SCRIPT_NAME'        => '50-reliable_updates.sh',
						'SCRIPT_LOGS'        => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL'       => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
						'SCRIPT_URL_BACKUP'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME_BACKUP' => '08-backup.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'copy_site_to_existing_site.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'         => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/25-copy_site_to_existing_site.txt',
						'SCRIPT_NAME'        => '25-copy_site_to_existing_site.sh',
						'SCRIPT_LOGS'        => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL'       => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
						'SCRIPT_URL_BACKUP'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME_BACKUP' => '08-backup.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'change_file_upload_size.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/10-misc.txt',
						'SCRIPT_NAME' => '10-misc.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'update_wp_site_option.txt':
			case 'change_wp_credentials.txt':
			case 'add_wp_user.txt':
			case 'update_wp_config_option.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/30-wp_site_things.txt',
						'SCRIPT_NAME' => '30-wp_site_things.sh',
					),
					$common_array,
					$additional
				);
				break;

			/*********************************************************
			* The items below this are SERVER items, not APP items   *
			*/
			case 'backup_restore_delete_and_prune_server.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/08-backup.txt',
						'SCRIPT_NAME' => '08-backup.sh',
					),
					$common_array,
					$additional
				);
				break;

			case 'install_memcached.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/16-memcached.txt',
						'SCRIPT_NAME'  => '16-memcached.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['server_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_memcached.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/16-memcached.txt',
						'SCRIPT_NAME' => '16-memcached.sh',
					),
					$common_array,
					$additional
				);
				break;

			case 'install_redis.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/12-redis.txt',
						'SCRIPT_NAME'  => '12-redis.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['server_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'manage_redis.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/12-redis.txt',
						'SCRIPT_NAME' => '12-redis.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'email_gateway.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/14-mail.txt',
						'SCRIPT_NAME' => '14-mail.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'monitorix.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/21-monitorix.txt',
						'SCRIPT_NAME' => '21-monitorix.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'netdata_install.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/43-netdata.txt',
						'SCRIPT_NAME'  => '43-netdata.sh',
						'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
						'CALLBACK_URL' => $this->get_command_url( $instance['server_id'], $command_name, 'completed' ),
					),
					$common_array,
					$additional
				);
				break;
			case 'netdata.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/43-netdata.txt',
						'SCRIPT_NAME' => '43-netdata.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'monit.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/20-monit.txt',
						'SCRIPT_NAME' => '20-monit.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_290.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1010-upgrade_290_secure_php.txt',
						'SCRIPT_NAME' => '1010-upgrade_290_secure_php.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_460.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1020-upgrade_460_performance.txt',
						'SCRIPT_NAME' => '1020-upgrade_460_performance.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_461.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1030-upgrade_461_certbot_snap.txt',
						'SCRIPT_NAME' => '1030-upgrade_461_certbot_snap.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrades_462.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1040-upgrade_462_install_7g_firewall.txt',
						'SCRIPT_NAME' => '1040-upgrade_462_install_7g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_install_php_81.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1050-upgrade_install_php_81.txt',
						'SCRIPT_NAME' => '1050-upgrade_install_php_81.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_7g.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1060-upgrade_7g_firewall.txt',
						'SCRIPT_NAME' => '1060-upgrade_7g_firewall.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_wpcli.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1070-upgrade_wp_cli.txt',
						'SCRIPT_NAME' => '1070-upgrade_wp_cli.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'run_upgrade_install_php_intl.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/1080-upgrade_install_php_intl_module.txt',
						'SCRIPT_NAME' => '1080-upgrade_install_php_intl_module.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_status_callback.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/24-server_status.txt',
						'SCRIPT_NAME' => '24-server_status.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'maldet.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/26-lmd_clamav.txt',
						'SCRIPT_NAME' => '26-lmd_clamav.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_restart_callback.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/28-restart_callback.txt',
						'SCRIPT_NAME' => '28-restart_callback.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'schedule_server_reboot.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/36-schedule-server-reboot.txt',
						'SCRIPT_NAME' => '36-schedule-server-reboot.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'backup_config_files.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/37-backup-configuration.txt',
						'SCRIPT_NAME' => '37-backup-configuration.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'goaccess.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/27-goaccess.txt',
						'SCRIPT_NAME' => '27-goaccess.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'fail2ban.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/23-fail2ban.txt',
						'SCRIPT_NAME' => '23-fail2ban.sh',
					),
					$common_array,
					$additional
				);
				break;
			case 'server_update.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/29-server_update.txt',
						'SCRIPT_NAME' => '29-server_update.sh',
					),
					$common_array,
					$additional
				);
				break;

			/**************************************************************
			* The items below this are SERVER SYNC items, not APP items   *
			*/
			case 'server_sync_origin_setup.txt':
				$command_name = $additional['command'];
				$new_array    = array_merge(
					array(
						'SCRIPT_URL'   => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/71-origin.txt',
						'SCRIPT_NAME'  => '71-origin.sh',
						'SCRIPT_URL2'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/wp-sync',
						'SCRIPT_NAME2' => 'wp-sync',
					),
					$additional
				);
				break;
			case 'server_sync_destination_setup.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/72-destination.txt',
						'SCRIPT_NAME' => '72-destination.sh',
					),
					$additional
				);
				break;
			case 'server_sync_manage.txt':
				$new_array = array_merge(
					array(
						'SCRIPT_URL'  => trailingslashit( wpcd_url ) . $this->get_scripts_folder_relative() . $script_version . '/raw/71-origin.txt',
						'SCRIPT_NAME' => '71-origin.sh',
					),
					$additional
				);
				break;
		}

		$new_array = apply_filters( 'wpcd_wpapp_replace_script_tokens', $new_array, $array, $script_name, $script_version, $instance, $command, $additional );

		return array_merge( $array, $new_array );
	}


	/**
	 * Return create server popup view
	 * 
	 * @param string $view 
	 * 
	 * @return void|string
	 */
	public function ajax_server_handle_create_popup( $view = 'admin' ) {
		
		/* Check permissions */
		if ( ! current_user_can( 'wpcd_provision_servers' ) ) {
			$invalid_msg = __( 'You don\'t have access to provision a server.', 'wpcd' );
			if( $view == 'public' ) {
				echo $invalid_msg;
			} else {
				echo wp_send_json_error( array( 'msg' => $invalid_msg ) );
			}
			return;
		}

		/* Get list of directories within specified directory */
		$dir_path        = wpcd_path . 'includes/core/apps/wordpress-app/scripts';
		$dir_list        = wpcd_get_dir_list( $dir_path );
		$scripts_version = wpcd_get_option( "{$this->get_app_name()}_script_version" );
		if ( empty( $scripts_version ) ) {
			$scripts_version = 'v1';
		}

		/* Get list of regions */
		$provider_regions = $this->add_provider_support();
		$provider_regions = apply_filters( "wpcd_{$this->get_app_name()}_provider_regions_create_server_popup", $provider_regions );

		/* Get the list of providers - we'll need it in the popup area */
		$providers = $this->get_active_providers();
		$providers = apply_filters( "wpcd_{$this->get_app_name()}_providers_create_server_popup", $providers );

		/* Get list of OSes */
		$oslist = WPCD()->get_os_list();
		$oslist = apply_filters( "wpcd_{$this->get_app_name()}_oslist_create_server_popup", $oslist );

		/* Get list of webservers */
		$webserver_list = WPCD()->get_webserver_list();
		$webserver_list = apply_filters( "wpcd_{$this->get_app_name()}_webserver_list_create_server_popup", $webserver_list );

		/* Include the popup file */
		include apply_filters( "wpcd_{$this->get_app_name()}_create_popup", wpcd_path . 'includes/core/apps/wordpress-app/templates/create-popup.php' );
	}
	
	/**
	 * Single entry point for all ajax actions for server.
	 */
	public function ajax_server() {

		check_ajax_referer( 'wpcd-server', 'nonce' );

		$action = sanitize_text_field( $_REQUEST['_action'] );

		$result = null;
		$msg    = null;

		switch ( $action ) {
			/* Show the popup that asks the admin for app details when installing the wpapp */
			case 'install-app-popup':
				/* Which server are we installing on?*/
				$server_id = filter_input( INPUT_GET, 'server_id', FILTER_VALIDATE_INT );
				$id        = filter_input( INPUT_GET, 'server_id', FILTER_VALIDATE_INT );

				/* Verify that the user is allowed to add a site. */
				$user_id     = get_current_user_id();
				$post_author = get_post( $server_id )->post_author;
				if ( ! wpcd_user_can( $user_id, 'add_app_wpapp', $server_id ) && $post_author != $user_id ) {
					$msg = __( 'You don\'t have permission to install WordPress on this server.', 'wpcd' );
					break;
				}

				/* Get some data about the server so that the popup template can use it*/
				$ipv4            = WPCD_SERVER()->get_ipv4_address( $server_id );
				$server_name     = WPCD_SERVER()->get_server_name( $server_id );
				$server_provider = WPCD_SERVER()->get_server_provider( $server_id );
				$temp_sub_domain = WPCD_DNS()->get_full_temp_domain();

				/* Show the popup template*/
				include apply_filters( "wpcd_{$this->get_app_name()}_install_app_popup", wpcd_path . 'includes/core/apps/wordpress-app/templates/install-app-popup.php' );

				/* And get out of here */
				wp_die();
				break;

			/* Show the popup that asks the admin for server details when installing/deploying a new server */
			case 'create-popup':
				$this->ajax_server_handle_create_popup();
				
				/* And exit */
 				wp_die();
				break;

			/* Just show the log console template */
			case 'log-console':
				include wpcd_path . 'includes/core/apps/wordpress-app/templates/show-log-console.php';
				wp_die();
				break;

			/* Called when the admin pushes the install button on the server popup to create/deploy a new server */
			case 'create':
				/* Check permissions */
				if ( ! current_user_can( 'wpcd_provision_servers' ) ) {
					$invalid_msg = __( 'You don\'t have access to provision a server.', 'wpcd' );
					wp_send_json_error( array( 'msg' => $invalid_msg ) );
					break;
				}

				// Get arguments from form.
				$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );

				// Extract and sanitize some data from the args array.
				$webserver = '';
				if ( ! empty( $args['webserver-type'] ) ) {
					$webserver = sanitize_text_field( $args['webserver-type'] );
				}
				if ( empty( $webserver ) ) {
					$webserver = 'nginx';
				}
				$os       = sanitize_text_field( $args['os'] );
				$name     = sanitize_text_field( $args['name'] );
				$provider = sanitize_text_field( $args['provider'] );
				if ( isset( $args['invalid_message'] ) ) {
					$invalid_msg = sanitize_text_field( $args['invalid_message'] );
				} else {
					$invalid_msg = __( 'If you are seeing this message, something went very wrong at the start of the create a new server process. However, we are unable to be more specific at this time about its root cause.', 'wpcd' );
				}

				// Validate the server name and return right away if invalid format
				$name_pattern = '/^[a-z0-9-_]+$/i';

				if ( false !== strpos( mb_strtolower( $args['provider'] ), 'hivelocity' ) ) {
					// special check for hivelocity server names - periods are allowed because their names must be in xxx.yyy.zzz format
					// @TODO: We need to have a hook for validation and move this check into the HIVELOCITY plugin.
					$name_pattern = '/^[a-z0-9-_.]+$/i';
				}

				if ( ! empty( $name ) && ! preg_match( $name_pattern, $name ) ) {
					wp_send_json_error( array( 'msg' => $invalid_msg ) );
					break;
				}
				// End validate the server name.

				/* Validate the os */
				$oslist = WPCD()->get_os_list();
				if ( ! $oslist[ $os ] ) {
					wp_send_json_error( array( 'msg' => __( 'Invalid OS - security issue?', 'wpcd' ) ) );
					break;
				}

				/* Validate the webserver type */
				if ( ! empty( $webserver ) ) {
					$webserver_list = WPCD()->get_webserver_list();
					if ( ! $webserver_list[ $webserver ] ) {
						wp_send_json_error( array( 'msg' => __( 'Invalid Webserver type - security issue?', 'wpcd' ) ) );
						break;
					}
				}

				/* Everything ok so far - create the server instance. */
				$result = $this->create_instance();

				/* Check and handle errors if server instance was not created correctly. If it was created correctly, set the data that is to be sent back to front-end jscript. */
				if ( ( ! is_wp_error( $result ) ) && $result ) {
					$text_msg          = apply_filters( "wpcd_{$this->get_app_name()}_pre_create_server_message", __( 'Waiting for the server to startup...<br />We recommend that you do NOT exit this screen until you see a pop-up indicating that the installation is complete.<br />You can expect this process to take at least 10 minutes or more. Your first feedback message after this can take up to 5 minutes so please be patient.<br />If you do exit this screen, the process will continue in the background - you can check the COMMAND & SSH logs for progress reports.', 'wpcd' ), $args );
					$text_msg          = apply_filters( "wpcd_{$this->get_app_name()}_{$provider}_pre_create_server_message", $text_msg, $args );
					$msg               = '<span class="wpcd_pre_install_text">' . $text_msg . '</span>';
					$msg              .= wpcd_get_loading_svg_code(); // add in the loading icon.
					$result['command'] = array( 'name' => 'prepare_server' );
				} else {
					$err_msg = '';
					if ( is_wp_error( $result ) ) {
						$err_msg = $result->get_error_message();
					}
					$msg = sprintf( __( 'Unfortunately we could not start deploying this server - most likely because of an error from the provider api. <br />Please contact support or you can check the COMMAND & SSH logs for errors.<br />  You can close this screen and then retry the operation.<br />%s', 'wpcd' ), $err_msg );
					$msg = apply_filters( 'wpcd_wordpress-app-server_deployment_error', $msg );
					$msg = '<span class="wpcd_pre_install_text_error">' . $msg . '</span>';
				}

				break;

			/* Collect one or more log files based on parameters passed in $_POST */
			case 'logs':
				if ( ! isset( $_POST['params'] ) ) {
					break;
				}
				$id   = sanitize_text_field( $_POST['params']['id'] );
				$name = sanitize_text_field( $_POST['params']['name'] );
				$old  = sanitize_text_field( $_POST['params']['old'] );
				$done = false;
				// do not make this === as we are sending a boolean.
				if ( $old && $old == 'true' ) {
					$logs = $this->get_old_command_logs( $id, $name );
					$done = true;
				} else {
					$logs = $this->get_command_logs( $id, $name );
					// when the logs are unavailable.
					if ( is_wp_error( $logs ) ) {
						break;
					}
					if ( $this->is_command_done( $id, $name ) ) {
						$done = true;
					}
				}
				$result = array(
					'logs' => nl2br( $logs ),
					'done' => $done,
				);
				break;

			/* Called when the installation of an app is about to start - after the user has pushed the install button */
			case 'install-app':
				// Verify that the user is allowed to add a site.
				$server_id   = sanitize_text_field( $_REQUEST['id'] );
				$user_id     = get_current_user_id();
				$post_author = get_post( $server_id )->post_author;
				if ( ! wpcd_user_can( $user_id, 'add_app_wpapp', $server_id ) && $post_author != $user_id ) {
					$msg = __( 'You don\'t have permission to install WordPress on this server.', 'wpcd' );
					break;
				}

				// Grab some data, so we can validate it...
				$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );

				// Make sure that we get an unsanitized version of the array.
				// We need this to get the password field.
				// Text sanitation will remove certain special chars which are valid for password fields.
				// So we cannot use the sanitized password field.
				$args_unsanitized = wp_parse_args( wp_unslash( $_REQUEST['params'] ) );

				// Make sure we have data for all fields. Do not do a check for wp_locale though because, if it's blank, we'll default it 'en_US' later when the installation starts.
				if ( empty( $args['wp_domain'] ) || empty( $args['wp_user'] ) || empty( $args_unsanitized['wp_password'] ) || empty( $args['wp_email'] ) || empty( $args['wp_version'] ) || empty( $args['wpcd_app_type'] ) ) {
					$msg = __( 'Please fill out all the requested data - we cannot create a site without all the requested data!  Please close this screen and try again.', 'wpcd' );
					break;
				}

				// Prepare for the installation (which will escape the chars in the unsanitized password field).
				$result = $this->install_wp_validate();

				if ( ! is_wp_error( $result ) ) {
					$msg               = '<span class="wpcd_pre_install_text">' . __( 'Waiting for the installation to begin...<br />Do not exit this screen until you see a popup indicating that the installation is complete.', 'wpcd' ) . '</span>';
					$msg              .= wpcd_get_loading_svg_code(); // add in the loading icon.
					$result['post_id'] = sanitize_text_field( $_REQUEST['id'] );
					$result['command'] = array( 'name' => $result['command'] );
				}
				break;

			/* Called when the admin clicks on a link to get the true status of a server.  This link is generally in the server list. */
			case 'update-status':
				/* Check for missing parameters */
				if ( ! isset( $_POST['params']['id'] ) ) {
					wp_send_json_error( array( 'msg' => __( 'Missing required parameter.', 'wpcd' ) ) );
					break;
				}

				/* Make sure we have a valid post type */
				$id = sanitize_text_field( $_POST['params']['id'] ); // server_id.
				if ( get_post_type( $id ) != 'wpcd_app_server' ) {
					wp_send_json_error( array( 'msg' => __( 'Invalid post type.', 'wpcd' ) ) );
					break;
				}

				// Verify that the user is allowed to access the server .
				if ( ! $this->wpcd_user_can_view_wp_server( $id ) ) {
					wp_send_json_error( array( 'msg' => __( 'You are not allowed to perform this operation on this server.', 'wpcd' ) ) );
					break;
				}

				$provider    = WPCD_SERVER()->get_server_provider( $id ); // provider.
				$instance_id = WPCD_SERVER()->get_server_provider_instance_id( $id ); // instance id so the provider knows which one we're talking about.

				$details = WPCD()->get_provider_api( $provider )->call( 'details', array( 'id' => $instance_id ) );

				if ( ! is_wp_error( $details ) ) {
					$done = true;
					update_post_meta( $id, 'wpcd_server_current_state', $details['status'] );
					$msg    = __( 'Status sync completed.', 'wpcd' );
					$result = array(
						'status' => $details['status'],
						'done'   => $done,
					);
				} else {
					$result = $details;
				}
				break;

			/* Every other request is sent here - which is most of them  */
			default:
				$additional = array();
				if ( isset( $_POST['additional'] ) ) {
					$additional = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['additional'] ) ) );
				}
				$result = $this->do_instance_action( sanitize_text_field( $_POST['_id'] ), sanitize_text_field( $_POST['_action'] ), $additional );
		}

		if ( is_wp_error( $result ) ) {
			if ( ! empty( $msg ) ) {
				wp_send_json_error( array( 'msg' => $msg . '<br />' . $result->get_error_code() ) );
			} else {
				wp_send_json_error( array( 'msg' => $result->get_error_code() ) );
			}
		}

		wp_send_json_success(
			array(
				'msg'    => $msg,
				'result' => $result,
			)
		);
	}


	/**
	 * Generates the instances' details that are required by other functions.
	 *
	 * @TODO: This probably should be moved to the class-wpcd-app.php file.
	 * If we do that, we'll need to rethink the location parameter of how
	 * we get the custom fields list.
	 *
	 * @param int $id id.
	 */
	public function get_instance_details( $id ) {
		$attributes = array(
			'post_id' => $id,
		);

		/**
		 * Get list of custom fields for the server.
		 */

		/* @TODO: We're running this through a filter and requesting a specific location but not sure that is the right thing to do. */
		$custom_fields = apply_filters( "wpcd_{$this->get_app_name()}_create_wp_server_parms_custom_fields", WPCD_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-server-popup' ), $attributes, $attributes );

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

			// Any custom field with data goes into the array regardless of it's name prefix.
			if ( isset( $custom_fields[ $key ] ) ) {
				$attributes[ $key ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}
		}

		$details = WPCD()->get_provider_api( $attributes['provider'] )->call( 'details', array( 'id' => $attributes['provider_instance_id'] ) );

		// Merge post ids and server details into a single array.
		$attributes = array_merge( $attributes, $details );

		return $attributes;
	}


	/**
	 * Validate that install WP is good to go on the basis of the inputs.
	 *
	 * @param array $args args.
	 */
	public function install_wp_validate( $args = null ) {

		// Get WP data to process.
		if ( empty( $args ) ) {
			// data is comming in via $_REQUEST which means that the site is being provisioned via wp-admin or a UI.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );
			$id   = sanitize_text_field( $_REQUEST['id'] );  // Post ID of the server where the wp app is being installed.
		} else {
			// data is being passed in directly which means that the site is likely being provisioned via woocommerce or the REST API.
			$id = $args['id'];
		}

		$app_type = $args['wpcd_app_type'];
		// @codingStandardsIgnoreLine - added to ignore the misspelling in 'wordpress' below when linting with PHPcs.
		if ( $app_type !== 'wordpress' ) {
			return false;
		}

		// Get domain and check to make sure it's not a duplicate or otherwise in use in WPCD...
		$domain = sanitize_text_field( $args['wp_domain'] );
		if ( ! defined( 'WPCD_ALLOW_DUPLICATE_DOMAINS' ) || ( defined( 'WPCD_ALLOW_DUPLICATE_DOMAINS' ) && ! WPCD_ALLOW_DUPLICATE_DOMAINS ) ) {
			$existing_app_id = $this->get_app_id_by_domain_name( $domain );
			if ( $existing_app_id > 0 ) {
				return new \WP_Error( __( 'This domain already exists in our system.', 'wpcd' ) );
			}
		}

		// Get other fields needed to provision the site.
		$wp_user     = sanitize_user( sanitize_text_field( $args['wp_user'] ) );
		$wp_password = $args['wp_password'];  // Note that we are NOT sanitizing the password field.  We'll escape every non-alpha-numeric character later before passing to bash.
		$wp_email    = sanitize_email( $args['wp_email'] );
		$wp_version  = sanitize_text_field( $args['wp_version'] );
		$wp_locale   = sanitize_text_field( $args['wp_locale'] );
		if ( empty( $wp_locale ) ) {
			$wp_locale = 'en_US';
		}

		// Make sure our password does not contain any invalid characters that would cause BASH to throw up.
		if ( false !== strpbrk( $args['wp_password'], "\;/|<>&()`'" ) || false !== strpbrk( $args['wp_password'], '"' ) || false !== strpbrk( $args['wp_password'], ' ' ) ) {
			return new \WP_Error( __( 'The password for the site contains invalid characters.', 'wpcd' ) );
		}
		// Make sure our email address field does not contain any invalid characters that would cause BASH or WP to throw up.  If it does, stop the process and return.
		if ( $wp_email !== $args['wp_email'] ) {
			// This comparison works for this purpose because we're comparing the sanitized email value to the orignal value.  If there are issues, the two will not be the same.
			return new \WP_Error( __( 'The email address for the site contains invalid characters.', 'wpcd' ) );
		}
		// Make sure our user name field does not contain any invalid characters that would cause BASH or WP to throw up.  If it does, stop the process and return.
		if ( false === validate_username( $args['wp_user'] ) ) {
			return new \WP_Error( __( 'The user name for the site contains invalid characters.', 'wpcd' ) );
		}

		// Escape special chars in password with backslashes.
		$wp_password_original = $wp_password;
		$wp_password          = wpcd_escape_for_bash( $wp_password );

		// remove https/http/www. to make the domain a consistent NAME.TLD and make it lowercase...
		$domain                     = strtolower( wpcd_clean_domain( $domain ) );
		$args['wp_domain']          = $domain;
		$args['wp_original_domain'] = $args['wp_domain'];

		$additional = array(
			'domain'               => escapeshellarg( $domain ),
			'wp_user'              => escapeshellarg( $wp_user ),
			'wp_password'          => escapeshellarg( $wp_password ),
			'wp_password_original' => $wp_password_original,
			'wp_email'             => escapeshellarg( $wp_email ),
			'wp_version'           => escapeshellarg( $wp_version ),
			'wp_locale'            => escapeshellarg( $wp_locale ),
		);

		// Add custom fields into the $additional array - these fields are from the app-popup in wp-admin...
		$custom_fields = apply_filters( "wpcd_{$this->get_app_name()}_install_wp_app_parms_custom_fields", WPCD_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-app-popup' ), $args, $additional );
		foreach ( $custom_fields as $field ) {
			if ( isset( $args[ $field['name'] ] ) ) {
				$additional[ $field['name'] ] = escapeshellarg( $args[ $field['name'] ] );
			}
		}

		// Add custom fields into the $additional array - these fields are from a WooCommerce order...
		$custom_fields = apply_filters( "wpcd_{$this->get_app_name()}_install_wp_app_parms_custom_fields", WPCD_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-app-woocommerce' ), $args, $additional );
		foreach ( $custom_fields as $field ) {
			if ( isset( $args[ $field['name'] ] ) ) {
				$additional[ $field['name'] ] = escapeshellarg( $args[ $field['name'] ] );
			}
		}

		// Get any post-processing bash script urls from settings. Note that this will not end up in the site's postmeta.
		$post_process_script                       = wpcd_get_option( 'wpcd_wpapp_custom_script_after_site_create' );
		$additional['post_processing_script_site'] = $post_process_script;

		// Get the secret key manager api key from settings. Note that this will not end up in the site's postmeta.
		$secret_key_manager_api_key               = wpcd_get_option( 'wpcd_wpapp_custom_script_secrets_manager_api_key' );
		$additional['secret_key_manager_api_key'] = $secret_key_manager_api_key;

		/* Allow devs to hook into the array to add their own elements for use later - likely to be rarely used given that we now have the custom fields array. */
		$additional = apply_filters( "wpcd_{$this->get_app_name()}_install_wp_app_parms", $additional, $args );

		// command length should be <= 42.
		// $command = 'install_wp_' . md5( $domain );.
		$command = 'install_wp_' . (string) time();

		$additional['command'] = $command;

		/* add the APP CPT records first so there's some place to put logs and status info for this site */
		$app_post_id                   = $this->add_wp_app_post( $id, $args, $additional );
		$additional['new_app_post_id'] = $app_post_id;

		update_post_meta( $app_post_id, 'wpcd_app_action_status', 'yet-to-be' );
		update_post_meta( $app_post_id, 'wpcd_install_wp_command_name', $command );
		update_post_meta( $app_post_id, 'wpcd_app_action_args', $additional );

		update_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action", 'install-wordpress' );
		update_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_status", 'in-progress' );
		WPCD_SERVER()->add_deferred_action_history( $id, $this->get_app_name() );

		return $additional;
	}


	/**
	 * Install WP on the instance on the basis of the inputs.
	 *
	 * @param array $attributes attributes.
	 */
	public function install_wp( $attributes ) {
		$id = $attributes['post_id'];

		// get the app for this post id where the app is in progress.
		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'   => 'wpcd_app_action_status',
						'value' => 'yet-to-be',
					),
					array(
						'key'   => 'parent_post_id',
						'value' => $id,
					),
				),
				'fields'      => 'ids',
			)
		);

		if ( ! $posts ) {
			// @TODO: no app in-progress found for this server? handle this.
			return false;
		}

		$app_post_id = $posts[0];
		$command     = get_post_meta( $app_post_id, 'wpcd_install_wp_command_name', true );
		$args        = get_post_meta( $app_post_id, 'wpcd_app_action_args', true );

		// Get_post_meta seems to strips slashes which causes an issue with the password field if they contain that and certain other special chars.
		$args['wp_password'] = escapeshellarg( $args['wp_password_original'] );

		// Now merge the arrays.
		$attributes = array_merge( $attributes, $args );

		if ( $this->is_command_done( $id, $command ) ) {
			// Do nothing.
		} elseif ( ! $this->is_command_running( $id, $command ) ) {
			$instance = $this->get_instance_details( $id );
			$run_cmd  = $this->turn_script_into_command( $instance, 'install_wordpress_site.txt', $attributes );

			do_action( 'wpcd_log_error', sprintf( 'attempting to run install_wp command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

			if ( ! empty( $run_cmd ) ) {
				/* Now try to create the site */
				$result = $this->execute_ssh(
					'generic',
					$instance,
					array( 'commands' => $run_cmd ),
					function() use ( $instance, $command, $app_post_id ) {
						if ( ! $this->is_command_running( $instance['post_id'], $command ) ) {
							$this->set_command_started( $instance['post_id'], $command );
							// if this is a long running command (which it is not), make sure it does not run again.
							update_post_meta( $app_post_id, 'wpcd_app_action_status', 'in-progress' );
						}
					}
				);
				if ( is_wp_error( $result ) ) {
					do_action( 'wpcd_log_error', sprintf( 'Unable to run command %s on %s ', $run_cmd, print_r( $instance, true ) ), 'error', __FILE__, __LINE__, $instance, false );

					// if something went wrong, remove the 'temporary' meta so that another attempt will run.
					delete_post_meta( $app_post_id, 'wpcd_app_action_status' );
					delete_post_meta( $app_post_id, 'wpcd_install_wp_command_name' );
					delete_post_meta( $app_post_id, 'wpcd_app_action_args' );

				} else {
					return true;
				}
			}
		}
		return false;

	}


	/**
	 * Add an app record to the wpcd_app cpt for the
	 * WP APP being installed.
	 *
	 * This is generally called from the install_wp_validate() method
	 * above.
	 *
	 * @param int   $server_id the post id of the server where the app is being installed.
	 * @param array $args the arguments sent from the JS script that triggered the install_wp_validate() method above.
	 * @param array $additional the arguments that the install_wp_validate() method above is using to install the app on the server.
	 *
	 * @return int|boolean Post if of the app or FALSE if the creation of the CPT records fail.
	 */
	public function add_wp_app_post( $server_id, $args, $additional ) {

		/**
		* If 'wc_user_id' is not set in the instance or is blank then use the current logged in user as the post author.
		* 'wc_user_id' should be set if being called from front end.  If its blank or does not exist then this is being called
		* from the wp-admin area.
		*/
		if ( ( isset( $args['wc_user_id'] ) && empty( $args['wc_user_id'] ) ) || ( ! isset( $args['wc_user_id'] ) ) ) {
			// Do nothing there for now.
		} else {
			$post_author = $args['wc_user_id'];
		}

		/**
		 * If we still don't have a post author, then check to see if a 'user_id' element is set and use that.
		 */
		if ( empty( $post_author ) ) {
			if ( isset( $args['user_id'] ) ) {
				$post_author = $args['user_id'];
			}
		}

		/**
		 * If we still don't have a post author, then check to see if a 'author_email' element is set and use that.
		 * This element might be set by a call from the REST API but, obviously, can also be set from anywhere.
		 */
		if ( empty( $post_author ) ) {
			if ( isset( $args['author_email'] ) ) {
				$author_email = $args['author_email'];
				if ( ! empty( $author_email ) ) {
					$user = get_user_by( 'email', $author_email );
					if ( ! empty( $user ) ) {
						$post_author = $user->ID;
					}
				}
			}
		}

		/**
		 * If we still don't have an author, set it to the current user.
		 */
		if ( empty( $post_author ) ) {
			$post_author = get_current_user_id();
		}
		if ( empty( $post_author ) ) {
			$post_author = 1;
		}

		/**
		 * Create an app cpt record and add our data fields to it.
		 * $args consist of the following pieces of data that we
		 * will make use of below:
		 * $domain = $args['wp_domain'];
		 * $wp_user = $args['wp_user'];
		 * $wp_password = $args['wp_password'];
		 * $wp_email = $args['wp_email'];
		 * $wp_version = $args['wp_version'];
		 */
		$title       = $args['wp_domain'];
		$app_post_id = WPCD_POSTS_APP()->add_app( $this->get_app_name(), $server_id, $post_author, $title );

		if ( ! is_wp_error( $app_post_id ) && ! empty( $app_post_id ) ) {

			/* Enable app delete protection flag if global option is enabled in settings. */
			if ( wpcd_get_option( 'wordpress_app_sites_add_delete_protection' ) ) {
				$this->set_delete_protection_flag( $app_post_id ); // This function is in the ancestor app class (file: class-wpcd-app.php).
			}

			/**
			 * Setup an array of fields and loop through them to add them to the wpcd_app cpt record using the array_map function.
			 * Note that we are passing in the $args variable to the anonymous function by REFERENCE via a USE parm.
			 * In the VPN app we were using $instance but not sure that is needed here * its not passed into this function right now anyway.
			*/
			$appfields = array( 'domain', 'user', 'email', 'version', 'original_domain' );
			$appfields = apply_filters( "wpcd_{$this->get_app_name()}_add_wp_app_post_fields", $appfields );
			$x         = array_map(
				function( $f ) use ( &$args, $app_post_id ) {
					if ( isset( $args[ 'wp_' . $f ] ) ) {
						update_post_meta( $app_post_id, 'wpapp_' . $f, $args[ 'wp_' . $f ] );
						$args['apps']['app'][ 'wpapp_' . $f ] = $args[ 'wp_' . $f ];
					}
				},
				$appfields
			);

			/* Now, lets loop through our custom fields and see if any of them needs to be added to the database. */
			$custom_fields = apply_filters( "wpcd_{$this->get_app_name()}_add_wp_app_custom_post_fields", WPCD_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-app-popup' ), $args, $additional );
			foreach ( $custom_fields as $field ) {
				if ( isset( $args[ $field['name'] ] ) ) {
					update_post_meta( $app_post_id, $field['name'], $args[ $field['name'] ] );
				} elseif ( isset( $additional[ $field['name'] ] ) ) {
					update_post_meta( $app_post_id, $field['name'], $additional[ $field['name'] ] );
				}
			}

			/* Add the password field to the CPT separately because it needs to be encrypted */
			update_post_meta( $app_post_id, 'wpapp_password', $this::encrypt( $args['wp_password'] ) );

			/* Everything good, return the post id of the new app record */
			return $app_post_id;

		} else {

			return false;

		}

		return false;

	}

	/**
	 * Add temp domain to DNS when WP install is complete.
	 *
	 * Action Hook: wpcd_command_wordpress-app_completed_after_cleanup
	 *
	 * @param int    $server_id      post id of server.
	 * @param int    $app_id         post id of wp app.
	 * @param string $name           command name executed for new site.
	 * @param string $base_command   basename of command.
	 */
	public function wpcd_wpapp_install_complete( $server_id, $app_id, $name, $base_command ) {

		// If not installing an app, return.
		if ( 'install_wp' <> $base_command ) {
			return;
		}

		$app_post = get_post( $app_id );

		// Bail if not a post object.
		if ( ! $app_post || is_wp_error( $app_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' <> WPCD_WORDPRESS_APP()->get_app_type( $app_id ) ) {
			return;
		}

		// Get app instance array.
		$instance = WPCD_WORDPRESS_APP()->get_app_instance_details( $app_id );

		// Log what we're doing.
		do_action( 'wpcd_log_error', 'Sending request to DNS module to add domain if applicable ' . print_r( $instance, true ), 'trace', __FILE__, __LINE__ );

		// Do the DNS thing.
		$this->handle_temp_dns_for_new_site( $server_id, $app_id );

		// Install page_cache.
		$this->handle_page_cache_for_new_site( $app_id, $instance );

	}

	/**
	 * Add temp domain to DNS when WP install is complete.
	 *
	 * Called from function wpcd_wpapp_install_complete
	 *
	 * @param int $server_id      post id of server.
	 * @param int $app_id         post id of wp app.
	 */
	public function handle_temp_dns_for_new_site( $server_id, $app_id ) {

		// What's the IP of the server?
		$ipv4 = WPCD_SERVER()->get_ipv4_address( $server_id );

		// What's the IPv6 of the server?
		$ipv6 = WPCD_SERVER()->get_ipv6_address( $server_id );

		// What's the domain of the site?
		$domain = $this->get_domain_name( $app_id );

		// Add the DNS...
		$dns_success = WPCD_DNS()->set_dns_for_domain( $domain, $ipv4, $ipv6 );

		// Attempt to issue SSL..
		if ( true === (bool) $dns_success ) {
			if ( wpcd_get_option( 'wordpress_app_auto_issue_ssl' ) ) {
				do_action( 'wpcd_wordpress-app_do_toggle_ssl_status', $app_id, 'ssl-status' );
			}
		}

		return $dns_success;

	}

	/**
	 * Add page cache when WP install is complete.
	 *
	 * Called from function wpcd_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site.
	 */
	public function handle_page_cache_for_new_site( $app_id, $instance ) {

		if ( wpcd_get_option( 'wordpress_app_sites_install_page_cache' ) ) {
			$instance['action_hook'] = 'wpcd_pending_log_toggle_page_cache';
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'install-page-cache', $app_id, $instance, 'ready', $app_id, __( 'Waiting To Install Page Cache For New Site', 'wpcd' ) );
		}

	}


	/**
	 * Create the server instance on the basis of the inputs.
	 *
	 * NOTE: Changes to this function should also be checked
	 * against changes to the wc_spinup_wpapp() function
	 * in class-wordpress-woocommerce.php in the woocommerce
	 * servers add-on.
	 */
	public function create_instance() {
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );

		$webserver = '';
		if ( ! empty( $args['webserver-type'] ) ) {
			$webserver = sanitize_text_field( $args['webserver-type'] );
		}
		if ( empty( $webserver ) ) {
			$webserver = 'nginx';
		}
		$os       = sanitize_text_field( $args['os'] );
		$provider = sanitize_text_field( $args['provider'] );
		$region   = sanitize_text_field( $args['region'] );
		$size     = sanitize_text_field( $args['size'] );
		$user_id  = (int) $args['wp_user_id'];
		if ( empty( $user_id ) ) {
			$user = wp_get_current_user();
		} else {
			$user = get_user_by( 'ID', $user_id );
		}
		$type    = sanitize_text_field( $args['server_type'] );
		$version = sanitize_text_field( $args['script_version'] );

		/* Make sure we have a server type */
		if ( empty( $type ) ) {
			$type                = 'wordpress-app';
			$args['server_type'] = $type;
		}

		/* Generate the server name */
		if ( wpcd_get_option( 'wordpress_app_use_extended_server_name' ) || empty( $args['name'] ) ) {
			if ( empty( $args['name'] ) ) {
				$name = sanitize_title( sprintf( '%s-%s-%s', $provider, $user->user_nicename, get_gmt_from_date( '' ) ) );
			} else {
				$name = sanitize_title( sprintf( '%s-%s-%s-%s', $provider, $user->user_nicename, $args['name'], get_gmt_from_date( '' ) ) );
			}
		} else {
			$name = sanitize_text_field( $args['name'] );
		}

		$attributes = array(
			'webserver_type'   => $webserver,
			'initial_os'       => $os,
			'initial_app_name' => $type,
			'scripts_version'  => $version,
			'region'           => $region,
			'size_raw'         => $size,
			'name'             => $name,
			'provider'         => $provider,
			'init'             => true,
			'server-type'      => $type,
			'user_id'          => $user->ID,
		);

		// Add custom fields into the $attributes array...
		$custom_fields = apply_filters( "wpcd_{$this->get_app_name()}_create_wp_server_parms_custom_fields", WPCD_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-server-popup' ), $attributes, $args );
		foreach ( $custom_fields as $field ) {
			if ( isset( $args[ $field['name'] ] ) ) {
				$attributes[ $field['name'] ] = sanitize_text_field( $args[ $field['name'] ] );
			}
		}

		// Get any post-processing bash script urls from settings.
		// This one will end up getting added to the servers' post meta but should be deleted afterwards in the wpcd_wpapp_core_prepare_server_completed() function located in traits/after-prepare-server.php.
		$post_process_script                         = wpcd_get_option( 'wpcd_wpapp_custom_script_after_server_create' );
		$attributes['post_processing_script_server'] = $post_process_script;

		// Get the secret key manager api key from settings.
		// This one will end up getting added to the servers' post meta but should be deleted afterwards in the wpcd_wpapp_core_prepare_server_completed() function located in traits/after-prepare-server.php.
		$secret_key_manager_api_key               = wpcd_get_option( 'wpcd_wpapp_custom_script_secrets_manager_api_key' );
		$attributes['secret_key_manager_api_key'] = $secret_key_manager_api_key;

		/* Allow others to populate the attributes array which should get stored in the CPT automatically. */
		$attributes = apply_filters( "wpcd_{$this->get_app_name()}_initial_server_attributes", $attributes, $args );

		/* Create server */
		$instance = WPCD_SERVER()->create_server( 'create', $attributes );  // fire up a new server.

		/* Check for error */
		if ( ( is_wp_error( $instance ) ) || ( ! $instance ) ) {
			return $instance;
		}

		/* If we have a valid post id, add custom fields data to the server post object */
		$post_id = $instance['post_id'];
		if ( ( ! empty( $post_id ) ) && ( ! is_wp_error( $post_id ) ) ) {
			$custom_fields = apply_filters( "wpcd_{$this->get_app_name()}_create_wp_server_custom_post_fields", WPCD_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-server-popup' ), $attributes, $args );
			foreach ( $custom_fields as $field ) {
				if ( isset( $instance[ $field['name'] ] ) ) {
					update_post_meta( $post_id, $field['name'], $instance[ $field['name'] ] );
				}
			}
		}

		/* Install app on server */
		if ( $instance ) {
			$instance = $this->add_app( $instance );
		}

		return $instance;
	}

	/**
	 * Implement empty method from parent class that adds an app to a server.
	 *
	 * This is generally called immediately after the server is provisioned.
	 * But in some cases such as this one, it is called without an APP actually
	 * needing to be installed.  Instead it is used to complete adding data to
	 * the server CPT and to schedule subsequent actions that need to be handled
	 * via the scheduler.
	 *
	 * When a WP APP is actually being installed on the server, the add_wp_app_post
	 * function will be called instead.
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

		/* Restructure the server instance array to add the app data that is going into the wpcd_app CPT */
		if ( ! isset( $instance['apps'] ) ) {
			$instance['apps'] = array();
		}

		if ( 'something-else' === $instance['server-type'] ) {
			/**
			 * No apps are being installed.
			 */
			return;
		}

		/* Schedule after-server-create commands (commands to run after the server has been instantiated for the first time) */
		update_post_meta( $post_id, "wpcd_server_{$this->get_app_name()}_action", 'after-server-create-commands' );
		/* update_post_meta( $post_id, 'wpcd_server_after_create_action_app_id', $app_post_id ); */  // No app so no app_post_id var.
		update_post_meta( $post_id, "wpcd_server_{$this->get_app_name()}_action_status", 'in-progress' );
		WPCD_SERVER()->add_deferred_action_history( $post_id, $this->get_app_name() );
		if ( isset( $instance['init'] ) && true === $instance['init'] ) {
			update_post_meta( $post_id, 'wpcd_server_init', '1' );
		}

		return $instance;

	}

	/**
	 * *** NOT USED ***
	 * Sends email to the user.
	 *
	 * @param array $instance Array of attributes for the custom post type.
	 *
	 * An example $instance array would look like this:
			Array
			(
				[post_id] => 4978
				[initial_app_name] => wordpress-app
				[scripts_version] => v1
				[region] => us-central
				[size_raw] => g6-nanode-1
				[name] => spinupvpnwpadmin-test-ema_CX0x
				[provider] => linode
				[server-type] => wordpress-app
				[provider_instance_id] => 19823428
				[server_name] => spinupvpnwpadmin-test-ema_CX0x
				[created] => 2020-03-20 00:58:36
				[actions] => a:1:{s:7:"created";i:1584683916;}
				[wordpress-app_action] => email
				[wordpress-app_action_status] => in-progress
				[last_deferred_action_source] => a:15:{i:1584683916;s:13:"wordpress-app";i:1584683953;s:13:"wordpress-app";i:1584684013;s:13:"wordpress-app";i:1584684073;s:13:"wordpress-app";i:1584684133;s:13:"wordpress-app";i:1584684193;s:13:"wordpress-app";i:1584684253;s:13:"wordpress-app";i:1584684313;s:13:"wordpress-app";i:1584684373;s:13:"wordpress-app";i:1584684433;s:13:"wordpress-app";i:1584684493;s:13:"wordpress-app";i:1584684553;s:13:"wordpress-app";i:1584684613;s:13:"wordpress-app";i:1584684673;s:13:"wordpress-app";i:1584684735;s:13:"wordpress-app";}
				[init] => 1
				[ipv4] => 45.56.75.14
				[status] => active
				[action_id] =>
				[os] => unknown
				[ip] => 45.56.75.14
			).
	 */
	private function send_email( $instance ) {
		do_action( 'wpcd_log_error', 'sending email for ' . print_r( $instance, true ), 'debug', __FILE__, __LINE__ );

		// Get the email body.
		$email_body = $this->get_app_instance_summary( $instance['post_id'], 'email-admin' );

		if ( ! empty( $email_body ) ) {
			wp_mail(
				get_option( 'admin_email' ),
				__( 'Your New WordPress Server Is Ready', 'wpcd' ),
				$email_body,
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
		}

	}

	/**
	 * *** NOT USED ***
	 * Gets the summary of the instance for emails and instructions popup.
	 *
	 * @param int  $server_id POST ID of the server cpt record.
	 * @param bool $type Who is this for?  Possible values are 'email-admin', 'email-user', 'popup'.
	 *
	 * @return string
	 */
	private function get_app_instance_summary( $server_id, $type = 'email-admin' ) {

		// get the server post.
		$server_post = get_post( $server_id );

		// Get provider from server record.
		$provider    = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );
		$instance_id = get_post_meta( $server_post->ID, 'wpcd_server_provider_instance_id', true );
		$details     = WPCD()->get_provider_api( $provider )->call( 'details', array( 'id' => $instance_id ) );

		// Get server size from server record.
		$size     = get_post_meta( $server_post->ID, 'wpcd_server_size', true );
		$raw_size = get_post_meta( $server_post->ID, 'wpcd_server_raw_size', true );
		$region   = get_post_meta( $server_post->ID, 'wpcd_server_region', true );
		$provider = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );

		$template_suffix = 'email-admin';
		switch ( $type ) {
			case 'email-admin':
				$template_suffix = 'email-admin.html';
				break;
			case 'email-user':
				$template_suffix = 'email-user.html';
				break;
			default:
				$template_suffix = 'email-admin.html';
				break;
		}

		$template = file_get_contents( dirname( __FILE__ ) . '/templates/' . $template_suffix );
		return str_replace(
			array( '$NAME', '$PROVIDER', '$IP', '$SIZE', '$URL' ),
			array(
				get_post_meta( $server_post->ID, 'wpcd_server_name', true ),
				$this->get_providers()[ $provider ],
				$details['ip'],
				$size,
				site_url( 'account' ),
			),
			$template
		);
	}

	/**
	 * Adds a welcome message to the GENERAL SETTINGS tab.
	 *
	 * Filter hook: wpcd_general_settings_after_welcome_message
	 *
	 * @param array $meta_boxes current set of metaboxes defined on the settings screen.
	 *
	 * @return array $meta_boxes
	 */
	public function welcome_message_settings( $meta_boxes ) {

		$welcome_message  = '';
		$welcome_message .= '<b>' . __( 'Here are the basic steps needed to build your first server and deploy your first WordPress site:', 'wpcd' ) . '</b>';
		$welcome_message .= '<br />';
		$welcome_message .= '<ol>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Double-check that your current web server where this plugin is deployed has the appropriate timeouts as listed at the bottom of this screen. You will not be able to build your first server unless your web server timeouts are increased.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Make sure you have created and uploaded an SSH key pair to your server providers\' dashboard.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Generate an API key in your server providers\' dashboard.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Add your cloud server provider API key and other credentials to the WPCLOUD DEPLOY->SETTINGS screen - under the CLOUD PROVIDERS tab / menu.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Click on the ALL CLOUD SERVERS menu option and use the DEPLOY A NEW WordPress SERVER button to deploy a server.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'After the server is deployed, go back to the ALL CLOUD SERVERS menu option and click the INSTALL WordPress button in the server list.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '</ol>';
		$welcome_message .= '<br />';
		$welcome_message .= '&nbsp;&nbsp;&nbsp;&nbsp;';
		$welcome_message .= sprintf( '<a style="padding: 10px; border: solid; border-width: 1px; text-decoration: none; color: white; background-color: #03114A; border-color: #03114A;" target="_blank" href="%s">' . __( 'Quick Start Guide', 'wpcd' ) . '</a>', 'https://wpclouddeploy.com/documentation/wpcloud-deploy/introduction-to-wpcloud-deploy/' );

		$welcome_message = apply_filters( 'wpcd_settings_deploy_first_wp_site_text', $welcome_message );

		$meta_boxes[] = array(
			'id'             => 'wpcd_deploy_your_first_wordpress_site',
			'title'          => __( 'Deploy Your First WordPress Site', 'wpcd' ),
			'settings_pages' => 'wpcd_settings',
			'tab'            => 'general',
			'fields'         => array(
				array(
					'type' => 'heading',
					'id'   => 'wpcd_deploy_your_first_wordpress_site_heading',
					'name' => 'Deploy Your First WordPress Site',
					'desc' => $welcome_message,
				),
			),
		);

		return $meta_boxes;

	}

	/**
	 * Adds an additional set of message strings to the default "no application servers were found" message
	 * that shows up when there are no app servers in the app servers list.
	 *
	 * **** It looks like this FILTER does not work at all.
	 * **** The CPT creation process might be too early for
	 * **** it to fire properly?
	 *
	 * Filter hook: wpcd_no_app_servers_found_msg
	 *
	 * @param string $msg message.
	 *
	 * @return string $msg
	 */
	public function no_app_servers_found_msg( $msg ) {
		$msg .= '<br />';
		$msg .= __( 'To create a new server, first make sure you add your cloud provider credentials under the SETTINGS screen.', 'wpcd' );
		$msg .= '<br />';
		$msg .= __( 'Then, you can click the DEPLOY A NEW WordPress SERVER button at the top of this screen to create your first server.', 'wpcd' );
		return $msg;
	}

	/**
	 * Return an instance of self.
	 *
	 * @return WPCD_WORDPRESS_APP
	 */
	public function get_this() {
		return $this;
	}

	/**
	 * SSH function.
	 *
	 * @return WORDPRESS_SSH()
	 */
	public function ssh() {
		if ( empty( WPCD()->classes['wpcd_wordpress_ssh'] ) ) {
			WPCD()->classes['wpcd_wordpress_ssh'] = new WORDPRESS_SSH();
		}
		return WPCD()->classes['wpcd_wordpress_ssh'];
	}

	/**
	 * Settings function.
	 *
	 * @return WORDPRESS_APP_SETTINGS()
	 */
	public function settings() {
		if ( empty( WPCD()->classes['wpcd_app_wordpress_settings'] ) ) {
			WPCD()->classes['wpcd_app_wordpress_settings'] = new WORDPRESS_APP_SETTINGS();
		}
		return WPCD()->classes['wpcd_app_wordpress_settings'];
	}

	/**
	 * Returns the installed status of a service on the server.
	 * Services includes things like nginx, mariadb, memcached etc.
	 *
	 * @param int    $id  The post id of the server or app.
	 * @param string $service    The name of the service.
	 *
	 * @return string   'installed'/'not-installed'/'unknown'
	 */
	public function get_server_installed_service_status( $id, $service ) {

		$server_id = null;

		// What kind of id did we get?  App or server?
		if ( 'wpcd_app' === get_post_type( $id ) ) {
			$server_post = $this->get_server_by_app_id( $id );
			if ( $server_post ) {
				$server_id = $server_post->ID;
			}
		} elseif ( 'wpcd_app_server' === get_post_type( $id ) ) {
			$server_id = $id;
		}

		$status = 'not installed';

		if ( $server_id ) {
			switch ( $service ) {
				case 'memcached':
					$meta_status = get_post_meta( $server_id, 'wpcd_wpapp_memcached_installed', true );
					if ( 'yes' == $meta_status ) {
						$status = 'installed';
					} else {
						$status = 'not-installed';
					}
					break;

				case 'redis':
					$meta_status = get_post_meta( $server_id, 'wpcd_wpapp_redis_installed', true );
					if ( 'yes' == $meta_status ) {
						$status = 'installed';
					} else {
						$status = 'not-installed';
					}
					break;

			}
		}

		return $status;

	}

	/**
	 * Register the scripts for custom post types for the wp app.
	 *
	 * @param string $hook The page name hook.
	 */
	public function wpapp_enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'post.php' ) ) ) {
			$screen = get_current_screen();
			if ( ( is_object( $screen ) && 'wpcd_app' == $screen->post_type ) || WPCD_WORDPRESS_APP_PUBLIC::is_app_edit_page()  ) {
				wp_enqueue_script( 'wpcd-wpapp-admin-common', wpcd_url . 'includes/core/apps/wordpress-app/assets/js/wpcd-wpapp-admin-common.js', array( 'jquery', 'wpcd-magnific' ), wpcd_scripts_version, true );
				wp_localize_script(
					'wpcd-wpapp-admin-common',
					'params',
					apply_filters(
						'wpcd_app_script_args',
						array(
							'nonce' => wp_create_nonce( 'wpcd-app' ),
							'i10n'  => array(
								'loading' => __(
									'Loading',
									'wpcd'
								) . '...',
							),
						),
						'wpcd-wpapp-admin-common'
					)
				);
			}

			if ( ( is_object( $screen ) && ( 'wpcd_app' === $screen->post_type || 'wpcd_app_server' === $screen->post_type ) ) || WPCD_WORDPRESS_APP_PUBLIC::is_app_edit_page() || WPCD_WORDPRESS_APP_PUBLIC::is_server_edit_page() ) {
				wp_enqueue_script( 'wpcd-wpapp-admin', wpcd_url . 'includes/core/apps/wordpress-app/assets/js/wpcd-wpapp-admin-app.js', array( 'jquery', 'wpcd-magnific' ), wpcd_scripts_version, true );
				wp_localize_script(
					'wpcd-wpapp-admin',
					'params',
					apply_filters(
						'wpcd_app_script_args',
						array(
							'nonce' => wp_create_nonce( 'wpcd-app' ),
							'i10n'  => array(
								'loading' => __(
									'Loading',
									'wpcd'
								) . '...',
							),
						),
						'wpcd-wpapp-admin'
					)
				);
			}
		}

		if ( in_array( $hook, array( 'edit.php' ) ) ) {
			$screen = get_current_screen();
			if ( ( is_object( $screen ) && in_array( $screen->post_type, array( 'wpcd_app' ) ) ) || WPCD_WORDPRESS_APP_PUBLIC::is_apps_list_page() ) {
				wp_enqueue_style( 'wpcd-wpapp-admin-app-css', wpcd_url . 'includes/core/apps/wordpress-app/assets/css/wpcd-wpapp-admin-app.css', array(), wpcd_scripts_version );

				wp_enqueue_script( 'wpcd-wpapp-admin-post-type-wpcd-app', wpcd_url . 'includes/core/apps/wordpress-app/assets/js/wpcd-wpapp-admin-post-type-wpcd-app.js', array( 'jquery' ), wpcd_scripts_version, true );
				wp_localize_script(
					'wpcd-wpapp-admin-post-type-wpcd-app',
					'params3',
					apply_filters(
						'wpcd_app_script_args',
						array(
							'nonce'                => wp_create_nonce( 'wpcd-app' ),
							'_action'              => 'remove',
							'i10n'                 => array(
								'remove_site_prompt' => __( 'Are you sure you would like to delete this site and data? This action is NOT reversible!', 'wpcd' ),
								'install_wpapp'      => __( 'Install WordPress', 'wpcd' ),
							),
							'install_wpapp_url'    => admin_url( 'edit.php?post_type=wpcd_app_server' ),
							'bulk_actions_confirm' => __( 'Are you sure you want to perform this bulk action?', 'wpcd' ),
						),
						'wpcd-wpapp-admin-post-type-wpcd-app'
					)
				);
			}
		}

		$screen     = get_current_screen();
		$post_types = array( 'wpcd_app_server', 'wpcd_app', 'wpcd_team', 'wpcd_permission_type', 'wpcd_command_log', 'wpcd_ssh_log', 'wpcd_error_log', 'wpcd_pending_log' );

		if ( ( is_object( $screen ) && in_array( $screen->post_type, $post_types ) ) || WPCD_WORDPRESS_APP_PUBLIC::is_public_page() ) {
			wp_enqueue_script( 'wpcd-admin-common', wpcd_url . 'includes/core/apps/wordpress-app/assets/js/wpcd-admin-common.js', array( 'jquery' ), wpcd_scripts_version, true );
			wp_localize_script(
				'wpcd-admin-common',
				'readableCheck',
				array(
					'nonce'              => wp_create_nonce( 'wpcd-admin' ),
					'action'             => 'set_readable_check',
					'check_again_action' => 'readable_check_again',
					'cron_check_action'  => 'set_cron_check',
				)
			);
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
				self::wpapp_schedule_events();
				restore_current_blog();
			}
		} else {
			self::wpapp_schedule_events();
		}

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function wpapp_schedule_events() {
		// setup temporary script deletion.
		wp_clear_scheduled_hook( 'wpcd_wordpress_file_watcher' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_wordpress_file_watcher' );

		// setup deferred instance actions schedule.
		wp_clear_scheduled_hook( 'wpcd_wordpress_deferred_actions_for_server' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_wordpress_deferred_actions_for_server' );

		// setup repeated actions schedule.
		wp_clear_scheduled_hook( 'wpcd_wordpress_deferred_actions_for_apps' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_wordpress_deferred_actions_for_apps' );

		// @TODO does not work because the cron schedule is not registered.
		// wp_schedule_event( time(), 'every-10-seconds', "wpcd_wordpress_repeated_actions_for_apps" );
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
				self::wpapp_clear_scheduled_events();
				restore_current_blog();
			}
		} else {
			self::wpapp_clear_scheduled_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function wpapp_clear_scheduled_events() {
		wp_clear_scheduled_hook( 'wpcd_wordpress_file_watcher' );
		wp_clear_scheduled_hook( 'wpcd_wordpress_deferred_actions_for_server' );
		wp_clear_scheduled_hook( 'wpcd_wordpress_deferred_actions_for_apps' );
	}

	/**
	 * To schedule events for newly created site on WP Multisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new sites.
	 * @param  array  $args args.
	 * @return void
	 */
	public function wpapp_schedule_events_for_new_site( $new_site, $args ) {

		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {
			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::wpapp_schedule_events();
			restore_current_blog();
		}

	}

	/**
	 * Checks whether the specified directory is readable and .txt files can be accessible
	 */
	public function wpapp_admin_init() {
		if ( ! get_transient( 'wpcd_readable_check' ) ) {
			$dir = wpcd_path . 'includes/core/apps/wordpress-app/scripts/v1/raw/';
			$url = wpcd_url . 'includes/core/apps/wordpress-app/scripts/v1/raw/01-prepare_server.txt';

			// Use CURL to check to see if the server is responding for a request for the file defined in the $url var above..
			$curl_error = false;
			$ch         = curl_init( $url );
			$txt        = curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, 0 );
			curl_exec( $ch );
			if ( curl_error( $ch ) ) {
				$curl_error = true;
			}

			// Checks if directory is readable and .txt files can be accessible. Setting transient so that notice doesnt show at admin screens everytime.
			if ( is_readable( $dir ) && ( ! $curl_error ) ) {

				set_transient( 'wpcd_readable_check', 1, 12 * HOUR_IN_SECONDS );
			}
		}
	}

	/**
	 * Sets the transient for readable directory check
	 * This will be set when user dismisses the notice for readable directory check
	 */
	public function set_readable_check() {

		/* Nonce check */
		check_ajax_referer( 'wpcd-admin', 'nonce' );

		/* Permision check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss readable check.', 'wpcd' ) ) );
		}

		/* All checks passed so set the transient */
		set_transient( 'wpcd_readable_check', 1, 12 * HOUR_IN_SECONDS );
		wp_die();

	}

	/**
	 * Returns the metabox.io tab style to use for the main tabs in the WordPress app detail screen
	 */
	public function get_tab_style() {
		$tab_style = ( ! empty( wpcd_get_early_option( 'wordpress_app_tab_style' ) ) ) ? wpcd_get_early_option( 'wordpress_app_tab_style' ) : 'left';
		return $tab_style;
	}

	/**
	 * Returns the metabox.io tab style to use for the main tabs in the WordPress app server screen
	 */
	public function get_tab_style_server() {
		$tab_style = ( ! empty( wpcd_get_early_option( 'wordpress_app_server_tab_style' ) ) ) ? wpcd_get_early_option( 'wordpress_app_server_tab_style' ) : 'left';
		return $tab_style;
	}

	/**
	 * Sets the transient for readable directory check
	 * This will be set when user clicks check again link on the notice for readable directory check
	 */
	public function readable_check_again() {

		/* Nonce check */
		check_ajax_referer( 'wpcd-admin', 'nonce' );

		/* Permision check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - do readable check again.', 'wpcd' ) ) );
		}

		$this->wpapp_admin_init();

		if ( get_transient( 'wpcd_readable_check' ) ) {
			$return = array(
				'message' => __( 'Readable check successful!', 'wpcd' ),
			);
			wp_send_json_success( $return );
		} else {
			$return = array(
				'message' => __( 'Readable check failed!', 'wpcd' ),
			);
			wp_send_json_error( $return );
		}

		wp_die();
	}

	/**
	 * Add filters on the app listing screen at the backend
	 *
	 * Action hook: restrict_manage_posts
	 *
	 * @return void
	 */
	public function wpapp_wpcd_app_table_filtering() {

		global $typenow, $pagenow;

		$post_type = 'wpcd_app';

		if ( ( is_admin() && 'edit.php' === $pagenow && $typenow == $post_type ) || WPCD_WORDPRESS_APP_PUBLIC::is_apps_list_page() ) {

			// APP STATUS.
			$app_status_options = array(
				'on'  => __( 'Enabled', 'wpcd' ),
				'off' => __( 'Disabled', 'wpcd' ),
			);
			$app_status         = $this->generate_meta_dropdown( 'wpapp_site_status', __( 'App Status', 'wpcd' ), $app_status_options );
			echo $app_status;

			// PHP VERSION.
			$php_version_options = array(
				'7.4' => '7.4',
				'7.3' => '7.3',
				'7.2' => '7.2',
				'7.1' => '7.1',
				'5.6' => '5.6',
				'8.0' => '8.0',
			);
			$php_version         = $this->generate_meta_dropdown( 'wpapp_php_version', __( 'PHP Version', 'wpcd' ), $php_version_options );
			echo $php_version;

			// CACHE.
			$cache_options  = array(
				'on'  => __( 'Enabled', 'wpcd' ),
				'off' => __( 'Disabled', 'wpcd' ),
			);
			$php_page_cache = $this->generate_meta_dropdown( 'wpapp_page_cache_status', __( 'Page Cache', 'wpcd' ), $cache_options );
			echo $php_page_cache;

			$php_object_cache = $this->generate_meta_dropdown( 'wpapp_object_cache_status', __( 'Object Cache', 'wpcd' ), $cache_options );
			echo $php_object_cache;

			// SITE NEEDS UPDATES.
			$updates_options    = array(
				'yes' => __( 'Yes', 'wpcd' ),
				'no'  => __( 'No', 'wpcd' ),
			);
			$site_needs_updates = $this->generate_meta_dropdown( 'wpapp_sites_needs_updates', __( 'Site Needs Updates', 'wpcd' ), $updates_options );
			echo $site_needs_updates;

		}
	}

	/**
	 * To modify default query parameters and to show app listing based on custom filters
	 *
	 * Action filter: parse_query
	 *
	 * @param  object $query query.
	 */
	public function wpapp_wpcd_app_parse_query( $query ) {
		global $pagenow;

		$filter_action = filter_input( INPUT_GET, 'filter_action', FILTER_SANITIZE_STRING );
		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ! empty( $filter_action ) ) {
			$qv = &$query->query_vars;

			// APP STATUS.
			if ( isset( $_GET['wpapp_site_status'] ) && ! empty( $_GET['wpapp_site_status'] ) ) {
				$wpapp_site_status = filter_input( INPUT_GET, 'wpapp_site_status', FILTER_SANITIZE_STRING );

				if ( $wpapp_site_status === 'on' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_site_status',
							'value'   => $wpapp_site_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_site_status',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpapp_site_status',
						'value'   => $wpapp_site_status,
						'compare' => '=',
					);
				}
			}

			// PHP VERSION.
			if ( isset( $_GET['wpapp_php_version'] ) && ! empty( $_GET['wpapp_php_version'] ) ) {
				$wpapp_php_version = filter_input( INPUT_GET, 'wpapp_php_version', FILTER_SANITIZE_STRING );

				if ( $wpapp_php_version === '7.4' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_php_version',
							'value'   => $wpapp_php_version,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_php_version',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpapp_php_version',
						'value'   => $wpapp_php_version,
						'compare' => '=',
					);
				}
			}

			// PAGE CACHE.
			if ( isset( $_GET['wpapp_page_cache_status'] ) && ! empty( $_GET['wpapp_page_cache_status'] ) ) {
				$wpapp_page_cache_status = filter_input( INPUT_GET, 'wpapp_page_cache_status', FILTER_SANITIZE_STRING );

				if ( $wpapp_page_cache_status === 'off' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_nginx_pagecache_status',
							'value'   => $wpapp_page_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_nginx_pagecache_status',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpapp_nginx_pagecache_status',
						'value'   => $wpapp_page_cache_status,
						'compare' => '=',
					);
				}
			}

			// OBJECT CACHE.
			if ( isset( $_GET['wpapp_object_cache_status'] ) && ! empty( $_GET['wpapp_object_cache_status'] ) ) {
				$wpapp_object_cache_status = filter_input( INPUT_GET, 'wpapp_object_cache_status', FILTER_SANITIZE_STRING );

				if ( $wpapp_object_cache_status === 'off' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_memcached_status',
							'value'   => $wpapp_object_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_memcached_status',
							'compare' => 'NOT EXISTS',
						),
					);

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_redis_status',
							'value'   => $wpapp_object_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_redis_status',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_memcached_status',
							'value'   => $wpapp_object_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_redis_status',
							'value'   => $wpapp_object_cache_status,
							'compare' => '=',
						),
					);
				}
			}

			// SITE NEEDS UPDATES.
			if ( isset( $_GET['wpapp_sites_needs_updates'] ) && ! empty( $_GET['wpapp_sites_needs_updates'] ) ) {
				$wpapp_sites_needs_updates = filter_input( INPUT_GET, 'wpapp_sites_needs_updates', FILTER_SANITIZE_STRING );

				if ( $wpapp_sites_needs_updates === 'yes' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpcd_site_needs_updates',
							'value'   => $wpapp_sites_needs_updates,
							'compare' => '=',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpcd_site_needs_updates',
						'value'   => $wpapp_sites_needs_updates,
						'compare' => '=',
					);
				}
			}
		}

		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ! empty( $_GET['wpapp_php_version'] ) && empty( $filter_action ) ) {

			$qv               = &$query->query_vars;
			$qv['meta_query'] = array();

			$wpapp_php_version = filter_input( INPUT_GET, 'wpapp_php_version', FILTER_SANITIZE_STRING );

			if ( $wpapp_php_version == '7.4' ) {
				$qv['meta_query'][] = array(
					'relation' => 'OR',
					array(
						'key'     => 'wpapp_php_version',
						'value'   => $wpapp_php_version,
						'compare' => '=',
					),
					array(
						'key'     => 'wpapp_php_version',
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$qv['meta_query'][] = array(
					'key'     => 'wpapp_php_version',
					'value'   => $wpapp_php_version,
					'compare' => '=',
				);
			}
		}

	}

	/**
	 * To add custom filtering options based on meta fields.
	 * This filter will be added on app listing screen at the backend
	 *
	 * @param  string $field_key field key.
	 * @param  string $first_option first option.
	 * @param array  $options options.
	 *
	 * @return string
	 */
	public function generate_meta_dropdown( $field_key, $first_option, $options ) {

		$html          = '';
		$html         .= sprintf( '<select name="%s" id="filter-by-%s">', $field_key, $field_key );
		$html         .= sprintf( '<option value="">%s</option>', $first_option );
		$get_field_key = filter_input( INPUT_GET, $field_key, FILTER_SANITIZE_STRING );
		foreach ( $options as $key => $value ) {

			$selected = selected( $get_field_key, $key, false );
			$html    .= sprintf( '<option value="%s" %s>%s</option>', $key, $selected, $value );
		}
		$html .= '</select>';

		return $html;
	}

	/**
	 * Runs when the APP cleanup function is being called.
	 *
	 * Action Hook: wpcd_cleanup_app_after
	 *
	 * @param int $app_id app id.
	 */
	public function wpcd_cleanup_app_after( $app_id ) {

		update_post_meta( $app_id, 'wpcd_app_wordpress-app_action_status', '' );
		update_post_meta( $app_id, 'wpcd_app_wordpress-app_action', '' );
		update_post_meta( $app_id, 'wpcd_app_wordpress-app_action_args', '' );

	}

	/**
	 * Runs when the SERVER cleanup function is being called.
	 *
	 * Action Hook: wpcd_cleanup_server_after
	 *
	 * @param int $server_id server id.
	 */
	public function wpcd_cleanup_server_after( $server_id ) {

		delete_post_meta( $server_id, 'wpcd_server_wordpress-app_action' );
		delete_post_meta( $server_id, 'wpcd_server_wordpress-app_action_status' );
		delete_post_meta( $server_id, 'wpcd_server_wordpress-app_action_args' );
	}

	/**
	 * Sets the transient for cron check
	 * This will be set when user dismisses the notice for cron check
	 *
	 * Action Hook: wp_ajax_set_cron_check
	 */
	public function set_cron_check() {

		/* Nonce check */
		check_ajax_referer( 'wpcd-admin', 'nonce' );

		/* Permision check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss cron check.', 'wpcd' ) ) );
		}

		/* Permissions passed - set transient. */
		set_transient( 'wpcd_cron_check', 1, 12 * HOUR_IN_SECONDS );
		wp_die();

	}

	/**
	 * This function checks to see if commands can be run
	 * on the server.
	 *
	 * It does this by checking a variety of metas that
	 * variously indicates something is going on with the server.
	 *
	 * Filter Hook: wpcd_is_server_available_for_commands
	 *
	 * @param boolean $is_available   Current boolean that indicates whether the server is available.
	 * @param int     $server_id      Server id to check.
	 *
	 * @return boolean
	 */
	public function wpcd_is_server_available_for_commands( $is_available, $server_id ) {

		// Only need to check if server is unavailable if $is_available is true - we're not going to override it if it's already false!
		if ( true === $is_available ) {
			if ( ( ! empty( get_post_meta( $server_id, 'wpcd_server_wordpress-app_action', true ) ) ) || ( ! empty( get_post_meta( $server_id, 'wpcd_server_wordpress-app_action_status', true ) ) ) ) {
				return false;
			}
		}

		// Ok, so far the server is still available for commands.  Lets check the app records.
		$args = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'parent_post_id',
					'value' => $server_id,
				),
				array(
					'key'   => 'wpcd_app_wordpress-app_action_status',
					'value' => 'in-progress',
				),
			),
		);

		$app_posts = get_posts( $args );

		if ( $app_posts ) {
			return false;
		}

		return $is_available;
	}

	/**
	 * Return the REST API controller instance for a given name (base path)
	 *
	 * @param string $controller_name Name of controller.
	 *
	 * @return WPCD_REST_API_Controller_Base
	 */
	public function get_rest_controller( string $controller_name ) {
		return $this->rest_controllers[ $controller_name ];
	}

}
