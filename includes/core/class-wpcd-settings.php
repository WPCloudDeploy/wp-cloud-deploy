<?php
/**
 * This class handles settings for the main
 * plugin and includes hooks for
 * allowing apps to create their own
 * settings.
 *
 * This core class writes all options
 * to 'wpcd_settings' using metabox.io
 * which in turn just calls the
 * WordPress options api.
 *
 * Add-ons/apps can create their own
 * options page and setting variable
 * but we don't recommend that unless
 * absolutely necessary.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup settings screen
 *
 * @package wpcd
 * @version 1.0.0 / wpcd
 * @since 1.0.0
 */
class WPCD_Settings {

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
	 * WPCD_Settings constructor.
	 */
	public function __construct() {

		// Action hook to clear provider cache.
		add_action( 'wp_ajax_wpcd_provider_clear_cache', array( $this, 'wpcd_provider_clear_cache' ) );

		// Action hook to automatically create ssh keys.
		add_action( 'wp_ajax_wpcd_provider_auto_create_ssh_key', array( $this, 'wpcd_provider_auto_create_ssh_key' ) );

		// Action hook to automatically create ssh keys.
		add_action( 'wp_ajax_wpcd_provider_auto_create_ssh_key', array( $this, 'wpcd_provider_auto_create_ssh_key' ) );

		// Action hook to test provider connection.
		add_action( 'wp_ajax_wpcd_provider_test_provider_connection', array( $this, 'wpcd_provider_test_provider_connection' ) );

		// Action hook to check for plugin updates. This is initiated via a button on the license tab on the settings screen.
		add_action( 'wp_ajax_wpcd_check_for_updates', array( $this, 'wpcd_check_for_updates' ) );

		// Action hook to validate licenses. This is initiated via a button on the license tab on the settings screen.
		add_action( 'wp_ajax_wpcd_validate_licenses', array( $this, 'wpcd_validate_licenses' ) );

		// Action hook to reset defaults brand colors.
		add_action( 'wp_ajax_wpcd_reset_defaults_brand_colors', array( $this, 'wpcd_reset_defaults_brand_colors' ) );

		// Action hook to check licenses after fields are saved.  This is initiated via a checkbox on the license tab on the settings screen.
		add_action( 'rwmb_after_save_field', array( $this, 'check_license' ), 10, 5 );

		// Action hook to handle wisdom opt-out settings after fields are saved.  This is initiated via a checkbox on the license tab on the settings screen.
		add_action( 'rwmb_after_save_field', array( $this, 'handle_wisdom_opt_out' ), 10, 5 );

		// Action hook to check for updates when the WP admin screen is initialized.
		// This hook used to be admin_init but changed to init to support auto updates feaure released in WP 5.5.0.
		add_action( 'init', array( $this, 'check_for_updates' ) );

		$this->display_settings();

	}


