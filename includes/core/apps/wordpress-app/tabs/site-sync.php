<?php
/**
 * Site sync tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SITE_SYNC
 */
class WPCD_WORDPRESS_TABS_SITE_SYNC extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
		/* add_filter( 'wpcd_is_ssh_successful', array( $this, 'was_ssh_successful' ), 10, 5 ); */

		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

		/* Pending Logs Background Task: Trigger a site-sync process. */
		add_action( 'wpcd_pending_log_site_sync', array( $this, 'pending_log_site_sync' ), 10, 3 );

		// Allow the site sync action to be triggered via an action hook.  Will primarily be used by the woocommerce and powertools add-ons.
		add_action( 'wpcd_wordpress-app_do_site_sync', array( $this, 'do_site_sync_action' ), 10, 2 );
		add_action( 'wpcd_wordpress-app_do_site_sync_no_record', array( $this, 'do_site_sync_action_no_record' ), 10, 2 );

		// If the sync failed early, mark the pending log record as failed.
		add_action( 'wpcd_wordpress-app_site_sync_failed_early', array( $this, 'handle_site_sync_site_failed' ), 10, 4 );  // Hook: wpcd_wordpress-app_site_sync_failed_early.

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the source app cpt.
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

		// if the command is to copy a site to a new server then we need to do some things to the original and target app records...
		if ( 'site-sync' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Was the command successful?
			$success = $this->is_ssh_successful( $logs, 'site_sync.txt' );

			// Maybe this was triggered by a pending log task.  If so, grab the meta so we can update the task record later.
			$task_id = get_post_meta( $id, 'wpapp_pending_log_site_sync', true );

			if ( true === (bool) $success ) {
				/*
				 * Need to create a new app record that points to the new server.
				 */

				// get new domain from temporary meta.
				$domain = get_post_meta( $id, 'wpcd_wpapp_site_sync_domain_temp', true );

				if ( $domain ) {

					/* get the app-post */
					$app_post = get_post( $id );

					if ( $app_post ) {

						// Get the destination server id.
						$destination_server_id = get_post_meta( $id, 'wpcd_wpapp_site_sync_destination_id_temp', true );

						/* Pull some data from the current domain because it will need to be added to the new domain */
						$author = $app_post->post_author;

						/* Fill out an array that will be passed to the add_app function */
						$args['wp_domain']   = $domain;
						$args['wp_user']     = get_post_meta( $id, 'wpapp_user', true );
						$args['wp_password'] = get_post_meta( $id, 'wpapp_password', true );
						$args['wp_email']    = get_post_meta( $id, 'wpapp_email', true );
						$args['wp_version']  = get_post_meta( $id, 'wpapp_version', true );

						/* Start: Check if one or more post records for the same domain exists on the target server - if so then delete them. */
						$delete_args = array(
							'post_type'   => 'wpcd_app',
							'post_status' => 'private',
							'numberposts' => 999,
							'meta_query'  => array(
								array(
									'key'     => 'wpapp_domain',
									'value'   => $domain,
									'compare' => '=',
								),
								array(
									'key'     => 'parent_post_id',
									'value'   => $destination_server_id,
									'compare' => '=',
								),
							),
						);
						$apps        = get_posts( $delete_args );

						if ( count( $apps ) >= 1 ) {
							foreach ( $apps as $app ) {
								$app_id = $app->ID;
								// Remove an action hook that will prevent wp_delete_post from returning because of a permission check.
								// We need to do this because the AJAX user has an ID of zero and so has no permissions!.
								// This will cause the before_delete_action hook to kill WP with the DIE statement.
								// By now AJAX permissions have passed so can bypass that security check.
								remove_action( 'before_delete_post', array( WPCD_POSTS_APP(), 'wpcd_app_delete_post' ), 10 );
								wp_delete_post( $app_id, false );
							}
						}
						/* End: Check if one or more post records for the same domain exists on the target server - if so then delete them. */

						// Add the post - the $args array will be added as postmetas to the new post.
						$new_app_post_id = $this->add_wp_app_post( $destination_server_id, $args, array() );

						if ( $new_app_post_id ) {

							// reset the password because the add_wp_app_post() function would have encrypted an already encrypted password.
							update_post_meta( $new_app_post_id, 'wpapp_password', $args['wp_password'] );

							// reset the author since the add_wp_app_post() function would have added a default which is not necessarily correct.
							$post_data = array(
								'ID'          => $new_app_post_id,
								'post_author' => $author,
							);
							wp_update_post( $post_data );

							// Update the new record to make sure it belongs to the same team(s).
							// @TODO: Only the first team is copied.  If the site has more than one team, only the first one is copied over.
							update_post_meta( $new_app_post_id, 'wpcd_assigned_teams', get_post_meta( $id, 'wpcd_assigned_teams', true ) );

							// Update the PHP version to match the original version.
							$this->set_php_version_for_app( $new_app_post_id, $this->get_php_version_for_app( $id ) );

							// Update the wp-login auth status.
							$this->set_wplogin_http_auth_status( $new_app_post_id, $this->get_wplogin_http_auth_status( $id ) );

							// Update the http auth status for the full site.
							$this->set_site_http_auth_status( $new_app_post_id, $this->get_site_http_auth_status( $id ) );

							// Copy multi-tenant related metas.
							$this->clone_mt_metas( $id, $new_app_post_id );  // Function located in traits file multi-tenant-app.php.

							// Finally, lets add a meta to indicate that this was a copy.
							update_post_meta( $new_app_post_id, 'wpapp_site_synced_from_app', $id );

							// Wrapup - let things hook in here - primarily the multisite and WC add-ons.
							do_action( "wpcd_{$this->get_app_name()}_site_sync_new_post_completed", $new_app_post_id, $id, $name );

						}

						// If this was triggered by a pending log task update the task as complete.
						if ( ! empty( $task_id ) ) {

							// Grab our data array from pending tasks record...
							$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

							// Mark the task as complete.
							WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );

						}
					}
				}
			} else {
				// If this was triggered by a pending log task update the task as failed.
				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as complete.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );

				}

				// Post alert to notification log that this failed.  Maybe add a hook?
			}
		}

		// if the command is to copy a site to a new server then we need to do some things.
		// Unlike the above code block, we will NOT be creating a new app record!
		if ( 'site-sync-no-record' === $command_array[0] ) {
			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Was the command successful?
			$success = $this->is_ssh_successful( $logs, 'site_sync.txt' );

			// Maybe this was triggered by a pending log task.  If so, grab the meta so we can update the task record later.
			$task_id = get_post_meta( $id, 'wpapp_pending_log_site_sync', true );

			if ( true === (bool) $success ) {
				// If this was triggered by a pending log task update the task as complete.
				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as complete.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );

				}
			} else {
				// If this was triggered by a pending log task update the task as failed.
				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as complete.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );

				}

				// Post alert to notification log that this failed.  Maybe add a hook?
			}
		}

		// if the command is to schedule a site sync to a new server was successful we need to tag the app record to show that a sync has been scheduled.
		if ( 'schedule-site-sync' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Was the command successful?
			$success = $this->is_ssh_successful( $logs, 'site_sync.txt' );

			if ( true === (bool) $success ) {
				update_post_meta( $id, 'wpcd_wpapp_site_sync_scheduled', '1' );
				update_post_meta( $id, 'wpcd_wpapp_site_sync_schedule_destination_id', get_post_meta( $id, 'wpcd_wpapp_site_sync_destination_id_temp', true ) );
				update_post_meta( $id, 'wpcd_wpapp_site_sync_schedule_destination_ip4', get_post_meta( $id, 'wpcd_wpapp_site_sync_destination_ipv4_temp', true ) );
			}
		}

		// Delete temporary metas associated with both site-sync and schedule-site-sync actions.
		delete_post_meta( $id, 'wpcd_wpapp_site_sync_destination_ipv4_temp' );
		delete_post_meta( $id, 'wpcd_wpapp_site_sync_destination_id_temp' );
		delete_post_meta( $id, 'wpcd_wpapp_site_sync_domain_temp' );

		// Remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'site-sync';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_site_sync_tab';
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
				'label' => __( 'Copy To Server', 'wpcd' ),
				'icon'  => 'fad fa-sync',
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
		// If admin has an admin lock in place and the user is not admin they cannot view the tab or perform actions on them.
		if ( $this->get_admin_lock_status( $id ) && ! wpcd_is_admin() ) {
			return false;
		}
		// If we got here then check team and other permissions.
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
			/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'site-sync', 'site-sync-no-record', 'schedule-site-sync', 'unschedule-site-sync', 'clear-scheduled-site-sync-meta' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'site-sync':
				case 'site-sync-no-record':
					$result = $this->site_sync( $action, $id );
					break;
				case 'schedule-site-sync':
					$result = $this->site_sync( $action, $id );
					break;
				case 'unschedule-site-sync':
					$result = $this->unschedule_site_sync( $action, $id );
					break;
				case 'clear-scheduled-site-sync-meta':
					$result = $this->clear_scheduled_site_sync_metas( $id, $action );
					break;
			}
		}
		return $result;

	}

	/**
	 * Trigger the site_sync function from an action hook.
	 *
	 * Action Hook: wpcd_wordpress-app_do_site_sync
	 *
	 * @param string $id ID of app to copy to new server.
	 * @param array  $args array arguments that the site_sync function needs.
	 */
	public function do_site_sync_action( $id, $args ) {
		$this->site_sync( 'site-sync', $id, $args );
	}

	/**
	 * Trigger the site_sync function from an action hook.
	 *
	 * Unlike the regular site-sync, the post-processing action
	 * will not create a new site record.
	 *
	 * Action Hook: wpcd_wordpress-app_do_site_sync_no_record
	 *
	 * @param string $id ID of app to copy to new server.
	 * @param array  $args array arguments that the site_sync function needs.
	 */
	public function do_site_sync_action_no_record( $id, $args ) {
		$this->site_sync( 'site-sync-no-record', $id, $args );
	}

	/**
	 * Copy site to a new server.
	 *
	 * Note: A lot of this code is replicated in the multitenant tab.
	 * Significant changes here probably should be made there as well.
	 *
	 * This is initiated from the site screen on the source server.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args arguments to be used if $_POST does not have them - usually when this is being run from a pending_log process.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function site_sync( $action, $id, $in_args = array() ) {

		// Save the $action value.
		$original_action = $action;

		// Get server post corresponding to the passed in app id...
		$source_server_post = $this->get_server_by_app_id( $id );

		// Extract args out to array.
		if ( empty( $in_args ) ) {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Set some source and destination vars to try to make things clearer for future readers of this script.
		$source_app_id  = $id;
		$source_id      = $source_server_post->ID;
		$destination_id = (int) $args['site_sync_destination'];

		// Bail if no destination server...
		if ( empty( $destination_id ) ) {
			$msg = __( 'Sorry but we were unable to obtain an id for the destination server.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $args );
			return new \WP_Error( $msg );
		}

		/**
		 * Check permissions on source and destination servers if a security override check is NOT in place.
		 * The security override check is passed via another program that has done the security checks.
		 * For example, the security override will be passed by the WC addon that sells WP sites.
		 */
		if ( ! isset( $in_args['sec_source_dest_check_override'] ) ) {
			// Bail if the destination server is not something the user is authorized to use!
			if ( ! in_array( $destination_id, wpcd_get_posts_by_permission( 'view_server', 'wpcd_app_server' ) ) ) {
				$msg = __( 'Sorry but you are not allowed to copy sites to the specified target server.', 'wpcd' );
				do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
				do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $args );
				return new \WP_Error( $msg );
			}

			// Bail if the source server is not something the user is authorized to use!
			if ( ! in_array( $source_id, wpcd_get_posts_by_permission( 'view_server', 'wpcd_app_server' ) ) ) {
				$msg = __( 'Sorry but you are not allowed to copy sites from the specified source server.', 'wpcd' );
				do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
				do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $args );
				return new \WP_Error( $msg );
			}
		}

		// Bail if the source and destination servers are the same!
		if ( $destination_id === $source_id ) {
			$msg = __( 'Sorry but it looks like you are trying to copy the site to the same server where it currently resides. If you would like to do that, use the CLONE SITE tab instead.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $args );
			return new \WP_Error( $msg );
		}

		// Figure out the domain name...
		$domain = $this->get_domain_name( $source_app_id );

		// Bail if no domain...
		if ( empty( $domain ) ) {
			$msg = __( 'Sorry but we were unable to obtain the domain name for this app.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $args );
			return new \WP_Error( $msg );
		} else {
			// we have a good domain so stick it in the arg array.
			$domain         = sanitize_text_field( $domain );  // shouldn't be necessary but doing it anyway.
			$domain         = wpcd_clean_domain( $domain );   // shouldn't be necessary but doing it anyway.
			$args['domain'] = $domain;
		}

		// Get data about the destination server.
		$destination_instance = $this->get_server_instance_details( $destination_id );

		// Get some data about the source server.
		$source_instance = $this->get_server_instance_details( $source_id );

		// Bail if error for source server.
		if ( is_wp_error( $source_instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Unable to execute this request because we cannot get the source server instance details for action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// Bail if error for destination server.
		if ( is_wp_error( $destination_instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Unable to execute this request because we cannot get the destination server instance details for action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $destination_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $destination_instance );
			return new \WP_Error( $msg );
		}

		// Extract some data from the source and destination instances and check to make sure they are valid.
		$ipv4_source      = $source_instance['ipv4'];
		$ipv4_destination = $destination_instance['ipv4'];
		if ( empty( $ipv4_source ) || empty( $ipv4_destination ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Oops - either the source or destination server is missing an ipv4 address - action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// Lets get the login user for the source server.
		$source_ssh_user = WPCD_SERVER()->get_root_user_name( $source_id );
		if ( empty( $source_ssh_user ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Oops - unable to get the login user name for the source server - action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// Lets get the login user for the destination server.
		$destination_ssh_user = WPCD_SERVER()->get_root_user_name( $destination_id );
		if ( empty( $destination_ssh_user ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Oops - unable to get the login user name for the destination server - action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $destination_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $destination_instance );
			return new \WP_Error( $msg );
		}

		// @TODO: Verify that neither server is part of a server-sync pair.

		// Ok, we got this far, basic checks have passed.  Sanitize some of the data we'll be passing to scripts and update the ARGS array since we'll be passing that to the command function...
		$args['interactive']    = 'no';
		$args['origin_ip']      = escapeshellarg( $ipv4_source );
		$args['destination_ip'] = escapeshellarg( $ipv4_destination );
		$args['sshuser']        = escapeshellarg( $destination_ssh_user );

		// construct the command to set up the origin/source server.
		$action  = 'auth';
		$run_cmd = $this->turn_script_into_command( $source_instance, 'site_sync_origin_setup.txt', array_merge( $args, array( 'action' => $action ) ) );
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $source_instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $source_instance, false );

		// run the command on the origin/source server and evaluate the results.
		$result  = $this->execute_ssh( 'generic', $source_instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'site_sync_origin_setup.txt' );
		if ( ! $success ) {
			if ( is_wp_error( $result ) ) {
				/* translators: %s is replaced with the result of the execute_ssh command. */
				$msg = sprintf( __( 'Unable to configure the origin server. The origin server returned this in response to commands: %s', 'wpcd' ), $result->get_error_message() );
			} else {
				/* translators: %s is replaced with the result of the execute_ssh command. */
				$msg = sprintf( __( 'Unable to configure the origin server. The origin server returned this in response to commands: %s', 'wpcd' ), $result );
			}
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'error', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// construct the command to set up the destination server.
		$action  = '';  // no action needs to be passed to the script since it only does one thing.
		$run_cmd = $this->turn_script_into_command( $destination_instance, 'site_sync_destination_setup.txt', array_merge( $args, array( 'action' => $action ) ) );
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $destination_instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $destination_instance, false );

		// run the command to setup the destination server and evaluate the results.
		$result  = $this->execute_ssh( 'generic', $destination_instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'site_sync_destination_setup.txt' );
		if ( ! $success ) {
			/* translators: %s is replaced with the result of the execute_ssh command. */
			$msg = sprintf( __( 'Unable to configure the destination server. The destination server returned this in response to commands: %s', 'wpcd' ), $result );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'error', __FILE__, __LINE__, $destination_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $destination_instance );
			return new \WP_Error( $msg );
		} else {
			// Command successful - update some metas on the app record to make sure that we have data to use later.
			update_post_meta( $source_app_id, 'wpcd_wpapp_site_sync_destination_ipv4_temp', $ipv4_destination );
			update_post_meta( $source_app_id, 'wpcd_wpapp_site_sync_destination_id_temp', $destination_id );
			update_post_meta( $source_app_id, 'wpcd_wpapp_site_sync_domain_temp', $domain );
		}

		/**
		 * At this point, both origin and destination servers are configured.
		 * There are now two possible actions.
		 *    1. Do an immdiate site sync - $action = 'site-sync'
		 *    2. Schedule a site sync - $action = 'schedule-site-sync'
		 */

		// Reset the action var to the original passed in value.
		$action = $original_action;

		// Setup unique command name.
		if ( empty( $action ) ) {
			$msg = __( 'The $action variable is empty - returning false from site-sync routine.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $source_instance );
			return false;
		}

		$command                    = sprintf( '%s---%s---%d', $action, $domain, time() );
		$source_instance['command'] = $command;
		$source_instance['app_id']  = $id;

		// Create a callback url if the action is schedule_site_sync.
		if ( 'schedule-site-sync' === $action ) {
			$callback_name                  = 'schedule_site_sync';
			$args['site_sync_callback_url'] = $this->get_command_url( $id, $callback_name, 'completed' );
		}

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$source_instance,
			'site_sync.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( "$msg: %s", print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_site_sync_failed_early", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// We might need to add an item to the PENDING TASKS LOG (generally because we're calling this from a WC order).
		if ( isset( $in_args['pending_tasks_type'] ) ) {
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $destination_id, $in_args['pending_tasks_type'], $command, $args, 'not-ready', $id, __( 'Waiting For Site Copy To Complete', 'wpcd' ) );
		}

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $source_instance, $action );

		return $return;
	}

	/**
	 * Stop syncing to a destination server.
	 *
	 * This is initiated from the site screen on the source server.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args arguments to be used if $_POST does not have them - usually when this is being run from a pending_log process.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function unschedule_site_sync( $action, $id, $in_args = array() ) {

		// Save the $action value.
		$original_action = $action;

		// Get server post corresponding to the passed in app id...
		$source_server_post = $this->get_server_by_app_id( $id );

		// Extract args out to array.
		if ( empty( $in_args ) ) {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Set some source and destination vars to try to make things clearer for future readers of this script.
		$source_app_id  = $id;
		$source_id      = $source_server_post->ID;
		$destination_id = (int) get_post_meta( $id, 'wpcd_wpapp_site_sync_schedule_destination_id', true );

		// Bail if no destination server...
		if ( empty( $destination_id ) ) {
			$msg = __( 'Sorry but we were unable to obtain an id for the destination server.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
			return new \WP_Error( $msg );
		}

		/**
		 * Check permissions on source servers if a security override check is NOT in place.
		 * The security override check is passed via another program that has done the security checks.
		 * For example, the security override might be passed by the WC addon that sells WP sites.
		 */
		if ( ! isset( $in_args['sec_source_dest_check_override'] ) ) {
			// Bail if the source server is not something the user is authorized to use!
			if ( ! in_array( $source_id, wpcd_get_posts_by_permission( 'view_server', 'wpcd_app_server' ) ) ) {
				$msg = __( 'Sorry but you are not allowed to copy sites from the specified source server.', 'wpcd' );
				do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
				return new \WP_Error( $msg );
			}
		}

		// Figure out the domain name...
		$domain = $this->get_domain_name( $source_app_id );

		// Bail if no domain...
		if ( empty( $domain ) ) {
			$msg = __( 'Sorry but we were unable to obtain the domain name for this app.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
			return new \WP_Error( $msg );
		} else {
			// we have a good domain so stick it in the arg array.
			$domain         = sanitize_text_field( $domain );  // shouldn't be necessary but doing it anyway.
			$domain         = wpcd_clean_domain( $domain );   // shouldn't be necessary but doing it anyway.
			$args['domain'] = $domain;
		}

		// Get data about the destination server.
		$destination_instance = $this->get_server_instance_details( $destination_id );

		// Get some data about the source server.
		$source_instance = $this->get_server_instance_details( $source_id );

		// Bail if error for source server.
		if ( is_wp_error( $source_instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Unable to execute this request because we cannot get the source server instance details for action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			return new \WP_Error( $msg );
		}

		// Bail if error for destination server.
		if ( is_wp_error( $destination_instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Unable to execute this request because we cannot get the destination server instance details for action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $destination_instance, false );
			return new \WP_Error( $msg );
		}

		// Extract some data from the source and destination instances and check to make sure they are valid.
		$ipv4_source      = $source_instance['ipv4'];
		$ipv4_destination = $destination_instance['ipv4'];
		if ( empty( $ipv4_source ) || empty( $ipv4_destination ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Oops - either the source or destination server is missing an ipv4 address - action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			return new \WP_Error( $msg );
		}

		// Add the IP to the args array.
		$args['destination_ip'] = $ipv4_destination;

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$source_instance,
			'site_sync_unschedule.txt',
			array_merge(
				$args,
				array(
					'command' => "{$action}_site",
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $source_instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $source_instance, false );

		$result = $this->execute_ssh( 'generic', $source_instance, array( 'commands' => $run_cmd ) );

		// If wp_error, return immediately.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Not wp_error, so evaluate the results.
		$success = $this->is_ssh_successful( $result, 'site_sync_unschedule.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is the action code we're executing for the site; %2$s is the error code or message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// We got here so everything ok - remove metas.
		$this->clear_scheduled_site_sync_metas( $id, $action );

		// Construct a return array.
		$result = array( 'refresh' => 'yes' );

		return $result;

	}

	/**
	 * Clear scheduled site sync metas.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed. Not used in this script.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	public function clear_scheduled_site_sync_metas( $id, $action ) {

		delete_post_meta( $id, 'wpcd_wpapp_site_sync_scheduled' );
		delete_post_meta( $id, 'wpcd_wpapp_site_sync_schedule_destination_id' );
		delete_post_meta( $id, 'wpcd_wpapp_site_sync_schedule_destination_ip4' );

		// Return an error so that it can be displayed in a dialog box...
		return new \WP_Error( __( 'Scheduled Site Sync Metas have been cleared.', 'wpcd' ) );
	}

	/**
	 * Gets the fields to be shown.
	 *
	 * @TODO: The logic to get an appropriate list of servers
	 * is duplicated in the multitenant-site.php file/tab in the
	 * get_list_of_destination_servers() function.  We should consolidate and
	 * make a central function.
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
			return array_merge( $fields, $this->get_disabled_header_field( 'site-sync' ) );
		}

		// What type of web server are we running?
		$webserver_type = $this->get_web_server_type( $id );

		// Get HTTP2 status since we cannot push a site with HTTP2 turned on.
		// This restriction applies only to NGINX servers.
		$http2_status = $this->http2_status( $id );
		if ( 'on' === $http2_status && 'nginx' === $webserver_type ) {
			$desc = __( 'You cannot copy this site to a new server at this time because HTTP2 is enabled. Please disable it before attempting this operation.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'Site Sync', 'wpcd' ),
				'tab'  => 'site-sync',
				'type' => 'heading',
				'desc' => $desc,
			);

			return $fields;
		}

		// We got here so ok to show fields related to copying the site to a new server.
		$desc  = __( 'Copy this site to another server. If the domain already exists on the target server it will be overwritten.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'You should make sure that your destination server has any optional components installed. For example,if you enabled REDIS or MEMCACHED on here, your destination should have that installed.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( '***Please see important notes at the bottom of this page if this is the first time you are using this function!***', 'wpcd' );

		$fields[] = array(
			'name' => __( 'Site Sync', 'wpcd' ),
			'tab'  => 'site-sync',
			'type' => 'heading',
			'desc' => $desc,
		);

		$source_server    = $this->get_server_by_app_id( $id );
		$source_server_id = $source_server->ID;

		// What type of web server are we running?
		$webserver_type = $this->get_web_server_type( $id );

		// Now we need to construct an array of server posts that the user is allowed to see.
		$post__in = wpcd_get_posts_by_permission( 'view_server', 'wpcd_app_server' );

		// Remove the current ID if it's the server posts array. Note the use of ArrayMap and passing in the $source_server_id to the annonymous function.
		$post__in = array_filter(
			$post__in,
			function( $array_entry ) use ( $source_server_id ) {
				if ( $source_server_id === (int) $array_entry ) {
					return false;
				} else {
					return $array_entry;
				}
			}
		);

		// Remove from the array any server that does not match the webserver type where this site is running.
		// Note the use of ArrayMap and passing in the $webserver_type to the annoymous function.
		$post__in = array_filter(
			$post__in,
			function( $array_entry ) use ( $webserver_type ) {
				$this_webserver_type = $this->get_web_server_type( (int) $array_entry );
				if ( $this_webserver_type !== $webserver_type ) {
					return false;
				} else {
					return $array_entry;
				}
			}
		);

		$fields[] = array(
			'name' => __( 'Select Destination Server', 'wpcd' ),
			'tab'  => 'site-sync',
			'type' => 'heading',
			'desc' => __( 'Where would you like to send a copy of this site?', 'wpcd' ),
		);

		// Setup a field with the dropdown equal to the posts in the $post__in var we just setup above.
		$fields[] = array(
			'id'          => 'wpcd_app_site-sync-target-server',
			'name'        => '',
			'tab'         => 'site-sync',
			'label'       => __( 'Target server', 'wpcd' ),
			'field_type'  => 'select',
			'placeholder' => __( 'Select your destination server', 'wpcd' ),
			'desc'        => '',
			'type'        => 'post',
			'post_type'   => 'wpcd_app_server',
			'query_args'  => array(
				/* @TODO: need to restrict this list to only server posts of type wp server and drop existing destination and source servers */
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post__in'       => empty( $post__in ) ? array( -1 ) : $post__in,
			),
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'site_sync_destination',
			),
		);

		$fields[] = array(
			'name' => __( 'Copy Now', 'wpcd' ),
			'tab'  => 'site-sync',
			'type' => 'heading',
		);

		$copy_desc = __( 'Immediately copy this site to the destination server selected above.', 'wpcd' );
		if ( 'yes' === $this->is_remote_db( $id ) ) {
			$copy_desc .= '<br />' . '<b>' . __( 'Warning: This site appears to be using a remote database server.  Your target server should have a local database server since the database server will be switched to localhost to avoid database naming conflicts.', 'wpcd' ) . '</b>';
		}

		$fields[] = array(
			'id'         => 'wpcd_app_site_sync',
			'name'       => '',
			'tab'        => 'site-sync',
			'type'       => 'button',
			'std'        => __( 'Start Copy', 'wpcd' ),
			'desc'       => $copy_desc,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'site-sync',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_site-sync-target-server' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to copy this site to the specified server?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to copy this site to the specified server...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/* Periodic Sync */
		$fields[] = array(
			'name' => __( 'Daily Sync - NOTE: Limited Release/Fast-Ring Feature', 'wpcd' ),
			'tab'  => 'site-sync',
			'type' => 'heading',
			'desc' => __( 'Sync this site to the server selected above once per day.', 'wpcd' ),
		);

		if ( 1 === (int) get_post_meta( $id, 'wpcd_wpapp_site_sync_scheduled' ) ) {

			// The site is scheduled to be synced to another server already.
			$fields[] = array(
				'id'   => 'wpcd_app_site_sync_periodic_set',
				'name' => '',
				'tab'  => 'site-sync',
				'type' => 'custom-html',
				/* Translators: %s is the server name taken from the post title of the server custom post type. */
				'std'  => sprintf( __( 'This site has already been set up for daily syncing with another server: %s', 'wpcd' ), get_the_title( get_post_meta( $id, 'wpcd_wpapp_site_sync_schedule_destination_id', true ) ) ),
			);

			/**
			 *  Because the site is already scheduled, we can add the option to unsync.
			 */
			$fields[] = array(
				'id'   => 'wpcd_app_site_sync_periodic_unsync_header',
				'name' => __( 'Stop Scheduled Sync', 'wpcd' ),
				'tab'  => 'site-sync',
				'type' => 'heading',
				'desc' => __( 'Stop daily syncing to the destination server.', 'wpcd' ),
			);

			/* Set the text of the confirmation prompt */
			$confirmation_prompt = __( 'Are you sure you would like to stop the daily sync for this site?', 'wpcd' );

			$fields[] = array(
				'id'         => 'wpcd_app_site_sync_periodic_unsync',
				'tab'        => 'site-sync',
				'std'        => __( 'Stop Syncing', 'wpcd' ),
				'type'       => 'button',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'unschedule-site-sync',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			/**
			 *  Because the site is already scheduled, we can add the option to clear metas and reset.
			 */
			$fields[] = array(
				'id'   => 'wpcd_app_site_sync_periodic_clear_meta_header',
				'name' => __( 'Clear Metas', 'wpcd' ),
				'tab'  => 'site-sync',
				'type' => 'heading',
				'desc' => __( 'Clear metas and reset plugin state. Any scheduled syncs for this site will still exist on the server but the plugin will not be aware of it', 'wpcd' ),
			);

			/* Set the text of the confirmation prompt */
			$confirmation_prompt = __( 'Are you sure you would like to clear and reset the metas?', 'wpcd' );

			$fields[] = array(
				'id'         => 'wpcd_app_site_sync_periodic_clear_meta',
				'tab'        => 'site-sync',
				'std'        => __( 'Clear', 'wpcd' ),
				'type'       => 'button',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'clear-scheduled-site-sync-meta',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

		} else {

			/**
			 * Enable site sync button.
			 */
			$fields[] = array(
				'id'         => 'wpcd_app_site_sync_periodic',
				'name'       => '',
				'tab'        => 'site-sync',
				'type'       => 'button',
				'std'        => __( 'Schedule', 'wpcd' ),
				'desc'       => '',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'schedule-site-sync',
					// the id.
					'data-wpcd-id'                  => $id,
					// fields that contribute data for this action.
					'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_site-sync-target-server' ) ),
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to schedule this site every day to sync to the specified server?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to schedule copying this site to the specified server...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

		}

		// Show some important notes.
		$important_notes  = __( 'Please keep these two notes in mind before using this function:', 'wpcd' );
		$important_notes .= '<br />';
		$important_notes .= __( '1. Root access is usually required on both servers.  Therefore this function might not work on some server providers such as AWS EC2, AWS Lightsail, Azure & Google Cloud!', 'wpcd' );
		$important_notes .= '<br />';
		$important_notes .= __( '2. The private ssh key must NOT be protected by a password.', 'wpcd' );
		$fields[]         = array(
			'name' => __( 'Important Notes', 'wpcd' ),
			'tab'  => 'site-sync',
			'type' => 'heading',
			'desc' => $important_notes,
		);

		return $fields;

	}

	/**
	 * Perform a site sync - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_pending_log_site_sync
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site on which this action apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function pending_log_site_sync( $task_id, $site_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		$args['site_sync_destination']          = $args['target_server'];
		$args['sec_source_dest_check_override'] = 1;

		// Add a postmeta to the site we can use later.
		update_post_meta( $site_id, 'wpapp_pending_log_site_sync', $task_id );

		// Should we create a new app record on every new copy?
		$add_new_app_record = (bool) get_post_meta( $site_id, 'wpcd_wpapp_sitesync_schedule_new_record', true );

		/* Trigger the site sync */
		if ( true === $add_new_app_record ) {
			do_action( 'wpcd_wordpress-app_do_site_sync', $site_id, $args );
		} else {
			do_action( 'wpcd_wordpress-app_do_site_sync_no_record', $site_id, $args );
		}

	}

	/**
	 * Handle things when the Site Sync operation failed before the bash script gets called (aka early failure.)
	 *
	 * Primarily, we'll be updating the pending log record as failed.
	 * Maybe later we'll add a notification?
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_site_sync_failed_early | wpcd_wordpress-app_site_sync_failed_early
	 *
	 * @param int    $id       Post ID of the site.
	 * @param int    $action   String indicating the action name.
	 * @param string $message  Failure message if any.
	 * @param array  $args     All args that were passed in to the clone-site action.  Sometimes this can be an empty array.
	 *
	 * @return void
	 */
	public function handle_site_sync_site_failed( $id, $action, $message, $args ) {

		$site_post = get_post( $id );

		// Bail if not a post object.
		if ( ! $site_post || is_wp_error( $site_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_app_type( $id ) ) {
			return;
		}

		// This only matters if we are syncing a site.  If not, then bail.
		if ( 'site-site' !== $action && 'site-sync-no-record' !== $action ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_app_instance_details( $id );

		if ( 'wpcd_app' === get_post_type( $id ) ) {

			// Now check the pending tasks table for a record where the key=$id and type='site-sync' and state='in-process'
			// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
			$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $id, 'in-process', 'site-sync' );

			if ( $posts ) {

				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				// Mark post as failed.
				WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed', '', '', false, $message );

			}
		}
	}

}

new WPCD_WORDPRESS_TABS_SITE_SYNC();
