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
		if ( defined( 'DISABLE_WPCD_CRON' ) && true === DISABLE_WPCD_CRON ) {
			// Clear old crons.
			wp_unschedule_hook( 'wpcd_wordpress_deferred_actions_for_server' );
			wp_unschedule_hook( 'wpcd_wordpress_deferred_actions_for_apps' );
			wp_unschedule_hook( 'wpcd_wordpress_file_watcher' );
			wp_unschedule_hook( 'wpcd_scan_notifications_actions' );
			wp_unschedule_hook( 'wpcd_clean_up_pending_logs' );
			wp_unschedule_hook( 'wpcd_email_alert_for_long_pending_tasks' );

			if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
				wp_unschedule_hook( 'wpcd_vpn_deferred_actions' );
				wp_unschedule_hook( 'wpcd_vpn_file_watcher' );
			}

			if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
				wp_unschedule_hook( 'wpcd_basic_server_deferred_actions' );
			}

			if ( defined( 'WPCD_LOAD_STABLEDIFF_APP' ) && ( true === WPCD_LOAD_STABLEDIFF_APP ) ) {
				wp_unschedule_hook( 'wpcd_stablediff_deferred_actions' );
				wp_unschedule_hook( 'wpcd_stablediff_file_watcher' );
			}
		}
	}

	/**
	 * Set up WP cron actions for WPCD.
	 */
	public function wpcd_wp_cron_actions() {
		// Perform action only when defined( 'DISABLE_WPCD_CRON','true' ).
		if ( defined( 'DISABLE_WPCD_CRON' ) && false === DISABLE_WPCD_CRON ) {

			WP_CLI::warning( __( 'DISABLE_WPCD_CRON should be (true) in wp-config.php', 'wpcd' ) );
			exit();
		}

		/**
		 * Set constant to let others know we're doing our cron stuff.
		 */
		define( 'WPCD_DOING_CORE_CRON', true );

		/**
		 * Add notification we've started.
		 */
		// phpcs:ignore
		do_action( 'wpcd_log_error', __( 'Starting better core crons', 'wpcd' ), 'better_cron', __FILE__, __LINE__, '', false );

		/**
		 * Unschedule cron when defined( 'DISABLE_WPCD_CRON','true' ).
		 * This should not be necessary but it's a prophylactic.
		 */
		$this->wpcd_disable_wp_cron();

		/**
		 * Allow others to hook in before we fire all our cron processes.
		 */
		do_action( 'wpcd_before_better_crons' );

		/**
		 * Sequentially fire all our CRON action hooks.
		 */
		do_action( 'wpcd_wordpress_deferred_actions_for_server' );
		do_action( 'wpcd_wordpress_deferred_actions_for_apps' );
		do_action( 'wpcd_wordpress_file_watcher' );
		do_action( 'wpcd_scan_notifications_actions' );
		do_action( 'wpcd_email_alert_for_long_pending_tasks' );
		do_action( 'wpcd_clean_up_pending_logs' );

		if ( defined( 'WPCD_LOAD_VPN_APP' ) && ( true === WPCD_LOAD_VPN_APP ) ) {
			do_action( 'wpcd_vpn_deferred_actions' );
			do_action( 'wpcd_vpn_file_watcher' );
		}

		if ( defined( 'WPCD_LOAD_BASIC_SERVER_APP' ) && ( true === WPCD_LOAD_BASIC_SERVER_APP ) ) {
			do_action( 'wpcd_basic_server_deferred_actions' );
		}

		if ( defined( 'WPCD_LOAD_STABLEDIFF_APP' ) && ( true === WPCD_LOAD_STABLEDIFF_APP ) ) {
			do_action( 'wpcd_stablediff_deferred_actions' );
			do_action( 'wpcd_stablediff_file_watcher' );
		}

		/**
		 * Allow others to hook in after we fire all our core cron processes.
		 */
		do_action( 'wpcd_after_better_crons' );

		/**
		 * Add notification we've finished.
		 */
		// phpcs:ignore
		do_action( 'wpcd_log_error', __( 'Better core crons complete', 'wpcd' ), 'better_cron', __FILE__, __LINE__, '', false );

	}

}


add_action( 'cli_init', 'wpcd_cli_register_commands' );
/**
 * Registers our command when cli get's initialized.
 */
function wpcd_cli_register_commands() {
	WP_CLI::add_command( 'wpcd', 'BETTER_WPCD_CRONS' );
}