	/**
	 * Main function for creating the settings page.
	 */
	public function display_settings() {

		// Load up css and js scripts used for this screen.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

		// Register an options page.
		add_filter( 'mb_settings_pages', array( &$this, 'register_options_page' ) );

		// Register meta boxes and fields for settings page.
		add_filter(
			'rwmb_meta_boxes',
			function ( $meta_boxes ) {

				// Set some long text used for the timeout description.
				$ssh_timeout_desc  = __( 'If you are experiencing unusual behavior when running commands, such as server deployment hanging or not completing, it might be due to one or more timeout settings on the WordPress server where this plugin is running.', 'wpcd' );
				$ssh_timeout_desc .= __( '<br />PHP scripts have a timeout associated with them that is set in your PHP.INI file.  Your webserver has a timeout too. The length of time that an SSH command has to run is limited by these two factors.', 'wpcd' );
				$ssh_timeout_desc .= __( '<br />The load on your target WordPress server and type of command being executed are the main factors in determining how long it will take a command to run. You want to give it as long as possible before timing out.', 'wpcd' );
				$ssh_timeout_desc .= __( '<br />We therefore recommend that you increase the timeouts used on this server to 180 seconds or more. ', 'wpcd' );
				$ssh_timeout_desc .= '<br />';
				if ( (int) ini_get( 'max_execution_time' ) >= 180 ) {
					/* translators: %s: The max excution time limit for php. */
					$ssh_timeout_desc .= sprintf( __( '<br />It looks like your <b>php_max_execution_time</b> in your php.ini file is already set to 180 seconds or more. It is currently set to:  %s.  So, no change to this is necessary at this time.', 'wpcd' ), (string) ini_get( 'max_execution_time' ) );
				} else {
					/* translators: %s: The max excution time limit for php. */
					$ssh_timeout_desc .= sprintf( __( '<br />Set your <b>php_max_execution_time</b> in your php.ini file to 180 seconds or more. It is currently set to: %s', 'wpcd' ), (string) ini_get( 'max_execution_time' ) );
				}

				$webserver_timeout_desc  = __( 'Webservers also have timeouts.  Unfortunately we are unable to read their current values.  So please check the items below and change them as necessary.', 'wpcd' );
				$webserver_timeout_desc .= __( '<br />When you are done you can change the SSH timeout in the field below.', 'wpcd' );
				$webserver_timeout_desc .= '<br />';
				$webserver_timeout_desc .= __( '<br /><b>FOR NGINX:</b>', 'wpcd' );
				$webserver_timeout_desc .= __( '<br />&nbsp;&nbsp;&nbsp;&nbsp;Set your <b>fastcgi_read_timeout</b>, <b>client_header_timeout</b> and <b>client_body_timeout</b> values in your nginx configuration file to 600 seconds or more. ', 'wpcd' );
				$webserver_timeout_desc .= __( '<br /><b>FOR NGINX used as a proxy in front of an APACHE server:</b>', 'wpcd' );
				$webserver_timeout_desc .= __( '<br />&nbsp;&nbsp;&nbsp;&nbsp;Set your <b>proxy_read_timeout</b> value in your nginx configuration file to 600 seconds or more. ', 'wpcd' );
				$webserver_timeout_desc .= __( '<br /><b>FOR APACHE Servers:</b>', 'wpcd' );
				$webserver_timeout_desc .= __( '<br />&nbsp;&nbsp;&nbsp;&nbsp;Set your <b>TimeOut</b> value in your .htaccess file to 600 seconds or more. ', 'wpcd' );
				$webserver_timeout_desc .= '<br />';
				$webserver_timeout_desc .= __( '<br />After these settings are complete, you can set the SSH TIMEOUT value below to something higher such as 300. ', 'wpcd' );

				// Set some long text used for the introduction and welcome message.
				$ssh_welcome_text = '';
				if ( ! defined( 'WPCD_SKIP_WELCOME_MESSAGE' ) || ( defined( 'WPCD_SKIP_WELCOME_MESSAGE' ) && ! WPCD_SKIP_WELCOME_MESSAGE ) ) {
					$ssh_welcome_text  = __( 'Hello - thanks for choosing WPCloudDeploy.  We make it easy for you to deploy new servers and websites from right here inside your WordPress Admin screen.', 'wpcd' );
					$ssh_welcome_text .= '<br />';
					/* translators: %s: The link to the WPCD website. */
					$ssh_welcome_text .= sprintf( __( 'Learn more on our site at <a href="%s">wpclouddeploy.com</a>.', 'wpcd' ), 'https://wpclouddeploy.com' );
					$ssh_welcome_text  = apply_filters( 'wpcd_settings_welcome_text_initial', $ssh_welcome_text );
				}

				// Allow apps to append their own welcome message.
				$ssh_welcome_text = apply_filters( 'wpcd_settings_welcome_message', $ssh_welcome_text );

				$meta_boxes[] = array(
					'id'             => 'general',
					'title'          => __( 'General', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'general',
					'fields'         => array(
						array(
							'name' => __( 'Welcome Message', 'wpcd' ),
							'id'   => 'wpcd_general',
							'type' => 'heading',
							'desc' => $ssh_welcome_text,
						),
					),
				);

				// Allow apps to add  their own sections just after the welcome message.
				$meta_boxes = apply_filters( 'wpcd_general_settings_after_welcome_message', $meta_boxes );

				// Show timeout and other instructions - if not disabled in wp-config.php.
				if ( ! defined( 'WPCD_SKIP_SSH_TIMEOUT_SETTINGS' ) || ( defined( 'WPCD_SKIP_SSH_TIMEOUT_SETTINGS' ) && ! WPCD_SKIP_SSH_TIMEOUT_SETTINGS ) ) {

					$meta_boxes[] = array(
						'id'             => 'ssh-and-webserver-timeouts',
						'title'          => __( 'SSH And Webserver Timeouts', 'wpcd' ),
						'settings_pages' => 'wpcd_settings',
						'tab'            => 'general',
						'fields'         => array(
							array(
								'type' => 'heading',
								'name' => 'SSH and Webserver Timeouts',
								'desc' => $ssh_timeout_desc,
							),

							array(
								'type' => 'heading',
								'name' => 'WebServer Timeouts',
								'desc' => $webserver_timeout_desc,
							),

							array(
								'type' => 'divider',
							),

							array(
								'name'        => __( 'SSH Timeout', 'wpcd' ),
								'id'          => 'ssh_timeout',
								'type'        => 'number',
								'min'         => 30,
								'std'         => 30,
								'placeholder' => 30,
								'desc'        => __( 'The default value is 30 seconds which is the default value set when PHP is installed.', 'wpcd' ),
							),
						),
					);

				}

				$logging_desc  = __( 'You can log various error types to the database. Be careful because if you turn on all items below, you will end up with thousands of entries in your database.', 'wpcd' );
				$logging_desc .= '<br />' . __( 'To prevent this, we set the log limit to 100 records but you can adjust this number as well.', 'wpcd' );
				$logging_desc .= '<br /><b>' . __( 'DO NOT TURN THIS ON UNLESS OUR SUPPORT TEAM TELLS YOU TO DO SO!  We know you want to do it but please do not do so without our support guidance!', 'wpcd' ) . '</b>';
				$logging_desc .= '<br />' . __( 'If you do decide to turn it on without our support team\'s guidance, please do not then open a support ticket for anything you find in the logs. Why? Because lots of stuff that is normal will look scary.', 'wpcd' );
				$meta_boxes[]  = array(
					'id'             => 'logging',
					'title'          => __( 'Logging and Tracing', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'logging',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Logging and Tracing',
							'desc' => $logging_desc,
						),

						array(
							'name'            => __( 'Log these types', 'wpcd' ),
							'id'              => 'logging_and_tracing_types',
							'type'            => 'checkbox_list',
							'options'         => array(
								'error'          => __( 'Error', 'wpcd' ),
								'trace'          => __( 'Trace', 'wpcd' ),
								'warning'        => __( 'Warnings', 'wpcd' ),
								'debug'          => __( 'Debug', 'wpcd' ),
								'security'       => __( 'Security', 'wpcd' ),
								'provider-cache' => __( 'Provider Cache', 'wpcd' ),
								'other'          => __( 'Other', 'wpcd' ),
							),
							'select_all_none' => true,
						),
						array(
							'name'    => __( 'Only include messages with the following text', 'wpcd' ),
							'id'      => 'include_msg_txt',
							'type'    => 'fieldset_text',
							'options' => array(
								'include_msg' => __( 'Include Text', 'wpcd' ),
							),
							'clone'   => true,
						),
						array(
							'name'    => __( 'Only include messages from the following files', 'wpcd' ),
							'id'      => 'include_file_txt',
							'type'    => 'fieldset_text',
							'options' => array(
								'include_files' => __( 'Include Files', 'wpcd' ),
							),
							'clone'   => true,
						),
						array(
							'name'    => __( 'Always exclude messages with the following text', 'wpcd' ),
							'id'      => 'exclude_msg_txt',
							'type'    => 'fieldset_text',
							'options' => array(
								'exclude_msg' => __( 'Exclude Text', 'wpcd' ),
							),
							'clone'   => true,
						),

						array(
							'type' => 'heading',
							'name' => 'Log Limits',
							'desc' => __( 'Limit the number of entries in logs. If set to zero or nothing is entered, we are going to leave 999 entries at all times and delete everything else. Note that some of these logs are used to calculate statistics for certain screens in the Power Tools add-on. If you use those screens, you will want to keep the limits here on the higher side.', 'wpcd' ),
						),
						array(
							'name' => __( 'Auto Trim Notification Log', 'wpcd' ),
							'id'   => 'auto_trim_notification_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim Notification Sent Log', 'wpcd' ),
							'id'   => 'auto_trim_notification_sent_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim SSH Log', 'wpcd' ),
							'id'   => 'auto_trim_ssh_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim Command Log', 'wpcd' ),
							'id'   => 'auto_trim_command_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim Pending Tasks', 'wpcd' ),
							'id'   => 'auto_trim_pending_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim Error Log', 'wpcd' ),
							'id'   => 'auto_trim_error_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Delete At Most', 'wpcd' ),
							'id'   => 'most_items_to_delete',
							'type' => 'number',
							'desc' => __( 'To prevent timeouts we limit the number of records we will delete in any one shot.  If set to zero, we will delete 100 records at a time.', 'wpcd' ),
						),

						array(
							'type' => 'heading',
							'name' => 'Debug.log',
							'desc' => __( 'Errors and warnings will always be logged to the debug.log file.  But you can also choose to log the following types.  Be careful though because turning these items on can write sensitive information to the debug.log file!' ),
						),
						array(
							'name'            => __( 'Log these types to debug.log', 'wpcd' ),
							'id'              => 'logging_and_tracing_types_debug_log',
							'type'            => 'checkbox_list',
							'options'         => array(
								'trace'          => __( 'Trace', 'wpcd' ),
								'debug'          => __( 'Debug', 'wpcd' ),
								'security'       => __( 'Security', 'wpcd' ),
								'provider-cache' => __( 'Provider Cache', 'wpcd' ),
								'other'          => __( 'Other', 'wpcd' ),
							),
							'select_all_none' => true,
						),

					),
				);

				$meta_boxes[] = array(
					'id'             => 'provider-cache-settings',
					'title'          => __( 'Cache settings for providers', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => $this->get_provider_cache_limit_fields(),
				);

				$meta_boxes[] = array(
					'id'             => 'show-advanced-metaboxes',
					'title'          => __( 'Show Advanced Details Metaboxes', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Show Advanced Metaboxes',
							'desc' => __( 'The server and site detail screens can show some advanced metaboxes with gory details about the data we store internally. Turn this on when advised by support.', 'wpcd' ),
						),

						array(
							'name' => __( 'Show Advanced Metaboxes', 'wpcd' ),
							'id'   => 'show-advanced-metaboxes',
							'type' => 'checkbox',
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'hide-wpcd-warnings',
					'title'          => __( 'Hide Warnings', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Hide Warnings',
							'desc' => __( 'Turn off certain warnings at the top of the admin screen.', 'wpcd' ),
						),

						array(
							'name' => __( 'Hide The Local Host Warning', 'wpcd' ),
							'id'   => 'hide-local-host-warning',
							'type' => 'checkbox',
							'desc' => __( 'You cannot run WPCD on a local host or a server that is not accessible from the public internet. We usually show a non-dismissible warning about this.  Sometimes, though, this is a false error so you might want to hide the message if you think you know better.', 'wpcd' ),
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'hide-do-provider-mb',
					'title'          => __( 'Hide Digital Ocean Provider', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Hide Digital Ocean Provider',
							'desc' => __( 'If you have installed at least one additional provider, you can disable the Digital Ocean provider here.', 'wpcd' ),
						),

						array(
							'name' => __( 'Hide Digital Ocean Provider', 'wpcd' ),
							'id'   => 'hide-do-provider',
							'type' => 'checkbox',
							'desc' => __( 'Check this to disable the default Digital Ocean provider.', 'wpcd' ),
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'long-command-timeout-mb',
					'title'          => __( 'Timeout for Long Running Commands', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Timeout for Long Running Commands',
							'desc' => __( 'When running commands, they are terminated after 25 minutes if not complete. This option allows you to increase that timeout. It is useful when provisioning very slow VMs at certain providers.', 'wpcd' ),
						),

						array(
							'name'        => __( 'Timeout', 'wpcd' ),
							'id'          => 'long-command-timeout',
							'type'        => 'number',
							'min'         => 15,
							'max'         => 120,
							'std'         => 25,
							'placeholder' => 25,
							'size'        => 10,
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'backup',
					'title'          => __( 'Backup Settings', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Backup your settings',
							'desc' => __( 'Use the items below to backup your settings offline.', 'wpcd' ),
						),

						array(
							'name' => __( 'Backup', 'wpcd' ),
							'id'   => 'backup-settings',
							'type' => 'backup',
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'hide-general-option',
					'title'          => __( 'Hide General Tab', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Hide the general tab',
							'desc' => __( 'After you\'ve used the data on the GENERAL tab a few times you might not need it anymore.  Use this option to hide it.', 'wpcd' ),
						),

						array(
							'name' => __( 'Hide The General Tab', 'wpcd' ),
							'id'   => 'hide-general-settings-tab',
							'type' => 'checkbox',
							'desc' => __( 'To hide the GENERAL SETTINGS tab, check this box & click the SAVE button below. Then, refresh the screen for this setting to take effect!', 'wpcd' ),
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'wpcd-hide-groups-fields',
					'title'          => __( 'Hide Server/App Group Metabox From Non-Admins', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => __( 'Hide Server/App Group Metabox From Non-Admins', 'wpcd' ),
							'desc' => __( 'Hide the server and app group metaboxes from non-admins.', 'wpcd' ),
						),
						array(
							'name' => __( 'Hide Server Group Metabox From Non-Admins', 'wpcd' ),
							'id'   => 'wpcd_hide_server_group_mb',
							'type' => 'checkbox',
						),
						array(
							'name' => __( 'Hide App Group Metabox From Non-Admins', 'wpcd' ),
							'id'   => 'wpcd_hide_site_group_mb',
							'type' => 'checkbox',
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'wpcd-email-warning-fields',
					'title'          => __( 'Warning Emails', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => __( 'Warning Emails', 'wpcd' ),
							'desc' => __( 'Control emails sent to Admin when certain actions fail to run', 'wpcd' ),
						),
						array(
							'name'    => __( 'Do Not Send Cron Warning Emails?', 'wpcd' ),
							'id'      => 'wpcd_do_not_send_cron_warning_emails',
							'type'    => 'checkbox',
							'tooltip' => __( 'Emails are sent when we detect that certain critical processes are not running as scheduled. This flag disables those emails. It is useful to disable these messages if the emails are constantly being sent but are false postitives.', 'wpcd' ),
						),
						array(
							'name'    => __( 'Do Not Send Pending Log Warning Emails?', 'wpcd' ),
							'id'      => 'wpcd_do_not_send_pending_log_warning_emails',
							'type'    => 'checkbox',
							'tooltip' => __( 'Emails are sent when we detect that there are pending tasks that have started but not completed in a reasonable time. This option disables those emails.', 'wpcd' ),
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'wpcd-wisdom-opt-out-fields',
					'title'          => __( 'Share Statistics', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => __( 'Share Statistics', 'wpcd' ),
							'desc' => __( 'Opt out of sharing site statistics.', 'wpcd' ),
						),
						array(
							'name'    => __( 'Do Not Share Site Statistics', 'wpcd' ),
							'id'      => 'wpcd_wisdom_opt_out',
							'type'    => 'checkbox',
							'tooltip' => __( 'Data Shared: Current theme & Version, Current WPCD Version, Site Name, WordPress Version, Site Language, Active and Inactive Plugins, System Email, Basic Server data such as PHP version, NGINX version, Memory Limit etc. See privacy policy for more information.', 'wpcd' ),
						),
					),
				);

				/**
				 * Allow others to add their own settings at the end of the MISC tab.
				 */
				$meta_boxes = apply_filters( 'wpcd_settings_after_misc_tab', $meta_boxes );

				$meta_boxes[] = array(
					'id'             => 'cloud-providers',
					'title'          => __( 'All Cloud Providers', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'cloud-provider',  // this is the top level tab on the setttings screen, not to be confused with the tabs inside a metabox as we're defining below!
					// List of tabs in the metabox, in one of the following formats:
					// 1) key => label
					// 2) key => array( 'label' => Tab label, 'icon' => Tab icon ).
					'tabs'           => $this->provider_metabox_tabs(),
					'tab_style'      => 'left',
					'tab_wrapper'    => true,
					'fields'         => $this->provider_fields(),

				);

				$meta_boxes[] = array(
					'id'             => 'fields',
					'title'          => __( 'Fields', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'fields',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => __( 'Server List Fields', 'wpcd' ),
							'desc' => __( 'Some columns on the server list are hidden. Use the options below to show them.', 'wpcd' ),
						),
						array(
							'id'   => 'wpcd_show_server_list_short_desc',
							'type' => 'checkbox',
							'name' => __( 'Show the short description column?', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_server_list_instance_id_column',
							'type'    => 'checkbox',
							'name'    => __( 'Show the instance id as a separate column?', 'wpcd' ),
							'tooltip' => __( 'The instance id is normally shown under the PROVIDER DETAILS column. If you want it as a separate column, turn this flag on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_server_list_hide_instance_id_column',
							'type'    => 'checkbox',
							'name'    => __( 'HIDE the instance id from the server list?', 'wpcd' ),
							'tooltip' => __( 'The instance id is normally shown under the PROVIDER DETAILS column. Check this field to not display it at all. Note that this applies only to non-admins.  It will always display for admins.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_server_list_region_column',
							'type'    => 'checkbox',
							'name'    => __( 'Show the region as a separate column?', 'wpcd' ),
							'tooltip' => __( 'The region is normally shown under the PROVIDER DETAILS column. If you want it as a separate column, turn this flag on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_server_list_date',
							'type'    => 'checkbox',
							'name'    => __( 'Show the date column?', 'wpcd' ),
							'tooltip' => __( 'The server creation date column takes up space and is usually not that useful after a while.  If you need it, just turn this flag on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_server_list_server_type',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server type column?', 'wpcd' ),
							'tooltip' => __( 'The server type column takes up space but if you are only using one application type then this isn\'t useful information.  If you need it because you are handling multiple types of application servers, just turn this flag on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_server_list_owner',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server owner column?', 'wpcd' ),
							'tooltip' => __( 'The server type column takes up space but if you are the only owner or server manager then this isn\'t useful information.  If you need it because you indeed have multiple owners for your servers, just turn this flag on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_hide_server_list_owner_non_admins',
							'type'    => 'checkbox',
							'name'    => __( 'Hide owner column from non-admins?', 'wpcd' ),
							'tooltip' => __( 'Do not show the owner column to non-admins, even if the above option is turned on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_server_list_team',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server team column?', 'wpcd' ),
							'tooltip' => __( 'The server team column takes up space but if you are not using teams then this isn\'t useful information so by default it is turned off. Check this box to turn it on.', 'wpcd' ),
						),

						// Applist fields.
						array(
							'type' => 'heading',
							'name' => __( 'App List Fields', 'wpcd' ),
							'desc' => __( 'Some columns on the applications list are hidden. Use the options below to show them.', 'wpcd' ),
						),
						array(
							'id'   => 'wpcd_show_app_list_short_desc',
							'type' => 'checkbox',
							'name' => __( 'Show the short description column?', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_app_list_date',
							'type'    => 'checkbox',
							'name'    => __( 'Show the date column?', 'wpcd' ),
							'tooltip' => __( 'The app creation date column takes up space and is usually not that useful after a while.  If you need it, just turn this flag on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_app_list_app_type',
							'type'    => 'checkbox',
							'name'    => __( 'Show the application type column?', 'wpcd' ),
							'tooltip' => __( 'The application type column takes up space but if you are only using one application type then this isn\'t useful information.  If you need it because you are handling multiple types of applications, just turn this flag on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_app_list_owner',
							'type'    => 'checkbox',
							'name'    => __( 'Show the app owner column?', 'wpcd' ),
							'tooltip' => __( 'The app owner column takes up space but if you are the only owner then this isn\'t useful information.  If you need it because you indeed have multiple owners for your apps, just turn this flag on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_hide_app_list_owner_non_admins',
							'type'    => 'checkbox',
							'name'    => __( 'Hide owner column from non-admins?', 'wpcd' ),
							'tooltip' => __( 'Do not show the owner column to non-admins, even if the above option is turned on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_app_list_team',
							'type'    => 'checkbox',
							'name'    => __( 'Show the app team column?', 'wpcd' ),
							'tooltip' => __( 'The app team column takes up space but if you are not using teams then this isn\'t useful information so by default it is turned off. Check this box to turn it on.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_app_list_provider',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server provider column?', 'wpcd' ),
							'tooltip' => __( 'The server provider is shown under the SERVER column to save space.  If you would like to sort the list by this field then you need to enable this option so that it is shown in its own column.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_app_list_region',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server region column?', 'wpcd' ),
							'tooltip' => __( 'The server region is shown under the SERVER column to save space.  If you would like to sort the list by this field then you need to enable this option so that it is shown in its own column.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_app_list_ipv4',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server IP column?', 'wpcd' ),
							'tooltip' => __( 'The server IP is shown under the SERVER column to save space.  If you would like to sort the list by this field then you need to enable this option so that it is shown in its own column.', 'wpcd' ),
						),

						array(
							'id'      => 'wpcd_show_app_list_health',
							'type'    => 'checkbox',
							'name'    => __( 'Show the app health column?', 'wpcd' ),
							'tooltip' => __( 'The app health data is shown under the APP SUMMARY column to save space.  You can enable this option so that it is shown in its own column.', 'wpcd' ),
						),

						// Applist compound fields: Server Column.
						array(
							'type' => 'heading',
							'name' => __( 'App List Compound Fields', 'wpcd' ),
							'desc' => __( 'You can make some of the columns that hold multiple pieces of information shorter for non-admin users.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_hide_app_list_server_name_in_server_column',
							'type'    => 'checkbox',
							'name'    => __( 'Hide the server name in the server column?', 'wpcd' ),
							'tooltip' => __( 'The server name is usually shown in the SERVER column on the sites screen. Check this box to remove it for non-admin users.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_hide_app_list_provider_in_server_column',
							'type'    => 'checkbox',
							'name'    => __( 'Hide the provider data in the server column?', 'wpcd' ),
							'tooltip' => __( 'The provider information is usually shown in the SERVER column on the sites screen. Check this box to remove it for non-admin users.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_hide_app_list_region_in_server_column',
							'type'    => 'checkbox',
							'name'    => __( 'Hide the region data in the server column?', 'wpcd' ),
							'tooltip' => __( 'The region information is usually shown in the SERVER column on the sites screen. Check this box to remove it for non-admin users.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_hide_app_list_appslink_in_server_column',
							'type'    => 'checkbox',
							'name'    => __( 'Hide the link to apps in the server column?', 'wpcd' ),
							'tooltip' => __( 'A link to other apps on the server is usually shown in the SERVER column on the sites screen. Check this box to remove it for non-admin users.', 'wpcd' ),
						),

						// State labels.
						array(
							'type' => 'heading',
							'name' => __( 'Labels', 'wpcd' ),
							'desc' => __( 'Labels are additional short pieces of data that appear next to the application title in the application list.', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_private_state',
							'type'    => 'checkbox',
							'name'    => __( 'Show Private State Label?', 'wpcd' ),
							'tooltip' => __( 'Since all servers and apps are PRIVATE, we do not show this state label. You can force display of the PRIVATE state label with this option.', 'wpcd' ),
							'desc'    => '',
							'tab'     => 'wordpress-app-general-wpadmin',
						),

						// IPv6.
						array(
							'type' => 'heading',
							'name' => __( 'IPv6', 'wpcd' ),
							'desc' => __( 'How should we handle IPv6 data?', 'wpcd' ),
						),
						array(
							'id'      => 'wpcd_show_ipv6',
							'type'    => 'checkbox',
							'name'    => __( 'Show IPv6 Fields?', 'wpcd' ),
							'tooltip' => __( 'Most systems are not set up for IPv6. But we do sometimes collect it when provided by the server provider. Check this box if you want to  display it when it\'s available and to be able to sort and filter by it.', 'wpcd' ),
							'desc'    => '',
							'tab'     => 'wordpress-app-general-wpadmin',
						),

						// Server sizes.
						array(
							'type' => 'heading',
							'name' => __( 'Server Sizes', 'wpcd' ),
							'desc' => __( 'If selling with WooCommerce and you want custom server sizes, add them here.  If nothing is added here the default sizes will remain Large, Medium and Small.', 'wpcd' ),
						),
						array(
							'name'    => '',
							'id'      => 'wpcd_default_server_sizes',
							'type'    => 'fieldset_text',
							'options' => array(
								'size'      => __( 'Size Code - No Spaces', 'wpcd' ),
								'size_desc' => __( 'Size Description (Customers see this!)', 'wpcd' ),
							),
							'clone'   => true,
						),

					),
				);

				$meta_boxes[] = array(
					'id'             => 'tools',
					'title'          => __( 'Tools', 'wpcd' ),
					'settings_pages' => 'wpcd_settings',
					'tab'            => 'tools',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Clean Up Applications',
							'desc' => __( 'This action resets all in-process metas for all apps. This means that any background process that is "stuck" will stop running and any app deployment that is in-process will be terminated.', 'wpcd' ),
						),

						array(
							'type'       => 'button',
							'std'        => __( 'Clean Up Apps', 'wpcd' ),
							'attributes' => array(
								'id'          => 'wpcd-cleanup-apps',
								'data-action' => 'wpcd_cleanup_apps',
								'data-nonce'  => wp_create_nonce( 'wpcd-cleanup-apps' ),
							),
						),

						array(
							'type' => 'heading',
							'name' => 'Clean Up Servers',
							'desc' => __( 'This action resets all in-process metas for all servers. This means that any background process that is "stuck" will stop running and any server deployment that is in-process will be terminated.', 'wpcd' ),
						),

						array(
							'type'       => 'button',
							'std'        => __( 'Clean Up Servers', 'wpcd' ),
							'attributes' => array(
								'id'          => 'wpcd-cleanup-servers',
								'data-action' => 'wpcd_cleanup_servers',
								'data-nonce'  => wp_create_nonce( 'wpcd-cleanup-servers' ),
							),
						),

						array(
							'type' => 'heading',
							'name' => __( 'Bulk Email Server Batches', 'wpcd' ),
							'desc' => __( 'View all email batches sent for servers.', 'wpcd' ),
						),

						array(
							'type'       => 'button',
							'std'        => __( 'Server Batches', 'wpcd' ),
							'attributes' => array(
								'onclick' => 'window.location.href="' . admin_url( 'edit.php?post_type=wpcd_server_batch' ) . '"',
							),
						),

						array(
							'type' => 'heading',
							'name' => __( 'Bulk Email App Batches', 'wpcd' ),
							'desc' => __( 'Voew all email batches sent for apps.', 'wpcd' ),
						),

						array(
							'type'       => 'button',
							'std'        => __( 'App Batches', 'wpcd' ),
							'attributes' => array(
								'onclick' => 'window.location.href="' . admin_url( 'edit.php?post_type=wpcd_app_batch' ) . '"',
							),
						),

					),
				);

				// Data Syncing.
				if ( wpcd_data_sync_allowed() ) {
					$meta_boxes[] = array(
						'id'             => 'data-sync',
						'title'          => __( 'Sync', 'wpcd' ),
						'settings_pages' => 'wpcd_settings',
						'tab'            => 'data-sync',
						'tabs'           => array(
							'data_sync_send'    => __( 'Send Data', 'wpcd' ),
							'data_sync_receive' => __( 'Received Data', 'wpcd' ),
						),
						'tab_style'      => 'left',
						'tab_wrapper'    => true,
						'fields'         => array(

							array(
								'type' => 'heading',
								'name' => 'About Data Syncing',
								'desc' => __( 'Data sync allows you to push your server and site custom post type records to another WPCD site.  You can do this to create a point-in-time backup or you can schedule it to push as often as once per hour.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),

							array(
								'type' => 'heading',
								'name' => 'Destination Site',
								'desc' => __( 'Use this section to configure the details of the site that will be receiving your data.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),
							array(
								'name' => __( 'Destination Site', 'wpcd' ),
								'id'   => 'wpcd_sync_target_site',
								'type' => 'text',
								'size' => 60,
								'desc' => __( 'This is the site that will receive data.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),

							array(
								'name' => __( 'Encryption Key', 'wpcd' ),
								'id'   => 'wpcd_sync_enc_key',
								'type' => 'text',
								'size' => 60,
								'desc' => __( 'This is the key that will be used to encrypt the data before sending it.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),

							array(
								'name' => __( 'User ID', 'wpcd' ),
								'id'   => 'wpcd_sync_user_id',
								'type' => 'text',
								'size' => 60,
								'desc' => __( 'Your user name on the destination site - this can be any admin account on the destination.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),

							array(
								'name'  => __( 'Password', 'wpcd' ),
								'id'    => 'wpcd_sync_password',
								'type'  => 'text',
								'size'  => 60,
								'desc'  => __( 'Your password for your admin account on the destination site.', 'wpcd' ),
								'class' => 'wpcd_settings_pass_toggle',
								'tab'   => 'data_sync_send',
							),

							array(
								'type' => 'heading',
								'name' => 'Automatic Syncing',
								'desc' => __( 'Use this section to configure how often you would like to push data.  If you do not want to push on a regular basis, just turn off the first check-box.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),

							array(
								'name' => __( 'Do you want to enable auto export?', 'wpcd' ),
								'id'   => 'wpcd_sync_auto_export',
								'type' => 'checkbox',
								'desc' => __( 'Turn this on to push your server and site metadata on a regular basis to destination site you configured above.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),

							array(
								'name'    => __( 'How frequently do you want send your data?', 'wpcd' ),
								'id'      => 'wpcd_sync_set_cron',
								'type'    => 'select',
								'options' => array(
									'hourly' => __( 'Hourly', 'wpcd' ),
									'daily'  => __( 'Daily', 'wpcd' ),
								),
								'tooltip' => __( 'This applies only if the checkbox above is enabled.', 'wpcd' ),
								'desc'    => '',
								'tab'     => 'data_sync_send',
							),

							array(
								'type' => 'heading',
								'name' => 'Choose Data For Export',
								'desc' => __( 'We automatically export your server, sites and team data. However, you can optionally include additional data.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),
							array(
								'name'    => __( 'Do you want to export your settings?', 'wpcd' ),
								'id'      => 'wpcd_export_all_settings',
								'type'    => 'checkbox',
								'tooltip' => __( 'If you include your settings, you will be able to be up and running faster on the destination site.  But it will wipe out any settings on that site. And you will still need to manually copy your encryption key from the wp-config.php file on this site to the destination site.', 'wpcd' ),
								'tab'     => 'data_sync_send',
							),

							array(
								'type' => 'heading',
								'name' => 'Immediate Push',
								'desc' => __( 'You can export data immediately without waiting for a scheduled sync to fire.', 'wpcd' ),
								'tab'  => 'data_sync_send',
							),
							array(
								'type'       => 'button',
								'std'        => __( 'PUSH', 'wpcd' ),
								'attributes' => array(
									'id'          => 'wpcd-sync-push',
									'data-action' => 'wpcd_sync_push',
									'data-nonce'  => wp_create_nonce( 'wpcd-sync-push' ),
								),
								'desc'       => __( 'Export your data to the destination site now.  This will NOT save your settings from above - we recommend you use the BLUE SAVE button at the very bottom of this screen to save your settings.', 'wpcd' ),
								'tab'        => 'data_sync_send',
							),

							array(
								'type'  => 'heading',
								'name'  => 'Destination Operations',
								'class' => 'wpcd_settings_larger_header_label',
								'desc'  => __( 'If this site is a destination that receives data from another server then you can configure your restore options in this section as well as initiate a restore operation.', 'wpcd' ),
								'tab'   => 'data_sync_receive',
							),

							array(
								'type' => 'heading',
								'name' => 'Encryption Keys',
								'desc' => __( 'In order to restore your SETTINGS data you need the encryption key from your wp-config.php file from the origin site.  You can add it to the wp-config.php file for this site OR you can enter it in the field below and have it saved temporarily in the database. If your origin site did NOT have an encryption key set in wp-config.php then you will need to re-enter your data on the settings screen on this site.', 'wpcd' ),
								'tab'  => 'data_sync_receive',
							),
							array(
								'type' => 'custom_html',
								'std'  => $this->get_encryption_key_v2_html(),
								'id'   => 'wpcd_encryption_key_v2_html',
								'tab'  => 'data_sync_receive',
							),

							array(
								'name'        => __( 'How many recently received files would you like to keep?', 'wpcd' ),
								'id'          => 'wpcd_restrict_no_files_store',
								'type'        => 'number',
								'min'         => 0,
								'std'         => 10,
								'placeholder' => 10,
								'desc'        => __( 'Set to 0 to store unlimited received files.', 'wpcd' ),
								'tab'         => 'data_sync_receive',
							),

							array(
								'type' => 'heading',
								'name' => __( 'Received Files', 'wpcd' ),
								'desc' => __( 'Use the options below to import SERVER and APP records sent from an origin site.', 'wpcd' ),
								'tab'  => 'data_sync_receive',
							),

							array(
								'type' => 'custom_html',
								'std'  => $this->get_received_files_html(),
								'id'   => 'wpcd_sync_received_files',
								'tab'  => 'data_sync_receive',
							),

						),
					);
				}

				// Show license fields.
				if ( ! defined( 'WPCD_HIDE_LICENSE_TAB' ) || ( defined( 'WPCD_HIDE_LICENSE_TAB' ) && ( ! WPCD_HIDE_LICENSE_TAB ) && ( 1 === get_current_blog_id() ) ) ) {

					$core_item_id = WPCD_ITEM_ID;

					$meta_boxes[] = array(
						'id'             => 'license',
						'title'          => __( 'License And Automatic Updates', 'wpcd' ),
						'settings_pages' => 'wpcd_settings',
						'tab'            => 'license',
						'fields'         => array_merge(
							array(
								array(
									'type' => 'heading',
									'name' => 'Core License',
									'desc' => __( 'The license for the core WPCD plugin', 'wpcd' ),
								),
								array(
									'name' => __( 'License', 'wpcd' ),
									'id'   => "wpcd_item_license_$core_item_id",
									'type' => 'text',
									'size' => 40,
									'desc' => empty( wpcd_get_early_option( "wpcd_item_license_$core_item_id" ) ) ? __( 'Please enter your license key for the core plugin. Note: If you have an ALL ACCESS or BUSINESS bundle license please do not use those bundle keys; instead use the CORE plugin license key.', 'wpcd' ) : get_transient( "wpcd_license_notes_for_$core_item_id" ) . '<br />' . get_transient( "wpcd_license_updates_for_$core_item_id" ),
								),

								array(
									'type' => 'heading',
									'name' => 'Add-on Licenses',
									'desc' => __( 'The licenses for installed add-ons', 'wpcd' ),
								),
							),
							$this->get_license_fields_for_add_ons(),
							array(

								array(
									'type' => 'divider',
								),

								array(
									'type' => 'heading',
									'name' => 'License And Update Check Options',
									'desc' => __( 'Options that control the license and update checks', 'wpcd' ),
								),
								array(
									'name'        => __( 'WPCD Store URL', 'wpcd' ),
									'id'          => 'wpcd_store_url',
									'type'        => 'text',
									'size'        => 91,
									'std'         => 'https://wpclouddeploy.com',
									'placeholder' => 'https://wpclouddeploy.com',
									'desc'        => __( 'Enter the url to the WPCD store.  If left blank it will be set to https://wpclouddeploy.com.', 'wpcd' ),
								),
								array(
									'name'        => __( 'Period For Checking Licenses', 'wpcd' ),
									'id'          => 'wpcd_license_check_period',
									'type'        => 'number',
									'min'         => 1,
									'std'         => 24,
									'placeholder' => 24,
									'desc'        => __( 'How often should we check for license expiration and validity?  This value is specified in hours.', 'wpcd' ),
								),
								array(
									'name'        => __( 'Timeout', 'wpcd' ),
									'id'          => 'wpcd_license_check_timeout',
									'type'        => 'number',
									'min'         => 15,
									'std'         => 30,
									'placeholder' => 30,
									'desc'        => __( 'Set a timeout in seconds for each call we make to the licensing server.', 'wpcd' ),
								),
								array(
									'type'       => 'button',
									'name'       => __( 'Check for updates', 'wpcd' ),
									'std'        => __( 'Check for updates', 'wpcd' ),
									'attributes' => array(
										'id'               => 'wpcd-check-for-updates',
										'data-action'      => 'wpcd_check_for_updates',
										'data-nonce'       => wp_create_nonce( 'wpcd-update-check' ),
										'data-loading_msg' => __( 'Please wait...', 'wpcd' ),
									),
									'tooltip'    => __( 'After the screen refreshes, navigate to the WordPress Updates screen to see notices of any new WPCD updates that might be available.', 'wpcd' ),
								),
								array(
									'type'       => 'button',
									'name'       => __( 'Validate licenses', 'wpcd' ),
									'std'        => __( 'Validate licenses', 'wpcd' ),
									'attributes' => array(
										'id'               => 'wpcd-validate-licenses',
										'data-action'      => 'wpcd_validate_licenses',
										'data-nonce'       => wp_create_nonce( 'wpcd-license-validate' ),
										'data-loading_msg' => __( 'Please wait...', 'wpcd' ),
									),
									'tooltip'    => __( 'After the screen refreshes, scroll up to see license detail messages under each license key field.', 'wpcd' ),
								),
								array(
									'name'    => __( 'Force Update Check', 'wpcd' ),
									'id'      => 'wpcd_license_force_update_check',
									'type'    => 'checkbox',
									'std'     => 0,
									'desc'    => __( 'Check this box and save settings to force an update check immediately. Use this option if the buttons above do not seem to be working.', 'wpcd' ),
									'tooltip' => __( 'You should uncheck this box and save again to turn this off - otherwise you will be taking an unncessary performance penalty every time you save this screen.', 'wpcd' ),
								),
								array(
									'name'    => __( 'Force License Check', 'wpcd' ),
									'id'      => 'wpcd_license_force_license_check',
									'type'    => 'checkbox',
									'std'     => 0,
									'desc'    => __( 'Check this box and save settings to force an immediate license check on all licenses. Use this option if the buttons above do not seem to be working.', 'wpcd' ),
									'tooltip' => __( 'You should uncheck this box and save again to turn this off - otherwise you will be taking an unncessary performance penalty every time you save this screen.', 'wpcd' ),
								),
							),
						),
					);

				}

				// Enable saving filter on the a random field so we can clear the provider cache when settings are saved.
				add_filter( 'rwmb_wpcd_show_server_list_short_desc_value', array( &$this, 'wpcd_clear_all_providers_cache' ), 10, 3 );

				return apply_filters( 'wpcd_settings_metaboxes', $meta_boxes );

			},
		);

	}

	/**
	 * Registers an option page using the metabox.io infrastructure
	 *
	 * Filter hook: mb_settings_pages
	 *
	 * @param array $settings_pages Array of current settings pages.
	 *
	 * @return array $settings_pages New array of settings pages.
	 */
	public function register_options_page( $settings_pages ) {

		// Define tabs.
		if ( true === ( boolval( wpcd_get_early_option( 'hide-general-settings-tab' ) ) ) ) {
			$tabs = array(
				'cloud-provider' => __( 'Cloud Providers', 'wpcd' ),
			);
		} else {
			$tabs = array(
				'general'        => __( 'General Settings', 'wpcd' ),
				'cloud-provider' => __( 'Cloud Providers', 'wpcd' ),
			);
		}

		// Allow other components to hook into this and setup their own tabs..
		$tabs = apply_filters( 'wpcd_settings_tabs', $tabs );

		// Final set of tabs.
		$tabs['fields'] = __( 'Fields', 'wpcd' );

		$tabs['misc']    = __( 'Misc', 'wpcd' );
		$tabs['logging'] = __( 'Logging and Tracing', 'wpcd' );
		$tabs['tools']   = __( 'Tools', 'wpcd' );
		if ( ! defined( 'WPCD_HIDE_LICENSE_TAB' ) || ( defined( 'WPCD_HIDE_LICENSE_TAB' ) && ! WPCD_HIDE_LICENSE_TAB ) ) {
			$tabs['license'] = __( 'License & Updates', 'wpcd' );
		}

		if ( wpcd_data_sync_allowed() ) {
			$tabs['data-sync'] = __( 'Data Sync', 'wpcd' );
		}

		// Settings page array with the tabs.
		$settings_pages[] = array(
			'id'          => 'wpcd_settings',
			'option_name' => 'wpcd_settings',
			'menu_title'  => __( 'Settings', 'wpcd' ),
			'icon_url'    => 'dashicons-layout',
			'style'       => 'no-boxes',
			'parent'      => 'edit.php?post_type=wpcd_app_server',
			'position'    => 80,
			'columns'     => 2,
			'tabs'        => $tabs,
			'capability'  => 'wpcd_manage_settings',
		);

		return apply_filters( 'wpcd_settings_pages', $settings_pages );
	}

	/**
	 * Generate one tab for each cloud server provider
	 *
	 * @return array
	 */
	public function provider_metabox_tabs() {
		$provider_tabs = array();
		$providers     = WPCD()->get_cloud_providers();
		foreach ( $providers as $provider => $name ) {
			$tab_id        = "wpcd-cloud-{$provider}";
			$new_tab       = array(
				$tab_id => array(
					'label' => $name,
					'icon'  => 'dashicons-networking',
				),
			);
			$provider_tabs = $provider_tabs + $new_tab;
		}
		return $provider_tabs;
	}

	/**
	 * Return an array of fields for each cloud server provider
	 * Settings for each cloud server will end up in individual tabs
	 * under a single metabox on the settings screen.
	 *
	 * @return array
	 */
	public function provider_fields() {

		$provider_fields = array();
		$providers       = WPCD()->get_cloud_providers();

		foreach ( $providers as $provider => $name ) {

			/* Make sure we have a valid api reference for the current provider */
			if ( empty( WPCD()->get_provider_api( $provider ) ) ) {
				continue;
			}

			/* Set a var to hold some error messaages */
			$errmsg = '';

			/* Get sizes and keys from the provider */
			$sizes = WPCD()->get_provider_api( $provider )->call( 'sizes' );
			$keys  = WPCD()->get_provider_api( $provider )->call( 'keys' );

			if ( $sizes instanceof \WP_Error ) {
				$errmsg .= $sizes->get_error_code() . '/' . $sizes->get_error_message();
				$sizes   = array();
			}

			if ( $keys instanceof \WP_Error ) {
				$errmsg .= $keys->get_error_code() . '/' . $keys->get_error_message();
				$keys    = array();
			}
			/* End get sizes and keys from the provider */

			/* Fields will show up in this tab in the metabox */
			$tab_id = "wpcd-cloud-{$provider}";

			/* Fields for api keys - heading area */
			if ( ( ! defined( 'WPCD_HIDE_PROVIDER_HELP_LINKS' ) ) || ( defined( 'WPCD_HIDE_PROVIDER_HELP_LINKS' ) && ! WPCD_HIDE_PROVIDER_HELP_LINKS ) ) {
				$fields_part0 = array(

					array(
						'type' => 'heading',
						'tab'  => $tab_id,
						'name' => __( 'API Keys', 'wpcd' ),
					),

					array(
						'id'   => "vpn_{$provider}_help_link",
						'type' => 'custom_html',
						'name' => __( 'Need Help?', 'wpcd' ),
						/* translators: %s: A link to the help documentation for providers. */
						'std'  => sprintf( __( '%s', 'wpcd' ), '<a href="' . WPCD()->get_provider_api( $provider )->get_provider_help_link() . '"' . 'target="_blank"' . '>' . __( 'View the documentation', 'wpcd' ) . '</a>' ),
						'tab'  => $tab_id,
					),

				);
			} else {

				$fields_part0 = array(
					array(
						'type' => 'heading',
						'tab'  => $tab_id,
						'name' => __( 'API Keys', 'wpcd' ),
					),
				);

			}

			/* Add warning for providers that are in use */
			$args    = array(
				'post_type'   => 'wpcd_app_server',
				'post_status' => 'private',
				'meta_key'    => 'wpcd_server_provider',
				'meta_value'  => $provider,
				'fields'      => 'ids',
			);
			$servers = get_posts( $args );

			if ( count( $servers ) ) {

				if ( ( ! defined( 'WPCD_HIDE_PROVIDER_HELP_LINKS' ) ) || ( defined( 'WPCD_HIDE_PROVIDER_HELP_LINKS' ) && ! WPCD_HIDE_PROVIDER_HELP_LINKS ) ) {
					$top_warning_help_link = '<a href="https://wpclouddeploy.com/documentation/cloud-providers/changing-ssh-keys-in-cloud-provider-settings/"  target="_blank">' . __( 'Learn more', 'wpcd' ) . '</a>';
				} else {
					$top_warning_help_link = '';
				}

				/* Translators: 1. %s - Link to help text for warning */
				$top_warning_text = sprintf( __( 'WPCD: Warning - This provider is being used by one or more servers.  Caution should be exercised when making changes otherwise the connection to your server(s) could be broken. %s', 'wpcd' ), $top_warning_help_link );

				$wpcd_provider_top_warning = array(
					'id'    => "vpn_{$provider}_in_use_warning",
					'type'  => 'custom_html',
					'name'  => '',
					'tab'   => $tab_id,
					'std'   => $top_warning_text,
					'class' => "wpcd_settings_provider_in_use_warning wpcd_settings_{$provider}_provider_in_use_warning",
				);
				array_unshift( $fields_part0, $wpcd_provider_top_warning );
			}
			/* End add warning for providers that are in use */

			/**
			 * Button to test provider connection.  This will be added into an array later below.
			 */
			if ( WPCD()->get_provider_api( $provider )->get_feature_flag( 'test_connection' ) && ! $this->is_api_key_empty( $provider ) ) {
				$last_test_connection_status = $this->wpcd_get_last_test_connection_status( $provider );
				if ( true === $last_test_connection_status ) {
					$ssh_test_connection_heading_desc = __( 'The last attempt to test your connection to this provider was successful.', 'wpcd' );
				} else {
					$ssh_test_connection_heading_desc = __( 'The last test was unsuccessful or a test has never been run on this provider.', 'wpcd' );
				}

				$fields_test_provider_connection = array(
					'id'         => "vpn_{$provider}_test_provider_connection",
					'type'       => 'button',
					'std'        => __( 'Test Connection', 'wpcd' ),
					'desc'       => $ssh_test_connection_heading_desc,
					'attributes' => array(
						'class'         => 'wpcd-provider-test-provider-connection',
						'data-action'   => 'wpcd_provider_test_provider_connection',
						'data-nonce'    => wp_create_nonce( 'wpcd-test-provider-connection' ),
						'data-provider' => $provider,
					),
					'tab'        => $tab_id,
				);
			} else {
				$fields_test_provider_connection = array(
					'type' => 'hidden',
					'std'  => 'not used - empty because this provider does not support testing a provider connection.',
					'tab'  => $tab_id,
				);
			}

			/* First group of api fields */
			$fields_part1 = array(

				array(
					'id'                => "vpn_{$provider}_apikey",
					'type'              => 'text',
					'name'              => apply_filters( "wpcd_cloud_provider_settings_api_key_name_{$provider}", __( 'API Key', 'wpcd' ) ),
					'desc'              => apply_filters( "wpcd_cloud_provider_settings_api_key_desc_{$provider}", __( 'You can get this key from your providers security or API dashboard. It is encrypted before being stored in the database.', 'wpcd' ) ),
					'label_description' => apply_filters( "wpcd_cloud_provider_settings_api_key_label_desc_{$provider}", '' ),
					'size'              => '60',
					'tab'               => $tab_id,
					'class'             => 'wpcd_settings_pass_toggle',
				),

				array(
					'id'   => "vpn_{$provider}_keynotes",
					'type' => 'textarea',
					'name' => __( 'Notes', 'wpcd' ),
					'rows' => '1',
					'desc' => __( 'Your notes about this api key - optional', 'wpcd' ),
					'tab'  => $tab_id,
				),

				$fields_test_provider_connection,

				apply_filters(
					"wpcd_cloud_provider_settings_after_api_{$provider}",
					array(
						'type' => 'hidden',
						'std'  => 'not used - marker for filter after api key.',
						'tab'  => $tab_id,
					),
					$tab_id
				),

			);

			$fields_part1 = apply_filters( "wpcd_cloud_provider_settings_after_part1_{$provider}", $fields_part1, $tab_id );

			/* Basic instructional fields after api keys are filled in. */
			$fields_part2 = array();
			if ( $this->is_api_key_empty( $provider ) ) {
				// Show some instructions when the API keys are empty.
				$fields_part2 = array(
					array(
						'id'    => "vpn_{$provider}_key_not_available_instructions",
						'type'  => 'custom_html',
						'name'  => __( 'Additional Data', 'wpcd' ),
						'tab'   => $tab_id,
						'std'   => __( 'When you save your API keys you will see additional fields open up below. If you do not see these additional fields, try clicking the SAVE SETTINGS button again.', 'wpcd' ),
						'class' => "wpcd_settings_key_not_available_instructions wpcd_settings_{$provider}_key_not_available_instructions",
					),
				);
			}

			$can_connect_to_provider = $this->wpcd_can_connect_to_provider( $provider );

			/**
			 * SSH Key fields.
			 */
			/* translators: %1: provider name. %2: provider name again. */
			$ssh_keys_heading_desc = sprintf( __( 'For security, we only use public-private key pairs for server management. You must upload at least one public key to %1$s. Public keys that have been uploaded to %2$s\'s dashboard will show up in the drop-down below once your credentials above are configured and saved.<br /><br />You must click the SAVE SETTINGS at the bottom of this screen at least once after you enter your api key above in order for this list to populate. If the list continues to be blank, double-check that you have added at least one SSH key in your cloud provider\'s dashboard.', 'wpcd' ), $provider, $provider );
			if ( WPCD()->get_provider_api( $provider )->get_feature_flag( 'ssh_create' ) ) {
				$ssh_keys_heading_desc .= '<br /><br />' . __( 'Note: If you used the button above to automatically create keys and that operation was successful, you new keys should already be setup below. ', 'wpcd' );
			}
			$ssh_keys_heading_desc = apply_filters( "wpcd_cloud_provider_settings_ssh_keys_heading_desc_{$provider}", $ssh_keys_heading_desc );
			$fields_part3          = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {
				$fields_part3 = array(
					array(
						'type' => 'heading',
						'tab'  => $tab_id,
						'name' => __( 'SSH Keys', 'wpcd' ),
						'desc' => $ssh_keys_heading_desc,
					),

					array(
						'id'      => "vpn_{$provider}_sshkey_id",
						'type'    => 'select',
						'name'    => __( 'Public SSH Key', 'wpcd' ),
						'tooltip' => __( 'For most providers this public key is stored at the provider and will be installed into each server instance when the instance is first created.  If you change this value only FUTURE instances will get the new key. Instances that are already up and running will retain the prior key and any future operations on those instances will FAIL!  Note that some providers such as UPCLOUD and CUSTOM PROVIDERS might not use this key.  Check the documentation for the provider if you are unsure if this is required.', 'wpcd' ),
						'options' => $keys,
						/* translators: %s: provider name. */
						'desc'    => apply_filters( "wpcd_cloud_provider_settings_ssh_keys_select_desc_{$provider}", sprintf( __( 'Public key saved in %s. If you have just uploaded your public key and do not see it here, try clicking the CLEAR CACHE button at the bottom of this page.', 'wpcd' ), $name ) ),
						'tab'     => $tab_id,
					),
				);

				/**
				 * Button to automatically create ssh keys.
				 */
				if ( WPCD()->get_provider_api( $provider )->get_feature_flag( 'ssh_create' ) && ! $this->is_api_key_empty( $provider ) ) {
					$ssh_auto_create_keys_heading_desc  = __( 'SSH keys are critical for proper operation of this service.', 'wpcd' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'This provider can automatically create your SSH keys for you and submit them to your account.', 'wpcd' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'If you are not familiar with creating and managing keys or you would like a set of keys created for you, click the button below.', 'wpcd' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'However, it is important that you only do this if you have not already created servers with your own keys!', 'wpcd' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'Otherwise you could be locked out of your servers and your keys could be lost!', 'wpcd' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'If this operation is successful you should immediately make a copy of your private key which will be shown in the text boxes below. Store it in a safe place!', 'wpcd' );

					$fields_auto_create_ssh_keys = array(
						array(
							'type' => 'heading',
							'tab'  => $tab_id,
							'name' => __( 'Automatically Create SSH Keys: STOP AND READ CAREFULLY!', 'wpcd' ),
							'desc' => $ssh_auto_create_keys_heading_desc,
						),

						array(
							'id'         => "vpn_{$provider}_auto_create_ssh_key",
							'type'       => 'button',
							'std'        => __( 'Create SSH Key-Pair', 'wpcd' ),
							'attributes' => array(
								'class'         => 'wpcd-provider-auto-create-ssh-key',
								'data-action'   => 'wpcd_provider_auto_create_ssh_key',
								'data-nonce'    => wp_create_nonce( 'wpcd-auto-create-ssh-key' ),
								'data-provider' => $provider,
							),
							'tab'        => $tab_id,
						),
					);
				} else {
					$fields_auto_create_ssh_keys = array();
				}

				if ( ! empty( $fields_auto_create_ssh_keys ) ) {
					$fields_part3 = array_merge( $fields_auto_create_ssh_keys, $fields_part3 );
				}

				$fields_part3 = apply_filters( "wpcd_cloud_provider_settings_after_part3_{$provider}", $fields_part3, $tab_id );
			}

			$fields_part4 = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {
				$private_keys_text_note = apply_filters( 'wpcd_cloud_provider_settings_important_private_key_notes', '<a href="https://wpclouddeploy.com/documentation/_notes/important-notes-about-private-ssh-keys/" target="_blank" >' . __( 'View important notes about private keys.', 'wpcd' ) . '</a>' );
				$fields_part4           = array(
					array(
						'id'    => "vpn_{$provider}_sshkey",
						'type'  => 'textarea',
						'name'  => __( 'Private SSH Key', 'wpcd' ),
						'rows'  => '10',
						/* Translators: 1. %s - A note about private keys */
						'desc'  => apply_filters( 'wpcd_private_ssh_key_settings_desc', sprintf( __( 'Private key corresponding to the selected public key - it will be used to connect to the instance. The key will be encrypted when saved and decrypted when retrieved. Also, make sure that there are no spaces or extra lines entered at the end of the key-text.  %s', 'wpcd' ), $private_keys_text_note ) ),
						'tab'   => $tab_id,
						'class' => 'wpcd_settings_pass_toggle',
					),

					array(
						'id'    => "vpn_{$provider}_sshkey_passwd",
						'type'  => 'text',
						'name'  => __( 'Private SSH Key Password', 'wpcd' ),
						'size'  => '90',
						'desc'  => sprintf( __( 'Password for the above private key. It will be encrypted on save and decrypted when retrieved.', 'wpcd' ), $name ) . '<br />' . __( 'If your private key does not have a password associated with it you can leave this blank.', 'wpcd' ) . '<br />' . __( 'Note: For keypairs generated on a MAC or on WSL, do NOT apply a password to your private key file!', 'wpcd' ),
						'tab'   => $tab_id,
						'class' => 'wpcd_settings_pass_toggle',
					),

					array(
						'id'   => "vpn_{$provider}_sshkeynotes",
						'type' => 'textarea',
						'name' => __( 'Notes', 'wpcd' ),
						'rows' => '1',
						'desc' => __( 'Your notes about this ssh key - optional', 'wpcd' ),
						'tab'  => $tab_id,
					),

				);
			}

			/* Sizes fields; Other optional Fields; */
			$fields_part5 = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {

				// Custom images.
				if ( WPCD()->get_provider_api( $provider )->get_feature_flag( 'custom_images' ) ) {
					$fields_part5 = array_merge(
						$fields_part5,
						array(
							array(
								'type' => 'heading',
								'tab'  => $tab_id,
								'name' => __( 'Custom Snapshots & Images', 'wpcd' ),
								'desc' => __( 'These snapshots can be used in place of the default OS images.  Please check our documentation for this provider to get more details on the limitations of using custom snapshots & custom images.', 'wpcd' ),
							),
							array(
								'id'      => "vpn_{$provider}_ubuntu1804lts",
								'type'    => 'text',
								'name'    => __( 'Ubuntu 18.04', 'wpcd' ),
								'desc'    => __( 'The custom snapshot, image or backup id to be used instead of the default Ubuntu 18.04 image.', 'wpcd' ),
								'tooltip' => __( 'See our documentation for for more information about this item.', 'wpcd' ),
								'tab'     => $tab_id,
							),
							array(
								'id'      => "vpn_{$provider}_ubuntu2004lts",
								'type'    => 'text',
								'name'    => __( 'Ubuntu 20.04', 'wpcd' ),
								'desc'    => __( 'The custom snapshot, image or backup id to be used instead of the default Ubuntu 20.04 image.', 'wpcd' ),
								'tooltip' => __( 'See our documentation for for more information about this item.', 'wpcd' ),
								'tab'     => $tab_id,
							),
							array(
								'id'      => "vpn_{$provider}_ubuntu2204lts",
								'type'    => 'text',
								'name'    => __( 'Ubuntu 22.04', 'wpcd' ),
								'desc'    => __( 'The custom snapshot, image or backup id to be used instead of the default Ubuntu 22.04 image.', 'wpcd' ),
								'tooltip' => __( 'See our documentation for for more information about this item.', 'wpcd' ),
								'tab'     => $tab_id,
							),
						),
					);
				}

				// Backups?
				if ( WPCD()->get_provider_api( $provider )->get_feature_flag( 'enable_backups_on_server_create' ) ) {
					$fields_part5 = array_merge(
						$fields_part5,
						array(
							array(
								'id'   => "vpn_{$provider}_provider_backups",
								'type' => 'heading',
								'name' => __( 'Provider Backups', 'wpcd' ),
								'tab'  => $tab_id,
								'desc' => __( 'Set provider backup behavior if backups are supported.', 'wpcd' ),
							),
							array(
								'id'      => "vpn_{$provider}_enable_provider_backups_on_server_create",
								'type'    => 'checkbox',
								'name'    => __( 'Enable Provider Backups', 'wpcd' ),
								'tab'     => $tab_id,
								'tooltip' => __( 'Enable automatic backups for every new server.', 'wpcd' ),
							),
						),
					);
				}

				// Tags?
				if ( WPCD()->get_provider_api( $provider )->get_feature_flag( 'enable_dynamic_tags_on_server_create' ) ) {
					$fields_part5 = array_merge(
						$fields_part5,
						array(
							array(
								'id'   => "vpn_{$provider}_provider_tags",
								'type' => 'heading',
								'name' => __( 'Tags', 'wpcd' ),
								'tab'  => $tab_id,
								'desc' => __( 'Set provider behavior if server tags are supported.', 'wpcd' ),
							),
							array(
								'id'      => "vpn_{$provider}_tags_on_server_create",
								'type'    => 'text',
								'name'    => __( 'Tag For New Servers', 'wpcd' ),
								'desc'    => __( 'Note: Some providers require that you use tags that have already been defined while others allow you to set random/dynamic tags.', 'wpcd' ),
								'size'    => '30',
								'tab'     => $tab_id,
								'tooltip' => __( 'Apply this tag to every new server. If left blank, the tag will default to WPCD for providers that allow for dynamic/random tags.', 'wpcd' ),
							),
						),
					);
				}

				// Sizes fields.
				if ( $this->show_server_sizes( $provider ) ) {
					if ( apply_filters( 'wpcd_show_server_sizes_in_settings', false ) ) {
						$fields_part5 = array_merge(
							$fields_part5,
							array(
								array(
									'id'    => "vpn_{$provider}_sizes_instructions",
									'type'  => 'heading',
									'name'  => __( 'Server Sizes', 'wpcd' ),
									'tab'   => $tab_id,
									'desc'  => __( 'These pre-defined sizes are used when defining products in the WooCommerce screens.', 'wpcd' ),
									'class' => "wpcd_settings_sizes_instructions wpcd_settings_{$provider}_sizes_instructions",
								),
							),
							$this->get_generic_size_fields( $tab_id, $provider, $sizes )
						);
					}
				}
			}

			$fields_part6 = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {

				$fields_part6 = array(
					array(
						'id'   => "vpn_{$provider}_white_label",
						'type' => 'heading',
						'name' => 'White Label',
						'desc' => __( 'Change provider display name / description.', 'wpcd' ),
						'tab'  => $tab_id,
					),

					array(
						'id'   => "vpn_{$provider}_alt_desc",
						'type' => 'text',
						'name' => __( 'Alternative Name', 'wpcd' ),
						'desc' => __( 'Use this as the providers display name', 'wpcd' ),
						'tab'  => $tab_id,
					),

				);
			}

			$fields_part7 = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {

				// Get list of cached transients for this provider.
				$cached_transient_list = WPCD()->get_provider_api( $provider )->get_cached_transient_list();

				$fields_part7 = array(
					array(
						'id'   => "vpn_{$provider}_cache_instructions",
						'type' => 'heading',
						'name' => 'Cache',
						'desc' => __( 'If keys, sizes and other information in the drop-down selects above is not up-to-date, you can clear the cache which will force a refresh from your cloud provider. The cache is usually cleared every 24 hours.', 'wpcd' ),
						'tab'  => $tab_id,
					),

					array(
						'id'         => "vpn_{$provider}_clear_cache",
						'type'       => 'button',
						'std'        => __( 'Clear Cached Data', 'wpcd' ),
						'attributes' => array(
							'class'         => 'wpcd-provider-clear-cache',
							'data-action'   => 'wpcd_provider_clear_cache',
							'data-nonce'    => wp_create_nonce( 'wpcd-clear-cache' ),
							'data-provider' => $provider,
						),
						'tab'        => $tab_id,
					),

					array(
						'id'   => "vpn_{$provider}_cached_transients_header",
						'type' => 'heading',
						'name' => 'Cached Transients',
						'desc' => __( 'Any data cached in transients for this provider is listed below. To refresh the cache click the CLEAR CACHE button above.', 'wpcd' ),
						'tab'  => $tab_id,
					),
					array(
						'id'   => "vpn_{$provider}_cache_transients",
						'type' => 'custom_html',
						'std'  => $cached_transient_list,
						'tab'  => $tab_id,
					),

				);
			}

			/* Show API error messages if any */
			if ( ! empty( $errmsg ) ) {
				$provider_fields[] = array(
					'id'   => "vpn_{$provider}_err_msgs",
					'name' => __( 'Error Messages', 'wpcd' ),
					'type' => 'custom_html',
					'std'  => '<span class="wpcd_settings_err_msg">' . $errmsg . '</span>',
					'tab'  => $tab_id,
				);
			}

			/* Merge arrays */
			$provider_fields = array_merge( $provider_fields, $fields_part1, $fields_part2, $fields_part3, $fields_part4, $fields_part5, $fields_part6, $fields_part7 );

			/**
			 * Allow providers to insert additional configuration items
			 */
			$provider_fields = apply_filters( "wpcd_cloud_provider_settings_{$provider}", $provider_fields, $tab_id );

			/**
			 * Add the top header to the settings...
			 */
			$provider_fields = array_merge( $fields_part0, $provider_fields );

			// Enable filter on the private key password field to encrypt it when its being stored.
			add_filter( "rwmb_vpn_{$provider}_sshkey_passwd_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the private key to encrypt it when its being stored.
			add_filter( "rwmb_vpn_{$provider}_sshkey_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the api key field to encrypt it when its being stored.
			add_filter( "rwmb_vpn_{$provider}_apikey_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the user_name field to encrypt it when its being stored. Note that this field is usually added to the settings array by a provider (eg: upcloud) and is not a standard field.
			add_filter( "rwmb_vpn_{$provider}_user_name_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the user_password field to encrypt it when its being stored. Note that this field is usually added to the settings array by a provider (eg: upcloud) and is not a standard field.
			add_filter( "rwmb_vpn_{$provider}_user_password_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the secret_key field to encrypt it when its being stored. Note that this field is usually added to the settings array by a provider (eg: exoscale) and is not a standard field.
			add_filter( "rwmb_vpn_{$provider}_secret_key_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the private key password field to decrypt it when its being retrieved.
			add_filter( "rwmb_vpn_{$provider}_sshkey_passwd_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the private key field to decrypt it when its being retrieved.
			add_filter( "rwmb_vpn_{$provider}_sshkey_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the api key field to decrypt it when its being retrieved.
			add_filter( "rwmb_vpn_{$provider}_apikey_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the user_name field to decrypt it when its being retrieved. Note that this field is usually added to the settings array by a provider (eg: upcloud) and is not a standard field.
			add_filter( "rwmb_vpn_{$provider}_user_name_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the user_password field to decrypt it when its being retrieved. Note that this field is usually added to the settings array by a provider (eg: upcloud) and is not a standard field.
			add_filter( "rwmb_vpn_{$provider}_user_password_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the secret_key field to decrypt it when its being retrieved. Note that this field is usually added to the settings array by a provider (eg: exoscale) and is not a standard field.
			add_filter( "rwmb_vpn_{$provider}_secret_key_field_meta", array( &$this, 'decrypt' ), 10, 3 );

		}

		return $provider_fields;

	}

	/**
	 * Get fields for sizes (small, medium large etc.)
	 *
	 * @param int    $tab_id             The ID of the tab being rendered.
	 * @param string $provider           The provider slug.
	 * @param array  $provider_sizes     Array of existing provider sizes.
	 */
	public function get_generic_size_fields( $tab_id, $provider, $provider_sizes ) {

		$sizes  = WPCD_WOOCOMMERCE()->get_wc_size_options();
		$return = array();
		foreach ( $sizes as $size => $size_desc ) {
			$return[] = array(
				'id'      => "vpn_{$provider}_{$size}",
				'type'    => 'select',
				'name'    => $size_desc,
				'options' => $provider_sizes,
				'size'    => '60',
				'tab'     => $tab_id,
				'class'   => "wpcd_server_size_setting wpcd_server_size_{$size}_setting",
			);
		}

		return $return;

	}

	/**
	 * Get the fields for the provider cache limits
	 *
	 * @return array $fields array of fields for provider cache limits.
	 */
	public function get_provider_cache_limit_fields() {

		$fields[] = array(
			'id'   => 'wpcd_provider_cache_limits_header',
			'name' => __( 'Cache Limits for Cloud Providers', 'wpcd' ),
			'type' => 'heading',
			'desc' => __( 'We need to cache data received from your cloud provider - this helps with performance as well as rate-limits that the cloud provider sets.  We strongly recommend that you set these values to 1440 or greater after you have completed your provider setup.  The intial values are set at 15 minutes.', 'wpcd' ),
		);

		$methods = array( 'sizes', 'keys', 'regions', 'other' );
		foreach ( $methods as $method ) {
			$fields[] = array(
				'id'   => "vpn_{$method}_cache_limit",
				'type' => 'number',
				'desc' => __( 'Number of minutes to cache this data retrieved from cloud provider', 'wpcd' ),
				'std'  => 15,
				'size' => 10,
				'name' => ucfirst( $method ),  // @Todo - translation issue here!
			);
		}

		return $fields;
	}

	/**
	 * Encrypt data before it is saved in the database
	 *
	 * @param string $new new value being saved.
	 * @param string $field name of being field saved.
	 * @param string $old old value of the field.
	 *
	 * @return string $new the encrypted value of the field.
	 */
	public function encrypt( $new, $field, $old ) {
		return WPCD()->encrypt( $new );
	}

	/**
	 * Decrypt data before it is shown on the screen
	 *
	 * @param string $meta the value in the field being decrypted.
	 * @param string $field the name of the field.
	 * @param string $saved the original saved value of the field.
	 *
	 * @return string $meta the decrypted value of the field.
	 */
	public function decrypt( $meta, $field, $saved ) {
		return WPCD()->decrypt( $meta );
	}

	/**
	 * Determine whether to show the select drop-downs with the server sizes.
	 *
	 * No need to show them if the api key is empty.
	 *
	 * @param string $provider provider (provider slug).
	 *
	 * @return boolean
	 */
	public function show_server_sizes( $provider ) {

		$show_server_sizes = true;

		if ( $this->is_api_key_empty( $provider ) ) {
			$show_server_sizes = false;
		} elseif ( defined( 'WPCD_SKIP_SERVER_SIZES_SETTING' ) && WPCD_SKIP_SERVER_SIZES_SETTING ) {
			$show_server_sizes = false;
		}

		return $show_server_sizes;
	}

	/**
	 * Checks whether the API key is empty.
	 *
	 * @param string $provider provider (provider slug).
	 *
	 * @return boolean
	 */
	public function is_api_key_empty( $provider ) {

		if ( ( empty( wpcd_get_early_option( "vpn_{$provider}_apikey" ) ) ) || ( empty( WPCD()->decrypt( ( wpcd_get_early_option( "vpn_{$provider}_apikey" ) ) ) ) ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Register the scripts for this page
	 *
	 * Action Hook: admin_enqueue_scripts
	 *
	 * @param string $hook the hook name on which to register these scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {

		// Enqueue the font-awesome pro kit.
		wp_register_script( 'wpcd-fontawesome-pro', 'https://kit.fontawesome.com/4fa00a8874.js', array(), 6.2, true );
		wp_enqueue_script( 'wpcd-fontawesome-pro' );

		// Enqueue some of our scripts.
		wp_register_script( 'wpcd-admin-settings', wpcd_url . 'assets/js/wpcd-admin-settings.js', array( 'jquery', 'wp-util' ), wpcd_scripts_version, true );
		wp_enqueue_script( 'wpcd-admin-settings' );

		wp_register_script( 'wpcd-admin-settings-data-sync', wpcd_url . 'assets/js/wpcd-admin-settings-data-sync.js', array( 'jquery', 'wp-util' ), wpcd_scripts_version, true );
		wp_enqueue_script( 'wpcd-admin-settings-data-sync' );

		wp_localize_script(
			'wpcd-admin-settings-data-sync',
			'wpcd_admin_settings_data_sync_params',
			array(
				'nonce' => wp_create_nonce( 'wpcd-settings' ),
				'i10n'  => array(
					'empty_target_site'       => __( 'Please enter Target Site url.', 'wpcd' ),
					'empty_enc_key'           => __( 'Please enter Encryption Key.', 'wpcd' ),
					'empty_user_id'           => __( 'Please enter User ID.', 'wpcd' ),
					'empty_password'          => __( 'Please enter Password.', 'wpcd' ),
					'wait_msg'                => __( 'Please wait for a while...', 'wpcd' ),
					'delete_wait_msg'         => __( 'Deleting...', 'wpcd' ),
					'restore_wait_msg'        => __( 'Restoring...', 'wpcd' ),
					'save_wait_msg'           => __( 'Saving...', 'wpcd' ),
					'restore_confirmation'    => __( 'Are you sure you would like to import this data?', 'wpcd' ),
					'empty_encryption_key_v2' => __(
						'Please enter Encryption Key.',
						'wpcd'
					),
				),
			)
		);

		// JS fix for Metabox.io issue where the user ends up on tab #1 after a page refreshes.  This JS keeps the user on the tab they were on after a page refresh.
		wp_register_script( 'wpcd-mbio-tabs-fix.', wpcd_url . 'assets/js/wpcd-mbio-tabs-fix.js', array( 'jquery', 'rwmb-tabs' ), wpcd_scripts_version, true );
		wp_enqueue_script( 'wpcd-mbio-tabs-fix.' );

	}

	/**
	 * Clear a provider's cache
	 *
	 * Action Hook: wp_ajax_wpcd_provider_clear_cache
	 */
	public function wpcd_provider_clear_cache() {

		// nonce check.
		check_ajax_referer( 'wpcd-clear-cache', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		// Extract the provider from the ajax request.
		$provider = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'provider', FILTER_DEFAULT ) );

		// Call the clear cache function.
		WPCD()->get_provider_api( $provider )->clear_cache();

		// ok, we got this far...
		$msg = __( 'The cache has been cleared for this provider. This page will now refresh.', 'wpcd' );

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );
		wp_die();
	}

	/**
	 * Automatically Create SSH Key at the provider.
	 *
	 * Action Hook: wp_ajax_wpcd_provider_auto_create_ssh_key
	 */
	public function wpcd_provider_auto_create_ssh_key() {

		// nonce check.
		check_ajax_referer( 'wpcd-auto-create-ssh-key', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		// Extract the provider from the ajax request.
		$provider = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'provider', FILTER_DEFAULT ) );

		// Create key.
		$key_pair                      = WPCD_WORDPRESS_APP()->ssh()->create_key_pair();
		$attributes                    = array();
		$attributes['public_key']      = $key_pair['public'];
		$attributes['public_key_name'] = 'WPCD_AUTO_CREATE_' . wpcd_random_str( 10, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' );

		// Call the ssh_create function.
		$key_id = WPCD()->get_provider_api( $provider )->call( 'ssh_create', $attributes );

		if ( is_array( $key_id ) && ( ! is_wp_error( $key_id ) ) && ( $key_id ) && ( ! empty( $key_id['ssh_key_id'] ) ) ) {

			// Ok, we got this far. Save to our options array.
			wpcd_set_option( "vpn_{$provider}_sshkey_id", $key_id['ssh_key_id'] );
			wpcd_set_option( "vpn_{$provider}_sshkey", WPCD()->encrypt( $key_pair['private'] ) );
			wpcd_set_option( "vpn_{$provider}_public_sshkey", $key_pair['public'] );
			wpcd_set_option( "vpn_{$provider}_sshkeynotes", $attributes['public_key_name'] . ': ' . __( 'This key was automatically created.', 'wpcd' ) );

			// Set success message.
			$msg = __( 'The ssh key-pair has been created. This page will now refresh.', 'wpcd' );
		} else {

			// Failed.
			$msg = __( 'The attempt to create an ssh key-pair was not successful.  Please try again and/or contact our support team. This page will now refresh.', 'wpcd' );

		}

		// Clear cache so that when the page refreshes we can get new data.
		WPCD()->get_provider_api( $provider )->clear_cache();

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );
		wp_die();
	}

	/**
	 * Test connection to provider.
	 *
	 * Action Hook: wp_ajax_wpcd_provider_test_provider_connection
	 *
	 * Related functions: wpcd_can_connect_to_provider (below) and wpcd_get_last_test_connection_status (below)
	 *
	 * Changes to this might need to be reflected in our setup wizard code: /includes/core/class-wpcd-setup-wizard.
	 */
	public function wpcd_provider_test_provider_connection() {

		// nonce check.
		check_ajax_referer( 'wpcd-test-provider-connection', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		// Extract the provider from the ajax request.
		$provider = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'provider', FILTER_DEFAULT ) );

		// Setup variables to be used by transient and then delete the existing one.
		$apikey        = WPCD()->get_provider_api( $provider )->get_api_key();
		$transient_key = 'wpcd_provider_connection_test_success_flag_' . $provider . hash( 'sha256', $apikey );
		delete_transient( $transient_key );

		// Call the test_connection function.
		$attributes        = array();
		$connection_status = WPCD()->get_provider_api( $provider )->call( 'test_connection', $attributes );

		if ( true === $connection_status['test_status'] ) {

			// Update transient.
			set_transient( $transient_key, 'connection_successful', 86400 ); // Transient set to expire in 24 hours. Note we are not using a boolean for this transient for good reason.

			// Set success message.
			$msg = __( 'The connection was successful.', 'wpcd' );

		} else {

			// Failed.
			$msg = __( 'The attempt to connect to your provider with the api keys provided was unsuccessful.  Please try again and/or contact our support team. This page will now refresh.', 'wpcd' );

		}

		// Clear cache so that when the page refreshes we can get new data.
		WPCD()->get_provider_api( $provider )->clear_cache();

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );
		wp_die();
	}

	/**
	 * Can we connect to the provider?
	 *
	 * Related functions: wpcd_provider_test_provider_connection (above) and wpcd_get_last_test_connection_status (below)
	 *
	 * @param string $provider The provider slug.
	 *
	 * @return boolean.
	 */
	public function wpcd_can_connect_to_provider( $provider ) {

		$return = false;

		if ( WPCD()->get_provider_api( $provider )->get_feature_flag( 'test_connection' ) ) {

			// See if transient is already set.
			$apikey           = WPCD()->get_provider_api( $provider )->get_api_key();
			$transient_key    = 'wpcd_provider_connection_test_success_flag_' . $provider . hash( 'sha256', $apikey );
			$transient_status = get_transient( $transient_key );

			if ( 'connection_successful' === $transient_status ) {
				$return = true;
			} else {
				if ( ! $transient_status ) {
					// Transient does not exist so check for connection and then update the transient if successful.
					$attributes        = array();
					$connection_status = WPCD()->get_provider_api( $provider )->call( 'test_connection', $attributes );

					if ( ! is_wp_error( $connection_status ) && ! empty( $connection_status['test_status'] ) ) {
						if ( true === (bool) $connection_status['test_status'] ) {
							$return = true;
							set_transient( $transient_key, 'connection_successful', 86400 ); // Transient set to expire in 24 hours. Note we are not using a boolean for this transient for good reason.
						}
					} else {
						$return = false;
					}

					/**
					 * Clear cache so that when the page refreshes we can get new data.
					 * We need to be careful where we place this call otherwise the cache
					 * can be inadvertently cleared for all providers everytime we load up
					 * or refresh settings page
					 */
					WPCD()->get_provider_api( $provider )->clear_cache();

				}
			}
		} else {
			$return = true;  // If the provider does not have the ability to test a connection, always return true.
		}

		return $return;

	}

	/**
	 * What was the result of the last attempt to connect to the provider?
	 *
	 * @param string $provider The provider slug.
	 *
	 * @return boolean.
	 */
	public function wpcd_get_last_test_connection_status( $provider ) {

		$return = false;

		if ( WPCD()->get_provider_api( $provider )->get_feature_flag( 'test_connection' ) ) {

			// See if transient is already set.
			$apikey           = WPCD()->get_provider_api( $provider )->get_api_key();
			$transient_key    = 'wpcd_provider_connection_test_success_flag_' . $provider . hash( 'sha256', $apikey );
			$transient_status = get_transient( $transient_key );

			if ( 'connection_successful' === $transient_status ) {
				$return = true;
			} else {
				$return = false;
			}
		} else {
			$return = false;
		}

		return $return;

	}

	/**
	 * Clear caches for all providers.
	 *
	 * Called on saving settings on a random field hook.
	 *
	 * Filter Hook: rwmb_wpcd_show_server_list_short_desc_value
	 *
	 * @param string $new new value being saved.
	 * @param string $field name of being field saved.
	 * @param string $old old value of the field.
	 *
	 * @return string $new The same value being passed in because we're not doing anything with it.
	 */
	public function wpcd_clear_all_providers_cache( $new, $field, $old ) {

		$provider_fields = array();
		$providers       = WPCD()->get_cloud_providers();

		foreach ( $providers as $provider => $name ) {
			// Call the clear cache function.
			$provider_api = WPCD()->get_provider_api( $provider );
			if ( ! empty( $provider_api ) ) {
				$provider_api->clear_cache();
			}
		}

		return $new;
	}

	/**
	 * Metabox.io Callback function after saving settings fields.
	 *
	 * This one will be used to check the license fields.
	 * Note that this function is called once for each settings field.
	 * So we need to check to make sure that the field is one we're
	 * interested in handling here.
	 *
	 * Filter Hook: rwmb_after_save_field
	 *
	 * @param string $not_used set to null because it's not used.
	 * @param array  $field field settings.
	 * @param string $new new value of field.
	 * @param string $old old value of field.
	 * @param int    $object_id the metabox object id.
	 */
	public function check_license( $not_used, $field, $new, $old, $object_id ) {
		if ( true === is_admin() ) {

			/* Is the field a license field for the core plugin? If so, check licenses for the core plugin. */
			$core_item_id = WPCD_ITEM_ID;
			if ( "wpcd_item_license_$core_item_id" === $field['id'] && $new !== $old ) {
				WPCD_License::check_license( $new, WPCD_ITEM_ID );
			}

			/* Is the field a license field for one of the add-ons? If so, check licenses for the addon. */
			$add_ons = apply_filters( 'wpcd_register_add_ons_for_licensing', array() );  // The array of existing add-ons.
			foreach ( $add_ons as $item ) {
				if ( ! empty( $item ) ) {
					foreach ( $item as $item_id => $item_name ) {
						if ( "wpcd_item_license_$item_id" === $field['id'] && $new !== $old ) {
							WPCD_License::check_license( $new, $item_id );
						}
					}
				}
			}

			/* Do we need to force a software update check right away? */
			if ( 'wpcd_license_force_update_check' === $field['id'] && ( 1 === ( (int) $new ) ) ) {
				do_action( 'wpcd_log_error', 'Admin requested immediate software update check.', 'trace', __FILE__, __LINE__, array(), false );
				WPCD_License::check_for_updates();  // Use wp_remote_post to check the status of each individual plugin/add-on and sets a transient that is displayed on the license tab.
				WPCD_License::update_plugins(); // This one calls the actual EDD updater class.
			}

			/* Do we need to force a license check on all licenses immediately? */
			if ( 'wpcd_license_force_license_check' === $field['id'] && ( 1 === ( (int) $new ) ) ) {
				do_action( 'wpcd_log_error', 'Admin requested immediate license validation check on all licenses.', 'trace', __FILE__, __LINE__, array(), false );
				WPCD_License::validate_all_licenses();
			}
		}
	}

	/**
	 * Metabox.io Callback function after saving settings fields.
	 *
	 * This one will be used to set a standard WordPress option called 'wisdom_opt_out'
	 * to let the Wisdom plugin know that the user has opted out of sharing statistics.
	 *
	 * @see: https://wisdomplugin.com/support/#options
	 *
	 * Filter Hook: rwmb_after_save_field
	 *
	 * @param string $not_used set to null because it's not used.
	 * @param array  $field field settings.
	 * @param string $new new value of field.
	 * @param string $old old value of field.
	 * @param int    $object_id the metabox object id.
	 */
	public function handle_wisdom_opt_out( $not_used, $field, $new, $old, $object_id ) {
		if ( true === is_admin() ) {
			/* Did the user enable the opt-out flag? */
			if ( 'wpcd_wisdom_opt_out' === $field['id'] ) {
				if ( ( 1 === ( (int) $new ) ) ) {
					if ( ! get_option( 'wisdom_opt_out' ) ) {
						// Option does not already exist so log the change.  We check for the option existence first because we don't want to log every time we save - we just want to log the first time.
						do_action( 'wpcd_log_error', 'Admin has chosen NOT to share statistics.', 'other', __FILE__, __LINE__, array(), false );
					}
					update_option(
						'wisdom_opt_out',
						array(
							'wisdom_registered_setting' => 1,
							'wisdom_opt_out'            => 1,
						)
					);
				} else {
					// Flag is unset so delete option if it exists.
					if ( get_option( 'wisdom_opt_out' ) ) {
						// But first we log the change.
						do_action( 'wpcd_log_error', 'Admin has chosen to share statistics.', 'other', __FILE__, __LINE__, array(), false );
					}
					delete_option( 'wisdom_opt_out' );
				}
			}
		}
	}

	/**
	 * Return an array of license fields for each add-on.
	 *
	 * @return array
	 */
	public function get_license_fields_for_add_ons() {

		/* The array of fields to return. */
		$fields = array();

		/* The array of existing add_ons.  */
		$add_ons = apply_filters( 'wpcd_register_add_ons_for_licensing', array() );

		foreach ( $add_ons as $item ) {
			if ( ! empty( $item ) ) {
				foreach ( $item as $item_id => $item_name ) {
					$fields[] = array(
						'name' => "{$item_name['name']}",
						'id'   => "wpcd_item_license_$item_id",
						'type' => 'text',
						'size' => 40,
						'desc' => empty( wpcd_get_early_option( "wpcd_item_license_$item_id" ) ) ? __( 'Please enter your license key for this item.', 'wpcd' ) : get_transient( "wpcd_license_notes_for_$item_id" ) . '<br />' . get_transient( "wpcd_license_updates_for_$item_id" ),
					);
				}
			}
		}

		return $fields;

	}

	/**
	 * Check for plugin & license updates
	 *
	 * Action Hook: init
	 */
	public static function check_for_updates() {

		// To support auto-updates feature released in WP 5.5 , this needs to run during the wp_version_check cron job for privileged users.
		$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;
		if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
			return;
		}

		// License check class file needs to be loaded here.
		if ( ! class_exists( 'WPCD_License' ) ) {
			require_once wpcd_path . 'includes/core/class-wpcd-license.php';
		}

		if ( true === WPCD_License::show_license_tab() ) {

			// Check for software updates - the EDD class checker caches update checks once per week so no need for us to handle it.
			WPCD_License::update_plugins();

			// Check for license changes or expiration.
			if ( ! get_transient( 'wpcd_license_check_delay' ) ) {

				do_action( 'wpcd_log_error', "Validating all licenses on init. If this message shows up too many times, it means that transients aren't working as they should.", 'error', __FILE__, __LINE__, array(), false );
				WPCD_License::validate_all_licenses();

				// Write the transient so we can avoid checking for a period of time.
				$delay_check_period = wpcd_get_early_option( 'wpcd_license_check_period' );
				if ( empty( $delay_check_period ) ) {
					$delay_check_period = 24;  // 24 hours before next check ;
				}
				$delay_check_period = ( ( (int) $delay_check_period ) * 3600 );  // convert hours to seconds.
				set_transient( 'wpcd_license_check_delay', '1', $delay_check_period );
			}
		}
	}

	/**
	 * Gets the HTML for received files
	 *
	 * @return string
	 */
	public function get_received_files_html() {
		global $wpdb;
		$user_id        = get_current_user_id();
		$received_files = get_user_option( 'received_files', $user_id );

		$restricted_files = wpcd_get_early_option( 'wpcd_restrict_no_files_store' );

		if ( '' === $restricted_files ) {
			$restricted_files = 10;
		}

		// check if restore file table is not exists then create new table.
		WPCD_SYNC::wpcd_create_restore_file_table();

		$table_name = $wpdb->prefix . 'wpcd_restore_files';

		if ( 0 == $restricted_files ) {
			$get_files_sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY date_time DESC", array( $user_id ) );
		} else {
			$get_files_sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY date_time DESC LIMIT %d", array( $user_id, $restricted_files ) );
		}

		$received_files = $wpdb->get_results( $get_files_sql );

		// if not files received for the current user, show a message.
		if ( empty( $received_files ) ) {

			return __( 'No files received yet.', 'wpcd' );

		}

		$html = '';

		$html .= '<!-- The Modal -->
				<div class="cover-popup-bg-sec"></div>
				<div id="restore_file_key_popup" class="modal">
				  <!-- Modal content -->
				  <div class="modal-content">
				    <span class="close close_custom_popup">&times;</span>
				    <div class="decrypt_key_form_sec">
				    	<h3>Please enter a decryption key for restore a file</h3>
					    <input type="text" name="decryption_key_to_restore" id="decryption_key_to_restore">
					    <input type="hidden" name="restore_file_name" id="restore_file_name" value="">
					    <input type="hidden" name="restore_file_id" id="restore_file_id" value="">
					    <input type="hidden" name="restore_delete_existing" id="restore_delete_existing" value="">
					    <button type="button" id="enter_decryption_key" class="rwmb-button button hide-if-no-js">Enter</button>
				    </div>
				  </div>
				</div>';

		$html .= '<div class="wpcd-received-files-list">';

		foreach ( $received_files as $key => $rf ) {

			$restore_id    = $rf->restore_id;
			$recieved_from = $rf->recieved_from;
			$file_name     = $rf->file_name;
			$file_data     = $rf->file_data;
			$date_time     = $rf->date_time;

			$html .= '<div class="wpcd-received-files-list-item">';
			$html .= '<span class="wpcd-received-files-list-item-links">';
			$html .= sprintf( '<a href="#" class="wpcd-received-files-delete" data-file-name="%s" data-restore-id="%s">%s</a>', $file_name, $restore_id, __( 'Delete', 'wpcd' ) );
			$html .= '<span class="wpcd-received-files-list-item-links-separator">|</span>';
			$html .= sprintf( '<a href="#" class="wpcd-received-files-restore" data-file-name="%s" data-restore-id="%s" data-delete-existing="%d">%s</a>', $file_name, $restore_id, 1, __( 'Delete Existing & Restore', 'wpcd' ) );
			$html .= '<span class="wpcd-received-files-list-item-links-separator">|</span>';
			$html .= sprintf( '<a href="#" class="wpcd-received-files-restore" data-file-name="%s" data-restore-id="%s" data-delete-existing="%d">%s</a>', $file_name, $restore_id, 0, __( 'Restore', 'wpcd' ) );
			$html .= '</span>';
			$html .= sprintf( '%s | %s | %s', $file_name, 'From: ' . $recieved_from, 'Date: ' . $date_time );
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Gets the HTML for encryption key save option
	 *
	 * @return string
	 */
	public function get_encryption_key_v2_html() {

		$wpcd_encryption_key_v2 = get_option( 'wpcd_encryption_key_v2' );

		$html = '';

		$html .= '<div class="rwmb-field rwmb-text-wrapper"><div class="rwmb-label">
					<label for="wpcd_encryption_key_v2">Encryption Key From Origin Site</label>
				</div>
				
				<div class="rwmb-input"><input size="60" value="' . $wpcd_encryption_key_v2 . '" type="text" id="wpcd_encryption_key_v2" class="rwmb-text valid" name="wpcd_encryption_key_v2" aria-invalid="false"></div></div>

				<div class="rwmb-field rwmb-button-wrapper"><div class="rwmb-input"><button type="button" id="wpcd-encryption-key-save" class="rwmb-button button hide-if-no-js" data-action="wpcd_encryption_key_save" data-nonce="' . wp_create_nonce( 'wpcd-encryption-key-save' ) . '">Save</button></div></div>';

		return $html;
	}

	/**
	 * Check for updates via an AJAX call.
	 *
	 * Action Hook: wp_ajax_wpcd_check_for_updates
	 */
	public function wpcd_check_for_updates() {

		// nonce check.
		check_ajax_referer( 'wpcd-update-check', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		do_action( 'wpcd_log_error', 'Admin requested immediate software update check.', 'trace', __FILE__, __LINE__, array(), false );

		WPCD_License::check_for_updates();  // Use wp_remote_post to check the status of each individual plugin/add-on and sets a transient that is displayed on the license tab.

		WPCD_License::update_plugins(); // This one calls the actual EDD updater class.

		wp_die();
	}

	/**
	 * Validate licenses via an AJAX call.
	 *
	 * Action Hook: wp_ajax_wpcd_check_for_licenses
	 */
	public function wpcd_validate_licenses() {

		// nonce check.
		check_ajax_referer( 'wpcd-license-validate', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		do_action( 'wpcd_log_error', 'Admin requested immediate license validation check on all licenses.', 'trace', __FILE__, __LINE__, array(), false );

		WPCD_License::validate_all_licenses();

		wp_die();
	}

	/**
	 * Reset defaults brand colors via an AJAX call.
	 *
	 * Action Hook: wp_ajax_wpcd_reset_defaults_brand_colors
	 */
	public function wpcd_reset_defaults_brand_colors() {

		// nonce check.
		check_ajax_referer( 'wpcd-reset-brand-colors', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		$wpcd_settings = get_option( 'wpcd_settings' );

		// Set defaults brand colors.
		$wpcd_settings['wordpress_app_primary_brand_color']               = WPCD_PRIMARY_BRAND_COLOR;
		$wpcd_settings['wordpress_app_secondary_brand_color']             = WPCD_SECONDARY_BRAND_COLOR;
		$wpcd_settings['wordpress_app_tertiary_brand_color']              = WPCD_TERTIARY_BRAND_COLOR;
		$wpcd_settings['wordpress_app_accent_background_color']           = WPCD_ACCENT_BG_COLOR;
		$wpcd_settings['wordpress_app_medium_background_color']           = WPCD_MEDIUM_BG_COLOR;
		$wpcd_settings['wordpress_app_light_background_color']            = WPCD_LIGHT_BG_COLOR;
		$wpcd_settings['wordpress_app_alternate_accent_background_color'] = WPCD_ALTERNATE_ACCENT_BG_COLOR;

		$wpcd_settings['wordpress_app_fe_primary_brand_color']               = WPCD_FE_PRIMARY_BRAND_COLOR;
		$wpcd_settings['wordpress_app_fe_secondary_brand_color']             = WPCD_FE_SECONDARY_BRAND_COLOR;
		$wpcd_settings['wordpress_app_fe_tertiary_brand_color']              = WPCD_FE_TERTIARY_BRAND_COLOR;
		$wpcd_settings['wordpress_app_fe_accent_background_color']           = WPCD_FE_ACCENT_BG_COLOR;
		$wpcd_settings['wordpress_app_fe_medium_background_color']           = WPCD_FE_MEDIUM_BG_COLOR;
		$wpcd_settings['wordpress_app_fe_light_background_color']            = WPCD_FE_LIGHT_BG_COLOR;
		$wpcd_settings['wordpress_app_fe_alternate_accent_background_color'] = WPCD_FE_ALTERNATE_ACCENT_BG_COLOR;
		$wpcd_settings['wordpress_app_fe_positive_color']                    = WPCD_FE_POSITIVE_COLOR;
		$wpcd_settings['wordpress_app_fe_negative_color']                    = WPCD_FE_NEGATIVE_COLOR;

		// Update the settings options.
		update_option( 'wpcd_settings', $wpcd_settings );

		$msg = __( 'Brand colors fields have been reset defaults. This page will now refresh.', 'wpcd' );

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );

		wp_die();
	}
}
