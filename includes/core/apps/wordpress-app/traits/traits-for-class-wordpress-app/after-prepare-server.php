<?php
/**
 * Trait:
 * Contains functions that run things after a server is deployed.
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_after_prepare_server
 */
trait wpcd_wpapp_after_prepare_server {

	/**
	 * Add tasks to pending log for:
	 *  - Installing Callbacks
	 *  - Setup standard backups
	 *  - Setup critical files backups (Local server configuration backups)
	 *  - Run services status
	 *  - Delete protect servers
	 *
	 * Action hook: wpcd_command_{$this->get_app_name()}_{$base_command}_{$status} || wpcd_wordpress-app_prepare_server_completed
	 *
	 * @param int    $server_id      The post id of the server record.
	 * @param string $command_name   The full name of the command that triggered this action.
	 */
	public function wpcd_wpapp_core_prepare_server_completed( $server_id, $command_name ) {

		$server_post = get_post( $server_id );

		// Bail if not a post object.
		if ( ! $server_post || is_wp_error( $server_post ) ) {
			return;
		}

		// Bail if not a WordPress app.
		if ( 'wordpress-app' <> WPCD_WORDPRESS_APP()->get_server_type( $server_id ) ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_instance_details( $server_id );

		if ( 'wpcd_app_server' === get_post_type( $server_id ) ) {

			// Mark server as delete protected.
			if ( wpcd_get_option( 'wordpress_app_servers_add_delete_protection' ) ) {
				WPCD_POSTS_APP_SERVER()->wpcd_app_server_set_deletion_protection_flag( $server_id );
			}

			// Setup task to install backup scripts.
			if ( wpcd_get_option( 'wordpress_app_servers_activate_backups' ) ) {
				$instance['action_hook'] = 'wpcd_core_after_server_prepare_install_server_backups';
				WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'core-install-server-backups', $server_id, $instance, 'ready', $server_id, __( 'Waiting To Install Backup Scripts For New Server', 'wpcd' ) );
			}

			// Setup task to install backup of server configuration scripts.
			if ( wpcd_get_option( 'wordpress_app_servers_activate_config_backups' ) ) {
				$instance['action_hook'] = 'wpcd_core_after_server_prepare_install_server_configuration_backups';
				WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'core-install-server-config-backups', $server_id, $instance, 'ready', $server_id, __( 'Waiting To Install Backup Scripts For New Server', 'wpcd' ) );
			}

			// Setup task to refresh services.
			// Unlike the other tasks we set up above that trigger hooks in this file, we're hooking directly into the action at the top of the services.php server tab file.
			// That hook function will also automatically update the pending task record because it doesn't care about the return status.
			if ( wpcd_get_option( 'wordpress_app_servers_refresh_services' ) ) {
				$instance['action_hook'] = 'wpcd_wordpress-app_server_refresh_services';
				WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'core-install-refresh-services-status', $server_id, $instance, 'ready', $server_id, __( 'Waiting To Refresh Services Status For New Server', 'wpcd' ) );
			}

