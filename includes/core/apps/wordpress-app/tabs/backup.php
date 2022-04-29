<?php
/**
 * Backup tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_BACKUP
 */
class WPCD_WORDPRESS_TABS_BACKUP extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
		/* add_filter( 'wpcd_is_ssh_successful', array( $this, 'was_ssh_successful' ), 10, 5 ); */

		// Allow the manual backup to be triggered via an action hook. Hook Name: wpcd_wordpress-app_manual_backup_for_site.
		add_action( "wpcd_{$this->get_app_name()}_manual_backup_for_site", array( $this, 'backup_actions' ), 10, 2 );

		/* Pending Logs Background Task: Trigger manual backup */
		add_action( 'wpcd_pending_log_manual_site_backup', array( $this, 'pending_log_manual_site_backup' ), 10, 3 );

		// Command completed hook.
		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the site cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed( $id, $name ) {
		if ( get_post_type( $id ) !== 'wpcd_app' ) {
			return;
		}

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905.
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run.
		// [1] => cf1110.wpvix.com.
		// [2] => 911.
		$command_array = explode( '---', $name );

		// if the command is to manually backup a site then we should check to see if the command was successful.
		// If so, update any pending log records (if it was indeed triggered by a pending log entry).
		// Regardless, post a notice in the notification tables.
		if ( 'backup-run-manual' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = (bool) $this->is_ssh_successful( $logs, 'backup_restore.txt' );

			// Get the pending log task id from the temporary post meta we added to the site.
			$task_id = get_post_meta( $id, 'wpapp_pending_log_manual_backup_task_id', true );

			if ( true === $success ) {

				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as complete.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );

				}

				// Add success notification.
				do_action( 'wpcd_log_notification', $id, 'alert', __( 'The manual backup has been completed.', 'wpcd' ), 'backup', null );

			} else {

				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as failed.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );

				}

				// Add failure notification.
				do_action( 'wpcd_log_notification', $id, 'error', __( 'The manual backup has failed.', 'wpcd' ), 'backup', null );

			}
		}

		// Delete action-specific the temporary meta if it exists.
		delete_post_meta( $id, 'wpapp_pending_log_manual_backup_task_id' );

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'backup';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_backup_tab';
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 * @param int   $id   The post ID of the server.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs, $id ) {
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Backup & Restore', 'wpcd' ),
				'icon'  => 'fad fa-server',
			);
		}
		return $tabs;
	}

	/**
	 * Checks whether or not the user can view the current tab.
	 *
	 * @param int $id The post ID of the site.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		return ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Called when an action needs to be performed on the tab.
	 *
	 * @param mixed  $result The default value of the result.
	 * @param string $action The action to be performed.
	 * @param int    $id The post ID of the app.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->wpcd_user_can_view_wp_app( $id ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %1$s in file %2$s', 'wpcd' ), $action, __FILE__ ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'backup-run-manual', 'backup-run-schedule', 'restore-from-backup', 'restore-from-backup-nginx-only', 'restore-from-backup-wpconfig-only', 'delete-all-local-site-backups', 'prune-local-site-backups' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'backup-run-manual':
					$result = $this->backup_actions( $action, $id );
					break;
				case 'backup-run-schedule':
					$result = $this->auto_backup_action( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
				case 'refresh-backup-list':
					$result = $this->refresh_backup_list( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
				case 'restore-from-backup':
				case 'restore-from-backup-nginx-only':
				case 'restore-from-backup-wpconfig-only':
					$result = $this->backup_actions( $action, $id );
					break;
				case 'delete-all-local-site-backups':
					$result = $this->backup_actions( $action, $id );
					break;
				case 'prune-local-site-backups':
					$result = $this->backup_actions( $action, $id );
					break;
			}
			// Most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
			// But we don't want to force a refresh after the manual backup or restore.  Otherwise that will clear the screen.
			if ( ! in_array( $action, array( 'backup-run-manual', 'restore-from-backup' ), true ) && ! is_wp_error( $result ) ) {
				$result = array( 'refresh' => 'yes' );
			}
		}
		return $result;
	}

	/**
	 * Performs the backup/restore action.
	 *
	 * @param string $action action.
	 * @param int    $id id.
	 */
	public function backup_actions( $action, $id ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		$run_cmd = '';

		// Get an array of credentials and buckets.
		$creds  = $this->get_s3_credentials_for_backup( $id );  // Function get_s3_credentials_for_backup is located in a trait file.
		$key    = $creds['aws_access_key_id'];
		$secret = $creds['aws_secret_access_key'];
		$bucket = $creds['aws_bucket_name'];

		// Some cred is empty? If so, bail!
		if ( empty( $key ) || empty( $secret ) || empty( $bucket ) ) {
			return new \WP_Error( __( 'Some credentials are empty', 'wpcd' ) );
		}

		// If a bucket was passed in for this action, add it to the creds array.
		if ( ! empty( $args['aws_bucket_manual_backup'] ) ) {
			$creds['aws_bucket_name']       = escapeshellarg( sanitize_text_field( $args['aws_bucket_manual_backup'] ) );
			$creds['aws_bucket_name_noesc'] = sanitize_text_field( $args['aws_bucket_manual_backup'] );
		}

		// @TODO: If some creds are empty we should bail out here!

		// Save bucket name into a variable without escapeshellarg applied because we'll be storing this in the db.
		$bucket = $creds['aws_bucket_name_noesc'];

		// Get the domain we're working on.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		switch ( $action ) {
			case 'backup-run-manual':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'backup_restore.txt',
					array_merge(
						$args,
						$creds,
						array(
							'command' => $command,
							'action'  => 'backup',
							'domain'  => $domain,
						)
					)
				);
				break;

			case 'restore-from-backup':
			case 'restore-from-backup-nginx-only':
			case 'restore-from-backup-wpconfig-only':
				// Make sure we have a backup to restore.
				if ( empty( $args['backup_item'] ) ) {
					return new \WP_Error( __( 'No backup was selected.', 'wpcd' ) );
				}

				// Remove everything before the first occurence of the domain name in the backup that is being restored.
				// The original string is formatted as '6  cf13.wpvix.com/cf13.wpvix.com_2020-04-03-07h42m08s'.
				// The new string just needs to remove the number and spaces before the first occurence of the domain name so you end up with just 'cf13.wpvix.com/cf13.wpvix.com_2020-04-03-07h42m08s'.
				$args['backup_item'] = strstr( $args['backup_item'], $domain );

				// Set some elements in the args array to match the names that the restore bash script expects.
				$args['overwrite'] = 'yes';
				$args['backup']    = escapeshellarg( trim( $args['backup_item'] ) );

				// What kind of restore are we doing?
				switch ( $action ) {
					case 'restore-from-backup':
						$restore_action = 'restore'; // Full restore.
						break;
					case 'restore-from-backup-nginx-only':
						$restore_action = 'restore_nginx';
						break;
					case 'restore-from-backup-wpconfig-only':
						$restore_action = 'restore_wpconfig';
						break;
				}

				// construct the run command.
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'backup_restore.txt',
					array_merge(
						$args,
						$creds,
						array(
							'command' => $command,
							'action'  => $restore_action,
							'domain'  => $domain,
						)
					)
				);

				break;

			case 'delete-all-local-site-backups':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'backup_restore_delete_and_prune.txt',
					array_merge(
						$args,
						$creds,
						array(
							'command'      => $command,
							'action'       => 'delete_site_backups',
							'domain'       => $domain,
							'confirmation' => 'yes',
						)
					)
				);
				break;

			case 'prune-local-site-backups':
				$args['manual_prune_backup_retention_days'] = escapeshellarg( trim( $args['manual_prune_backup_retention_days'] ) );
				$run_cmd                                    = $this->turn_script_into_command(
					$instance,
					'backup_restore_delete_and_prune.txt',
					array_merge(
						$args,
						$creds,
						array(
							'command'      => $command,
							'action'       => 'prune_site_backups',
							'domain'       => $domain,
							'days'         => $args['manual_prune_backup_retention_days'],
							'confirmation' => 'yes',
						)
					)
				);
				break;

		}

		/**
		 * Run the constructed commmand
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;
	}


	/**
	 * Gets the fields to be shown.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields( array $fields, $id ) {
		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'backup' ) );
		}

		// run backup manually.
		$fields[] = array(
			'name' => __( 'Take a Manual Backup - This Site Only', 'wpcd' ),
			'desc' => __( 'Start a backup for this site right now!', 'wpcd' ),
			'tab'  => 'backup',
			'type' => 'heading',
		);

		$hide_field = (bool) wpcd_get_early_option( 'wordpress_app_site_backup_hide_aws_bucket_name' ) && ( ! wpcd_is_admin() );
		$fields[]   = array(
			'name'       => $hide_field ? '' : __( 'AWS Bucket Name', 'wpcd' ),
			'id'         => 'wpcd_app_aws_bucket_manual_backup',
			'desc'       => $hide_field ? '' : __( 'Put the backup in this bucket. Leave this blank if you would like the backup to be placed in the default bucket.', 'wpcd' ),
			'tab'        => 'backup',
			'type'       => $hide_field ? 'hidden' : 'text',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'aws_bucket_manual_backup',
			),
			'std'        => '',
			'size'       => 90,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_manual_backup',
			'tab'        => 'backup',
			'type'       => 'button',
			'std'        => __( 'Run Manual Backup', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'backup-run-manual',
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_aws_bucket_manual_backup' ) ),                // the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to start a backup now?', 'wpcd' ),              // show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to start backup...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the backup has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the backup is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// Auto backups.
		$auto_backup_status         = get_post_meta( $id, 'wpapp_auto_backups_status', true );
		$auto_backup_bucket         = get_post_meta( $id, 'wpapp_auto_backup_bucket', true );
		$auto_backup_retention_days = get_post_meta( $id, 'wpapp_auto_backup_retention_days', true );
		$auto_backup_delete_remotes = get_post_meta( $id, 'wpapp_auto_backup_delete_remotes', true );

		// Set the confirmation prompt text.
		if ( 'on' === $auto_backup_status ) {
			$auto_backups_confirmation_prompt = __( 'Are you sure you would like to disable daily automatic backups for this site?', 'wpcd' );
		} else {
			$auto_backups_confirmation_prompt = __( 'Are you sure you would like to enable daily automatic backups for this site?', 'wpcd' );
		}

		if ( 'on' === $auto_backup_status ) {		
			// Backups have been enabled.  Show message about disabling it first before making changes.
			$fields[] = array(
				'name' => __( 'Automatic Backups - This Site Only', 'wpcd' ),
				'desc' => __( 'Backups are enabled for this site. If you would like to make changes, please disable it first using the switch below.', 'wpcd' ),
				'tab'  => 'backup',
				'type' => 'heading',
			);			
		} else {
			$fields[] = array(
				'name' => __( 'Automatic Backups - This Site Only', 'wpcd' ),
				'desc' => __( 'Enable automatic backups to run once per day for this site.  You should set up your S3 credentials in SETTINGS or on the server page and create a bucket for these backups before turning this option on!', 'wpcd' ),
				'tab'  => 'backup',
				'type' => 'heading',
			);
		}

		$hide_field = (bool) wpcd_get_early_option( 'wordpress_app_site_backup_hide_aws_bucket_name' ) && ( ! wpcd_is_admin() );
		if ( 'on' !== $auto_backup_status ) {
			// Backups are not currently enabled so we can show all fields.		
			$fields[]   = array(
				'id'         => 'wpcd_app_action_auto_backup_bucket_name',
				'desc'       => $hide_field ? '' : __( 'If this is left blank then the global bucket name from the SETTINGS screen will be used.', 'wpcd' ),
				'tab'        => 'backup',
				'type'       => $hide_field ? 'hidden' : 'text',
				'name'       => $hide_field ? '' : __( 'AWS Bucket Name', 'wpcd' ),
				'std'        => $auto_backup_bucket,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'auto_backup_bucket_name',
				),
				'size'       => 90,
				'save_field' => false,
			);

			$hide_field = (bool) wpcd_get_early_option( 'wordpress_app_site_backup_hide_retention_days' ) && ( ! wpcd_is_admin() );
			$fields[]   = array(
				'id'         => 'wpcd_app_action_auto_backup_retention_days',
				'desc'       => $hide_field ? '' : __( 'If left blank or zero, the backups will never be deleted. If set to -1, we will NEVER keep backups on disk (NOT RECOMMENDED).', 'wpcd' ),
				'tab'        => 'backup',
				'type'       => $hide_field ? 'hidden' : 'number',
				'min'        => -1,
				'name'       => $hide_field ? '' : __( 'Retention Days', 'wpcd' ),
				'std'        => $auto_backup_retention_days,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'auto_backup_retention_days',
				),
				'size'       => 90,
				'save_field' => false,
			);

			$hide_field = (bool) wpcd_get_early_option( 'wordpress_app_site_backup_hide_del_remote_backups' ) && ( ! wpcd_is_admin() );
			$fields[]   = array(
				'id'         => 'wpcd_app_action_auto_backup_delete_remotes',
				'name'       => $hide_field ? '' : __( 'Delete Remote Backups', 'wpcd' ),
				'tab'        => 'backup',
				'type'       => $hide_field ? 'hidden' : 'select',
				'options'    => array(
					'off' => __( 'Disabled', 'wpcd' ),
					'on'  => __( 'Enabled', 'wpcd' ),
				),
				'std'        => $auto_backup_delete_remotes,
				'desc'       => $hide_field ? '' : __( 'Delete remote backups when deleting local backups that exceed the retention days. We recommend that you keep this disabled and set a low number for the retention days above.', 'wpcd' ),
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'auto_backup_delete_remotes',
				),
				'save_field' => false,
			);
		}
		$fields[] = array(
			'id'         => 'wpcd_app_action_auto_backup',
			'name'       => 'on' === $auto_backup_status ? '' : __( 'Schedule It', 'wpcd' ),
			'tab'        => 'backup',
			'type'       => 'switch',
			'on_label'   => __( 'Enabled', 'wpcd' ),
			'off_label'  => __( 'Disabled', 'wpcd' ),
			'std'        => $auto_backup_status === 'on',
			'desc'       => 'on' === $auto_backup_status ? '' : __( 'Enable daily automatic backups', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'backup-run-schedule',
				// fields that contribute data for this action.
				'data-wpcd-fields'              => 'on' === $auto_backup_status ? '' : json_encode( array( '#wpcd_app_action_auto_backup_bucket_name', '#wpcd_app_action_auto_backup_retention_days', '#wpcd_app_action_auto_backup_delete_remotes' ) ),                // the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => $auto_backups_confirmation_prompt,
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/* Start restore section */
		$fields[] = array(
			'name' => __( 'Restores', 'wpcd' ),
			'desc' => __( 'Use this section to restore data from your backups. Use with care - restores are a destructive operation.  You should make a backup before performing a restore.  <br />We strongly recommend that you make a snapshot of your server as well!', 'wpcd' ),
			'tab'  => 'backup',
			'type' => 'heading',
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_refresh_backup_list',
			'name'       => '',
			'tab'        => 'backup',
			'type'       => 'button',
			'std'        => __( 'Refresh Backup List', 'wpcd' ),
			'desc'       => __( 'Get a list of all the backups on the server - they will be shown in the drop-down below', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'refresh-backup-list',
				// the id.
				'data-wpcd-id'     => $id,
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// Get list of backups from meta and transform into something that the metabox.io select fields can process; plus get other related metadata from the app records.
		$backup_list_array     = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_backups_list', true ) );
		$backup_list_date      = get_post_meta( $id, 'wpapp_backups_list_date', true );
		$new_backup_list_array = null;
		if ( ! empty( $backup_list_array ) ) {
			foreach ( $backup_list_array as $value ) {
				$new_backup_list_array[ $value ] = $value;
			}
		}

		// How many items in the backup list?
		if ( is_array( $new_backup_list_array ) ) {
			$backup_list_count = (string) count( $new_backup_list_array );
		} else {
			$backup_list_count = 0;
		}

		$fields[] = array(
			'id'         => 'wpcd_app_action_backup_list',
			'name'       => __( 'Backup List', 'wpcd' ),
			'tab'        => 'backup',
			'type'       => 'select',
			'std'        => __( 'Backup List', 'wpcd' ),
			'desc'       => sprintf( __( 'The list of backups from the last REFRESH BACKUP LIST action on %s', 'wpcd' ), $backup_list_date ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the id.
				'data-wpcd-id'   => $id,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'backup_item',
			),
			'options'    => $new_backup_list_array,
			'save_field' => false,
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_restore_backup',
			'name'       => '',
			'tab'        => 'backup',
			'type'       => 'button',
			'std'        => __( 'Restore Selected Backup', 'wpcd' ),
			'desc'       => __( 'Restore backup, overwriting all data!!!', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'restore-from-backup',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_action_backup_list' ) ),              // make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you really really SURE you want to restore this backup, overwriting all data on the existing site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to restore your data.  We hope you took a backup before starting this restore process!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the restore has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the restore is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 4,
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_restore_backup_nginx_only',
			'name'       => '',
			'tab'        => 'backup',
			'type'       => 'button',
			'std'        => __( 'Restore NGINX Configuration File', 'wpcd' ),
			'desc'       => __( 'Restore only the site NGINX configuration file from this backup.', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'restore-from-backup-nginx-only',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_action_backup_list' ) ),              // make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you really really SURE you want to restore this backup, overwriting your NGINX web server configuration file on the existing site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to restore your data.  We hope you took a backup before starting this restore process!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the restore has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the restore is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 4,
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_restore_backup_wpconfig_only',
			'name'       => '',
			'tab'        => 'backup',
			'type'       => 'button',
			'std'        => __( 'Restore WPConfig.php File', 'wpcd' ),
			'desc'       => __( 'Restore only the site wpconfig.php configuration file from this backup.', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'restore-from-backup-wpconfig-only',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_action_backup_list' ) ),              // make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you really really SURE you want to restore this backup, overwriting your wpconfig.php configuration file on the existing site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to restore your data.  We hope you took a backup before starting this restore process!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the restore has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the restore is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 4,
		);
		/* End restore section */

		/* Delete All Backups */
		$fields[] = array(
			'name' => __( 'Delete Backups', 'wpcd' ),
			'desc' => __( 'Manually delete local backups. Before you can use this option you must have configured backups and run the backup process at least once.', 'wpcd' ),
			'tab'  => 'backup',
			'type' => 'heading',
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_delete_all_local_site_backups',
			'name'       => '',
			'tab'        => 'backup',
			'type'       => 'button',
			'std'        => __( 'Delete All Site Backups', 'wpcd' ),
			'desc'       => __( 'Delete ALL local backups for this site - i.e.: backups stored on the server.', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'delete-all-local-site-backups',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you really really SURE you want to delete all backups for this site? This action cannot be reversed!', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to delete all backups for this site on your server.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the restore has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the restore is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);
		/* End Delete All Backups */

		/* Manually Prune Backups */
		$hide_prune_section = (bool) wpcd_get_early_option( 'wordpress_app_site_backup_hide_prune_backups_section' ) && ( ! wpcd_is_admin() );  // Note: If we ever decide to create action options on teams or for owners, this conditional should be removed in favor of those.
		if ( ! $hide_prune_section ) {
			$fields[] = array(
				'name' => __( 'Prune Backups', 'wpcd' ),
				'desc' => __( 'Delete old local backups. Before you can use this option you must have configured backups and run the backup process at least once.', 'wpcd' ),
				'tab'  => 'backup',
				'type' => 'heading',
			);

			$fields[] = array(
				'id'         => 'wpcd_app_action_manual_prune_backup_retention_days',
				'desc'       => __( 'If left blank or zero, the backups will never be deleted.', 'wpcd' ),
				'tab'        => 'backup',
				'type'       => 'number',
				'std'        => 7,
				'name'       => __( 'Retention Days', 'wpcd' ),
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'manual_prune_backup_retention_days',
				),
				'size'       => 90,
				'save_field' => false,
			);

			$fields[] = array(
				'id'         => 'wpcd_app_action_delete_local_site_backups',
				'name'       => '',
				'tab'        => 'backup',
				'type'       => 'button',
				'std'        => __( 'Prune Backups For This Site', 'wpcd' ),
				'desc'       => __( 'Prune backups for this site.  You must have set a retention interval above before this is used!', 'wpcd' ),
				// fields that contribute data for this action.
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'prune-local-site-backups',
					// the id.
					'data-wpcd-id'                  => $id,
					// fields that contribute data for this action.
					'data-wpcd-fields'              => json_encode( array( '#wpcd_app_action_manual_prune_backup_retention_days' ) ),               // make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you really really SURE you want to prune backups for this site? This action cannot be reversed!', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to prune backups for this site on your server.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the restore has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the restore is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);
		}
		/* End Manually Prune Backups */

		return $fields;

	}

	/**
	 * Gets the list of WP sites.
	 */
	private function get_sites() {
		$sites = array();
		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'numberposts' => 300,
				'meta_query'  => array(
					array(
						'key'   => 'wpcd_server_initial_app_name',
						'value' => $this->get_app_name(),
					),
				),
				'fields'      => 'ids',
			)
		);

		if ( $posts ) {
			foreach ( $posts as $id ) {
				$sites[ $id ] = get_post_meta( $id, 'wpapp_domain', true );
			}
		}

		return $sites;

	}


	/**
	 * Turn on/off auto backups - single/current site
	 *
	 * @param int $id     The postID of the app cpt.
	 *
	 * @return boolean|WP_Error success/failure
	 */
	public function auto_backup_action( $id ) {

		// Get some data from the app record.
		$auto_backup_status = get_post_meta( $id, 'wpapp_auto_backups_status', true );
		$auto_backup_bucket = get_post_meta( $id, 'wpapp_auto_backup_bucket', true );

		// What action are we going to try to perform here?
		if ( 'on' === $auto_backup_status ) {
			// currently on, so assume we're going to turn it off.
			$action     = 'unschedule';
			$new_status = 'off';
		} else {
			// assume currently off and therefore need to turn it on.
			$action     = 'schedule';
			$new_status = 'on';
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get an array of credentials and buckets, with escapeshellarg already applied.
		$creds = $this->get_s3_credentials_for_backup( $id ); // Function get_s3_credentials_for_backup is located in a trait file.

		// Get args passed in..
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// If a bucket was passed in for this action, add it to the creds array.
		if ( ! empty( $args['auto_backup_bucket_name'] ) ) {
			$creds['aws_bucket_name']       = escapeshellarg( sanitize_text_field( $args['auto_backup_bucket_name'] ) );
			$creds['aws_bucket_name_noesc'] = sanitize_text_field( $args['auto_backup_bucket_name'] );
		}

		// Set the s3_sync_parms element in the args array to match what the bash script expects.
		$auto_backup_delete_remotes = $args['auto_backup_delete_remotes'];
		if ( 'on' === $auto_backup_delete_remotes ) {
			$args['s3_sync_delete_parm'] = 'delete';  // note that we are not apply escapeshellarg to this. @todo - it might be safe to do it but needs testing.
		} else {
			$args['s3_sync_delete_parm'] = 'follow-symlinks';  // stick something in here so that the backup script doesn't error out.
		}

		// @TODO: If some creds are empty we should bail out here!

		// Save bucket name into a variable without escapeshellarg applied because we'll be storing this in the db.
		$bucket = $creds['aws_bucket_name_noesc'];

		// Get retention days.
		$retention_days = (int) sanitize_text_field( $args['auto_backup_retention_days'] );
		if ( empty( $retention_days ) ) {
			$retention_days = 0;
		}

		// Pass the URL to which backups will call to let us know its starting or completing.
		// Note that, unlike other callbacks, this one is hard-coded into the backup script.
		// So all it needs is the site url to callback into.
		$args['callback_domain'] = home_url();

		// apply escapshellarg on retention days.
		$args['auto_backup_retention_days'] = escapeshellarg( $retention_days );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup_restore_schedule.txt',
			array_merge(
				$args,
				$creds,
				array(
					'action' => $action,
					'domain' => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		// log.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'backup_restore_schedule.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Now that we know we've been successful set the new status on the app record as well as any other data that needs to be stamped there.
		update_post_meta( $id, 'wpapp_auto_backups_status', $new_status );
		if ( ! empty( $bucket ) ) {
			update_post_meta( $id, 'wpapp_auto_backup_bucket', $bucket );
			update_post_meta( $id, 'wpapp_auto_backup_delete_remotes', $auto_backup_delete_remotes );
		}
		if ( ! empty( $retention_days ) ) {
			update_post_meta( $id, 'wpapp_auto_backup_retention_days', $retention_days );
		}

		return $success;
	}

	/**
	 * Get the list of backups from the server
	 *
	 * @param int $id     The postID of the app cpt.
	 *
	 * @return boolean|WP_Error success/failure
	 */
	public function refresh_backup_list( $id ) {

		// What action are we going to try to perform on the server script?
		$action = 'list_backups';

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup_restore_refresh_backup_list.txt',
			array(
				'action' => $action,
				'domain' => get_post_meta(
					$id,
					'wpapp_domain',
					true
				),
			)
		);

		// log.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Now we have to parse the results to get the actual list...
		$backup_list     = array();
		$delimiter_start = '==backup list start==';  // The backup list will be between these two strings...
		$delimiter_end   = '==backup list end==';
		$backup_string   = wpcd_get_string_between( $result, $delimiter_start, $delimiter_end );
		$backup_list     = wpcd_split_lines_into_array( $backup_string );

		// Now that we know we've been successful update the app record with the new list of backups.
		update_post_meta( $id, 'wpapp_backups_list', $backup_list );
		update_post_meta( $id, 'wpapp_backups_list_date', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );

		// Always return true.
		return true;
	}

	/**
	 * Manual backup for a site - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_pending_log_manual_site_backup
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site on which this action apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function pending_log_manual_site_backup( $task_id, $site_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		// Add a postmeta to the site we can use later.
		update_post_meta( $site_id, 'wpapp_pending_log_manual_backup_task_id', $task_id );

		/* Trigger manual backup for the site */
		do_action( 'wpcd_wordpress-app_manual_backup_for_site', 'backup-run-manual', $site_id );

	}

}

new WPCD_WORDPRESS_TABS_BACKUP();
