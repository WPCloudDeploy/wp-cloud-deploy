<?php
/**
Plugin Name: WPCloudDeploy
Plugin URI: https://wpclouddeploy.com
Description: Deploy and manage cloud servers and apps from inside the WordPress Admin dashboard.
Version: 4.15.0
Requires at least: 5.4
Requires PHP: 7.4
Item Id: 1493
Author: WPCloudDeploy
Author URI: https://wpclouddeploy.com
Domain Path: /languages
 */
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Class WPCD_Init.
 */
class WPCD_Init {

	/**
	 * Construct function.
	 */
	public function __construct() {

		$plugin_data       = get_plugin_data( __FILE__ );
		$extra_plugin_data = get_file_data( __FILE__, array( 'ItemId' => 'Item Id' ) );

		if ( ! defined( 'wpcd_url' ) ) {
			// Deprecated constants - lowercased.
			define( 'wpcd_url', plugin_dir_url( __FILE__ ) );
			define( 'wpcd_path', plugin_dir_path( __FILE__ ) );
			define( 'wpcd_root', dirname( plugin_basename( __FILE__ ) ) );
			define( 'wpcd_plugin', plugin_basename( __FILE__ ) );
			define( 'wpcd_extension', $plugin_data['Name'] );
			define( 'wpcd_version', $plugin_data['Version'] );
			define( 'wpcd_textdomain', 'wpcd' );
			define( 'wpcd_requires', '2.0.3' );
			define( 'wpcd_rest_version', '1' );
			define( 'wpcd_db_version', '1' );

			// Redefine again as upper-case since we're going to be moving all constants to uppercase going forward.
			// Note that there are additional constants here vs above since we added new ones.  But we're only
			// adding new UPPERCASE ones.
			define( 'WPCD_URL', plugin_dir_url( __FILE__ ) );
			define( 'WPCD_PATH', plugin_dir_path( __FILE__ ) );
			define( 'WPCD_ROOT', dirname( plugin_basename( __FILE__ ) ) );
			define( 'WPCD_PLUGIN', plugin_basename( __FILE__ ) );
			define( 'WPCD_BASE_FILE', __FILE__ );
			define( 'WPCD_EXTENSION', $plugin_data['Name'] );
			define( 'WPCD_VERSION', $plugin_data['Version'] );
			define( 'WPCD_ITEM_ID', $extra_plugin_data['ItemId'] );
			define( 'WPCD_TEXTDOMAIN', 'wpcd' );
			define( 'WPCD_REQUIRES', '2.0.3' );
			define( 'WPCD_REST_VERSION', '1' );
			define( 'WPCD_DB_VERSION', '1' );

			// Define the default brand colors.
			define( 'WPCD_PRIMARY_BRAND_COLOR', '#E91E63' );
			define( 'WPCD_SECONDARY_BRAND_COLOR', '#FF5722' );
			define( 'WPCD_TERTIARY_BRAND_COLOR', '#03114A' );
			define( 'WPCD_ACCENT_BG_COLOR', '#3F4C5F' );
			define( 'WPCD_MEDIUM_BG_COLOR', '#FAFAFA' );
			define( 'WPCD_LIGHT_BG_COLOR', '#FDFDFD' );
			define( 'WPCD_ALTERNATE_ACCENT_BG_COLOR', '#CFD8DC' );

			// Define a variable that can be used for versioning scripts - required to force multisite to use different version numbers for each site.
			if ( is_multisite() ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					define( 'wpcd_scripts_version', (string) get_current_blog_id() . '_' . (string) time() );
					define( 'WPCD_SCRIPTS_VERSION', (string) get_current_blog_id() . '_' . (string) time() );
				} else {
					define( 'wpcd_scripts_version', (string) get_current_blog_id() . '_' . (string) wpcd_version );
					define( 'WPCD_SCRIPTS_VERSION', (string) get_current_blog_id() . '_' . (string) wpcd_version );
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					define( 'wpcd_scripts_version', (string) time() );
					define( 'WPCD_SCRIPTS_VERSION', (string) time() );
				} else {
					define( 'wpcd_scripts_version', (string) wpcd_version );
					define( 'WPCD_SCRIPTS_VERSION', (string) wpcd_version );
				}
			}
		}

