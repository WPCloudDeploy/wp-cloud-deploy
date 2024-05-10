<?php
/**
Plugin Name: WPCloudDeploy
Plugin URI: https://wpclouddeploy.com
Description: Deploy and manage cloud servers and apps from inside the WordPress Admin dashboard.
Version: 5.8.1
Requires at least: 5.8
Requires PHP: 7.4
Item Id: 1493
Author: WPCloudDeploy
Author URI: https://wpclouddeploy.com
Domain Path: /languages
GitHub Plugin URI: WPCloudDeploy/wp-cloud-deploy
Primary Branch: main
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

			// Define the default brand colors for wp-admin.
			define( 'WPCD_PRIMARY_BRAND_COLOR', '#E91E63' );
			define( 'WPCD_SECONDARY_BRAND_COLOR', '#FF5722' );
			define( 'WPCD_TERTIARY_BRAND_COLOR', '#03114A' );
			define( 'WPCD_ACCENT_BG_COLOR', '#0d091a' );
			define( 'WPCD_MEDIUM_ACCENT_BG_COLOR', '#D3D3D3' );
			define( 'WPCD_MEDIUM_BG_COLOR', '#F3F3F5' );
			define( 'WPCD_LIGHT_BG_COLOR', '#FDFDFD' );
			define( 'WPCD_ALTERNATE_ACCENT_BG_COLOR', '#CFD8DC' );
			define( 'WPCD_POSITIVE_COLOR', '#008000' );
			define( 'WPCD_NEGATIVE_COLOR', '#8B0000' );
			define( 'WPCD_ALT_NEGATIVE_COLOR', '#FF0000' );
			define( 'WPCD_WHITE_COLOR', '#ffffff' );
			define( 'WPCD_TERMINAL_BG_COLOR', '#000000' );
			define( 'WPCD_TERMINAL_FG_COLOR', '#ffffff' );

			// Define the default brand colors for front-end.
			define( 'WPCD_FE_PRIMARY_BRAND_COLOR', '#E91E63' );
			define( 'WPCD_FE_SECONDARY_BRAND_COLOR', '#281d67' );
			define( 'WPCD_FE_TERTIARY_BRAND_COLOR', '#03114A' );
			define( 'WPCD_FE_ACCENT_BG_COLOR', '#0d091a' );
			define( 'WPCD_FE_MEDIUM_ACCENT_BG_COLOR', '#D3D3D3' );
			define( 'WPCD_FE_MEDIUM_BG_COLOR', '#F3F3F5' );
			define( 'WPCD_FE_LIGHT_BG_COLOR', '#FDFDFD' );
			define( 'WPCD_FE_ALTERNATE_ACCENT_BG_COLOR', '#CFD8DC' );
			define( 'WPCD_FE_POSITIVE_COLOR', '#008000' );
			define( 'WPCD_FE_NEGATIVE_COLOR', '#8B0000' );
			define( 'WPCD_FE_ALT_NEGATIVE_COLOR', '#FF0000' );
			define( 'WPCD_FE_WHITE_COLOR', '#ffffff' );

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

		/* Check for incompatible add-ons */
		if ( is_admin() && ! $this->check_all_addons_compatible() ) {
			// You will likely not get here because if the check shows add-ons are incompatible we will deactivate ourselves.
			return false;
		}

		/* Use init hook to load up required files */
		add_action( 'init', array( $this, 'required_files' ), -20 );

		/* Use admin_init hook to run things that should only be run when the admin is logged in. */
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		/* Add hook into option changes so we can detect when a plugin has been activated. */
		add_action( 'updated_option', array( $this, 'wpcd_updated_option' ), 10, 3 );

		/* Create a custom schedule for 1 minute */
		add_filter( 'cron_schedules', array( $this, 'custom_cron_schedule' ) );

		/* Activation and deactivation hooks */
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		/* Show some notices as required */
		add_action( 'admin_notices', array( $this, 'wpcd_global_admin_notice' ) );

		/* Show documentation and quick-start links in the plugin list. */
		add_filter( 'plugin_row_meta', array( $this, 'wpcd_append_support_and_faq_links' ), 10, 4 );

		/* Attempt to get and show any upgrade notice for the next version of the plugin - @see https://wisdomplugin.com/add-inline-plugin-update-message/ */
		add_action( 'in_plugin_update_message-wp-cloud-deploy/wpcd.php', array( $this, 'wpcd_plugin_update_message' ), 10, 2 );

		/* Load languages files */
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		/* Send email to admin if critical crons aren't running. */
		add_action( 'shutdown', array( $this, 'send_email_for_absent_crons' ), 20 );

		/* Show version number in admin footer */
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 10, 1 );
	}

	/**
	 * Create cron timers for use by other wpcd functions.
	 * Three timers - 1 min, 2 min and 15 min.
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
				'display'  => __( 'Every 2 minutes', 'wpcd' ),
			);
		}
		if ( ! isset( $schedules['every_fifteen_minute'] ) ) {
			$schedules['every_fifteen_minute'] = array(
				'interval' => 900,
				'display'  => __( 'Every 15 minutes', 'wpcd' ),
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

		// If all add-ons are not compatible, deactivate ourselves.
		$this->check_all_addons_compatible();

		require_once wpcd_path . 'includes/core/class-wpcd-base.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-base.php';
		require_once wpcd_path . 'includes/core/apps/class-wpcd-app.php';
		require_once wpcd_path . 'includes/core/class-wpcd-posts-permission-type.php';
		require_once wpcd_path . 'includes/core/class-wpcd-dns.php';
		require_once wpcd_path . 'includes/core/functions.php';
		require_once wpcd_path . 'includes/core/functions-kses.php';
		require_once wpcd_path . 'includes/core/functions-icons.php';
		require_once wpcd_path . 'includes/core/functions-metabox.php';

		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/vpn/class-vpn-app.php';
		}

		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/basic-server/class-basic-server-app.php';
		}

		if ( defined( 'WPCD_LOAD_STABLEDIFF_APP' ) && ( true === WPCD_LOAD_STABLEDIFF_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/stable-diffusion/class-stablediff-app.php';
		}

		// @TODO: Have to make these static till autoloading is implemented.
		// @TODO: This is also poor because N crons will be registered for N providers even if only one provider is actually active and has credentials.
		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			WPCD_VPN_APP::activate( $network_wide );
		}
		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			WPCD_BASIC_SERVER_APP::activate( $network_wide );
		}

		if ( defined( 'WPCD_LOAD_STABLEDIFF_APP' ) && ( true === WPCD_LOAD_STABLEDIFF_APP ) ) {
			WPCD_STABLEDIFF_APP::activate( $network_wide );
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

		/* Custom tables for DNS and PROVIDERS */
		if ( true === wpcd_is_custom_dns_provider_tables_enabled() ) {
			require_once wpcd_path . 'includes/core/custom-table/api/class-wpcd-custom-table-api.php';
			require_once wpcd_path . 'includes/core/custom-table/api/class-wpcd-ct-provider-api.php';
			require_once wpcd_path . 'includes/core/custom-table/api/class-wpcd-ct-dns-provider-api.php';
			require_once wpcd_path . 'includes/core/custom-table/api/class-wpcd-ct-dns-zone-api.php';
			require_once wpcd_path . 'includes/core/custom-table/api/class-wpcd-ct-dns-zone-record-api.php';

			require_once wpcd_path . 'includes/core/apps/wordpress-app/public/class-wordpress-app-public.php';
			require_once wpcd_path . 'required_plugins/mb-custom-table/mb-custom-table.php';
			require_once wpcd_path . 'includes/core/custom-table/class-wpcd-custom-table.php';
			require_once wpcd_path . 'includes/core/custom-table/class-wpcd-provider.php';
			require_once wpcd_path . 'includes/core/custom-table/class-wpcd-dns-provider.php';
			require_once wpcd_path . 'includes/core/custom-table/class-wpcd-dns-zone.php';
			require_once wpcd_path . 'includes/core/custom-table/class-wpcd-dns-zone-record.php';

			WPCD_MB_Custom_Table::Activate( $network_wide );
		}

		require_once wpcd_path . 'includes/core/apps/wordpress-app/public/class-wordpress-app-public.php';
		WPCD_WORDPRESS_APP_PUBLIC::activate( $network_wide );

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

		// Set cron for some options that the Wisdom plugin will pick up.
		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				if ( ! wp_next_scheduled( 'wpcd_wisdom_custom_options' ) ) {
					wp_schedule_event( time(), 'twicedaily', 'wpcd_wisdom_custom_options' );
				}
				restore_current_blog();
			}
		} else {
			if ( ! wp_next_scheduled( 'wpcd_wisdom_custom_options' ) ) {
				wp_schedule_event( time(), 'twicedaily', 'wpcd_wisdom_custom_options' );
			}
		}

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

		if ( defined( 'WPCD_LOAD_STABLEDIFF_APP' ) && ( true === WPCD_LOAD_STABLEDIFF_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/stable-diffusion/class-stablediff-app.php';
		}

		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-app.php';

		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			WPCD_VPN_APP::deactivate( $network_wide );
		}

		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			WPCD_BASIC_SERVER_APP::deactivate( $network_wide );
		}

		if ( defined( 'WPCD_LOAD_STABLEDIFF_APP' ) && ( true === WPCD_LOAD_STABLEDIFF_APP ) ) {
			WPCD_STABLEDIFF_APP::deactivate( $network_wide );
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

		// Clear old cron.
		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				wp_unschedule_hook( 'wpcd_wisdom_custom_options' );
				restore_current_blog();
			}
		} else {
			wp_unschedule_hook( 'wpcd_wisdom_custom_options' );
		}

		// Clear long-lived transients.
		delete_transient( 'wpcd_wisdom_custom_options_first_run_done' );
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
		require_once wpcd_path . '/required_plugins/mb-admin-columns/mb-admin-columns.php';
		require_once wpcd_path . '/required_plugins/meta-box-conditional-logic/meta-box-conditional-logic.php';
		require_once wpcd_path . '/required_plugins/meta-box-tabs/meta-box-tabs.php';
		require_once wpcd_path . '/required_plugins/meta-box-tooltip/meta-box-tooltip.php';
		require_once wpcd_path . '/required_plugins/mb-term-meta/mb-term-meta.php';
		require_once wpcd_path . '/required_plugins/meta-box-columns/meta-box-columns.php';
		require_once wpcd_path . '/required_plugins/meta-box-group/meta-box-group.php';
		require_once wpcd_path . '/required_plugins/mb-user-meta/mb-user-meta.php';
		require_once wpcd_path . '/required_plugins/mb-custom-table/mb-custom-table.php';

		/* Include custom metabox.io fields we created. */
		require_once wpcd_path . 'includes/core/metabox-io-custom-fields/wpcd-card-container-field.php';

		/* Load up some licensing files. */
		if ( true === is_admin() ) {
			require_once WPCD_PATH . '/includes/vendor/WPCD_EDD_SL_Plugin_Updater.php';
			require_once WPCD_PATH . '/includes/core/class-wpcd-license.php';
		}

		/* Load up our files */
		require_once wpcd_path . 'includes/core/functions.php';
		require_once wpcd_path . 'includes/core/functions-kses.php';
		require_once wpcd_path . 'includes/core/functions-icons.php';
		require_once wpcd_path . 'includes/core/functions-metabox.php';
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
		require_once wpcd_path . 'includes/core/class-wpcd-app-expiration.php';
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

		// 3rd party optional integrations.
		require_once wpcd_path . 'includes/core/integrations/class-logtivity.php';

		// Handle admin notices.
		if ( is_admin() ) {
			require_once wpcd_path . 'includes/core/functions-handle-admin-notices.php';
		}

		// WPCD Better Crons.
		require_once wpcd_path . 'includes/core/class-wpcd-better-crons.php';

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
		* For the STABLEDIFF App
		*/
		/* @TODO: Need to find a more dynamic way to load these by letting apps register themselves at the right time and having them load up their own files */
		if ( defined( 'WPCD_LOAD_STABLEDIFF_APP' ) && ( true === WPCD_LOAD_STABLEDIFF_APP ) ) {
			require_once wpcd_path . 'includes/core/apps/stable-diffusion/class-stablediff-app.php';
			require_once wpcd_path . 'includes/core/apps/stable-diffusion/class-stablediff-app-settings.php';
			require_once wpcd_path . 'includes/core/apps/stable-diffusion/class-stablediff-ssh.php';
			require_once wpcd_path . 'includes/core/apps/stable-diffusion/class-stablediff-woocommerce.php';
		}

		/**
		* For the WP App
		*/
		/* @TODO: Need to find a more dynamic way to load these by letting apps register themselves at the right time and having them load up their own files */
		$this->wpcd_load_wpapp_traits();
		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-app.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-app-settings.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-ssh.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-posts-site-package.php';

		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-posts-update-plan.php';
			require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-posts-update-plan-log.php';
			require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-posts-quota-profile.php';
			require_once wpcd_path . 'includes/core/apps/wordpress-app/class-wordpress-posts-quota-limits.php';
		}

		// Integrations for the WP APP.
		require_once wpcd_path . 'includes/core/apps/wordpress-app/integrations/class-wordpress-app-logtivity.php';

		// Remaining files for the WP APP.
		require_once wpcd_path . 'includes/core/apps/wordpress-app/functions-classes-wordpress-app.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/public/class-wordpress-app-public.php';

		/**
		 * Remaining core files.
		 */
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

		/**
		 * Finally, maybe ask for setup using Setup wizard.
		 * Proceed only if both options 'wpcd_plugin_setup' & 'wpcd_skip_wizard_setup' = false
		 * 'wpcd_plugin_setup' will be added at the end of wizard steps
		 * 'wpcd_skip_wizard_setup' will be set to true if user chose to skip wizard from admin notice
		 */
		if ( ! get_option( 'wpcd_plugin_setup', false ) && ! get_option( 'wpcd_skip_wizard_setup', false ) ) {
			require_once wpcd_path . 'includes/core/class-wpcd-setup-wizard.php';
		}

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
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/script-handlers.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/after-prepare-server.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/push-commands.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/admin-columns.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/backup.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/woocommerce_support.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/multi-tenant-app.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/upgrade.php';
		require_once wpcd_path . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/unused.php';
	}

	/**
	 * Warn user of a number of issues that could affect the clean running of wpcd:
	 *  1. If encryption key in wp-config is not defined.
	 *  2. Permalink structure is incorrect.
	 *  3. Certain files cannot be read.
	 *  4. Certain crons are not running on a regular basis.
	 */
	public function wpcd_global_admin_notice() {

		// Only show messages to admins.
		if ( ! wpcd_is_admin() ) {
			return;
		}

		$screen     = get_current_screen();
		$post_types = array( 'wpcd_app_server', 'wpcd_app', 'wpcd_team', 'wpcd_permission_type', 'wpcd_command_log', 'wpcd_ssh_log', 'wpcd_error_log', 'wpcd_pending_log' );

		$php_version    = phpversion();
		$php_version_id = str_replace( '.', '0', $php_version );

		// Checks to see if "php version check" transient is set or not. If not set then show an admin notice.
		if ( ! get_transient( 'wpcd_php_version_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
			// Here 70400 is a php version 7.4.0.
			if ( (int) $php_version_id < 70400 ) {
				$class = 'notice notice-error is-dismissible wpcd-php-version-check';
				/* translators: %s php version */
				$message = sprintf( __( '<strong>WPCloudDeploy plugin requires a PHP version greater or equal to "7.4.0". You are running %s.</strong>', 'wpcd' ), $php_version );
				/* Translators: %2$s is a set of CSS classes; %2$s is a text message about the PHP version being incompatible. */
				printf( '<div data-dismissible="notice-php-warning" class="%2$s"><p>%3$s</p></div>', wp_create_nonce( 'wpcd-admin-dismissible-notice' ), $class, $message );
			}
		}

		// Are we running on localhost?  Of so throw error.
		if ( in_array( isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '', array( '127.0.0.1', '::1' ), true ) ) {
			if ( ! wpcd_get_early_option( 'hide-local-host-warning' ) ) {
				// Checks to see if "localhost check" transient is set or not. If not set then show an admin notice.
				if ( ! get_transient( 'wpcd_localhost_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
					$class   = 'notice notice-error is-dismissible wpcd-localhost-check';
					$message = __( '<strong>You cannot run the WPCloudDeploy plugin on a localhost server or a server that cannot be reached from the internet.</strong>', 'wpcd' );
					/* Translators: %2$s is a set of CSS classes; %2$s is a text message about wpcd not compatible with localhost installations. */
					printf( '<div data-dismissible="notice-localhost-warning" class="%2$s"><p>%3$s</p></div>', wp_create_nonce( 'wpcd-admin-dismissible-notice' ), $class, $message );
				}
			}
		}

		if ( ! defined( 'WPCD_ENCRYPTION_KEY' ) ) {
			$class = 'notice notice-error is-dismissible';
			/* translators: %s read more */
			$message = sprintf( __( '<strong>WPCD_ENCRYPTION_KEY</strong> is not defined in wp-config.php. We STRONGLY recommend that you define an encryption key in that file so that your passwords and private ssh key data can be more securely stored in the database!  In the meantime we have created a temporary key and stored it in your database. %s', 'wpcd' ), '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/wp-config-entries/" target=”_blank”>' . __( 'Read More', 'wpcd' ) . '</a>' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about the encryption key being required in wp-config.php. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		if ( defined( 'WPCD_ENCRYPTION_KEY' ) && ( 'your very long encryption key goes here' === WPCD_ENCRYPTION_KEY ) ) {
			$class = 'notice notice-error is-dismissible';
			/* translators: %s read more */
			$message = sprintf( __( '<strong>WPCD_ENCRYPTION_KEY</strong> is defined in wp-config.php but is using the example key from our documentation. This is still insecure so we STRONGLY recommend that you set a new encryption key. %s', 'wpcd' ), '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/wp-config-entries/" target=”_blank”>' . __( 'Read More', 'wpcd' ) . '</a>' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about the encryption key not being correct. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// Check to make sure we're not using the default permalink structure.
		$structure = get_option( 'permalink_structure' );
		if ( empty( $structure ) ) {
			// means that default structure is in use which is no good for callbacks.
			$class   = 'notice notice-error';
			$message = __( 'Warning: WPCloudDeploy cannot use the WordPress default permalink. Please change the permalinks option to something other than <em>plain.</em> This can be done under the WordPress <strong>SETTINGS->Permalinks</strong> menu option.', 'wpcd' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about permalinks not being set properly. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// Checks to see if "text files are readable" transient is set or not. If not set then show an admin notice.
		if ( ! get_transient( 'wpcd_readable_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
			$class   = 'notice notice-error is-dismissible wpcd-readability-check';
			$message = __( '<strong>WPCD: Warning</strong> - The <em>includes/core/apps/wordpress-app/scripts/v1/raw/ </em> folder on your WordPress server does not allow text files to be read by browsers and other outside viewers. This folder contains the script files that we execute on your WordPress server. Please modify your web server configuration to allow .txt files in this folder to be readable. Otherwise, The WPCD plugin will not be able to manage your servers and sites. <br /><br /> Note that you CANNOT run this plugin on a local machine - it must be run on a server that is reachable from the public internet. <br /><br /> If you dismiss this message but do not resolve the issue, it will appear again in 12 hours. <br /><br /> <a href="" id="wpcd-check-again">Check Again</a>', 'wpcd' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about the files that are not readable but should be. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// Make sure all our CRONS are running or, if not, that the user has seen and dismissed the warning notice.
		$not_loaded_crons = $this->get_list_of_absent_crons();

		// Checks to see if "cron check" transient is set or not. If not set then show an admin notice.
		if ( ! get_transient( 'wpcd_loaded_timeout' ) && ! get_transient( 'wpcd_cron_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
			$class            = 'notice notice-error is-dismissible wpcd-cron-check';
			$not_loaded_crons = implode( ', ', $not_loaded_crons );
			$message          = __( '<strong>WPCD: Warning</strong> - ' . $not_loaded_crons . ' cron(s) are not running.', 'wpcd' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about crons that are not currently running. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// If our REDIS plugin is active, show a message since that's no longer necessary for REDIS functionality.
		if ( class_exists( 'WPCD_Redis_Init' ) ) {
			$class   = 'notice notice-error wpcd-redis-deprecation-check';
			$message = __( '<strong>WPCD: Warning</strong> - The <em>REDIS</em> plugin for WPCloudDeploy is no longer required. Please deactivate it!', 'wpcd' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about the REDIS plugin not being required. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}
	}

	/**
	 * If critical crons aren't running, send email to admin.
	 *
	 * Action Hook: Shutdown.
	 */
	public function send_email_for_absent_crons() {

		// Exit if option to suppress sending these emails is turned on.
		if ( true === (bool) wpcd_get_early_option( 'wpcd_do_not_send_cron_warning_emails' ) ) {
			return;
		}

		// Get list of crons that aren't running.
		$not_loaded_crons = $this->get_list_of_absent_crons();

		// Send email to site administrator if we have some crons that are not running.
		if ( ! empty( $not_loaded_crons ) ) {

			$str_crons = implode( ', ', $not_loaded_crons );
			$to        = get_site_option( 'admin_email' );
			$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
			$subject   = sprintf(
				/* translators: %s: Site title. */
				__( '[%s] - One or more critical crons are not running...', 'wpcd' ),
				wp_specialchars_decode( get_option( 'blogname' ) )
			);
			$body   = array();
			$body[] = __( 'Hello Admin,', 'wpcd' );
			$body[] = '';
			$body[] = __( '<strong>WPCD: Warning</strong> - certain critical CRONS are not running on your site.  Below are the ones that appear to be missing:', 'wpcd' );
			$body[] = '';
			$body[] = $str_crons;
			$body[] = '';
			$body[] = __( 'It is possible that this is a minor hiccup or false positive and the cron(s) are still running. You can use the free WP CRONTROL plugin to examine running crons to see if the crons are still present and active.', 'wpcd' );
			$body[] = '';
			$body[] = __( 'Before contacting support, please try to disable and renable the plugin to reactivate crons. Additionally, please verify that your WP CRON is firing every 1 minute - either from enough frequent site traffic, a native LINUX cron process or better yet, the use of the WPCD BETTER CRONS method.', 'wpcd' );
			$body[] = '';
			$body[] = __( '--------', 'wpcd' );
			$body[] = '';
			$body[] = __( 'If you do not want to receive these emails you can turn them off in the settings area under the MISC tab.', 'wpcd' );
			$body[] = '';
			$body[] = __( 'Thanks,', 'wpcd' );
			$body[] = '';
			$body[] = __( '- Your WP Management Dashboard BOT.', 'wpcd' );

			// Apply filters to each var for the outgoing email.
			$headers = apply_filters( 'wpcd_send_email_for_absent_crons_to', $headers );
			$to      = apply_filters( 'wpcd_send_email_for_absent_crons_to', $to );
			$subject = apply_filters( 'wpcd_send_email_for_absent_crons_subject', $subject );
			$body    = apply_filters( 'wpcd_send_email_for_absent_crons_body', $body );

			$email = array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => implode( "\n" . PHP_EOL . '<br />', $body ),
				'headers' => $headers,
			);

			if ( defined( 'WPCD_CRONS_NOT_RUNNING_EMAIL_TEST' ) && WPCD_CRONS_NOT_RUNNING_EMAIL_TEST ) {
				// When this constant is set, we're always going to send an email - useful for TESTING.
				wp_mail( $email['to'], wp_specialchars_decode( $email['subject'] ), $email['body'], $email['headers'] );
			} else {
				// Send an email every 8 hours. Start by getting the current date and time.
				$current_datetime     = gmdate( 'Y-m-d H:i:s' );
				$str_current_datetime = strtotime( $current_datetime );

				// Calculate the next date/time to send an alert email. The option wpcd_crons_not_running_email_next_send_date is where we store the next time we have to send an email if all our crons aren't running.
				$next_email_send_date     = get_option( 'wpcd_crons_not_running_email_next_send_date' );
				$str_next_email_send_date = strtotime( $next_email_send_date );

				if ( $str_current_datetime >= $str_next_email_send_date ) {
					$next_send_time = gmdate( 'Y-m-d H:i:s', strtotime( '+8 hours' ) );
					update_option( 'wpcd_crons_not_running_email_next_send_date', $next_send_time );
					wp_mail( $email['to'], wp_specialchars_decode( $email['subject'] ), $email['body'], $email['headers'] );
				}
			}
		}

	}

	/**
	 * Returns a list of critical crons that aren't running.
	 *
	 * Only returns the list if the user has not dismissed the notification at the top of the screen.
	 */
	public function get_list_of_absent_crons() {

		$not_loaded_crons = array();

		if ( ! get_transient( 'wpcd_cron_check' ) ) {
			$wpcd_crons = array( 'send_email_alert_for_long_pending_tasks', 'do_deferred_actions_for_server', 'do_deferred_actions_for_app', 'wordpress_file_watcher_delete_temp_files', 'scan_new_notifications_to_send', 'clean_up_pending_logs' );
			$wpcd_crons = apply_filters( 'wpcd_crons_needing_active_check', $wpcd_crons );

			if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
				$wpcd_crons[] = 'file_watcher_delete_temp_files';
				$wpcd_crons[] = 'do_deferred_actions_for_vpn';
			}

			if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
				$wpcd_crons[] = 'do_deferred_actions_for_basic_server';
			}

			if ( defined( 'WPCD_LOAD_STABLEDIFF_APP' ) && ( true === WPCD_LOAD_STABLEDIFF_APP ) ) {
				$wpcd_crons[] = 'do_deferred_actions_for_stablediff';
			}

			foreach ( $wpcd_crons as $cron ) {
				$cron_transient = "wpcd_{$cron}_is_active";

				if ( ! get_transient( $cron_transient ) ) {
					$not_loaded_crons[] = $cron;
				}
			}

			// If empty means all crons are loaded properly, then set the transient so that we can recheck once per hour.
			// This transient will also be set if the user dismisses the notice they get at the top of the screen.
			// Here we will set it so that we'll recheck in 1 hour.  If it gets set when the user dismisses the notice it will be set for 12 hours.
			// See the function set_cron_check() in file class-wordpress-app.php.
			if ( count( $not_loaded_crons ) === 0 ) {
				set_transient( 'wpcd_cron_check', 1, 1 * HOUR_IN_SECONDS );
			}
		} else {
			// Transient is set.  But the time remaining can sometimes be negative. While we're not sure why that happens, if it is, delete it!
			$time_left = (int) wpcd_get_transient_remaining_time_in_mins( 'wpcd_cron_check' );

			if ( false === $time_left ) {
				// looks like an object cache is in use so we can't get the time left data.  Therefore do nothing.
			} else {
				if ( $time_left < 0 ) {
					delete_transient( 'wpcd_cron_check' );
				}
			}
		}

		return $not_loaded_crons;

	}

	/**
	 * Filters the array of row meta for each/specific plugin in the Plugins list table.
	 * Appends additional links below each/specific plugin on the plugins page.
	 *
	 * Action Hook: plugin_row_meta
	 *
	 * @since 4.15.1
	 *
	 * @access  public
	 * @param   array  $links_array            An array of the plugin's metadata.
	 * @param   string $plugin_file_name       Path to the plugin file.
	 * @param   array  $plugin_data            An array of plugin data.
	 * @param   string $status                 Status of the plugin.
	 *
	 * @return  array  $links_array New links to be added to the plugin list.
	 */
	public function wpcd_append_support_and_faq_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
		if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {

			// You can still use `array_unshift()` to add links at the beginning.
			$links_array[] = '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy/introduction-to-wpcloud-deploy/" target="_blank">Quick Start</a>';
			$links_array[] = '<a href="https://wpclouddeploy.com/doc-landing/" target="_blank">Documentation</a>';
			$links_array[] = '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/bootstrapping-a-wordpress-server-with-our-scripts/" target="_blank">Bootstrap Your Own Server</a>';

			$settings_link = admin_url( 'edit.php?post_type=wpcd_app_server&page=wpcd_settings' );
			$links_array[] = '<a href="' . $settings_link . '">Settings</a>';

			$links_array[] = '<a href="https://wpclouddeploy.com/pricing/" target="_blank">Premium Options</a>';
			$links_array[] = '<a href="https://wpclouddeploy.com/support/" target="_blank">Support</a>';
		}

		return $links_array;
	}

	/**
	 * Attempt to get and show any upgrade notice for the next version of the plugin.
	 *
	 * @see https://wisdomplugin.com/add-inline-plugin-update-message/
	 * @see https://developer.wordpress.org/reference/hooks/in_plugin_update_message-file/
	 *
	 * Action Hook: in_plugin_update_message-{$file} | in_plugin_update_message-wpcd/wpcd.php
	 *
	 * @since 4.15.1
	 *
	 * @param array  $data The array of plugin metadata.
	 * @param object $response An object of metadata about the available plugin update.
	 *
	 * @return void
	 */
	public function wpcd_plugin_update_message( $data, $response ) {
		if ( ! defined( 'WPCD_HIDE_CHANGELOG_IN_PLUGIN_LIST' ) || ( defined( 'WPCD_HIDE_CHANGELOG_IN_PLUGIN_LIST' ) && ! WPCD_HIDE_CHANGELOG_IN_PLUGIN_LIST ) ) {
			if ( isset( $data['upgrade_notice'] ) ) {
				printf(
					'<div class="update-message">%s</div>',
					wp_kses_post( wpautop( $data['upgrade_notice'] ) )
				);
			} else {
				$release_notes      = wpcd_get_string_between( $data['sections']->changelog, '<p>', '<p>' );  // Grab data between two paragraph tags - this gives us the raw release notes for the most recent release.
				$release_notes      = wpcd_get_string_between( $data['sections']->changelog, '<ul>', '</ul>' ); // Just grab the list and remove everthing above and below it.
				$release_notes_link = '<br /><a href="https://wpclouddeploy.com/category/release-notes/" target="_blank">' . __( 'View friendly release notes to learn about any breaking changes that might affect you.', 'wpcd' ) . '</a>';
				printf(
					'<div class="update-message">%s</div>',
					'<br />' . wp_kses_post( $release_notes ) . wp_kses_post( $release_notes_link )
				);
			}
		}
	}

	/**
	 * Add our version number and message to the admin footer area.
	 *
	 * @since 5.3.0
	 *
	 * Filter Hook: admin_footer_text
	 *
	 * @param string $msg The existing message.
	 *
	 * @return string
	 */
	public function admin_footer_text( $msg ) {

		// Bail if we're not far enough into the initialization process to get the screen id.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $msg;
		}

		// Message to show defaults to the original incoming message string.
		$return = $msg;

		$screen = get_current_screen();

		/* @TODO: The list of post types and taxonomies used below should be a global function that is filtered and called by add-ons to add their own CPT to the arrays. */
		if ( ( is_object( $screen ) && ( in_array( $screen->post_type, array( 'wpcd_app', 'wpcd_app_server', 'wpcd_cloud_provider', 'wpcd_ssh_log', 'wpcd_team', 'wpcd_command_log', 'wpcd_pending_log', 'wpcd_error_log', 'wpcd_schedules', 'wpcd_snapshots', 'wpcd_permission_type', 'wpcd_notify_log', 'wpcd_notify_user', 'wpcd_notify_sent' ), true ) || in_array( $screen->taxonomy, array( 'wpcd_app_group', 'wpcd_app_server_group', 'wpcd_reporting_group' ), true ) ) ) ) {

			if ( defined( 'WPCD_LONG_NAME' ) && ! empty( WPCD_LONG_NAME ) ) {
				$product_name = WPCD_LONG_NAME;
			} else {
				$product_name = 'WPCloudDeploy';
			}

			/* Translators: %1$s is the WPCD Product Name; %2$s is the WPCD product version. */
			$return = sprintf( __( 'Powered by %1$s %2$s.', 'wpcd' ), $product_name, WPCD_VERSION ) . '<br />' . $return;

		}

		return $return;

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
	 * *** This function not used yet.
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

	/**
	 * Processes that run only when in the wp-admin area.
	 *
	 * Action Hook: admin_init
	 */
	public function admin_init() {

		// Setup the Wisdom custom options on first run.
		if ( function_exists( 'wpcd_start_plugin_tracking' ) ) {
			if ( ! (bool) get_transient( 'wpcd_wisdom_custom_options_first_run_done' ) ) {
				do_action( 'wpcd_wisdom_custom_options' );  // Trigger our custom calculations.
				$wisdom = wpcd_start_plugin_tracking();
				$wisdom->schedule_tracking(); // Setup the wisdom cron. Normally this is done automatically by the wisdom code upon plugin activation but we end up bypassing it because we delay things a bit so we can setup custom vars.  So have to set it up manually.
				set_transient( 'wpcd_wisdom_custom_options_first_run_done', 1, ( 60 * 24 * 7 ) * MINUTE_IN_SECONDS );
			}
		}

	}

	/**
	 * Check to see if all installed plugins are compatible with this version of WPCD.
	 *
	 * @return boolean
	 */
	public function are_all_addons_compatible() {

		// Might require this file since we're calling this function early.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Default return value to true.
		$return = true;

		/* Array of our add-ons and their minimum compatible version */
		$add_ons['wpcd-alibaba-provider/wpcd-alibaba-provider.php']             = '1.3.0';
		$add_ons['wpcd-aws-ec2-provider/wpcd-aws-ec2-provider.php']             = '1.9.0';
		$add_ons['wpcd-aws-lightsail-provider/wpcd-aws-lightsail-provider.php'] = '1.6.0';
		$add_ons['wpcd-azure-provider/wpcd-azure-provider.php']                 = '1.4.0';
		$add_ons['wpcd-custom-server-provider/wpcd-custom-server-provider.php'] = '1.1.0';
		$add_ons['wpcd-exoscale-provider/wpcd-exoscale-provider.php']           = '1.3.1';
		$add_ons['wpcd-git-control/wpcd-git-control.php']                       = '1.0.0';
		$add_ons['wpcd-google-provider/wpcd-google-provider.php']               = '1.3.0';
		$add_ons['wpcd-hetzner-provider/wpcd-hetzner-provider.php']             = '1.4.1';
		$add_ons['wpcd-linode-provider/wpcd-linode-provider']                   = '1.4.0';
		$add_ons['wpcd-multisite/wpcd-multisite.php']                           = '1.7.0';
		$add_ons['wpcd-multi-tenant/wpcd-multi-tenant.php']                     = '1.0.0';
		$add_ons['wpcd-power-tools/wpcd-power-tools.php']                       = '2.4.0';
		$add_ons['wpcd-redis/wpcd-redis.php']                                   = '1.3.1';
		$add_ons['wpcd-server-sync/wpcd-server-sync.php']                       = '1.6.0';
		$add_ons['wpcd-upcloud-provider/wpcd-upcloud-provider.php']             = '2.3.0';
		$add_ons['wpcd-virtual-cloud-provider/wpcd-virtual-cloud-provider.php'] = '1.1.1';
		$add_ons['wpcd-vultr-provider/wpcd-vultr-provider.php']                 = '2.3.1';
		$add_ons['wpcd-wc-sell-servers/wpcd-wc-sell-servers.php']               = '9999.9999.9999';
		$add_ons['wpcd-wc-sell-sites/wpcd-wc-sell-sites.php']                   = '9999.9999.9999';
		$add_ons['wpcd-woocommerce/wpcd-woocommerce.php']                       = '3.6.0';

		// Initialize list of incompatible add_ons.
		$incompatible_add_ons = array();

		// Get the full list of activated plugins.
		$active_plugins = get_option( 'active_plugins' );

		// Output list of our activated plugins that need to be version checked.
		foreach ( $active_plugins as $active_plugin ) {

			// Is it one of ours?
			if ( true === array_key_exists( $active_plugin, $add_ons ) ) {
				// it's one of ours so get version data.
				$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $active_plugin );
				$version     = $plugin_data['Version'];

				// Compare version.
				if ( -1 === version_compare( $version, $add_ons[ $active_plugin ] ) ) {
					$incompatible_add_ons[] = $active_plugin;
				}
			}
		}

		// Write incompatible versions array to option.
		update_option( 'wpcd_incompatible_addons', $incompatible_add_ons );

		// Set return value and deactivate plugin if we have incompatible addons.
		if ( ! empty( $incompatible_add_ons ) ) {
			$return = false;

			// Write an error out to the error log so the admin can see why things aren't being activated.
			error_log( __( 'WPCD Cannot remain activated because the following add-ons are not compatible with this version.', 'wpcd' ) );
			error_log( print_r( $incompatible_add_ons, true ) );

			// Deactivate the plugin.
			if ( is_admin() ) {
				// Immediately deactivate self.
				$this->wpcd_plugin_force_deactivate();

				// Show Message and Die.
				$incompatible_addons = get_option( 'wpcd_incompatible_addons', $incompatible_add_ons );
				$message             = __( 'These addons are incompatible with WPCloudDeploy. WPCloudDeploy has been deactivated.', 'wpcd' );
				foreach ( $incompatible_addons as $incompatible_addon ) {
					$message .= '<br />' . $incompatible_addon;
				}
				wp_die( $message );

			}
		}

		return $return;
	}

	/**
	 * Deactivate self. Usually when an incompatible plugin is present.
	 */
	public function wpcd_plugin_force_deactivate() {

		deactivate_plugins( plugin_basename( __FILE__ ) );

	}

	/**
	 * If a plugin has been activated check to make sure it's not one that we can't handle.
	 *
	 * Action Hook: updated_option
	 *
	 * @param string $option_name Name of option being updated.
	 * @param mixed  $old_value Original option value.
	 * @param mixed  $value New option value.
	 */
	public function wpcd_updated_option( $option_name, $old_value, $value ) {

		if ( ! is_admin() ) {
			return;
		}

		if ( 'active_plugins' === $option_name ) {
			$this->are_all_addons_compatible();
		}

	}

	/**
	 * Check to see if all add-ons are compatible.
	 * Runs from the constructor or activation.
	 * We must make sure the check runs only once per version.
	 */
	public function check_all_addons_compatible() {
		// If this check has already been done just return true.
		$check_version = get_option( 'wpcd_addons_compatible_last_version_checked' );

		if ( version_compare( $check_version, WPCD_VERSION ) === 0 ) {
			return true;
		}

		// If we get here, we have to run the compatiblity check at least once.
		if ( $this->are_all_addons_compatible() ) {
			update_option( 'wpcd_addons_compatible_last_version_checked', WPCD_VERSION );
			return true;
		}

		return true;
	}


	/**
	 * Show admin notice when an incompatible plugin is present.
	 * Should run just before we deactivate self.
	 *
	 * Action Hook: admin_notices
	 *
	 * *** This function not used because we deactivate the plugin before the admin_notices hook can be called.
	 * *** Keeping it around in case we find a use for it later.
	 */
	public function wpcd_plugin_deactivate_admin_notice() {

		$incompatible_addons = get_option( 'wpcd_incompatible_addons', $incompatible_add_ons );
		if ( ! empty( $incompatible_addons ) ) {
			// Incompatible add-ons are active.
			$class    = 'notice notice-error wpcd-incompatible-addons';
			$message  = __( '<strong>These addons are incompatible with WPCD.</strong>', 'wpcd' );
			$message .= print_r( $incompatible_addons, true );
			printf( '<div data-dismissible="notice-incompatible-addons-notice" class="%2$s"><p>%3$s</p></div>', wp_create_nonce( 'wpcd-admin-incompatible-addons-notice' ), $class, $message );
		}

	}

}

/**
 * Get WPCD running.
 */
$wpcd_throwaway = new WPCD_Init();

/**
 * Statistics Collection
 */
if ( ! class_exists( 'Plugin_Usage_Tracker' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/wisdom_plugin/class-plugin-usage-tracker.php';
}
if ( ! function_exists( 'wpcd_start_plugin_tracking' ) ) {
	/**
	 * Start statistics tracking using the Wisdom Plugin.
	 */
	function wpcd_start_plugin_tracking() {
		$wisdom = new Plugin_Usage_Tracker(
			__FILE__,
			'https://statistics.wpclouddeploy.com',
			array( 'wisdom_opt_out', 'wisdom_wpcd_server_count', 'wisdom_wpcd_app_count' ),
			false,
			false,
			3
		);
		return $wisdom;
	}
	// Start Wisdom but only if the custom options have been calculated at least once.
	// The initial calculation only happens if the admin area has been accessed at least once after the plugin was activated.
	// (See the admin_init() function in the main plugin class above.)
	// After that the calculations occur on a cron hook.
	// (See the function set_wisdom_custom_options() in file includes/core/wp-cloud-deploy.php).
	if ( true === (bool) get_transient( 'wpcd_wisdom_custom_options_first_run_done' ) ) {
		wpcd_start_plugin_tracking();
	}
}
