<?php
/**
 * This class handles WP CLI command for linux cron.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BETTER WPCD CRONS
 *
 * @package wpcd
 * @version 1.0.0 / wpcd
 * @since 4.2.0
 */
class BETTER_WPCD_CRONS {

	/**
	 * Disable WP cron when defined( 'DISABLE_WPCD_CRON','true' ) for WPCD
	 */
	private function wpcd_disable_wp_cron() {
		// Perform action only when defined( 'DISABLE_WPCD_CRON','true' ).

		if ( defined( 'DISABLE_WPCD_CRON' ) && DISABLE_WPCD_CRON == true ) {
			// Clear old crons.
			wp_unschedule_hook( 'wpcd_wordpress_deferred_actions_for_apps' );
			wp_unschedule_hook( 'wpcd_scan_notifications_actions' );
			wp_unschedule_hook( 'wpcd_vpn_deferred_actions' );
			wp_unschedule_hook( 'wpcd_wordpress_file_watcher' );
			wp_unschedule_hook( 'wpcd_email_alert_for_long_pending_tasks' );
			wp_unschedule_hook( 'wpcd_wisdom_custom_options' );
			wp_unschedule_hook( 'put_do_weekly_action' );
			wp_unschedule_hook( 'wpcd_basic_server_deferred_actions' );
			wp_unschedule_hook( 'wpcd_vpn_file_watcher' );
			wp_unschedule_hook( 'wpcd_wordpress_deferred_actions_for_server' );
			wp_unschedule_hook( 'wpcd_clean_up_pending_logs' );
		}
	}

	/**
	 * Set up WP cron actions for WPCD.
	 */
	public function wpcd_wp_cron_actions() {

		// Perform action only when defined( 'DISABLE_WPCD_CRON','true' ).
		if ( defined( 'DISABLE_WPCD_CRON' ) && DISABLE_WPCD_CRON == false ) {

			WP_CLI::warning( __( 'DISABLE_WPCD_CRON should be (true) in wp-config.php', 'wpcd' ) );
			exit();
		}

		/**
		 * Unschedule cron when defined( 'DISABLE_WPCD_CRON','true' ).
		 */
		$this->wpcd_disable_wp_cron();

		/**
		 * Perform all actions that need a polling mechanism.
		 */
		do_action( 'wpcd_wordpress_deferred_actions_for_apps' );

		/**
		 * Scan for new notifications and send it to the user
		*/
		do_action( 'wpcd_scan_notifications_actions' );

		/**
		 * Perform all deferred actions that need multiple steps to perform.
		 *
		 * @TODO: Update this header to list examples and parameters and expected inputs.
		*/
		do_action( 'wpcd_vpn_deferred_actions' );

		/**
		 * Deletes scripts in our scripts temporary folder if they are more than 10 minutes old.
		 * Generally, the scripts temp folder is used to place files uploaded to the server
		 * for execution as its being instantiated. 10 minutes is more than enough time for that to happen.
		 */
		do_action( 'wpcd_vpn_deferred_actions' );

		/**
		 * Deletes scripts in our scripts temporary folder if they are more than 10 minutes old.
		 * Generally, the scripts temp folder is used to place files uploaded to the server
		 * for execution as its being instantiated. 10 minutes is more than enough time for that to happen.
		 */
		do_action( 'wpcd_wordpress_file_watcher' );

		/**
		 * Send email to wpcd site admin if any pending task has been started but more than 15 mins has gone by without it completing.
		 */
		do_action( 'wpcd_email_alert_for_long_pending_tasks' );

		/**
		 * Set some Wisdom options.
		 *
		 * The option names are set at the bottom of the wpcd.php file.
		 * When Wisdom fires its data collection functions it scans for those option names.
		 * We'll set the value of those options here.
		 */
		do_action( 'wpcd_wisdom_custom_options' );

		/**
		 * This is our function to get everything going
		 * Check that user has opted in
		 * Collect data
		 * Then send it back
		 */
		do_action( 'put_do_weekly_action' );

		/**
		 * Perform all deferred actions that need multiple steps to perform.
		 *
		 * @TODO: Update this header to list examples and parameters and expected inputs.
		 *
		 * Also, OOPS, this might compete with other APPS?  We might need to use different keys here...
		 */
		do_action( 'wpcd_basic_server_deferred_actions' );

		/**
		 * Deletes scripts in our scripts temporary folder if they are more than 10 minutes old.
		 * Generally, the scripts temp folder is used to place files uploaded to the server
		 * for execution as its being instantiated. 10 minutes is more than enough time for that to happen.
		 */
		do_action( 'wpcd_vpn_file_watcher' );

		/**
		 * Perform all deferred or background actions for a server.
		 */
		do_action( 'wpcd_wordpress_deferred_actions_for_server' );

		/**
		 * Cron function code to clean up the pending logs.
		 * Anything that has been running for too long
		 * (around 2 hours) will be marked as failed.
		 */
		do_action( 'wpcd_clean_up_pending_logs' );
	}

	/**
	 * Disable powertool cron when defined( 'DISABLE_WPCD_CRON','true' ) for WPCD
	 */
	private function wpcd_disable_powertool_cron() {
		// Perform action only when defined( 'DISABLE_WPCD_CRON','true' ).

		if ( defined( 'DISABLE_WPCD_CRON' ) && DISABLE_WPCD_CRON == true ) {
			// Clear old crons.
			wp_unschedule_hook( 'wpcd_scan_scheduled_apps_automatic_image' );
			wp_unschedule_hook( 'wpcd_scan_scheduled_apps' );
			wp_unschedule_hook( 'wpcd_scan_scheduled_servers' );
		}
	}

	/**
	 * Set up WP cron actions for Powertool.
	 */
	public function wpcd_powertool_cron_actions() {

		// Perform action only when defined( 'DISABLE_WPCD_CRON','true' ).
		if ( defined( 'DISABLE_WPCD_CRON' ) && DISABLE_WPCD_CRON == false ) {

			WP_CLI::warning( __( 'DISABLE_WPCD_CRON should be (true) in wp-config.php', 'wpcd' ) );
			exit();
		}

		/**
		 * Unschedule cron when defined( 'DISABLE_WPCD_CRON','true' ).
		 */
		$this->wpcd_disable_powertool_cron();

		/**
		 * Cron function code to scan all apps past due for having their images captured.
		 *
		 * Action Hook: wpcd_scan_scheduled_apps_automatic_image
		 */
		do_action( 'wpcd_scan_scheduled_apps_automatic_image' );

		/**
		 * Cron function code for scan for all scheduled apps to create snapshots.
		 *
		 * Action Hook: wpcd_scan_scheduled_apps
		 */
		do_action( 'wpcd_scan_scheduled_apps' );

		/**
		 * Cron function code for scan for all scheduled servers to create snapshots.
		 *
		 * Action Hook: wpcd_scan_scheduled_servers
		 */
		do_action( 'wpcd_scan_scheduled_servers' );
	}

}


add_action( 'cli_init', 'wpcd_cli_register_commands' );
/**
 * Registers our command when cli get's initialized.
 */
function wpcd_cli_register_commands() {
	WP_CLI::add_command( 'wpcd', 'BETTER_WPCD_CRONS' );
}