		/* Use init hook to load up required files */
		add_action( 'init', array( $this, 'required_files' ), -20 );

		/* Create a custom schedule for 1 minute */
		add_filter( 'cron_schedules', array( $this, 'custom_cron_schedule' ) );

		/* Activation and deactivation hooks */
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		/* Show some notices as required */
		add_action( 'admin_notices', array( $this, 'wpcd_global_admin_notice' ) );

		/* Load languages files */
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

	}

	/**
	 * Create cron timers for use by other wpcd functions.
	 * Two timers - 1 min and 2 min.
	 *
	 * @param Array $schedules schedules.
	 */
	public function custom_cron_schedule( $schedules ) {
		// if this schedule is not already defined by someone else...
		if ( ! isset( $schedules['every_minute'] ) ) {
			$schedules['every_minute'] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => __( 'Every minute', 'wpcd' ),
			);
		}
		if ( ! isset( $schedules['every_two_minute'] ) ) {
			$schedules['every_two_minute'] = array(
				'interval' => 120,
				'display'  => __( 'Every 2 minute', 'wpcd' ),
			);
		}
		return $schedules;
	}

	/**
	 * Fires on activation of plugin.
	 *
	 * @param String $network_wide network_wide.
	 */
	public function activate( $network_wide ) {
		require_once wpcd_path . 'includes/core/class-wpcd-base.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-base.php';
		require_once wpcd_path . 'includes/core/apps/class-wpcd-app.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-permission-type.php';
		require_once wpcd_path . 'includes/core/class-wpcd-dns.php';
		require_once wpcd_path . 'includes/core/functions.php';

		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/vpn/class-vpn-app.php';
		}

		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/basic-server/class-basic-server-app.php';
		}

		// @TODO: Have to make these static till autoloading is implemented
		// @TODO: This is also poor because N crons will be registered for N providers even if only one provider is actually active and has credentials
		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			WPCD_VPN_APP::activate( $network_wide );
		}
		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			WPCD_BASIC_SERVER_APP::activate( $network_wide );
		}

		$this->wpcd_load_wpapp_traits();
		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-app.php';
		WPCD_WORDPRESS_APP::activate( $network_wide );

		WPCD_POSTS_PERMISSION_TYPE::activate( $network_wide );

		require_once wpcd_path . 'includes/core/class-wpcd-sync.php';
		WPCD_Sync::activate( $network_wide );

		require_once wpcd_path . 'includes/core/class-wpcd-roles-capabilities.php';
		WPCD_ROLES_CAPABILITIES::activate( $network_wide );

		$this->wpcd_load_core_traits();
		require_once wpcd_path . 'includes/core/class-wpcd-posts-app-server.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-app.php';
		WPCD_POSTS_APP_SERVER::activate( $network_wide );
		WPCD_POSTS_APP::activate( $network_wide );

		require_once wpcd_path . 'includes/core/class-wpcd-posts-notify-user.php';
		WPCD_NOTIFY_USER::activate( $network_wide );

		if ( defined( 'WPCD_DISABLE_EMAIL_NOTIFICATIONS' ) && ( false === WPCD_DISABLE_EMAIL_NOTIFICATIONS ) ) {
			require_once wpcd_path . 'includes/core/class-wpcd-email-notifications.php';
			WPCD_EMAIL_NOTIFICATIONS::activate( $network_wide );
		}

		// Set transient for not showing the cron check message immediately as soon as plugin is activated.
		// It will be shown after 2 minutes if crons are not scheduled and loaded.
		set_transient( 'wpcd_loaded_timeout', 1, 2 * MINUTE_IN_SECONDS );

		require_once wpcd_path . 'includes/core/class-wpcd-posts-log.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-pending-tasks-log.php';
		WPCD_PENDING_TASKS_LOG::activate( $network_wide );

		flush_rewrite_rules();
	}

	/**
	 * Fires on deactivation of plugin.
	 *
	 * @param String $network_wide network_wide.
	 */
	public function deactivate( $network_wide ) {
		require_once wpcd_path . 'includes/core/apps/class-wpcd-app.php';

		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/vpn/class-vpn-app.php';
		}

		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/basic-server/class-basic-server-app.php';
		}

		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-app.php';

		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			WPCD_VPN_APP::deactivate( $network_wide );
		}

		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			WPCD_BASIC_SERVER_APP::deactivate( $network_wide );
		}

		WPCD_WORDPRESS_APP::deactivate( $network_wide );

		require_once wpcd_path . 'includes/core/class-wpcd-sync.php';
		WPCD_Sync::deactivate( $network_wide );

		require_once wpcd_path . 'includes/core/class-wpcd-posts-notify-user.php';
		WPCD_NOTIFY_USER::deactivate( $network_wide );

		if ( wpcd_email_notifications_allowed() ) {
			require_once wpcd_path . 'includes/core/class-wpcd-email-notifications.php';
			WPCD_EMAIL_NOTIFICATIONS::deactivate( $network_wide );
		}

		require_once wpcd_path . 'includes/core/class-wpcd-posts-log.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-pending-tasks-log.php';
		WPCD_PENDING_TASKS_LOG::deactivate( $network_wide );
	}

	/**
	 * Include additional files as needed
	 */
	public function required_files() {
		/* These two files ensure that certain third party plugins are available - primarily METABOX.IO */
		require_once wpcd_path . 'required_plugins/class-tgm-plugin-activation.php';
		require_once wpcd_path . 'required_plugins/wpcd-required-plugins.php';
		/* End ensure third party plugins are available */

		/* Include the SETTINGS PAGE and other related metabox.io extension files */
		require_once wpcd_path . 'required_plugins/mb-settings-page/mb-settings-page.php';

		if ( is_admin() ) {
			require_once wpcd_path . '/required_plugins/mb-admin-columns/mb-admin-columns.php';
			require_once wpcd_path . '/required_plugins/meta-box-tabs/meta-box-tabs.php';
			require_once wpcd_path . '/required_plugins/meta-box-tooltip/meta-box-tooltip.php';
			require_once wpcd_path . '/required_plugins/mb-term-meta/mb-term-meta.php';
			require_once wpcd_path . '/required_plugins/meta-box-columns/meta-box-columns.php';
			require_once wpcd_path . '/required_plugins/meta-box-group/meta-box-group.php';
			require_once wpcd_path . '/required_plugins/mb-user-meta/mb-user-meta.php';
		}

		/* Load up some licensing files. */
		if ( true === is_admin() ) {
			require_once WPCD_PATH . '/includes/vendor/WPCD_EDD_SL_Plugin_Updater.php';
			require_once WPCD_PATH . '/includes/core/class-wpcd-license.php';
		}

		/* Load up our files */
		require_once wpcd_path . 'includes/core/functions.php';
		require_once wpcd_path . 'includes/core/class-wpcd-custom-fields.php';
		require_once wpcd_path . 'includes/core/class-wpcd-roles-capabilities.php';
		require_once wpcd_path . 'includes/core/class-wpcd-data-sync-rest.php';
		require_once wpcd_path . 'includes/core/class-wpcd-server-statistics.php';
		$this->wpcd_load_core_traits();
		require_once wpcd_path . 'includes/core/class-wpcd-base.php';
		require_once wpcd_path . 'includes/core/class-wpcd-dns.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-base.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-app.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-app-server.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-cloud-provider.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-log.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-notify-log.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-notify-user.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-notify-sent-log.php';
		if ( wpcd_email_notifications_allowed() ) {
			require_once wpcd_path . 'includes/core/class-wpcd-email-notifications.php';
		}
		require_once wpcd_path . 'includes/core/class-wpcd-posts-ssh-log.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-error-log.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-command-log.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-pending-tasks-log.php';
		require_once wpcd_path . 'includes/core/class-wpcd-server.php';
		require_once wpcd_path . 'includes/core/class-wpcd-settings.php';
		if ( wpcd_data_sync_allowed() ) {
			require_once wpcd_path . 'includes/core/class-wpcd-sync.php';
		}
		require_once wpcd_path . 'includes/core/class-wpcd-setup.php';

		require_once wpcd_path . 'includes/core/class-wpcd-posts-team.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-permission-type.php';

		require_once wpcd_path . 'includes/core/providers/class-cloud-provider-api.php';
		require_once wpcd_path . 'includes/core/providers/class-digital-ocean-parent.php';
		require_once wpcd_path . 'includes/core/providers/class-digital-ocean.php';
		if ( defined( 'WPCD_LOAD_BACKUP_DO_PROVIDER' ) && ( true === WPCD_LOAD_BACKUP_DO_PROVIDER ) ) {
			require_once wpcd_path . 'includes/core/providers/class-digital-ocean-alternate.php';
		}
		require_once wpcd_path . 'includes/core/providers/class-custom-server-parent.php';

		require_once wpcd_path . 'includes/core/apps/class-wpcd-app.php';
		require_once wpcd_path . 'includes/core/apps/class-wpcd-app-settings.php';
		require_once wpcd_path . 'includes/core/apps/class-wpcd-ssh.php';
		require_once wpcd_path . 'includes/core/apps/class-wpcd-woocommerce.php';

		if ( is_admin() ) {
			require_once wpcd_path . 'includes/core/functions-handle-admin-notices.php';
		}

		/**
		* For the VPN App
		*/
		/* @TODO: Need to find a more dynamic way to load these by letting apps register themselves at the right time and having them load up their own files */
		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/vpn/class-vpn-app.php';
			require_once wpcd_path . 'includes/core/apps/vpn/class-vpn-app-settings.php';
			require_once wpcd_path . 'includes/core/apps/vpn/class-vpn-ssh.php';
			require_once wpcd_path . 'includes/core/apps/vpn/class-vpn-woocommerce.php';
		}

		/**
		* For the BASIC SERVER App
		*/
		/* @TODO: Need to find a more dynamic way to load these by letting apps register themselves at the right time and having them load up their own files */
		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/basic-server/class-basic-server-app.php';
			require_once wpcd_path . 'includes/core/apps/basic-server/class-basic-server-app-settings.php';
			require_once wpcd_path . 'includes/core/apps/basic-server/class-basic-server-ssh.php';
			require_once wpcd_path . 'includes/core/apps/basic-server/class-basic-server-woocommerce.php';
		}

		/**
		* For the WP App
		*/
		/* @TODO: Need to find a more dynamic way to load these by letting apps register themselves at the right time and having them load up their own files */
		$this->wpcd_load_wpapp_traits();
		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-app.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-app-settings.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-ssh.php';

		require_once wpcd_path . 'includes/core/wp-cloud-deploy.php';
		require_once wpcd_path . 'includes/core/class-wpcd-server.php';
		require_once wpcd_path . 'includes/core/functions-classes.php';

		/**
		 * Now fire up the main class
		 * The constructor should place
		 * a reference to  itself inside the
		 * $GLOBAL variable.
		 *
		 * @TODO: There's likely a better way to do this - maybe an action hook such as plugins-loaded or init
		 * that calls the constructor function directly?
		 */
		$wpcd_throwaaway_var = new WP_CLOUD_DEPLOY();

	}

	/**
	 * Load CORE Trait Files
	 */
	public function wpcd_load_core_traits() {
		require_once wpcd_path . 'includes/core/traits/get_set_post_type.php';
		require_once wpcd_path . 'includes/core/traits/metaboxes_for_taxonomies_for_servers_and_apps.php';
		require_once wpcd_path . 'includes/core/traits/metaboxes_for_teams_for_servers_and_apps.php';
		require_once wpcd_path . 'includes/core/traits/metaboxes_for_labels_notes_for_servers_and_apps.php';
	}


	/**
	 * Load WP APP Trait Files
	 */
	public function wpcd_load_wpapp_traits() {
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/tabs-security.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/metaboxes-app.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/metaboxes-server.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/commands-and-logs.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/after-prepare-server.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/push-commands.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/admin-columns.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/backup.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/woocommerce_support.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/upgrade.php';
	}

	/**
	 * Warn user of a number of issues that could affect the clean running of wpcd:
	 *  1. If encryption key in wp-config is not defined.
	 *  2. Permalink structure is incorrect.
	 *  3. Certain files cannot be read.
	 *  4. Certain crons are not running on a regular basis.
	 */
	public function wpcd_global_admin_notice() {
		if ( in_array( isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '', array( '127.0.0.1', '::1' ), true ) ) {
			if ( ! wpcd_get_early_option( 'hide-local-host-warning' ) ) {
				$class   = 'notice notice-error';
				$message = __( '<strong>You cannot run the WPCloudDeploy plugin on a localhost server or a server that cannot be reached from the internet.</strong>', 'wpcd' );
				printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
			}
		}

		if ( ! defined( 'WPCD_ENCRYPTION_KEY' ) ) {
			$class = 'notice notice-error is-dismissible';
			/* translators: %s read more */
			$message = sprintf( __( '<strong>WPCD_ENCRYPTION_KEY</strong> is not defined in wp-config.php. We STRONGLY recommend that you define an encryption key in that file so that your passwords and private ssh key data can be more securely stored in the database!  In the meantime we have created a temporary key and stored it in your database. %s', 'wpcd' ), '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/wp-config-entries/" target=”_blank”>' . __( 'Read More', 'wpcd' ) . '</a>' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		if ( defined( 'WPCD_ENCRYPTION_KEY' ) && ( 'your very long encryption key goes here' === WPCD_ENCRYPTION_KEY ) ) {
			$class = 'notice notice-error is-dismissible';
			/* translators: %s read more */
			$message = sprintf( __( '<strong>WPCD_ENCRYPTION_KEY</strong> is defined in wp-config.php but is using the example key from our documentation. This is still insecure so we STRONGLY recommend that you set a new encryption key. %s', 'wpcd' ), '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/wp-config-entries/" target=”_blank”>' . __( 'Read More', 'wpcd' ) . '</a>' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// Check to make sure we're not using the default permalink structure.
		$structure = get_option( 'permalink_structure' );
		if ( empty( $structure ) ) {
			// means that default structure is in use which is no good for callbacks.
			$class   = 'notice notice-error';
			$message = __( 'Warning: WPCloudDeploy cannot use the WordPress default permalink. Please change the permalinks option to something other than <em>plain.</em> This can be done under the WordPress <strong>SETTINGS->Permalinks</strong> menu option.', 'wpcd' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		$screen     = get_current_screen();
		$post_types = array( 'wpcd_app_server', 'wpcd_app', 'wpcd_team', 'wpcd_permission_type', 'wpcd_command_log', 'wpcd_ssh_log', 'wpcd_error_log', 'wpcd_pending_log' );

		// Checks to see if "text files are readable" transient is set or not. If not set then show an admin notice.
		if ( ! get_transient( 'wpcd_readable_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
			$class   = 'notice notice-error is-dismissible wpcd-readability-check';
			$message = __( '<strong>WPCD: Warning</strong> - The <em>includes/core/apps/wordpress-app/scripts/v1/raw/ </em> folder on your WordPress server does not allow text files to be read by browsers and other outside viewers. This folder contains the script files that we execute on your WordPress server. Please modify your web server configuration to allow .txt files in this folder to be readable. Otherwise, The WPCD plugin will not be able to manage your servers and sites. <br /><br /> Note that you CANNOT run this plugin on a local machine - it must be run on a server that is reachable from the public internet. <br /><br /> If you dismiss this message but do not resolve the issue, it will appear again in 12 hours. <br /><br /> <a href="" id="wpcd-check-again">Check Again</a>', 'wpcd' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		$not_loaded_crons = array();

		if ( ! get_transient( 'wpcd_cron_check' ) ) {
			$wpcd_crons = array( 'do_deferred_actions_for_server', 'do_deferred_actions_for_app', 'wordpress_file_watcher_delete_temp_files', 'scan_new_notifications_to_send', 'clean_up_pending_logs' );
			$wpcd_crons = apply_filters( 'wpcd_crons_needing_active_check', $wpcd_crons );

			if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
				$wpcd_crons[] = 'file_watcher_delete_temp_files';
				$wpcd_crons[] = 'do_deferred_actions_for_vpn';
			}

			if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
				$wpcd_crons[] = 'do_deferred_actions_for_basic_server';
			}

			foreach ( $wpcd_crons as $cron ) {
				$cron_transient = "wpcd_{$cron}_is_active";

				if ( ! get_transient( $cron_transient ) ) {
					$not_loaded_crons[] = $cron;
				}
			}

			// If empty means all crons are loaded properly, then set the transient.
			if ( count( $not_loaded_crons ) === 0 ) {
				set_transient( 'wpcd_cron_check', 1, 12 * HOUR_IN_SECONDS );
			}
		}

		// Checks to see if "cron check" transient is set or not. If not set then show an admin notice.
		if ( ! get_transient( 'wpcd_loaded_timeout' ) && ! get_transient( 'wpcd_cron_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
			$class            = 'notice notice-error is-dismissible wpcd-cron-check';
			$not_loaded_crons = implode( ', ', $not_loaded_crons );
			$message          = __( '<strong>WPCD: Warning</strong> - ' . $not_loaded_crons . ' cron(s) are not running.', 'wpcd' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * With the introduction of plugins language packs in WordPress loading the textdomain is slightly more complex.
	 *
	 * We now have 3 steps:
	 *
	 * 1. Check for the language pack in the WordPress core directory
	 * 2. Check for the translation file in the plugin's language directory
	 * 3. Fallback to loading the textdomain the classic way
	 *
	 * @since    2.8.0
	 * @return boolean True if the language file was loaded, false otherwise
	 */
	public function load_plugin_textdomain() {

		$lang_dir       = trailingslashit( wpcd_root ) . 'languages/';
		$lang_path      = trailingslashit( wpcd_path ) . 'languages/';
		$locale         = apply_filters( 'plugin_locale', get_locale(), 'wpcd' );
		$mofile         = "wpcd-$locale.mo";
		$glotpress_file = WP_LANG_DIR . '/plugins/wpcd/' . $mofile;

		// Look for the GlotPress language pack first of all.
		if ( file_exists( $glotpress_file ) ) {
			$language = load_textdomain( 'wpcd', $glotpress_file );
		} elseif ( file_exists( $lang_path . $mofile ) ) {
			$language = load_textdomain( 'wpcd', $lang_path . $mofile );
		} else {
			$language = load_plugin_textdomain( 'wpcd', false, $lang_dir );
		}

		return $language;

	}

	/**
	 * Activation hook
	 *
	 * ** Not currently called or used. Saving for later.
	 */
	public function activation_hook() {
		// first install.
		$version = get_option( 'wpcd_version' );
		if ( ! $version ) {
			update_option( 'wpcd_last_version_upgrade', wpcd_version );
		}

		if ( wpcd_version !== $version ) {
			update_option( 'wpcd_version', wpcd_version );
		}

		// run setup.
		if ( ! class_exists( 'WPCD_Setup' ) ) {
			require_once wpcd_path . 'includes/core/class-wpcd-setup.php';
		}

		$wpcd_setup = new WPCD_Setup();
		$wpcd_setup->run_setup();
	}


}

/**
 * Get WPCD running.
 */
$wpcd_throwaway = new WPCD_Init();
