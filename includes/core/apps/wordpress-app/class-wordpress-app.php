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
	use wpcd_wpapp_script_handlers;
	use wpcd_wpapp_push_commands;
	use wpcd_wpapp_admin_column_data;
	use wpcd_wpapp_backup_functions;
	use wpcd_wpapp_upgrade_functions;
	use wpcd_wpapp_woocommerce_support;
	use wpcd_wpapp_unused_functions;
	use wpcd_wpapp_multi_tenant_app;

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
		add_action( "wpcd_{$this->get_app_name()}_command_aptget_status_completed", array( &$this, 'push_command_aptget_status_completed' ), 10, 4 );  // When a server sends us information that apt-get is running - see bash script #24.
		add_action( "wpcd_{$this->get_app_name()}_command_posttypes_status_completed", array( &$this, 'push_command_posttypes_status_completed' ), 10, 6 );  // When a server sends us information about the post types on a site - see bash script #24.
		add_action( "wpcd_{$this->get_app_name()}_command_maldet_scan_completed", array( &$this, 'push_command_maldet_scan_completed' ), 10, 4 );  // When a server sends us a report of maldet scan results - see bash script #26.
		add_action( "wpcd_{$this->get_app_name()}_command_server_restart_completed", array( &$this, 'push_command_server_restart_completed' ), 10, 4 );  // When a server sends us a report of restart or shutdown.
		add_action( "wpcd_{$this->get_app_name()}_command_monit_log_completed", array( &$this, 'push_command_monit_log_completed' ), 10, 4 );  // When a server sends us a monit alert or report.
		add_action( "wpcd_{$this->get_app_name()}_command_start_domain_backup_completed", array( &$this, 'push_command_domain_backup_v1_started' ), 10, 4 );  // When a server sends us a notification telling us a scheduled backup was started for a domain.
		add_action( "wpcd_{$this->get_app_name()}_command_end_domain_backup_completed", array( &$this, 'push_command_domain_backup_v1_completed' ), 10, 4 );  // When a server sends us a notification telling us a scheduled backup was completed for a domain.
		add_action( "wpcd_{$this->get_app_name()}_command_server_config_backup_completed", array( &$this, 'push_command_server_config_backup' ), 10, 4 );  // When a server sends us a notification telling us a backup of the server configuration has started or ended.
		add_action( "wpcd_{$this->get_app_name()}_command_test_rest_api_completed", array( &$this, 'push_command_test_rest_api_completed' ), 10, 4 );  // When a server sends us a test notification (initiated from the TOOLS tab on a server screen).

		// Push commands and callbacks from sites.
		add_action( "wpcd_{$this->get_app_name()}_command_schedule_site_sync_completed", array( &$this, 'push_command_schedule_site_sync' ), 10, 4 );  // When a scheduled site sync has started or ended.
		add_action( "wpcd_{$this->get_app_name()}_command_install_logtivity_status_completed", array( &$this, 'push_command_install_logtivity_completed' ), 10, 4 );  // When logtivity has been installed.

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

		// Filter hook to setup additional search fields specific to the wordpress-app.
		add_filter( 'wpcd_app_search_fields', array( $this, 'wpcd_app_search_fields' ), 10, 1 );

		// Filter hook to add new columns to the APP list.
		add_filter( 'manage_wpcd_app_posts_columns', array( $this, 'app_posts_app_table_head' ), 10, 1 );

		// Action hook to add values in new columns in the APP list.
		add_action( 'manage_wpcd_app_posts_custom_column', array( $this, 'app_posts_app_table_content' ), 10, 2 );

		// Filter hook to add new columns to the SERVER list.
		add_filter( 'manage_wpcd_app_server_posts_columns', array( $this, 'app_server_table_head' ), 10, 1 );

		// Show some app details in the wp-admin list of apps.
		add_filter( 'wpcd_app_admin_list_summary_column', array( &$this, 'app_admin_list_summary_column' ), 10, 2 );

		// Show some app details in the wp-admin server column.
		add_filter( 'wpcd_app_admin_list_server_column_before_apps_link', array( &$this, 'app_admin_list_server_column_before_apps_link' ), 10, 2 );

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

		// Add primary Metabox.IO metaboxes for the WordPress app into the APP details CPT screen.
		add_filter( "wpcd_app_{$this->get_app_name()}_metaboxes", array( $this, 'add_meta_boxes' ), 10, 1 );

		// Add misc Metabox.IO metaboxes for the WordPress app into the APP details CPT screen. These will be placed in the sidebar or under the primary boxes.
		add_filter( 'rwmb_meta_boxes', array( $this, 'add_meta_boxes_misc' ), 10, 1 );

		// Add Metabox.IO metaboxes for the SERVER CPT into the server details CPT screen.
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_server_metaboxes' ), 10, 1 ); // Register application metabox stub with filter. Note that this is a METABOX.IO filter, not a core WP filter.
		add_filter( "wpcd_server_{$this->get_app_name()}_metaboxes", array( $this, 'add_meta_boxes_server' ), 10, 1 );
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_server_metaboxes_misc' ), 10, 1 ); // These will be placed in the sidebar or under the primary boxes.

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpapp_schedule_events_for_new_site' ), 10, 2 );

		// Action hook to set transient if directory is readable and .txt files are accessible.
		add_action( 'admin_init', array( $this, 'wpapp_admin_init_is_readable' ) );

		// Admin hook that will handle any automatic upgrades or database changes.
		add_action( 'admin_init', array( $this, 'wpapp_admin_init_app_silent_auto_upgrade' ) );  // This function is in the upgrade.php trait file.

		// Action hook to handle ajax request to set transient if user closed the readable notice check.
		add_action( 'wp_ajax_set_readable_check', array( $this, 'set_readable_check' ) );

		// Action hook to handle ajax request to set transient if user clicked the "check again" option in the "readable check" notice.
		add_action( 'wp_ajax_readable_check_again', array( $this, 'readable_check_again' ) );

		// Action hook to handle ajax request to execute passwordless login.
		add_action( 'wp_ajax_passwordless_login', array( $this, 'passwordless_login' ) );

		// Action hook to extend admin filter options.
		add_action( 'restrict_manage_posts', array( $this, 'wpapp_wpcd_app_table_filtering' ) );

		// Filter hook to filter app listing on custom meta data.
		add_filter( 'parse_query', array( $this, 'wpapp_wpcd_app_parse_query' ), 10, 1 );

		// Action hook to handle ajax request to set transient if user closed the notice for cron check.
		add_action( 'wp_ajax_set_cron_check', array( $this, 'set_cron_check' ) );

		// Action hook to handle ajax request to set transient if user closed the notice for php version check.
		add_action( 'wp_ajax_php_version_check', array( $this, 'php_version_check' ) );

		// Action hook to handle ajax request to set transient if user closed the notice for localhost check.
		add_action( 'wp_ajax_localhost_version_check', array( $this, 'localhost_version_check' ) );
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
	 * Include the files corresponding to the tabs for a site
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
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/security.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/statistics.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/logs.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/wp-site-users.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/wpconfig-options.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/redirect-rules.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/file-manager.php';

		if ( true === wpcd_is_git_enabled() ) {
			require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/git-control-site.php';
		}

		if ( true === wpcd_is_mt_enabled() ) {
			require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs/multitenant-site.php';
		}

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
		require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/ols_console.php';

		if ( true === wpcd_is_git_enabled() ) {
			require_once wpcd_path . 'includes/core/apps/wordpress-app/tabs-server/git_control.php';
		}

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

		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $app_id );
		$webserver_type_name = $this->get_web_server_description_by_id( $app_id );

		// SSL enabled?
		$ssl_status               = $this->get_site_local_ssl_status( $app_id );   // Returns a boolean.
		$ssl_status_display_value = true === $ssl_status ? __( 'On', 'wpcd' ) : __( 'Off', 'wpcd' );
		if ( true === boolval( $ssl_status ) ) {
			$ssl_class_name = 'wpcd_site_details_top_row_element_ssl_on';
		} else {
			$ssl_class_name = 'wpcd_site_details_top_row_element_ssl_off';
		}

		// Page Cache.
		$page_cache_status        = $this->get_page_cache_status( $app_id );
		$page_cache_display_value = 'on' === $page_cache_status ? __( 'On', 'wpcd' ) : __( 'Off', 'wpcd' );
		if ( 'on' === $page_cache_status ) {
			$page_cache_class_name = 'wpcd_site_details_top_row_element_page_cache_on';
		} else {
			$page_cache_class_name = 'wpcd_site_details_top_row_element_page_cache_off';
		}

		// Git.
		$git_status               = $this->get_git_status( $app_id );
		$git_status_display_value = '';
		if ( true === $git_status ) {
			$git_status_display_value = __( 'On', 'wpcd' );
			$git_status_class_name    = 'wpcd_site_details_top_row_element_git_status';

			$git_branch_display_value = $this->get_git_branch( $app_id );
			$git_branch_class_name    = 'wpcd_site_details_top_row_element_git_branch';  // Not used - for now we're using the $git_status_class_name for both git status and branch name.
		}

		// Site Type.
		$site_type               = $this->get_mt_site_type( $app_id );
		$site_type_display_value = '';
		if ( 'standard' !== $site_type && ! empty( $site_type ) ) {
			$site_type_display_value = $site_type;
			$site_type_class_name    = 'wpcd_site_details_top_row_element_site_type';
		}

		/**
		 * Wrap the page cache, ssl status and other elements into a set of spans that will go underneath the domain name.
		 */
		$other_data = '<div class="wpcd_site_details_top_row_element_wrapper">';

		if ( wpcd_is_admin() && ( ! wpcd_get_early_option( 'wordpress_app_disable_passwordless_login' ) ) ) {
			$passwordless_login_link = $this->get_passwordless_login_link_for_display( $app_id, __( 'Login', 'wpcd' ) );
			$other_data             .= '<span class="wpcd_medium_chicklet wpcd_site_details_top_row_element_passwordless_login">' . $passwordless_login_link . '</span>';
		}

		$other_data .= '<span class="wpcd_medium_chicklet wpcd_site_details_top_row_element_wstype">' . $webserver_type_name . '</span>';
		$other_data .= '<span class=" wpcd_medium_chicklet ' . $ssl_class_name . '">' . sprintf( __( 'SSL: %s', 'wpcd' ), $ssl_status_display_value ) . '</span>';
		$other_data .= '<span class=" wpcd_medium_chicklet ' . $page_cache_class_name . '">' . sprintf( __( 'Cache: %s', 'wpcd' ), $page_cache_display_value ) . '</span>';
		if ( ! empty( $git_status ) ) {
			/* Translators: %s is the git status (on or off). */
			$other_data .= '<span class=" wpcd_medium_chicklet ' . $git_status_class_name . '">' . sprintf( __( 'Git: %s', 'wpcd' ), $git_status_display_value ) . '</span>';
		}
		if ( ! empty( $git_branch_display_value ) ) {
			/* Translators: %s is the git branch name. */
			$other_data .= '<span class=" wpcd_medium_chicklet ' . $git_status_class_name . '">' . sprintf( __( 'Branch: %s', 'wpcd' ), $git_branch_display_value ) . '</span>';
		}
		if ( ! empty( $site_type_display_value ) ) {
			/* Translators: %s is the multi-tenant site type. */
			$other_data .= '<span class=" wpcd_medium_chicklet ' . $site_type_class_name . '">' . sprintf( __( '%s', 'wpcd' ), $site_type_display_value ) . '</span>';
		}
		$other_data .= '</div>';
		/* End Wrap page cache, ssl status and other elements */

		// Copy IP.
		$copy_app_ip = wpcd_wrap_clipboard_copy( $this->get_ipv4_address( $app_id ) );

		if ( wpcd_get_early_option( 'wpcd_show_ipv6' ) ) {
			$copy_app_ip .= wpcd_wrap_clipboard_copy( $this->get_ipv6_address( $app_id ) );
		}

		// There should be no 'other data' if the setting to not show it is enabled.
		if ( wpcd_get_option( 'wordpress_app_hide_chicklet_area_in_site_detail' ) ) {
			$other_data = '';
		}

		// Certain columns need to be expanded or contracted based on whether other columns are allowed to be shown or hidden.
		$view_admin_site_columns       = 2;
		$hide_view_apps_on_server_link = false;
		if ( true === (bool) wpcd_get_early_option( 'wordpress_app_hide_view_apps_on_server_link' ) && ! wpcd_is_admin() ) {
			$view_admin_site_columns       = 3;
			$hide_view_apps_on_server_link = true;
		}

		$fields[] = array(
			'name'    => __( 'Domain', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => wpcd_wrap_clipboard_copy( $this->get_domain_name( $app_id ) ) . $other_data,
			'columns' => 'left' === $this->get_tab_style() ? 4 : 4,
			'class'   => 'left' === $this->get_tab_style() ? 'wpcd_site_details_top_row wpcd_site_details_top_row_domain wpcd_site_details_top_row_domain_left' : 'wpcd_site_details_top_row wpcd_site_details_top_row_domain',
		);
		$fields[] = array(
			'name'    => __( 'IP', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => $copy_app_ip,
			'columns' => 'left' === $this->get_tab_style() ? 2 : 2,
			'class'   => 'wpcd_site_details_top_row wpcd_site_details_top_row_ip',
		);
		$fields[] = array(
			'name'    => __( 'Admin', 'wpcd' ),
			'id'      => 'wpcd_app_action_site-detail-header-view-admin',
			'type'    => 'button',
			'std'     => $this->get_formatted_wpadmin_link( $app_id ),
			'columns' => 'left' === $this->get_tab_style() ? $view_admin_site_columns : $view_admin_site_columns,
			'class'   => 'wpcd_site_details_top_row wpcd_site_details_top_row_admin_login',
		);
		$fields[] = array(
			'name'    => __( 'Front-end', 'wpcd' ),
			'id'      => 'wpcd_app_action_site-detail-header-view-site',
			'type'    => 'button',
			'std'     => $this->get_formatted_site_link( $app_id ),
			'columns' => 'left' === $this->get_tab_style() ? $view_admin_site_columns : $view_admin_site_columns,
			'class'   => 'wpcd_site_details_top_row wpcd_site_details_top_row_front_end',
		);

		if ( false === $hide_view_apps_on_server_link ) {
			$server_post_id = get_post_meta( $app_id, 'parent_post_id', true );
			if ( is_admin() ) {
				// We're viewing in the wp-admin area.
				$url = admin_url( 'edit.php?post_type=wpcd_app&server_id=' . (string) $server_post_id );
			} else {
				// We're viewing on the front-end.
				$url = get_permalink( (int) $server_post_id );
			}
			$apps_on_server = sprintf( '<a href="%s" target="_blank">%s</a>', $url, __( 'View Apps', 'wpcd' ) );

			$fields[] = array(
				'name'    => __( 'Apps on Server', 'wpcd' ),
				'id'      => 'wpcd_app_action_site-detail-header-view-apps',
				'type'    => 'button',
				'std'     => $apps_on_server,
				'columns' => 'left' === $this->get_tab_style() ? 2 : 2,
				'class'   => 'wpcd_site_details_top_row wpcd_site_details_top_row_apps_on_server',
			);
		}

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

		// Does a warning need to be shown because SITE PACKAGES are likely still running?
		if ( true === $this->wpcd_is_site_package_running( $app_id ) ) {
			$message  = '<b><small>' . __( 'WPCD: It appears we are still setting up this site for you.', 'wpcd' );
			$message .= '<br />';
			$message .= __( 'Certain actions for site packages are still running.', 'wpcd' );
			$message .= '<br />';
			$message .= __( 'The site should be available shortly!', 'wpcd' ) . '</b></small>';

			$fields[] = array(
				'type' => 'divider',
			);

			$fields[] = array(
				'name'  => __( 'Not-Ready Notice', 'wpcd' ),
				'type'  => 'custom_html',
				'std'   => $message,
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

		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );

		// Is git installed?
		$git_status = $this->get_git_status( $id );

		$other_data  = '<div class="wpcd_site_details_top_row_element_wrapper">';
		$other_data .= '<span class="wpcd_medium_chicklet wpcd_server_details_top_row_element_wstype">' . $webserver_type_name . '</span>';
		if ( true === $git_status ) {
			$other_data .= '<span class="wpcd_medium_chicklet wpcd_server_details_top_row_element_git_status">' . __( 'Git On', 'wpcd' ) . '</span>';
		}
		$other_data .= '</div>';

		$copy_ip = wpcd_wrap_clipboard_copy( WPCD_SERVER()->get_ipv4_address( $id ) );
		if ( wpcd_get_early_option( 'wpcd_show_ipv6' ) ) {
			$copy_ip .= wpcd_wrap_clipboard_copy( WPCD_SERVER()->get_ipv6_address( $id ) );
		}

		$fields['general-welcome-top-col_1'] = array(
			'name'    => __( 'Server Name', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'wpcd_server_name', true ) . $other_data,
			'columns' => 3,
			'class'   => 'wpcd_server_details_top_row wpcd_server_details_top_row_server_name',
		);

		$fields['general-welcome-top-col_2'] = array(
			'name'    => __( 'IP', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => $copy_ip,
			'columns' => 3,
			'class'   => 'wpcd_server_details_top_row wpcd_server_details_top_row_ip',
		);

		$fields['general-welcome-top-col_3'] = array(
			'name'    => __( 'Provider', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => WPCD()->wpcd_get_cloud_provider_desc( get_post_meta( $id, 'wpcd_server_provider', true ) ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row wpcd_server_details_top_row_provider',
		);

		$fields['general-welcome-top-col_4'] = array(
			'name'    => __( 'Region', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'wpcd_server_region', true ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row wpcd_server_details_top_row_region',
		);

		if ( is_admin() ) {
			$apps_url = admin_url( 'edit.php?post_type=wpcd_app&server_id=' . $id );
		} else {
			$apps_url = get_permalink( WPCD_WORDPRESS_APP_PUBLIC::get_apps_list_page_id() ) . '?server_id=' . (string) $id;
		}

		$fields['general-welcome-top-col_5'] = array(
			'name'    => __( 'Apps', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => sprintf( '<a href="%s" target="_blank">%d</a>', esc_url( $apps_url ), WPCD_SERVER()->get_app_count( $id ) ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row wpcd_server_details_top_row_app_count',
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
	 * Returns a filtered array of allowed WP versions.
	 *
	 * @since 5.0.0
	 *
	 * @return array.
	 */
	public static function get_wp_versions() {

		// @SEE: https://wordpress.org/download/releases/
		$versions          = array( 'latest', '6.5.3', '6.4.4', '6.3.4', '6.2.5', '6.1.6', '6.0.7', '5.9.9', '5.8.9', '5.7.11', '5.6.13', '5.5.14', '5.4.15', '5.3.17', '5.2.20', '5.1.18', '5.0.21', '4.9.25', '4.8.24', '4.7.28' );
		$override_versions = wpcd_get_option( 'wordpress_app_allowed_wp_versions' );

		if ( ! empty( $override_versions ) ) {
			$versions = $override_versions;
		}

		// Possibly add in the 'nightly' version option.
		if ( wpcd_get_option( 'wordpress_app_versions_show_nightly' ) ) {
			$versions = array_merge( $versions, array( 'nightly' ) );
		}

		return apply_filters( 'wpcd_allowed_wp_versions', $versions );

	}

	/**
	 * Return a default OS version based on whether settings has one or not.
	 *
	 * @since 5.3.9
	 *
	 * @return string.
	 */
	public static function get_default_os() {
		$default_os = wpcd_get_option( 'wordpress_app_default_os' );
		if ( empty( $default_os ) ) {
			$default_os = 'ubuntu2204lts';
		}
		return apply_filters( 'wpcd_default_os', $default_os );
	}

	/**
	 * Returns the default webserver type.
	 *
	 * @since 5.0.0
	 *
	 * @return string.
	 */
	public static function get_default_webserver() {
		$default_web_server = wpcd_get_option( 'wordpress_app_default_webserver' );
		if ( empty( $default_web_server ) ) {
			$default_web_server = 'nginx';
		}
		return apply_filters( 'wpcd_default_web_server_type', $default_web_server );
	}

	/**
	 * Returns the webserver installed on a server.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id The post id of the server or app record.
	 *
	 * @return string|boolean
	 */
	public function get_web_server_type( $post_id ) {

		// If for some reason we didn't get a server_id, retrieve it from the app.
		$server_id = $post_id;
		if ( 'wpcd_app_server' !== get_post_type( $server_id ) ) {
			$server_id = $this->get_server_id_by_app_id( $server_id );
		}

		$web_server_type = false;
		if ( ! empty( $server_id ) && ( ! is_wp_error( $server_id ) ) ) {
			$web_server_type = get_post_meta( $server_id, 'wpcd_server_webserver_type', true );
			if ( empty( $web_server_type ) ) {
				$web_server_type = 'nginx';
			}
		}

		return $web_server_type;

	}

	/**
	 * Get the webserver name (full name) installed on a server
	 *
	 * @since 5.0
	 *
	 * @param string $web_server_type key.
	 *
	 * @return string
	 */
	public static function get_web_server_description( $web_server_type ) {

		$return = $web_server_type;

		switch ( $web_server_type ) {
			case 'ols':
				$return = __( 'OpenLiteSpeed', 'wpcd' );
				break;

			case 'nginx':
				$return = __( 'Nginx', 'wpcd' );
				break;

			case 'ols-enterprise':
				$return = __( 'LiteSpeed Enterprise', 'wpcd' );
				break;
		}

		return $return;

	}

	/**
	 * Get the webserver name (full name) installed on a server given an app or server id.
	 *
	 * @since 5.0
	 *
	 * @param int $post_id The post id of the server or app record.
	 *
	 * @return string
	 */
	public function get_web_server_description_by_id( $post_id ) {

		$web_server_type = $this->get_web_server_type( $post_id );

		return $this->get_web_server_description( $web_server_type );

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

		$value = false;

		// Check standard ssl metas.
		if ( 'on' === get_post_meta( $app_id, 'wpapp_ssl_status', true ) ) {
			$value = true;
		} else {
			$value = false;
		}

		// Check wildcard ssl metas.
		if ( false === $value ) {
			if ( 'on' === get_post_meta( $app_id, 'wpapp_multisite_wildcard_ssl_status', true ) ) {
				$value = true;
			}
		}

		return $value;

	}

	/**
	 * Get the status of cusstom ssl stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return boolean
	 */
	public function get_site_local_custom_ssl_status( $app_id ) {

		$value = false;

		// Check custom ssl metas.
		if ( 'on' === get_post_meta( $app_id, 'wpapp_custom_ssl_status', true ) ) {
			$value = true;
		} else {
			$value = false;
		}

		return $value;

	}

	/**
	 * Set the status of custom ssl stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 * @param string $status 'on' or 'off.
	 */
	public function set_site_local_custom_ssl_status( $app_id, $status ) {

		update_post_meta( $app_id, 'wpapp_custom_ssl_status', $status );

	}

	/**
	 * Get the status of WILDCARD ssl stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return boolean
	 */
	public function get_site_local_wildcard_ssl_status( $app_id ) {

		$value = false;

		if ( 'on' === get_post_meta( $app_id, 'wpapp_multisite_wildcard_ssl_status', true ) ) {
			$value = true;
		}

		return $value;

	}

	/**
	 * Sets the status of debug metas.
	 *
	 * @param int         $app_id is the post id of the app record we're working with.
	 * @param bool|string $debug_status Should be 'on' or 'off'.
	 *
	 * @return void
	 */
	public function set_site_local_wpdebug_flag( $app_id, $debug_status ) {

		if ( true === $debug_status || 'on' === $debug_status ) {
			update_post_meta( $app_id, 'wpapp_wp_debug', 1 );
		} else {
			update_post_meta( $app_id, 'wpapp_wp_debug', 0 );
		}

	}

	/**
	 * Get the status of the wp_debug flag stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return boolean
	 */
	public function get_site_local_wpdebug_flag( $app_id ) {

		if ( 1 === (int) get_post_meta( $app_id, 'wpapp_wp_debug', true ) ) {
			return true;
		} else {
			return false;
		}

	}


	/**
	 * Get a formatted link to wp_admin area
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 * @param bool   $icon Whether to style with just an icon instead of text.
	 *
	 * @return string
	 */
	public function get_formatted_wpadmin_link( $app_id, $icon = false ) {

		// get ssl status first.
		$ssl = $this->get_site_local_ssl_status( $app_id );

		// get domain name.
		$domain = $this->get_domain_name( $app_id );

		if ( true == $ssl ) {
			$url_wpadmin = 'https://' . $domain . '/wp-admin';
		} else {
			$url_wpadmin = 'http://' . $domain . '/wp-admin';
		}

		if ( true === $icon ) {
			return sprintf( '<a href = "%s" target="_blank">' . '<i class="fa-duotone fa-user-unlock"></i>' . '</a>', $url_wpadmin );
		} else {
			return sprintf( '<a href = "%s" target="_blank">' . __( 'Admin Login', 'wpcd' ) . '</a>', $url_wpadmin );
		}

	}

	/**
	 * Get a formatted public admin link (front end admin link.)
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 * @param string $label The label for the link.
	 *
	 * @return string
	 */
	public function get_formatted_public_admin_link( $app_id, $label ) {

		$link = get_permalink( $app_id );
		return sprintf( '<a href = "%s" target="_blank">' . $label . '</a>', $link );

	}

	/**
	 * Returns a boolean true/false if PHP 5.5/7.0/7.1/7.2/7.3 is supposed to be installed.
	 *
	 * @param int    $server_id ID of server being interrogated.
	 * @param string $php_version PHP version being inquired about - eg: 56,70,71,72,73.
	 *
	 * @return boolean
	 */
	public function is_old_php_version_installed( $server_id, $php_version ) {

		// "old" versions are 5.6, 7.0, 7.1. 7.2, 7.3.
		if ( ! in_array( $php_version, array( '56', '70', '71', '72', '73' ) ) ) {
			return false;
		}

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.3.9' ) === -1 ) {
			// Versions of the plugin before 5.3.9 automatically install PHP 5.6/7.0./7.2/7.2/7.3.
			return true;
		} else {
			// See if it was manually installed via an upgrade process - which would leave a meta field value behind on the server CPT record.
			$meta_name    = '';
			$meta_name    = sprintf( 'wpcd_server_php%s_installed', $php_version );
			$is_installed = (bool) $this->get_server_meta_by_app_id( $server_id, $meta_name, true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( true === $is_installed ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if PHP 80 is supposed to be installed.
	 *
	 * @param int $server_id ID of server being interrogated.
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
	 * @param int $server_id ID of server being interrogated.
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
	 * Returns a boolean true/false if PHP 82 is supposed to be installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_php_82_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.2.6' ) > -1 ) {
			// Versions of the plugin after 5.2.6 automatically install PHP 8.2.
			return true;
		} else {
			// See if it was manually installed via an upgrade process - which would leave a meta field value behind on the server CPT record.
			$is_php82_installed = (bool) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_php82_installed', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( true === $is_php82_installed ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}
	
	/**
	 * Returns a boolean true/false if PHP 83 is supposed to be installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_php_83_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.7.0' ) > -1 ) {
			// Versions of the plugin after 5.7.0 automatically install PHP 8.3.
			return true;
		} else {
			// See if it was manually installed via an upgrade process - which would leave a meta field value behind on the server CPT record.
			$is_php83_installed = (bool) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_php83_installed', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( true === $is_php83_installed ) {
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
	 * @param int    $server_id ID of server being interrogated.
	 * @param string $php_version PHP version - eg: php56, php71, php72, php73, php74, php81, php82, php83 etc.
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
			// Versions of WPCD prior to 4.15 activated almost all php versions.  Except PHP 8.0, 8.1, 8.2 & 8.3 are special cases because of the timing of when these were added to servers.
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
				case 'php82':
					if ( ! $this->is_php_82_installed( $server_id ) ) {
						$return = false;
					}
					break;
				case 'php83':
					if ( ! $this->is_php_83_installed( $server_id ) ) {
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
	 * Set the state of a php version after it's been activated/deactivated.
	 *
	 * @param int    $server_id ID of server being interrogated.
	 * @param string $php_version PHP version - eg: php56, php71, php72 etc.
	 * @param string $php_activation_state - 'enabled', 'disabled'.
	 */
	public function set_php_activation_state( $server_id, $php_version, $php_activation_state ) {

		$current_php_activation_state = wpcd_maybe_unserialize( get_post_meta( $server_id, 'wpcd_wpapp_php_activation_state', true ) );
		if ( empty( $current_php_activation_state ) ) {
			$current_php_activation_state = array();
		}

		$current_php_activation_state[ $php_version ] = $php_activation_state;
		update_post_meta( $server_id, 'wpcd_wpapp_php_activation_state', $current_php_activation_state );

	}

	/**
	 * Returns a boolean true/false if the 6G firewall Rules are installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_6g_installed( $server_id ) {

		// Check first to see if they were removed.
		$was_6g_removed = (bool) $this->get_server_meta_by_app_id( $server_id, 'wpcd_6g_removed', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( true === $was_6g_removed ) {
			return false;
		}

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.4.0' ) <= 0 ) {
			// Versions of the plugin after 5.4.0 did not activate 6g (though the files were still added to the server).
			return true;
		}

		// If you got here, assume false.
		return false;
	}

	/**
	 * Returns a boolean true/false if the 7G V 1.5 Firewall Rules are installed.
	 *
	 * ***This is no longer needed - see the is_7g16_installed() function below instead.
	 *
	 * @param int $server_id ID of server being interrogated.
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
	 * Returns a boolean true/false if the 7G V 1.6 Firewall Rules are installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_7g16_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.2.6' ) > -1 ) {
			// Versions of the plugin after 5.2.6 automatically install 7g V 1.6.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_7g_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 1.6 ) {
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
	 * @param int $server_id ID of server being interrogated.
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
	 * @param int $server_id ID of server being interrogated.
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
	 * Returns a boolean true/false if wpcli 2.7 is installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_wpcli27_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '4.27.0' ) > -1 ) {
			// Versions of the plugin after 4.14.2 automatically install wpcli 2.7.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_wpcli_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 2.7 ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if wpcli 2.8 is installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_wpcli28_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.3.2' ) > -1 ) {
			// Versions of the plugin after 5.3.2 automatically install wpcli 2.8.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_wpcli_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 2.8 ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if wpcli 2.9 is installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_wpcli29_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.4.1' ) > -1 ) {
			// Versions of the plugin after 5.4.0 automatically install wpcli 2.9.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_wpcli_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 2.9 ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Returns a boolean true/false if wpcli 2.10 is installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_wpcli210_installed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.6.1' ) > -1 ) {
			// Versions of the plugin after 5.6.0 automatically install wpcli 2.10.
			return true;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_wpcli_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 2.10 ) {
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
	 * @param int $server_id ID of server being interrogated.
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
	 * @param int $server_id ID of server being interrogated.
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
	 * Returns a boolean true/false if the server is a 5.2.6 or later server.
	 *
	 * Unlike the is_460_or_later() function above, this one does not check to
	 * the server upgrade meta. Right now the processes that use this particular
	 * version check function does care about the upgrade meta.  We only want
	 * To check the original installed version.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_526_or_later( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.2.6' ) > -1 ) {
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

		// Assume true.
		$return = true;

		// If meta shows site is disabled then return false.
		if ( 'off' === $this->site_status( $app_id ) ) {
			$return = false;
		}

		// If other indicators show site is unavailable return false.
		// This part should probably be extracted into it's own function and added to the is_site_available() query in all the tabs.
		// But for now it's more expdient to commingle them.
		if ( true === $return ) {
			if ( false === $this->is_site_available_for_commands( true, $app_id ) ) {
				$return = false;
			}
		}

		return $return;
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
	 * Sets the admin lock status.
	 *
	 * @since 5.0
	 *
	 * @param int         $app_id Post id of app being updated.
	 * @param string|bool $status Status of admin lock ('on','off',true,false).
	 *
	 * @return void
	 */
	public function set_admin_lock_status( $app_id, $status ) {

		if ( true === $status || 'on' === $status ) {
			update_post_meta( $app_id, 'wpcd_wpapp_admin_lock_status', 'on' );
		}

		if ( false === $status || 'off' === $status ) {
			update_post_meta( $app_id, 'wpcd_wpapp_admin_lock_status', 'off' );
		}

	}

	/**
	 * Returns an indicator whether a site has the admin lock disabled or enabled.
	 *
	 * @since 5.0
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean
	 */
	public function get_admin_lock_status( $app_id ) {

		$current_status = get_post_meta( $app_id, 'wpcd_wpapp_admin_lock_status', true );

		if ( empty( $current_status ) ) {
			$current_status = 'off';
		}

		if ( 'off' === $current_status ) {
			return false;
		}

		if ( 'on' === $current_status ) {
			return true;
		}

		return false;

	}

	/**
	 * Sets the maximum number of sites allowed on a server.
	 *
	 * @since 5.0
	 *
	 * @param int $id ID of server to update.
	 * @param int $count Number of sites allowed on the server.
	 *
	 * @return boolean|object The return value from the update_post_meta function.
	 */
	public function set_server_max_sites_allowed( $id, $count ) {
		return update_post_meta( $id, 'wpcd_server_max_sites', $count );
	}


	/**
	 * Returns the maximum sites allowed on a server.
	 *
	 * @since 5.0
	 *
	 * @param int $id ID of server being interrogated.
	 *
	 * @return int
	 */
	public function get_server_max_sites_allowed( $id ) {
		return (int) get_post_meta( $id, 'wpcd_server_max_sites', true );
	}

	/**
	 * Returns true if server has exceeded the sites allowed, false otherwise.
	 * If the number of sites allowed on a server is set to zero then return false.
	 *
	 * @since 5.0
	 *
	 * @param int $id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function get_has_server_exceeded_sites_allowed( $id ) {

		// Make sure the ID is for a server post type.
		$server_id = 0;
		$post_type = get_post_type( $id );
		if ( ( 'wpcd_app_server' !== $post_type ) && ( 'wpcd_app' === $post_type ) ) {
			$server_id = $this->get_server_id_by_app_id( $id );
		} else {
			if ( 'wpcd_app_server' === $post_type ) {
				$server_id = $id;
			}
		}

		// If we don't have a server id return false.
		if ( ! $server_id ) {
			return false;
		}

		// Get max number of sites sites allowed on server.
		$sites_allowed = $this->get_server_max_sites_allowed( $server_id );

		if ( $sites_allowed > 0 ) {
			// Get count of sites on server.
			$current_count = WPCD_SERVER()->get_app_count( $server_id );

			if ( $current_count >= $sites_allowed ) {
				return true;
			} else {
				return false;
			}
		}

		return false;

	}

	/**
	 * Returns an indicator whether the page cache is enabled or not.
	 *
	 * @since 5.0
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string 'on' or 'off'
	 */
	public function get_page_cache_status( $app_id ) {
		// Default current status.
		$current_status = '';

		/**
		 * We have to handle older WPCD versions (versions prior to WPCD 5.0).
		 */
		// First, check to see if the nginx meta has a value.
		$nginx_value = get_post_meta( $app_id, 'wpapp_nginx_pagecache_status', true );

		// If it does, convert it to our new meta and delete the old value.
		if ( ! empty( $nginx_value ) ) {
			update_post_meta( $app_id, 'wpapp_pagecache_status', $nginx_value );
			delete_post_meta( $app_id, 'wpapp_nginx_pagecache_status' );
		}

		// Now we can pick up the current status after any conversions.
		$current_status = wpcd_maybe_unserialize( get_post_meta( $app_id, 'wpapp_pagecache_status', true ) );

		if ( empty( $current_status ) ) {
			$current_status = 'off';
		}

		return $current_status;

	}

	/**
	 * Return the PHP version that is the default for this version of the app.
	 */
	public function get_wpapp_default_php_version() {
		return '8.1';
	}

	/**
	 * Returns the default PHP version for a server.
	 *
	 * Versions of WPCD earlier than 4.30 used 7.4.
	 * Versions after that uses 8.1.
	 *
	 * @param int $server_id The post id of the server (or app).
	 *
	 * @return string
	 */
	public function get_default_php_version_for_server( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
		if ( version_compare( $initial_plugin_version, '4.30.0' ) > -1 ) {
			// Versions of the plugin after 4.30.0 automatically uses PHP 8.1.
			return '8.1';
		} else {
			// Earlier versions used PHP 7.4.
			return '7.4';
		}

	}

	/**
	 * Returns the default PHP version for a server without the period in it.
	 * Eg: 74 instead of 7.4
	 *
	 * @param int $server_id The post id of the server (or app).
	 *
	 * @return string
	 */
	public function get_default_php_version_no_period( $server_id ) {
		$version = $this->get_default_php_version_for_server( $server_id );
		$version = str_replace( '.', '', $version, );
		return $version;
	}

	/**
	 * Get PHP version for app.
	 *
	 * @param int $app_id post id of app.
	 *
	 * @return string
	 */
	public function get_php_version_for_app( $app_id ) {

		// Check to see if the version is stamped on the record.
		$php_version = wpcd_maybe_unserialize( get_post_meta( $app_id, 'wpapp_php_version', true ) );

		// If not, then do some tortured logic to guess at it.
		if ( empty( $php_version ) ) {
			// What is the PLUGIN WPCD initial version?
			$initial_plugin_version_on_app = get_post_meta( $app_id, 'wpcd_app_plugin_initial_version', true );
			if ( empty( $initial_plugin_version_on_app ) ) {
				// Just get the wpcd default plugin version since we don't have anything else to use.
				$php_version = '7.4';  // Must be an old version of WPCD since all records should include the wpcd version.
			} else {
				if ( version_compare( $initial_plugin_version_on_app, '4.30.0' ) > -1 ) {
					// Versions of the plugin after 4.30.0 automatically uses PHP 8.1.
					return '8.1';
				} else {
					// Earlier versions used PHP 7.4.
					return '7.4';
				}
			}
		}

		return $php_version;

	}

	/**
	 * Set the PHP version for app.
	 *
	 * @param int    $app_id post id of app.
	 * @param string $php_version new php version (7.4, 8.1 etc.).
	 */
	public function set_php_version_for_app( $app_id, $php_version ) {
		update_post_meta( $app_id, 'wpapp_php_version', $php_version );
	}

	/**
	 * Get an array of PHP versions that are valid and active on the server.
	 *
	 * @param int|string $id The post id of the server.
	 *
	 * @return array Associated array of php versions.
	 */
	public function get_php_versions( $id ) {

		/* What type of web server are we running? */
		$webserver_type = $this->get_web_server_type( $id );

		/* What OS are we running on? */
		$server_id = $this->get_server_id_by_app_id( $id );
		$os        = WPCD_SERVER()->get_server_os( $server_id );

		// Create single element array if php 8.0 is installed.
		if ( $this->is_php_80_installed( $id ) ) {
			$php80 = array( '8.0' => '8.0' );
		} else {
			$php80 = array();
		}

		// Create single element array if php 8.1 is installed.
		if ( $this->is_php_81_installed( $id ) ) {
			$php81 = array( '8.1' => '8.1' );
		} else {
			$php81 = array();
		}

		// Create single element array if php 8.2 is installed.
		if ( $this->is_php_82_installed( $id ) ) {
			$php82 = array( '8.2' => '8.2' );
		} else {
			$php82 = array();
		}
		
		// Create single element array if php 8.3 is installed.
		if ( $this->is_php_83_installed( $id ) ) {
			$php83 = array( '8.3' => '8.3' );
		} else {
			$php83 = array();
		}

		// Array of other PHP versions.
		switch ( $webserver_type ) {
			case 'ols':
			case 'ols-enterprise':
				/* Different versions of PHP are supported on each OS for OLS.  Notable here is it does not support php 5.6 at all and it doesn't support anything below 7.4 on Ubuntu 22.04. */
				switch ( $os ) {
					case 'ubuntu1804lts':
						$other_php_versions = array(
							'7.4' => '7.4',
							'7.3' => '7.3',
							'7.2' => '7.2',
							'7.1' => '7.1',
						);
						break;

					case 'ubuntu2004lts':
						$other_php_versions = array(
							'7.4' => '7.4',
							'7.3' => '7.3',
							'7.2' => '7.2',
						);
						break;

					case 'ubuntu2204lts':
						$other_php_versions = array(
							'7.4' => '7.4',
						);
						break;

					default:
						$other_php_versions = array(
							'7.4' => '7.4',
							'7.3' => '7.3',
							'7.2' => '7.2',
							'7.1' => '7.1',
						);
						break;
				}
				break;

			case 'nginx':
			default:
				$other_php_versions = array(
					'7.4' => '7.4',
					'7.3' => '7.3',
					'7.2' => '7.2',
					'7.1' => '7.1',
					'5.6' => '5.6',
				);
				break;
		}

		// Array of php version options.
		$php_select_options = array_merge(
			$other_php_versions,
			$php80,
			$php81,
			$php82,
			$php83
		);

		// Filter out inactive versions.  Only applies to NGINX.  OLS always have all versions listed in the above switch statement active.
		if ( 'nginx' === $webserver_type ) {
			// Remove invalid PHP versions (those that are deactivated on the server).
			$server_id = $this->get_server_id_by_app_id( $id );
			if ( ! empty( $server_id ) ) {
				$php_versions = array(
					'php56' => '5.6',
					'php71' => '7.1',
					'php72' => '7.2',
					'php73' => '7.3',
					'php74' => '7.4',
					'php80' => '8.0',
					'php81' => '8.1',
					'php82' => '8.2',
					'php82' => '8.3',
				);
				foreach ( $php_versions as $php_version_key => $php_version ) {
					if ( ! $this->is_php_version_active( $server_id, $php_version_key ) ) {
						if ( ! empty( $php_select_options[ $php_version ] ) ) {
							unset( $php_select_options[ $php_version ] );
						}
					}
				}
			}
		}

		return $php_select_options;

	}

	/**
	 * Sets the page cache status meta on an app record..
	 *
	 * @param int    $app_id ID of app.
	 * @param string $status The status to set - should be 'on' or 'off'.
	 *
	 * @return boolean|array|object
	 */
	public function set_page_cache_status( $app_id, $status ) {
		return update_post_meta( $app_id, 'wpapp_pagecache_status', $status );
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
	 * Gets the disk quota defined for a site.
	 *
	 * @param int $app_id The post id of the app we're working with.
	 *
	 * @return int disk quota defined for a site or global setting.
	 */
	public function get_site_disk_quota( $app_id ) {

		// Get the quota defined on the site.
		$disk_space_quota = (int) get_post_meta( $app_id, 'wpcd_app_disk_space_quota', true );

		// No quota? Check global default.
		if ( empty( $disk_space_quota ) ) {
			$disk_space_quota = (int) wpcd_get_option( 'wordpress_app_sites_default_disk_quota' );
		}

		return $disk_space_quota;

	}

	/**
	 * Sets the disk quota for a site.
	 *
	 * @param int $app_id The post id of the app we're working with.
	 * @param int $quota  The disk quota for the site in megabytes.
	 *
	 * @return boolean|wp_error|object The value returned from the update_post_meta function.
	 */
	public function set_site_disk_quota( $app_id, $quota ) {

		return update_post_meta( $app_id, 'wpcd_app_disk_space_quota', $quota );

	}

	/**
	 * Get the total amount of disk space used for the site.
	 *
	 * This only works if the callbacks are installed and have run at least once to populate the appropriate meta.
	 *
	 * @param int $app_id is the post id of the app record we're asking about.
	 */
	public function get_total_disk_used( $app_id ) {

		$disk_used = 0;

		$site_push_data = wpcd_maybe_unserialize( get_post_meta( $app_id, 'wpcd_site_status_push', true ) );

		if ( ! empty( $site_push_data ) ) {

			if ( ! empty( $site_push_data['domain_file_size'] ) ) {
				$disk_used = $disk_used + (int) $site_push_data['domain_file_size'];
			}

			if ( ! empty( $site_push_data['domain_file_size'] ) ) {
				$disk_used = $disk_used + (int) $site_push_data['domain_file_size'];
			}

			if ( ! empty( $site_push_data['domain_backup_size'] ) ) {
				$disk_used = $disk_used + (int) $site_push_data['domain_backup_size'];
			}
		}

		return $disk_used;

	}


	/**
	 * Get a formatted link to the front-end of the site
	 *
	 * @param int    $app_id is the post id of the app record we're asking about.
	 * @param string $label Label for link (optional).
	 * @param bool   $icon Whether to style with just an icon instead of text (optional).
	 *
	 * @return string
	 */
	public function get_formatted_site_link( $app_id, $label = '', $icon = false ) {

		// get ssl status first.
		$ssl = $this->get_site_local_ssl_status( $app_id );

		// get domain name.
		$domain = $this->get_domain_name( $app_id );

		// Label.
		if ( empty( $label ) ) {
			$label = __( 'View site', 'wpcd' );
		}

		if ( true == $ssl ) {
			$url_site = 'https://' . $domain;
		} else {
			$url_site = 'http://' . $domain;
		}
		if ( true === $icon ) {
			return sprintf( '<a href = "%s" target="_blank">' . '<i class="fa-duotone fa-sidebar"></i>' . '</a>', $url_site );
		} else {
			return sprintf( '<a href = "%s" target="_blank">' . $label . '</a>', $url_site );
		}

	}

	/**
	 * Sets the status of SSL metas and, if necessary, http2 as well.
	 *
	 * @param int    $app_id is the post id of the app record we're working with.
	 * @param string $ssl_status Should be 'on' or 'off'.
	 *
	 * @return void
	 */
	public function set_ssl_status( $app_id, $ssl_status ) {

		// What type of web server are we running?
		$webserver_type = $this->get_web_server_type( $app_id );

		update_post_meta( $app_id, 'wpapp_ssl_status', $ssl_status );

		// Update HTTP2 status based on webserver type.
		switch ( $webserver_type ) {
			case 'ols':
			case 'ols-enterprise':
				if ( 'on' === $ssl_status ) {
					// SSL turned on so we turn http2 on.
					update_post_meta( $app_id, 'wpapp_ssl_http2_status', 'on' );  // OLS always have http2, http3 and spdy turned on by default.
				} else {
					// SSL turned off so we turn http2 off.
					update_post_meta( $app_id, 'wpapp_ssl_http2_status', 'off' );
				}
				break;

			case 'nginx':
			default:
				// For NGINX we can only turn ssl on/off if HTTP2 is off so this meta is always going to be "off".
				update_post_meta( $app_id, 'wpapp_ssl_http2_status', 'off' );
				break;

		}

	}

	/**
	 * Is the site using a remote database?
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 *
	 * @return string 'yes', 'no'.
	 */
	public function is_remote_db( $app_id ) {

		$is_remote_database = (string) get_post_meta( $app_id, 'wpapp_is_remote_database', true );

		if ( empty( $is_remote_database ) ) {
			$is_remote_database = 'no';
		}

		return $is_remote_database;

	}

	/**
	 * Mark the site as having a remote db.
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 */
	public function enable_remote_db_flag( $app_id ) {

		update_post_meta( $app_id, 'wpapp_is_remote_database', 'yes' );

	}

	/**
	 * Mark the site as having a local db.
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 */
	public function disable_remote_db_flag( $app_id ) {

		update_post_meta( $app_id, 'wpapp_is_remote_database', 'no' );

	}

	/**
	 * Set the wp-login page http auth status
	 *
	 * @param int    $app_id is the post id of the app record we're working with.
	 * @param string $status Should be 'no' or 'yes'.
	 */
	public function set_wplogin_http_auth_status( $app_id, $status ) {

		update_post_meta( $app_id, 'wpapp_wplogin_basic_auth_status', $status );

	}

	/**
	 * Is the site wp-login page protected with http auth?
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 *
	 * @return string 'yes', 'no'.
	 */
	public function get_wplogin_http_auth_status( $app_id ) {

		$wplogin_basic_auth_status = get_post_meta( $app_id, 'wpapp_wplogin_basic_auth_status', true );

		if ( empty( $wplogin_basic_auth_status ) ) {
			$wplogin_basic_auth_status = 'off';
		}

		return $wplogin_basic_auth_status;

	}

	/**
	 * Set the http auth status for the site.
	 *
	 * @param int    $app_id is the post id of the app record we're working with.
	 * @param string $status Should be 'no' or 'yes'.
	 */
	public function set_site_http_auth_status( $app_id, $status ) {

		update_post_meta( $app_id, 'wpapp_basic_auth_status', $status );

	}

	/**
	 * Is the site protected by http auth?
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 *
	 * @return string 'yes', 'no'.
	 */
	public function get_site_http_auth_status( $app_id ) {

		$basic_auth_status = get_post_meta( $app_id, 'wpapp_basic_auth_status', true );

		if ( empty( $basic_auth_status ) ) {
			$basic_auth_status = 'off';
		}

		return $basic_auth_status;

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
	 * Get whether git is enabled for a server or site.
	 *
	 * @param int $post_id The post id of the server or site record.
	 *
	 * @return boolean
	 */
	public function get_git_status( $post_id ) {

		if ( 'wpcd_app_server' === (string) get_post_type( $post_id ) ) {
			return (bool) get_post_meta( $post_id, 'wpcd_wpapp_git_status', true );
		}

		if ( 'wpcd_app' === (string) get_post_type( $post_id ) ) {
			return (bool) get_post_meta( $post_id, 'wpapp_git_status', true );
		}

		return false;
	}

	/**
	 * Set the meta that contains the git status for the server or site.
	 *
	 * @param int      $post_id The post id of the server or site record.
	 * @param int|bool $git_status The git status 1/true 0/false.
	 *
	 * @return int|bool|string
	 */
	public function set_git_status( $post_id, $git_status ) {

		if ( 'wpcd_app_server' == get_post_type( $post_id ) ) {
			return update_post_meta( $post_id, 'wpcd_wpapp_git_status', (int) $git_status );
		}

		if ( 'wpcd_app' == get_post_type( $post_id ) ) {
			return update_post_meta( $post_id, 'wpapp_git_status', (int) $git_status );
		}

	}

	/**
	 * Get the branch name that we think the site is on.
	 *
	 * @param int $app_id The post id of the server or site record.
	 *
	 * @return string
	 */
	public function get_git_branch( $app_id ) {

		return (string) get_post_meta( $app_id, 'wpapp_git_branch', true );

	}

	/**
	 * Set the branch name that we think the site is on.
	 *
	 * @param int    $app_id The post id of the server or site record.
	 * @param string $branch The branch name to set.
	 */
	public function set_git_branch( $app_id, $branch ) {

		return update_post_meta( $app_id, 'wpapp_git_branch', $branch );

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
		$action = sanitize_text_field( filter_input( INPUT_POST, '_action', FILTER_UNSAFE_RAW ) );
		if ( empty( $action ) ) {
			$action = sanitize_text_field( filter_input( INPUT_GET, '_action', FILTER_UNSAFE_RAW ) );
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
	 * Return create server popup view
	 *
	 * @param string $view String indicating whether we're viewing popup from admin or frontend (public).
	 *
	 * @return void|string
	 */
	public function ajax_server_handle_create_popup( $view = 'admin' ) {

		/* Check permissions */
		if ( ! current_user_can( 'wpcd_provision_servers' ) ) {
			$invalid_msg = __( 'You don\'t have access to provision a server. Perhaps you\'re not logged in?', 'wpcd' );
			if ( $view == 'public' ) {
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

				/* Get some defaults froms settings so the popup can use - but only if it's used by wpcd admins. */
				if ( wpcd_is_admin() ) {
					$default_wp_user_id   = WPCD()->decrypt( wpcd_get_option( 'wordpress_app_default_wp_user_id' ) );
					$default_wp_password  = WPCD()->decrypt( wpcd_get_option( 'wordpress_app_default_wp_password' ) );
					$default_wp_email     = wpcd_get_option( 'wordpress_app_default_wp_email' );
					$default_site_package = wpcd_get_option( 'wordpress_app_sites_default_site_package' );
					if ( true === boolval( wpcd_get_option( 'wordpress_app_auto_gen_password' ) ) ) {
						$default_wp_password = wpcd_generate_default_password();
					}
				} else {
					$default_wp_user_id   = '';
					$default_wp_password  = '';
					$default_wp_email     = '';
					$default_wp_password  = '';
					$default_site_package = '';
				}

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
					$invalid_msg = __( 'You don\'t have access to provision a server. Perhaps you\'re not logged in?', 'wpcd' );
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

				// Validate the server name and return right away if invalid format.
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

				/**
				 * Certain combinations of webservers and os's aren't allowed.
				 */
				if ( 'ubuntu1804lts' === $os && ( 'ols' === $webserver || 'ols-enterprise' === $webserver ) ) {
					wp_send_json_error( array( 'msg' => __( 'OpenLiteSpeed is not yet supported on Ubuntu 18.04 LTS.', 'wpcd' ) ) );
					break;
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
					/* Translators: %1: Error message. */
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

			/* Delete server records */
			case 'delete-server-record':
				// If for some reason this is called via CRON, do nothing and return.
				// We're not doing anything to delete servers via cron right now.
				// But we might later so adding this check now since we have it in the wpcd_app_delete_post() function.
				if ( true === wp_doing_cron() || true === wpcd_is_doing_cron() ) {
					$result = array(
						'status' => __( 'You cannot delete a server record inside of a CRON process.', 'wpcd' ),
						'done'   => false,
					);
					break;
				}
				// Which server record are we deleting?.
				$delete_server_id = sanitize_text_field( wp_unslash( $_POST['server_id'] ) );
				$user_id          = (int) get_current_user_id();
				$post_author      = (int) get_post( $id )->post_author;
				/**
				 * We're not deleting anything if the current user does not have permission to delete the server record
				 * or if the current user id does not match the server author id.
				 * Note: Changes to this permission logic might also need to be done in function
				 * wpcd_app_server_delete_post() located in file class-wpcd-posts-app-server.php.
				 */
				if ( ! wpcd_is_admin() ) {
					if ( ! wpcd_user_can( $user_id, 'delete_server', $delete_server_id ) && $post_author !== $user_id ) {
						$result = array(
							'status' => __( "Sorry! you don't have permission to delete a server record.", 'wpcd' ),
							'done'   => false,
						);
						break;
					}
				}
				// If we got here, ok to delete the server record and related child posts.
				$deleted_server_record = wp_delete_post( $delete_server_id );
				if ( $deleted_server_record ) {
					wpcd_delete_child_posts( 'wpcd_app', $delete_server_id );
					$result = array(
						'status' => __( 'Server record successfully deleted.', 'wpcd' ),
						'done'   => true,
					);
				} else {
					$result = array(
						'status' => __( 'Failed! something went wrong during delete server record', 'wpcd' ),
						'done'   => false,
					);
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
			// data is coming in via $_REQUEST which means that the site is being provisioned via wp-admin or a UI.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );
			$id   = sanitize_text_field( $_REQUEST['id'] );  // Post ID of the server where the wp app is being installed.
		} else {
			// data is being passed in directly which means that the site is likely being provisioned by others such as via the WPCD woocommerce integration or the REST API or powertools bulk installs
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

		// Set site package id if one is passed in.
		$wp_site_package = 0;
		if ( ! empty( $args['wp_site_package'] ) ) {
			$wp_site_package = $args['wp_site_package'];
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
			'wp_site_package'      => escapeshellarg( $wp_site_package ),
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
		$additional['post_processing_script_site'] = '';
		if ( $this->wpcd_can_user_execute_bash_scripts() ) {
			$post_process_script                       = wpcd_get_option( 'wpcd_wpapp_custom_script_after_site_create' );
			$additional['post_processing_script_site'] = $post_process_script;
		}

		// Get the secret key manager api key from settings. Note that this will not end up in the site's postmeta.
		$secret_key_manager_api_key               = wpcd_get_option( 'wpcd_wpapp_custom_script_secrets_manager_api_key' );
		$additional['secret_key_manager_api_key'] = $secret_key_manager_api_key;

		/**
		 * Allow devs to hook into the array to add their own elements for use later - likely to be rarely used given that we now have the custom fields array.
		 * Filter Name: wpcd_wordpress-app_install_wp_app_parms.
		 */
		$additional = apply_filters( "wpcd_{$this->get_app_name()}_install_wp_app_parms", $additional, $args );

		/**
		 * Allow devs to validate data in a way that can terminate processing.
		 * Filter Name: wpcd_wordpress-app_install_wp_app_parms_validate.
		 */
		if ( ! apply_filters( "wpcd_{$this->get_app_name()}_install_wp_app_parms_validate", true, $additional, $args ) ) {
			return new \WP_Error( __( 'There are some invalid data in this create site request. Please correct and try again.', 'wpcd' ) );
		}

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

		/* Give other addons and developers a chance to update the new app record now that we've added all our preliminary data to it. */
		do_action( "wpcd_{$this->get_app_name()}_after_create_post", $app_post_id, $args, $additional );

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
			$appfields = array( 'domain', 'user', 'email', 'version', 'original_domain', 'site_package' );
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

			/* Stamp the PHP version on the app record. */
			$this->set_php_version_for_app( $app_post_id, $this->get_wpapp_default_php_version() );

			/* Add the password field to the CPT separately because it needs to be encrypted */
			update_post_meta( $app_post_id, 'wpapp_password', $this::encrypt( $args['wp_password'] ) );

			/**
			 * Page caching is enabled by default for both NGINX and OLS so update the post meta to show that.
			 * As of WPCD 5.0, we're retiring the wpapp_nginx_pagecache_status meta and using just
			 * 'wpapp_pagecache_status' instead.
			 */
			$this->set_page_cache_status( $app_post_id, 'on' );

			/**
			 * Object caching is enabled by default for both NGINX and OLS in WPCD 5.5.2 and later.
			 */
			if ( $this->is_redis_installed( $server_id ) ) {
				$this->set_app_redis_installed_status( $app_post_id, true );
			}

			/**
			 * Set Quotas
			 */
			$this->set_site_disk_quota( $app_post_id, wpcd_get_option( 'wordpress_app_sites_default_new_site_disk_quota' ) );

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

		// Switch PHP version.
		$this->handle_switch_php_version( $app_id, $instance );

		// Maybe disable page_cache.
		$this->handle_page_cache_for_new_site( $app_id, $instance );

		// Maybe disable redis object cache.
		$this->handle_redis_object_cache_for_new_site( $app_id, $instance );

		// Maybe activate solidwp security.
		$this->handle_solidwp_security_for_new_site( $app_id, $instance );

		// Maybe activate logtivity.
		$this->handle_logtivity_for_new_site( $app_id, $instance );

		// Handle site package rules.
		$this->handle_site_package_rules( $app_id );

	}

	/**
	 * Execute the site package rules for a site after install is complete.
	 *
	 * Warning: This only gets called automatically on new sites right now.
	 * It does not get called when sites are cloned.
	 * However, our WOOCOMMERCE module will manually force a to this for
	 * products that use template sites.
	 *
	 * @param int  $app_id The post id of the app record.
	 * @param int  $in_site_package_id Apply this site package to the site instead of reading it from post meta.
	 * @param bool $is_subscription_switch Whether this is being called from a woocommerce subscription switch.
	 */
	public function handle_site_package_rules( $app_id, $in_site_package_id = 0, $is_subscription_switch = false ) {

		if ( empty( $in_site_package_id ) ) {
			// Get the site package from the app record.
			$site_package_id = get_post_meta( $app_id, 'wpapp_site_package', true );
		} else {
			// Get the site package id from incoming args.
			$site_package_id = $in_site_package_id;
		}

		// Bail if no package id.
		if ( empty( $site_package_id ) ) {
			return;
		}

		// Get the server id from the app_id.
		$server_id = $this->get_server_id_by_app_id( $app_id );

		// Bail if no server id.
		if ( empty( $server_id ) ) {
			return;
		}

		// Get the post record.
		$app_post = get_post( $app_id );

		// Bail if not a post object.
		if ( ! $app_post || is_wp_error( $app_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' <> $this->get_app_type( $app_id ) ) {
			return;
		}

		// Get the domain.
		$domain = $this->get_domain_name( $app_id, );

		// Bail if we have no domain.
		if ( empty( $domain ) ) {
			return;
		}

		// Set transient to indicate to others that something is still happening.
		$transient_name = $app_id . 'wpcd_site_package_running';
		set_transient( $transient_name, 'running', 120 );

		// Get the class instance that will allow us to send dynamic commands to the server via ssh.
		$ssh = new WPCD_WORDPRESS_TABS();

		// Add any custom version label to wp-config as well as the tenant site.
		// Will override any version label from template site.
		$version_label = get_post_meta( $site_package_id, 'wpcd_site_package_app_version', true );
		if ( ! empty( $version_label ) ) {
			do_action( 'wpcd_wordpress-app_do_update_wpconfig_option', $app_id, 'WPCD_VERSION_LABEL', $version_label );
			update_post_meta( $app_id, 'wpcd_app_std_site_version_label', $version_label );
		}

		// Push custom wp-config.php data.
		$keypairs = get_post_meta( $site_package_id, 'wpcd_wp_config_custom_data', true );
		if ( ! empty( $keypairs ) ) {
			foreach ( $keypairs as $keypair ) {
				switch ( $keypair[1] ) {
					case 'true':
						// Value is 'true' so need to pass boolean with 'raw' parameter to wp-cli.
						do_action( 'wpcd_wordpress-app_do_update_wpconfig_option', $app_id, $keypair[0], true, 'yes' );
						break;
					case 'false':
						// Value is 'false' so need to pass boolean with 'raw' parameter to wp-cli.
						do_action( 'wpcd_wordpress-app_do_update_wpconfig_option', $app_id, $keypair[0], false, 'yes' );
						break;
					default:
						do_action( 'wpcd_wordpress-app_do_update_wpconfig_option', $app_id, $keypair[0], $keypair[1], 'no' );
				}
			}
		}

		// Add custom site metas.
		$keypairs = get_post_meta( $site_package_id, 'wpcd_site_package_site_meta', true );
		if ( ! empty( $keypairs ) ) {
			foreach ( $keypairs as $keypair ) {
				update_post_meta( $app_id, $keypair[0], $keypair[1] );
			}
		}

		// Add or update wp options to tenant site.
		$keypairs = get_post_meta( $site_package_id, 'wpcd_site_package_tenant_wp_option', true );
		if ( ! empty( $keypairs ) ) {
			foreach ( $keypairs as $keypair ) {
				// Add option.  If it exists already, this might error.
				$command    = sprintf( 'sudo su - "%s" -c "wp --no-color option add %s %s" ', $domain, $keypair[0], $keypair[1] );
				$action     = 'add_custom_wp_option';
				$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );

				// Update the option in case it already exists.
				$command    = sprintf( 'sudo su - "%s" -c "wp --no-color option update %s %s" ', $domain, $keypair[0], $keypair[1] );
				$action     = 'update_custom_wp_option';
				$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
			}
		}

		/**
		 * Custom bash script: Before.
		 * Bash scripts example output (in one long script - line breaks here for readability.):
		 * export DOMAIN=test004.wpcd.cloud &&
		 * sudo -E wget --no-check-certificate -O wpcd_site_package_script_new_subscription_before.sh "https://gist.githubusercontent.com/elindydotcom/4c9f96ac48199284227c0ad687aedf75/raw/5295a17b832d8bb3748e0970ba0857063fd63247/wpcd_subscription_switch_sample_script" > /dev/null 2>&1
		 * && sudo -E dos2unix wpcd_site_package_script_new_subscription_before.sh > /dev/null 2>&1 &&
		 * echo "Executing Product Package Subscription Switch Bash Custom Script..." &&
		 * sudo -E bash ./wpcd_site_package_script_new_subscription_before.sh
		 */
		if ( WPCD_SITE_PACKAGE()->can_user_execute_bash_scripts() ) {
			if ( false === $is_subscription_switch ) {
				// Prepare export vars for bash scripts.
				$exportvars = 'export DOMAIN=%s';
				$exportvars = sprintf( $exportvars, $domain );

				// Call bash script for new sites.
				$script = get_post_meta( $site_package_id, 'wpcd_bash_scripts_new_sites_before', true );
				if ( ! empty( $script ) ) {
					$command  = $exportvars . ' && ';
					$command .= 'sudo -E wget --no-check-certificate -O wpcd_site_package_script_new_subscription_before.sh "%s" > /dev/null 2>&1 ';
					$command  = sprintf( $command, $script );  // add the script name to the string.
					$command .= ' && sudo -E dos2unix wpcd_site_package_script_new_subscription_before.sh > /dev/null 2>&1';
					$command .= ' && echo "Executing Product Package New Site Bash Custom Script..." ';
					$command .= ' && sudo -E bash ./wpcd_site_package_script_new_subscription_before.sh';

					$action     = 'site_pkg_bash_new_site_package_before';
					$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
				}
			}

			if ( true === $is_subscription_switch ) {
				// Prepare export vars for bash scripts.
				$exportvars = 'export DOMAIN=%s';
				$exportvars = sprintf( $exportvars, $domain );

				// Call bash script for new sites.
				$script = get_post_meta( $site_package_id, 'wpcd_bash_scripts_subscription_switch_before', true );
				if ( ! empty( $script ) ) {
					$command  = $exportvars . ' && ';
					$command .= 'sudo -E wget --no-check-certificate -O wpcd_package_script_subscription_switch_before.sh "%s" > /dev/null 2>&1 ';
					$command  = sprintf( $command, $script );  // add the script name to the string.
					$command .= ' && sudo -E dos2unix wpcd_package_script_subscription_switch_before.sh > /dev/null 2>&1';
					$command .= ' && echo "Executing Product Package New Site Bash Custom Script..." ';
					$command .= ' && sudo -E bash ./wpcd_package_script_subscription_switch_before.sh';

					$action     = 'site_pkg_bash_new_site_package_before';
					$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
				}
			}
		}

		// PHP Work Values.
		$php_pm_max_children = get_post_meta( $site_package_id, 'wpcd_php_pm_max_children', true );
		if ( ! empty( $php_pm_max_children ) ) {
			// get rest of php worker values.
			$php_pm                   = get_post_meta( $site_package_id, 'wpcd_php_pm', true );
			$php_pm_max_children      = get_post_meta( $site_package_id, 'wpcd_php_pm_max_children', true );
			$php_pm_start_servers     = get_post_meta( $site_package_id, 'wpcd_php_pm_start_servers', true );
			$php_pm_min_spare_servers = get_post_meta( $site_package_id, 'wpcd_php_pm_min_spare_servers', true );
			$php_pm_max_spare_servers = get_post_meta( $site_package_id, 'wpcd_php_pm_max_spare_servers', true );

			// stick them all into an array.
			$php_pm_args['pm']                   = $php_pm;
			$php_pm_args['pm_max_children']      = $php_pm_max_children;
			$php_pm_args['pm_start_servers']     = $php_pm_start_servers;
			$php_pm_args['pm_min_spare_servers'] = $php_pm_min_spare_servers;
			$php_pm_args['pm_max_spare_servers'] = $php_pm_max_spare_servers;

			// Fire action hook.
			do_action( 'wpcd_wordpress-app_do_change_php_workers', $app_id, $php_pm_args );
		}

		// Switch PHP version.
		$new_php_version = get_post_meta( $site_package_id, 'wpcd_new_php_version', true );
		if ( ! empty( $new_php_version ) ) {
			// What is the version on the current site?
			$current_php_version = $this->get_php_version_for_app( $app_id );
			if ( (string) $new_php_version !== (string) $current_php_version ) {
				// Call the action hook that will switch the php versions.
				do_action( 'wpcd_wordpress-app_do_change_php_version', $app_id, $new_php_version );
			}
		}

		// PHP Memory Limit.
		$new_memory_limit = get_post_meta( $site_package_id, 'wpcd_php_memory_limit', true );
		if ( ! empty( $new_memory_limit ) ) {
			do_action( 'wpcd_wordpress-app_do_change_php_options', $app_id, 'memory_limit', $new_memory_limit . 'M' );
		}

		// Max Execution Time.
		$new_execution_time = get_post_meta( $site_package_id, 'wpcd_php_max_execution_time', true );
		if ( ! empty( $new_execution_time ) ) {
			do_action( 'wpcd_wordpress-app_do_change_php_options', $app_id, 'max_execution_time', $new_execution_time );
		}

		// Max Execution Time.
		$new_input_vars = get_post_meta( $site_package_id, 'wpcd_php_max_input_vars', true );
		if ( ! empty( $new_input_vars ) ) {
			do_action( 'wpcd_wordpress-app_do_change_php_options', $app_id, 'max_input_vars', $new_input_vars );
		}

		// Get the list of plugins to deactivate.
		$plugins_to_deactivate = get_post_meta( $site_package_id, 'wpcd_plugins_to_deactivate', true );
		$plugins_to_deactivate = trim( preg_replace( '/\s+/', ' ', $plugins_to_deactivate ) );  // Strip line breaks and replace with space to make a single string of plugins separated by spaces.

		// Deactivate plugins. This should not be necessary for new sites but might be called from WC in a subscription switch.
		// So, for its better for sequencing to do it here and deactivate plugins before attempting to activate anything else.
		if ( ! empty( $plugins_to_deactivate ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color plugin deactivate %s" ', $domain, $plugins_to_deactivate );
			$action     = 'site_pkg_deactivate_plugins';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Get the list of repo plugins to install and activate.
		$plugins_to_install_activate = get_post_meta( $site_package_id, 'wpcd_plugins_to_install_from_repo', true );
		$plugins_to_install_activate = trim( preg_replace( '/\s+/', ' ', $plugins_to_install_activate ) );  // Strip line breaks and replace with space to make a single string of plugins separated by spaces.

		// Install and activate repo plugins.
		if ( ! empty( $plugins_to_install_activate ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color plugin install %s --activate" ', $domain, $plugins_to_install_activate );
			$action     = 'site_pkg_activate_plugins_from_repo';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Get the list of custom url plugins to install and activate.
		$plugins_to_install_activate = get_post_meta( $site_package_id, 'wpcd_plugins_to_install_from_url', true );
		$plugins_to_install_activate = trim( preg_replace( '/\s+/', ' ', $plugins_to_install_activate ) );

		// Install and activate external/custom url plugins.
		if ( ! empty( $plugins_to_install_activate ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color plugin install %s --activate" ', $domain, $plugins_to_install_activate );
			$action     = 'site_pkg_activate_plugins_external';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Get the list of pre-installed plugins to activate.
		// This will really only apply if WC is in use with template sites.
		$plugins_to_activate = get_post_meta( $site_package_id, 'wpcd_plugins_to_activate', true );
		$plugins_to_activate = trim( preg_replace( '/\s+/', ' ', $plugins_to_activate ) );

		// Activate plugins.
		if ( ! empty( $plugins_to_activate ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color plugin activate %s" ', $domain, $plugins_to_activate );
			$action     = 'site_pkg_activate_plugins';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Get the list of repo themes to install.
		$themes_to_install = get_post_meta( $site_package_id, 'wpcd_themes_to_install_from_repo', true );
		$themes_to_install = trim( preg_replace( '/\s+/', ' ', $themes_to_install ) );

		// Install repo themes.
		if ( ! empty( $themes_to_install ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color theme install %s " ', $domain, $themes_to_install );
			$action     = 'site_pkg_install_themes_from_repo';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Get the list of external themes to install.
		$themes_to_install = get_post_meta( $site_package_id, 'wpcd_themes_to_install_from_url', true );
		$themes_to_install = trim( preg_replace( '/\s+/', ' ', $themes_to_install ) );

		// Install external themes.
		if ( ! empty( $themes_to_install ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color theme install %s " ', $domain, $themes_to_install );
			$action     = 'site_pkg_install_themes_external';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Activate theme.
		$theme_to_activate = get_post_meta( $site_package_id, 'wpcd_theme_to_activate', true );
		if ( ! empty( $theme_to_activate ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color theme activate %s" ', $domain, $theme_to_activate );
			$action     = 'site_pkg_activate_plugins';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Delete updraft folder.  Only handle on new sites since existing sites might have installed updraft and want to keep their local backups around while switching subscriptions.
		if ( false === $is_subscription_switch ) {
			$delete_updraft = get_post_meta( $site_package_id, 'wpcd_site_package_delete_updraft', true );
			if ( true === (bool) $delete_updraft ) {
				$command    = sprintf( 'sudo rm /var/www/%s/html/wp-content/updraft/*.zip && sudo rm /var/www/%s/html/wp-content/updraft/*.txt && sudo rm /var/www/%s/html/wp-content/updraft/*.gz && echo "Updraft Folder Contents Deleted." ', $domain, $domain, $domain );
				$action     = 'site_pkg_delete_updraft_folder_contents';
				$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
			}
		}

		// Delete debug.log  Only handle on new sites since existing sites might deliberately have it turned on and want to keep it around while switching subscriptions.
		if ( false === $is_subscription_switch ) {
			$delete_debug = get_post_meta( $site_package_id, 'wpcd_site_package_delete_debug', true );
			if ( true === (bool) $delete_debug ) {
				$command    = sprintf( 'sudo rm /var/www/%s/html/wp-content/debug.log && echo "Debug.log file Deleted." ', $domain );
				$action     = 'site_pkg_delete_debug_log';
				$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
			}
		}

		// Activate SolidWP on the site.
		if ( false === $is_subscription_switch ) {
			$activate_solidwp_security = get_post_meta( $site_package_id, 'wpcd_site_package_activate_solidwp_security', true );
			if ( true === (bool) $activate_solidwp_security ) {
				do_action( 'wpcd_wordpress-app_do-activate_solidwp_security_for_site', $app_id, '' );
			}
		}

		// Activate logtivity on the site.
		if ( false === $is_subscription_switch ) {
			$activate_logtivity = get_post_meta( $site_package_id, 'wpcd_site_package_activate_logtivity', true );
			if ( true === (bool) $activate_logtivity ) {
				do_action( 'wpcd_wordpress-app_do-activate_logtivity_for_site', $app_id, '' );
			}
		}

		// Disable HTTP Authentication.
		if ( false === $is_subscription_switch ) {
			$disable_http_auth = get_post_meta( $site_package_id, 'wpcd_site_package_disable_http_auth', true );
			if ( true === (bool) $disable_http_auth ) {
				do_action( 'wpcd_wordpress-app_do_site_disable_http_auth', $app_id );
			}
		}

		// Apply categories/groups to site.
		if ( false === $is_subscription_switch ) {
			$groups = get_post_meta( $site_package_id, 'wpcd_site_package_apply_categories_new_sites', true ); // taxomomy_advanced fields stores multiple values in a single comma delimited row so this will return a comma delimited string.
			if ( ! empty( $groups ) ) {
				wp_set_post_terms( $app_id, $groups, 'wpcd_app_group', true );  // Luckily wp_post_terms accepts comma-delimited strings for post so no need to explode into array.
			}
		}
		if ( true === $is_subscription_switch ) {
			// Add subscription-switch specific groups.
			$groups = get_post_meta( $site_package_id, 'wpcd_site_package_apply_categories_subscription_switch', true ); // taxomomy_advanced fields stores multiple values in a single comma delimited row so this will return a comma delimited string.
			if ( ! empty( $groups ) ) {
				wp_set_post_terms( $app_id, $groups, 'wpcd_app_group', true );  // Luckily wp_post_terms accepts comma-delimited strings for post so no need to explode into array.
			}

			// Remove groups - only happens for subscription switches.
			$groups = get_post_meta( $site_package_id, 'wpcd_site_package_remove_categories_subscription_switch', true ); // taxomomy_advanced fields stores multiple values in a single comma delimited row so this will return a comma delimited string.
			if ( ! empty( $groups ) ) {
				$groups = explode( ',', $groups );
				$groups = array_values( $groups );
				foreach ( $groups as $key => $group ) {
					wp_remove_object_terms( (int) $app_id, (int) $group, 'wpcd_app_group' );  // Casting to INT is very important otherwise this function doesn't work.
				}
			}
		}

		// Update expiration.
		$expiration_in_minutes = (int) get_post_meta( $site_package_id, 'wpcd_site_package_expire_site_minutes', true );
		if ( true === $is_subscription_switch ) {
			// If switching expiration, only update the expiration date on the site if the package expiration field is zero or empty.
			if ( empty( $expiration_in_minutes ) ) {
				WPCD_APP_EXPIRATION()->clear_expiration( $app_id );
			}
		} else {
			// New site.
			if ( ! empty( $expiration_in_minutes ) ) {
				WPCD_APP_EXPIRATION()->set_expiration( $app_id, $expiration_in_minutes );
			}
		}

		// Create quota records.
		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			$quota_profile = (int) get_post_meta( $site_package_id, 'wpcd_site_package_quota_profile', true );
			if ( ! empty( $quota_profile ) ) {
				WPCD_POSTS_QUOTA_LIMITS()->create_limits( $quota_profile, $app_id );
			}
		}

		// Search and replace here - future use.

		// Crons here - future use.

		// Plugin & Theme updates here - future use.

		/**
		 * Custom bash script: After.
		 * Bash scripts example output (in one long script - line breaks here for readability.):
		 * export DOMAIN=test004.wpcd.cloud &&
		 * sudo -E wget --no-check-certificate -O wpcd_site_package_script_new_subscription_after.sh "https://gist.githubusercontent.com/elindydotcom/4c9f96ac48199284227c0ad687aedf75/raw/5295a17b832d8bb3748e0970ba0857063fd63247/wpcd_subscription_switch_sample_script" > /dev/null 2>&1
		 * && sudo -E dos2unix wpcd_site_package_script_new_subscription_after.sh > /dev/null 2>&1 &&
		 * echo "Executing Product Package Subscription Switch Bash Custom Script..." &&
		 * sudo -E bash ./wpcd_site_package_script_new_subscription_after.sh
		 */
		if ( WPCD_SITE_PACKAGE()->can_user_execute_bash_scripts() ) {
			if ( false === $is_subscription_switch ) {
				// Prepare export vars for bash scripts.
				$exportvars = 'export DOMAIN=%s';
				$exportvars = sprintf( $exportvars, $domain );

				// Call bash script for new sites.
				$script = get_post_meta( $site_package_id, 'wpcd_bash_scripts_new_sites_after', true );
				if ( ! empty( $script ) ) {
					$command  = $exportvars . ' && ';
					$command .= 'sudo -E wget --no-check-certificate -O wpcd_site_package_script_new_subscription_after.sh "%s" > /dev/null 2>&1 ';
					$command  = sprintf( $command, $script );  // add the script name to the string.
					$command .= ' && sudo -E dos2unix wpcd_site_package_script_new_subscription_after.sh > /dev/null 2>&1';
					$command .= ' && echo "Executing Product Package New Site Bash Custom Script..." ';
					$command .= ' && sudo -E bash ./wpcd_site_package_script_new_subscription_after.sh';

					$action     = 'site_pkg_bash_new_site_package_after';
					$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
				}
			}
		}
		// If we get here then it means that we have completed the core site package rules.
		// So flag the record as such.
		update_post_meta( $app_id, 'wpapp_site_package_core_rules_complete', true );

		// Clear 'in-process' transient.
		$transient_name = $app_id . 'wpcd_site_package_running';
		delete_transient( $transient_name );

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
				do_action( 'wpcd_wordpress-app_do_toggle_ssl_status_on', $app_id, 'ssl-status' );
			}
		}

		return $dns_success;

	}

	/**
	 * Switch PHP version if necessary after a new site has been installed.
	 *
	 * Called from function wpcd_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site. It's not used here yet and could be empty.
	 */
	public function handle_switch_php_version( $app_id, $instance ) {

		// What's the default new php version?
		$new_php_default_version = wpcd_get_option( 'wordpress_app_sites_set_php_version' );

		// if empty new default php version, bail.
		if ( empty( $new_php_default_version ) ) {
			return true;
		}

		// Bail if the new default php version is the wpcd app default.
		if ( (string) $this->get_wpapp_default_php_version() === (string) $new_php_default_version ) {
			return true;
		}

		// Call the action hook that will switch the php versions.
		do_action( 'wpcd_wordpress-app_do_change_php_version', $app_id, $new_php_default_version );

		return true;

	}

	/**
	 * Disable page cache when WP install is complete.
	 *
	 * Called from function wpcd_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site.
	 */
	public function handle_page_cache_for_new_site( $app_id, $instance ) {

		if ( wpcd_get_option( 'wordpress_app_sites_disable_page_cache' ) ) {
			$instance['action_hook'] = 'wpcd_wordpress-app_pending_log_toggle_page_cache';
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'disable-page-cache', $app_id, $instance, 'ready', $app_id, __( 'Disable Page Cache For New Site', 'wpcd' ) );
		}

	}

	/**
	 * Remove redis object cache when WP install is complete.
	 *
	 * Called from function wpcd_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site.
	 */
	public function handle_redis_object_cache_for_new_site( $app_id, $instance ) {

		if ( wpcd_get_option( 'wordpress_app_sites_disable_redis_cache' ) ) {
			$instance['action_hook'] = 'wpcd_wordpress-app_pending_log_toggle_redis_object_cache';
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'disable-redis-object-cache', $app_id, $instance, 'ready', $app_id, __( 'Disable Redis Object Cache For New Site', 'wpcd' ) );
		}

	}

	/**
	 * Activate Logtivity when WP install is complete.
	 *
	 * Called from function wpcd_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site.
	 */
	public function handle_logtivity_for_new_site( $app_id, $instance ) {

		if ( wpcd_get_option( 'wordpress_app_sites_activate_logtivity' ) ) {
			$instance['action_hook'] = 'wpcd_wordpress-app_pending_log_activate_logtivity';
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'activate-logtivity', $app_id, $instance, 'ready', $app_id, __( 'Activate Logtivity On New Site', 'wpcd' ) );
		}

	}

	/**
	 * Activate SolidWP Security Pro when WP install is complete.
	 *
	 * Called from function wpcd_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site.
	 */
	public function handle_solidwp_security_for_new_site( $app_id, $instance ) {

		if ( wpcd_get_option( 'wordpress_app_sites_activate_solidwp_security' ) ) {
			$instance['action_hook'] = 'wpcd_wordpress-app_pending_log_activate_solidwp_security';
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'activate-solidwp-security', $app_id, $instance, 'ready', $app_id, __( 'Activate SolidWP Security Pro On New Site', 'wpcd' ) );
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
		$attributes['post_processing_script_server'] = '';
		if ( $this->wpcd_can_user_execute_bash_scripts() ) {
			$post_process_script                         = wpcd_get_option( 'wpcd_wpapp_custom_script_after_server_create' );
			$attributes['post_processing_script_server'] = $post_process_script;
		}

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
		$welcome_message .= '<b>' . __( 'Here are the basic steps needed to build your first server and deploy your first WordPress server & site:', 'wpcd' ) . '</b>';
		$welcome_message .= '<br />';

		$welcome_message .= '<ol>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Double-check that your current web server where this plugin is installed has the appropriate timeouts as listed at the bottom of this screen. You will not be able to build your first server unless your web server timeouts are increased.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Generate an API key in your server providers\' dashboard.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'If you see the startup wizard notice at the top of this screen and your server provider is one of DigitalOcean, Linode, Vultr, UpCloud or Hetzner - use it to automatically connect to your server provider and setup your SSH keys.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '</ol>';

		$welcome_message .= '';
		$welcome_message .= '<b>' . __( 'If you cannot use the wizard or do not see the wizard notice:', 'wpcd' ) . '</b>';
		$welcome_message .= '<br />';

		$welcome_message .= '<ol>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Make sure you have created and uploaded an SSH key pair to your server providers\' dashboard.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Add your cloud server provider API key and other credentials to the WPCLOUDDEPLOY → SETTINGS → CLOUD PROVIDERS tab.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '</ol>';

		$welcome_message .= '';
		$welcome_message .= '<b>' . __( 'Now you can deploy your first WordPress server & site:', 'wpcd' ) . '</b>';
		$welcome_message .= '<br />';

		$welcome_message .= '<ol>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Click on the ALL CLOUD SERVERS menu option and use the DEPLOY A NEW WordPress SERVER button to deploy a server.', 'wpcd' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'After the server is deployed, go back to the CLOUD SERVERS menu option and click the INSTALL WordPress button in the server list.', 'wpcd' );
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
	 * Sets the meta that indicates whether memcached is installed on a server.
	 *
	 * @param int    $server_id  The post id of the server or app.
	 * @param string $status true/false.
	 */
	public function set_server_memcached_installed_status( $server_id, $status ) {

		if ( true === $status ) {
			update_post_meta( $server_id, 'wpcd_wpapp_memcached_installed', 'yes' );
		} else {
			update_post_meta( $server_id, 'wpcd_wpapp_memcached_installed', 'no' );
		}

	}

	/**
	 * Returns whether memcached is installed on a server.
	 *
	 * @param int $id  The post id of the server or app.
	 */
	public function is_memcached_installed( $id ) {
		// What kind of id did we get?  App or server?
		if ( 'wpcd_app' === get_post_type( $id ) ) {
			$server_post = $this->get_server_by_app_id( $id );
			if ( $server_post ) {
				$server_id = $server_post->ID;
			}
		} elseif ( 'wpcd_app_server' === get_post_type( $id ) ) {
			$server_id = $id;
		}

		$meta_status = get_post_meta( $server_id, 'wpcd_wpapp_memcached_installed', true );

		if ( 'yes' === $meta_status ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set flag that indicates whether or not MEMCACHED is installed on a site.
	 *
	 * @param int         $app_id  The post id of the  app.
	 * @param bool|string $status true/false or 'on'/'off'.
	 */
	public function set_app_memcached_installed_status( $app_id, $status ) {

		if ( true === $status || 'on' === $status ) {
			update_post_meta( $app_id, 'wpapp_memcached_status', 'on' );
		} else {
			update_post_meta( $app_id, 'wpapp_memcached_status', 'off' );
		}

	}

	/**
	 * Returns whether MEMCACHED is installed on a site.
	 *
	 * @param int $app_id  The post id of the app.
	 */
	public function get_app_is_memcached_installed( $app_id ) {

		$meta_status = get_post_meta( $app_id, 'wpapp_memcached_status', true );

		if ( 'on' === $meta_status ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Sets the meta that indicates whether REDIS is installed on a server.
	 *
	 * @param int    $server_id  The post id of the server or app.
	 * @param string $status true/false.
	 */
	public function set_server_redis_installed_status( $server_id, $status ) {

		if ( true === $status ) {
			update_post_meta( $server_id, 'wpcd_wpapp_redis_installed', 'yes' );
		} else {
			update_post_meta( $server_id, 'wpcd_wpapp_redis_installed', 'no' );
		}

	}

	/**
	 * Returns whether REDIS is installed on a server.
	 *
	 * @param int $id  The post id of the server or app.
	 */
	public function is_redis_installed( $id ) {
		// What kind of id did we get?  App or server?
		if ( 'wpcd_app' === get_post_type( $id ) ) {
			$server_post = $this->get_server_by_app_id( $id );
			if ( $server_post ) {
				$server_id = $server_post->ID;
			}
		} elseif ( 'wpcd_app_server' === get_post_type( $id ) ) {
			$server_id = $id;
		}

		$meta_status = get_post_meta( $server_id, 'wpcd_wpapp_redis_installed', true );

		if ( 'yes' === $meta_status ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set flag that indicates whether or not REDIS is installed on a site.
	 *
	 * @param int         $app_id  The post id of the  app.
	 * @param bool|string $status true/false or 'on'/'off'.
	 */
	public function set_app_redis_installed_status( $app_id, $status ) {

		if ( true === $status || 'on' === $status ) {
			update_post_meta( $app_id, 'wpapp_redis_status', 'on' );
		} else {
			update_post_meta( $app_id, 'wpapp_redis_status', 'off' );
		}

	}

	/**
	 * Returns whether REDIS is installed on a site.
	 *
	 * @param int $app_id  The post id of the app.
	 */
	public function get_app_is_redis_installed( $app_id ) {

		$meta_status = get_post_meta( $app_id, 'wpapp_redis_status', true );

		if ( 'on' === $meta_status ) {
			return true;
		} else {
			return false;
		}

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
					$meta_status = $this->is_memcached_installed( $server_id );
					if ( true === $meta_status ) {
						$status = 'installed';
					} else {
						$status = 'not-installed';
					}
					break;

				case 'redis':
					$meta_status = $this->is_redis_installed( $server_id );
					if ( true === $meta_status ) {
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
			if ( ( is_object( $screen ) && 'wpcd_app' == $screen->post_type ) || WPCD_WORDPRESS_APP_PUBLIC::is_app_edit_page() ) {
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

		if ( in_array( $hook, array( 'edit.php' ) ) || in_array( $hook, array( 'post.php' ) ) ) {
			$screen = get_current_screen();
			if ( ( is_object( $screen ) && in_array( $screen->post_type, array( 'wpcd_app' ) ) ) || WPCD_WORDPRESS_APP_PUBLIC::is_apps_list_page() || WPCD_WORDPRESS_APP_PUBLIC::is_app_edit_page() ) {
				wp_enqueue_style( 'wpcd-wpapp-admin-app-css', wpcd_url . 'includes/core/apps/wordpress-app/assets/css/wpcd-wpapp-admin-app.css', array(), wpcd_scripts_version );

				wp_enqueue_script( 'wpcd-wpapp-admin-post-type-wpcd-app', wpcd_url . 'includes/core/apps/wordpress-app/assets/js/wpcd-wpapp-admin-post-type-wpcd-app.js', array( 'jquery' ), wpcd_scripts_version, true );
				wp_localize_script(
					'wpcd-wpapp-admin-post-type-wpcd-app',
					'params3',
					apply_filters(
						'wpcd_app_script_args',
						array(
							'nonce'                     => wp_create_nonce( 'wpcd-app' ),
							'_action'                   => 'remove',
							'passwordless_login_action' => 'passwordless_login',
							'i10n'                      => array(
								'remove_site_prompt' => __( 'Are you sure you would like to delete this site and data? This action is NOT reversible!', 'wpcd' ),
								'install_wpapp'      => __( 'Install WordPress', 'wpcd' ),
							),
							'install_wpapp_url'         => admin_url( 'edit.php?post_type=wpcd_app_server' ),
							'bulk_actions_confirm'      => __( 'Are you sure you want to perform this bulk action?', 'wpcd' ),
						),
						'wpcd-wpapp-admin-post-type-wpcd-app'
					)
				);
			}
		}

		$screen     = get_current_screen();
		$post_types = array( 'wpcd_app_server', 'wpcd_app', 'wpcd_team', 'wpcd_permission_type', 'wpcd_command_log', 'wpcd_ssh_log', 'wpcd_error_log', 'wpcd_pending_log', 'wpcd_app_update_log' );

		if ( ( is_object( $screen ) && in_array( $screen->post_type, $post_types ) ) || WPCD_WORDPRESS_APP_PUBLIC::is_public_page() ) {

			wp_enqueue_style( 'wpcd-wpapp-admin-app-css', wpcd_url . 'includes/core/apps/wordpress-app/assets/css/wpcd-wpapp-admin-app.css', array(), wpcd_scripts_version );

			wp_enqueue_script( 'wpcd-admin-common', wpcd_url . 'includes/core/apps/wordpress-app/assets/js/wpcd-admin-common.js', array( 'jquery' ), wpcd_scripts_version, true );
			wp_localize_script(
				'wpcd-admin-common',
				'readableCheck',
				array(
					'nonce'                    => wp_create_nonce( 'wpcd-admin' ),
					'action'                   => 'set_readable_check',
					'check_again_action'       => 'readable_check_again',
					'cron_check_action'        => 'set_cron_check',
					'php_version_check_action' => 'php_version_check',
					'localhost_check_action'   => 'localhost_version_check',
				)
			);
		}

		// Enqueue CSS scripts for the SITE UPDATE & SITE HISTORY screens.
		$screen     = get_current_screen();
		$post_types = array( 'wpcd_app_update_log', 'wpcd_app_update_plan' );
		if ( ( is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) ) {

			wp_enqueue_style( 'wpcd-wpapp-update-plans-css', wpcd_url . 'includes/core/apps/wordpress-app/assets/css/wpcd-wpapp-update-plans.css', array(), wpcd_scripts_version );

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
		// Schedule nothing if our 'better cron' constant is true.
		if ( defined( 'DISABLE_WPCD_CRON' ) && DISABLE_WPCD_CRON == true ) {
			return;
		}

		// setup temporary script deletion.
		wp_clear_scheduled_hook( 'wpcd_wordpress_file_watcher' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_wordpress_file_watcher' );

		// setup deferred instance actions schedule that acts on server records.
		wp_clear_scheduled_hook( 'wpcd_wordpress_deferred_actions_for_server' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_wordpress_deferred_actions_for_server' );

		// setup actions schedule that acts on app records.
		wp_clear_scheduled_hook( 'wpcd_wordpress_deferred_actions_for_apps' );
		wp_schedule_event( time(), 'every_minute', 'wpcd_wordpress_deferred_actions_for_apps' );
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
	public function wpapp_admin_init_is_readable() {
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

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
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

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - do readable check again.', 'wpcd' ) ) );
		}

		$this->wpapp_admin_init_is_readable();

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
	 * Get the link to be shown for a passwordless login.
	 * This is used in multiple places hence extracted into this function.
	 *
	 * @param int    $app_id The app_id for the site that needs this link.
	 * @param string $label The label to use for the link.
	 */
	public function get_passwordless_login_link_for_display( $app_id, $label ) {
		$return = sprintf(
			'<a class="wpcd_action_passwordless_login" data-wpcd-id="%d" data-wpcd-domain="%s" href="">%s</a>',
			$app_id,
			$this->get_domain_name( $app_id ),
			esc_html( $label ),
		);
		return apply_filters( 'wpcd_wordpress-app_passwordless_login_link', $return, $app_id );
	}


	/**
	 * Handle passwordless login ajax request.
	 *
	 * Action Hook: wp_ajax_passwordless_login
	 */
	public function passwordless_login() {

		/* Nonce check */
		check_ajax_referer( 'wpcd-app', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action.', 'wpcd' ) ) );
		}

		/**
		 * From here on out, we're going to use the style of processing that we use in tabs.
		 * But because we're not in a tab we have to change some things around!
		 */

		// Grab data out of $POST.
		$id     = (int) $_POST['id'];
		$domain = sanitize_text_field( $_POST['domain'] );

		// Get app/server details.
		$instance = $this->get_app_instance_details( $id );

		// Set action var.
		$action = 'wp_site_get_passwordless_link';

		// Bail if no app/server details.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			return new \WP_Error( $message );
		}

		// Create args array.
		$args['domain'] = escapeshellarg( $domain );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'passwordless_login.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'passwordless_login.txt' );

		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			$message = sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result );
			return new \WP_Error( $message );
		} else {
			// grab the very last line in the results that should contain the url.
			list($url_array[]) = array_slice( explode( PHP_EOL, trim( $result ) ), -1, 1 );
			$return            = array(
				'redirect_to' => $url_array[0],
			);
			wp_send_json_success( $return );
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
			echo wpcd_kses_select( $app_status );

			// PHP VERSION.
			$php_version_options = array(
				'7.4' => '7.4',
				'7.3' => '7.3',
				'7.2' => '7.2',
				'7.1' => '7.1',
				'5.6' => '5.6',
				'8.0' => '8.0',
				'8.1' => '8.1',
				'8.2' => '8.2',
				'8.3' => '8.3',
			);
			$php_version         = $this->generate_meta_dropdown( 'wpapp_php_version', __( 'PHP Version', 'wpcd' ), $php_version_options );
			echo wpcd_kses_select( $php_version );

			// CACHE.
			$cache_options  = array(
				'on'  => __( 'Enabled', 'wpcd' ),
				'off' => __( 'Disabled', 'wpcd' ),
			);
			$php_page_cache = $this->generate_meta_dropdown( 'wpapp_page_cache_status', __( 'Page Cache', 'wpcd' ), $cache_options );
			echo wpcd_kses_select( $php_page_cache );

			$php_object_cache = $this->generate_meta_dropdown( 'wpapp_object_cache_status', __( 'Object Cache', 'wpcd' ), $cache_options );
			echo wpcd_kses_select( $php_object_cache );

			// SITE NEEDS UPDATES.
			$updates_options    = array(
				'yes' => __( 'Yes', 'wpcd' ),
				'no'  => __( 'No', 'wpcd' ),
			);
			$site_needs_updates = $this->generate_meta_dropdown( 'wpapp_sites_needs_updates', __( 'Site Needs Updates', 'wpcd' ), $updates_options );
			echo wpcd_kses_select( $site_needs_updates );

			/* Stuff only the admin should see. */
			if ( wpcd_is_admin() ) {

				/**
				 * Filters for site expiration status.
				 */
				$expiration_options = array(
					'1' => __( 'Expired', 'wpcd' ),
					'0' => __( 'Not Expired (Marked)', 'wpcd' ),
				);
				$expiration         = $this->generate_meta_dropdown( 'wpcd_app_expired_status', __( 'Expiration Status', 'wpcd' ), $expiration_options );

				echo wpcd_kses_select( $expiration );

				/**
				 * Filters specific to WooCommerce
				 */
				if ( true === wpcd_is_wc_module_enabled() || true === wpcd_is_mt_enabled() ) {

					// TEMPLATE FLAGS.
					$template_flag_options = array(
						'1' => __( 'Yes', 'wpcd' ),
						'0' => __( 'No', 'wpcd' ),
					);
					$is_template           = $this->generate_meta_dropdown( 'wpapp_is_template', __( 'Template', 'wpcd' ), $template_flag_options );
					echo wpcd_kses_select( $is_template );

				}

				/**
				 * Filters specific to Multi-tenant
				 */
				if ( true === wpcd_is_mt_enabled() ) {

					// MT SITE TYPE.
					$mt_site_type = WPCD_POSTS_APP()->generate_meta_dropdown( 'wpcd_app', 'wpcd_app_mt_site_type', __( 'MT Site Type', 'wpcd' ) );
					echo wpcd_kses_select( $mt_site_type );

					// MT VERSION.
					$mt_site_type = WPCD_POSTS_APP()->generate_meta_dropdown( 'wpcd_app', 'wpcd_app_mt_version', __( 'MT Version', 'wpcd' ) );
					echo wpcd_kses_select( $mt_site_type );

					// MT PARENT ID.
					$mt_parent_id = WPCD_POSTS_APP()->generate_meta_dropdown( 'wpcd_app', 'wpcd_app_mt_parent', __( 'MT Parent Template', 'wpcd' ) );
					echo wpcd_kses_select( $mt_parent_id );
				}

				/**
				 * Filters specific to WooCommerce
				 */
				if ( true === wpcd_is_wc_module_enabled() ) {
					// IS WOOCOMMERCE ORDER?
					$selected_value      = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_app_is_wc', FILTER_UNSAFE_RAW ) ); // Get existing value selected, if any.
					$html_output_for_wc  = '<select name="wpcd_app_is_wc" id="filter-by-wpcd_app_is_wc">';
					$html_output_for_wc .= '<option>' . __( 'Is WC Order?', 'wpcd' ) . '</option>';
					if ( '1' === $selected_value ) {
						$html_output_for_wc .= '<option value="1" selected="selected">' . __( 'Yes', 'wpcd' ) . '</option>';
					} else {
						$html_output_for_wc .= '<option value="1">' . __( 'Yes', 'wpcd' ) . '</option>';
					}
					$html_output_for_wc .= ' </select>';
					echo wpcd_kses_select( $html_output_for_wc );
				}
			}
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

		$filter_action = sanitize_text_field( filter_input( INPUT_GET, 'filter_action', FILTER_UNSAFE_RAW ) );
		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ! empty( $filter_action ) ) {
			$qv = &$query->query_vars;

			// APP STATUS.
			if ( isset( $_GET['wpapp_site_status'] ) && ! empty( $_GET['wpapp_site_status'] ) ) {
				$wpapp_site_status = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_site_status', FILTER_UNSAFE_RAW ) );

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
				$wpapp_php_version = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_php_version', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_php_version === $this->get_wpapp_default_php_version() ) {

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
				$wpapp_page_cache_status = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_page_cache_status', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_page_cache_status === 'off' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_pagecache_status',
							'value'   => $wpapp_page_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_pagecache_status',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpapp_pagecache_status',
						'value'   => $wpapp_page_cache_status,
						'compare' => '=',
					);
				}
			}

			// OBJECT CACHE.
			if ( isset( $_GET['wpapp_object_cache_status'] ) && ! empty( $_GET['wpapp_object_cache_status'] ) ) {
				$wpapp_object_cache_status = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_object_cache_status', FILTER_UNSAFE_RAW ) );

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
				$wpapp_sites_needs_updates = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_sites_needs_updates', FILTER_UNSAFE_RAW ) );

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

			// SITE EXPIRATION.
			if ( isset( $_GET['wpcd_app_expired_status'] ) && ! empty( $_GET['wpcd_app_expired_status'] ) ) {
				$wpapp_site_expired_status = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_app_expired_status', FILTER_UNSAFE_RAW ) );

				if ( '0' === $wpapp_site_expired_status || '1' === $wpapp_site_expired_status ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpcd_app_expired_status',
							'value'   => $wpapp_site_expired_status,
							'type'    => 'NUMERIC',
							'compare' => '=',
						),
					);
				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpcd_app_expired_status',
						'value'   => $wpapp_site_expired_status,
						'compare' => '=',
					);
				}
			}

			// Template Flag.
			// @todo: This logic does not handle empty metas.
			if ( isset( $_GET['wpapp_is_template'] ) && ! empty( $_GET['wpapp_is_template'] ) ) {
				$wpapp_template_flag = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_is_template', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_template_flag === '1' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpcd_is_template_site',
							'value'   => $wpapp_template_flag,
							'compare' => '=',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpcd_is_template_site',
						'value'   => $wpapp_template_flag,
						'compare' => '=',
					);
				}
			}

			// MT Site Type.
			if ( isset( $_GET['wpcd_app_mt_site_type'] ) && ! empty( $_GET['wpcd_app_mt_site_type'] ) ) {
				$wpapp_mt_site_type = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_app_mt_site_type', FILTER_UNSAFE_RAW ) );

				if ( ! empty( $wpapp_mt_site_type ) ) {
					$qv['meta_query'][] = array(
						array(
							'key'     => 'wpcd_app_mt_site_type',
							'value'   => $wpapp_mt_site_type,
							'compare' => '=',
						),
					);
				}
			}

			// MT Version.
			if ( isset( $_GET['wpcd_app_mt_version'] ) && ! empty( $_GET['wpcd_app_mt_version'] ) ) {
				$wpcd_app_mt_version = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_app_mt_version', FILTER_UNSAFE_RAW ) );

				if ( ! empty( $wpcd_app_mt_version ) ) {
					$qv['meta_query'][] = array(
						array(
							'key'     => 'wpcd_app_mt_version',
							'value'   => $wpcd_app_mt_version,
							'compare' => '=',
						),
					);
				}
			}

			// MT Parent.
			if ( isset( $_GET['wpcd_app_mt_parent'] ) && ! empty( $_GET['wpcd_app_mt_parent'] ) ) {
				$wpcd_app_mt_parent = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_app_mt_parent', FILTER_UNSAFE_RAW ) );

				if ( ! empty( $wpcd_app_mt_parent ) ) {
					$qv['meta_query'][] = array(
						array(
							'key'     => 'wpcd_app_mt_parent',
							'value'   => $wpcd_app_mt_parent,
							'compare' => '=',
						),
					);
				}
			}

			// IS WOOCOMMERCE ORDER?
			if ( wpcd_is_admin() ) {
				if ( isset( $_GET['wpcd_app_is_wc'] ) && ! empty( $_GET['wpcd_app_is_wc'] ) ) {
					$wpapp_is_wc = sanitize_text_field( filter_input( INPUT_GET, 'wpcd_app_is_wc', FILTER_UNSAFE_RAW ) );

					if ( '1' === $wpapp_is_wc ) {

						$qv['meta_query'][] = array(
							'relation' => 'OR',
							array(
								'key'     => 'wpapp_wc_subscription_id',
								'value'   => '',
								'compare' => '!=',
							),
						);
					}
				}
			}
		}

		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || wpcd_is_public_apps_list_query( $query ) ) && 'wpcd_app' === $query->query['post_type'] && ! empty( $_GET['wpapp_php_version'] ) && empty( $filter_action ) ) {

			$qv               = &$query->query_vars;
			$qv['meta_query'] = array();

			$wpapp_php_version = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_php_version', FILTER_UNSAFE_RAW ) );

			if ( $wpapp_php_version === $this->get_wpapp_default_php_version() ) {
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
		$get_field_key = sanitize_text_field( filter_input( INPUT_GET, $field_key, FILTER_UNSAFE_RAW ) );
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

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss cron check.', 'wpcd' ) ) );
		}

		/* Permissions passed - set transient. */
		set_transient( 'wpcd_cron_check', 1, 12 * HOUR_IN_SECONDS );
		wp_die();

	}

	/**
	 * Sets the transient for php version check
	 * This will be set when user dismisses the notice for php version check
	 *
	 * Action Hook: wp_ajax_php_version_check
	 */
	public function php_version_check() {

		/* Nonce check */
		check_ajax_referer( 'wpcd-admin', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss php version check.', 'wpcd' ) ) );
		}

		/* Permissions passed - set transient. */
		set_transient( 'wpcd_php_version_check', 1, 24 * HOUR_IN_SECONDS );
		wp_die();

	}

	/**
	 * Sets the transient for localhost check
	 * This will be set when user dismisses the notice for localhost check
	 *
	 * Action Hook: wp_ajax_localhost_version_check
	 */
	public function localhost_version_check() {

		/* Nonce check */
		check_ajax_referer( 'wpcd-admin', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss localhost check.', 'wpcd' ) ) );
		}

		/* Permissions passed - set transient. */
		set_transient( 'wpcd_localhost_check', 1, 24 * HOUR_IN_SECONDS );
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

		// Is aptget running?
		if ( $this->wpcd_is_aptget_running( $server_id ) ) {
			return false;
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
	 * Checks a special transient to see if aptget is running on the server.
	 *
	 * @param int $server_id The post id of the server.
	 *
	 * @return boolean
	 */
	public function wpcd_is_aptget_running( $server_id ) {

		$is_running = false;

		$transient_name = $server_id . 'wpcd_server_aptget_status';
		$running_status = get_transient( $transient_name );
		if ( 'running' === $running_status ) {
			$is_running = true;
		}

		return $is_running;

	}

	/**
	 * This function checks to see if commands can be run
	 * on the site.
	 *
	 * Filter Hook: wpcd_is_site_available_for_commands [hook not used anywhere - for future use - this function is called directly for now.]
	 *
	 * @param boolean $is_available   Current boolean that indicates whether the server is available.
	 * @param int     $app_id App id to check.
	 *
	 * @return boolean
	 */
	public function is_site_available_for_commands( $is_available, $app_id ) {

		if ( true === $is_available ) {
			if ( true === $this->wpcd_is_site_package_running( $app_id ) ) {
				return false;
			}
		}

		return $is_available;
	}

	/**
	 * Checks a special transient to see if site package might be running for an app/site.
	 *
	 * @param int $app_id The post id of the app/site.
	 *
	 * @return boolean
	 */
	public function wpcd_is_site_package_running( $app_id ) {

		$is_running = false;

		$transient_name = $app_id . 'wpcd_site_package_running';
		$running_status = get_transient( $transient_name );
		if ( 'running' === $running_status ) {
			$is_running = true;
		}

		return $is_running;
	}

	/**
	 * Return whether or not bash scripts can be run in site packages.
	 *
	 * You might not want to run them if WPCD is installed in a shared sites
	 * environment (saas).
	 *
	 * @param int $user_id User id running site packages - not used yet.
	 */
	public function wpcd_can_user_execute_bash_scripts( $user_id = 0 ) {

		if ( ! defined( 'WPCD_CUSTOM_SCRIPTS_NO_BASH' ) || ( defined( 'WPCD_CUSTOM_SCRIPTS_NO_BASH' ) && false === (bool) WPCD_CUSTOM_SCRIPTS_NO_BASH ) ) {
			return true;
		}

		return false;

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
