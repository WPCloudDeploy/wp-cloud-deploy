<?php
/**
 * This class handles custom fields.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup certain license functions
 *
 * @package wpcd
 * @version 1.0.0 / wpcd
 * @since 4.2.0
 */
class BETTER_WPCD_CRONS {

	/**
	 * Set up WP cron actions.
	 */
	public function wpcd_wp_cron_actions() {

		// Perform action only when defined( 'DISABLE_WP_CRON','true' ).
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON == false ) {

			WP_CLI::warning( __( 'DISABLE_WP_CRON should be (true) in wp-config.php', 'wpcd' ) );
			exit();
		}

		/**
		 * Perform all actions that need a polling mechanism.
		 *
		 * It searches for all 'wpcd_app' CPTs that have "wpcd_app_{$this->get_app_name()}_action_status" as 'in-progress'
		 * and calls the action wpcd_app_{$this->get_app_name()}_action on each post.
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
		 *
		 * @return void
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
		 *
		 * @since 1.0.0
		 * @param $force    Force tracking if it's not time
		 */
		do_action( 'put_do_weekly_action' );

		/**
		 * Cron function code to send compose email on scheduled time.
		 *
		 * @param int $post_id server or app id.
		 */
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
						// Set new crons for compose email.
						do_action( 'wpcd_check_for_scheduled_compose_email_send', $schedule_args );
					}
				}
			}
		}

		/**
		 * Gets the common code for export data
		 *
		 * @param string $wpcd_sync_target_site wpcd_sync_target_site.
		 * @param string $wpcd_sync_enc_key wpcd_sync_enc_key.
		 * @param int    $wpcd_sync_user_id wpcd_sync_user_id.
		 * @param string $wpcd_sync_password wpcd_sync_password.
		 * @param array  $wpcd_export_all_settings wpcd_export_all_settings.
		 * @param int    $ajax ajax.
		 */
		// do_action( 'wpcd_export_data_actions' );

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
		 *
		 * It searches for all 'wpcd_app_server' CPTs that have "wpcd_server_{$this->get_app_name()}_action_status" as 'in-progress'
		 * and calls the action wpcd_server_{$this->get_app_name()}_action on each post.
		 *
		 * Each server might have a task running on it that needs to be called.  This is specified in the
		 * meta wpcd_server_{$this->get_app_name()}_action.
		 * After processing each server's action that way, it will then call the PENDING TASKS function
		 * to see if any new processes can be started on servers that don't already have tasks running on them.
		 */
		do_action( 'wpcd_wordpress_deferred_actions_for_server' );

		/**
		 * Cron action to restart servers after resize
		 *
		 * @return void
		 */
		// $this->doAutoStartServer();
		// _auto_start_after_resize_cron

		/**
		 * Cron function code to clean up the pending logs.
		 * Anything that has been running for too long
		 * (around 2 hours) will be marked as failed.
		 */
		do_action( 'wpcd_clean_up_pending_logs' );
	}

}


add_action( 'cli_init', 'wpcd_cli_register_commands' );
/**
 * Registers our command when cli get's initialized.
 */
function wpcd_cli_register_commands() {
	WP_CLI::add_command( 'wpcd', 'BETTER_WPCD_CRONS' );
}