			// Setup task to run all linux updates.
			// Unlike the other tasks we set up above that trigger hooks in this file, we're hooking directly into the action at the top of the upgrade.php server tab file.
			// This is because the hook is being handled by the same task that handles the BULK actions for callbacks.
			// Notice that the task type does not start with 'core-install-'.
			if ( wpcd_get_option( 'wordpress_app_servers_run_all_linux_updates' ) ) {
				$instance['action_hook'] = 'pending_log_apply_all_linux_updates';
				WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'apply-all-linux-updates', $server_id, $instance, 'ready', $server_id, __( 'Waiting To Schedule All Linux Updates.', 'wpcd' ) );
			}

			// Setup task to create server call-backs.
			// Notice that the action hooks and task types are different from the standards we used above.
			// This is because the hook is being handled by the same task that handles the BULK actions for callbacks.
			// Notice that the task type does not start with 'core-install-'.
			if ( wpcd_get_option( 'wordpress_app_servers_activate_callbacks' ) ) {
				$instance['action_hook'] = 'wpcd_pending_log_install_a_callback';
				WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'install-server-callback', $server_id, $instance, 'ready', $server_id, __( 'Waiting To Install Callbacks For New Server', 'wpcd' ) );
			}
		}

	}

	/**
	 * Install server backups.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_core_after_server_prepare_install_server_backups
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing.
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function wpcd_core_install_server_backups( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Install callbacks on the designated server */
		do_action( 'wpcd_wordpress-manage_server_backup_action_all_sites', $server_id );

	}

	/**
	 * Handle backup install successful
	 *
	 * Action Hook: wpcd_server_{$this->get_app_name()}_server_auto_backup_action_all_sites_successful || wpcd_server_wordpress-app_server_auto_backup_action_all_sites_successful
	 *
	 * @param int    $server_id The server post id.
	 * @param string $action the action being processed - should be 'schedule_full'.
	 * @param array  $success_msg_array - An array of data related to the backup installation process.
	 *
	 * @return void
	 */
	public function wpcd_core_install_server_handle_backup_script_install_success( $server_id, $action, $success_msg_array ) {
		$this->handle_backup_script_install_success_or_failure( $server_id, $action, $success_msg_array, 'success', 'core-install-server-backups' );
	}

	/**
	 * Handle backup install failed
	 *
	 * Action Hook: wpcd_server_{$this->get_app_name()}_server_auto_backup_action_all_sites_failed || wpcd_server_wordpress-app_server_auto_backup_action_all_sites_failed
	 *
	 * @param int    $server_id The server post id.
	 * @param string $action the action being processed - should be 'schedule_full'.
	 * @param array  $success_msg_array - An array of data related to the backup installation process.
	 *
	 * @return void
	 */
	public function wpcd_core_install_server_handle_backup_script_install_failed( $server_id, $action, $success_msg_array ) {
		$this->handle_backup_script_install_success_or_failure( $server_id, $action, $success_msg_array, 'failed', 'core-install-server-backups' );
	}

	/**
	 * Handle backup install successful or failed.
	 *
	 * Handles the results of both the regular backups as well as the local configuration backups.
	 *
	 * @param int    $server_id The server post id.
	 * @param string $action the action being processed - should be 'schedule_full'.
	 * @param array  $success_msg_array - An array of data related to the backup installation process.
	 * @param string $success - should be 'success' or 'failed'.
	 * @param string $task_type - should be 'core-install-server-backups' or 'core-install-server-config-backups'
	 *
	 * @return void
	 */
	public function handle_backup_script_install_success_or_failure( $server_id, $action, $success_msg_array, $success, $task_type ) {

		$server_post = get_post( $server_id );

		// Bail if not a post object
		if ( ! $server_post || is_wp_error( $server_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' <> WPCD_WORDPRESS_APP()->get_server_type( $server_id ) ) {
			return;
		}

		// This only matters if we were installing the backup scripts.  If not, then bail.
		if ( 'core-install-server-backups' === $task_type ) {
			if ( 'schedule_full' <> $action ) {
				return;
			}
		}
		if ( 'core-install-server-config-backups' === $task_type ) {
			if ( 'conf_backup_enable' <> $action && 'conf_backup_disable' <> $action ) {
				return;
			}
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_instance_details( $server_id );

		if ( 'wpcd_app_server' === get_post_type( $server_id ) ) {

				// Now check the pending tasks table for a record where the key=$server_id and type='core-install-server-backups' and state='in-process'
				// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
				$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', $task_type );

			if ( $posts ) {

				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				// And mark it as successful or failed.
				if ( 'failed' === $success ) {
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );
				} else {
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );
				}
			}
		}

	}

	/**
	 * Install server configuration backups.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_core_after_server_prepare_install_server_configuration_backups
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing.
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function wpcd_core_install_server_configuration_backups( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Install callbacks on the designated server */
		do_action( 'wpcd_wordpress-app_toggle_server_configuration_backups', $server_id );

	}

	/**
	 * Handle backup of server configuration install successful
	 *
	 * Action Hook: wpcd_server_{$this->get_app_name()}_server_auto_backup_action_all_sites_successful || wpcd_server_wordpress-app_server_auto_backup_action_all_sites_successful
	 *
	 * @param int    $server_id The server post id.
	 * @param string $action the action being processed - should be 'schedule_full'.
	 * @param array  $success_msg_array - An array of data related to the backup installation process.
	 *
	 * @return void
	 */
	public function wpcd_core_install_server_handle_config_backup_install_success( $server_id, $action, $success_msg_array ) {
		$this->handle_backup_script_install_success_or_failure( $server_id, $action, $success_msg_array, 'success', 'core-install-server-config-backups' );
	}

	/**
	 * Handle backup of server configuration install failed
	 *
	 * Action Hook: wpcd_server_{$this->get_app_name()}_server_auto_backup_action_all_sites_failed || wpcd_server_wordpress-app_server_auto_backup_action_all_sites_failed
	 *
	 * @param int    $server_id The server post id.
	 * @param string $action the action being processed - should be 'schedule_full'.
	 * @param array  $success_msg_array - An array of data related to the backup installation process.
	 *
	 * @return void
	 */
	public function wpcd_core_install_server_handle_config_backup_install_failed( $server_id, $action, $success_msg_array ) {
		$this->handle_backup_script_install_success_or_failure( $server_id, $action, $success_msg_array, 'failed', 'core-install-server-config-backups' );
	}

}
