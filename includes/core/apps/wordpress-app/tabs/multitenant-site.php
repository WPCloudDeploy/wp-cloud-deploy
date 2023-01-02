<?php
/**
 * Clone site tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_MULTITENANT_SITE
 */
class WPCD_WORDPRESS_TABS_MULTITENANT_SITE extends WPCD_WORDPRESS_TABS {

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

		/* Pending Logs Background Task: Trigger MT conversion / Apply Version process. */
		add_action( 'wpcd_pending_log_mt_apply_version', array( $this, 'wpcd_pending_log_mt_apply_version' ), 10, 3 );

		// Allow the site conversion action to be triggered via an action hook.
		add_action( "wpcd_{$this->get_app_name()}_do_mt_apply_version", array( $this, 'do_mt_apply_version' ), 10, 2 ); // Hook: wpcd_wordpress-app_do_mt_apply_version.

		// If the site conversion / apply version action failed early, handle it if it's a pending log background process.
		add_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_failed_early", array( $this, 'handle_mt_site_conversion_failed_early' ), 10, 4 ); // Hook: wpcd_wordpress-app_mt_site_conversion_failed_early.

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the server cpt.
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

		// if the command is to create a new version we need to do a few things...
		if ( 'mt-create-version' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'mt_clone_site.txt' );

			if ( true === $success ) {

				// Get Webserver Type.
				$webserver_type = $this->get_web_server_type( $id );

				// get new domain from temporary meta.
				$new_domain = get_post_meta( $id, 'wpapp_temp_mt_new_version_target_domain', true );

				if ( $new_domain ) {

					/* get the app-post */
					$app_post = get_post( $id );

					if ( $app_post ) {

						/* Pull some data from the current domain because it will need to be added to the new domain */
						$server_id = get_post_meta( $id, 'parent_post_id', true );
						$author    = $app_post->post_author;

						/* Fill out an array that will be passed to the add_app function */
						$args['wp_domain']   = $new_domain;
						$args['wp_user']     = get_post_meta( $id, 'wpapp_user', true );
						$args['wp_password'] = get_post_meta( $id, 'wpapp_password', true );
						$args['wp_email']    = get_post_meta( $id, 'wpapp_email', true );
						$args['wp_version']  = get_post_meta( $id, 'wpapp_version', true );

						// Add the post - the $args array will be added as postmetas to the new post.
						$new_app_post_id = $this->add_wp_app_post( $server_id, $args, array() );

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

							// Was SSL enabled for the cloned site?  If so, flip the SSL metavalues.
							$this->set_ssl_status( $new_app_post_id, 'off' ); // Assume off for now.
							$success = $this->is_ssh_successful( $logs, 'manage_https.txt' );  // ***Very important Note: We didn't actually run the manage_https script.  We are just using the check logic for it to see if the same keyword output is in the clone site output since we are using the same keywords for both scripts.
							if ( true == $success ) {
								$this->set_ssl_status( $new_app_post_id, 'on' );
							}

							// Was page caching enabled on the original site?  If so, the caching plugin was copied as well so add the meta here for that.
							$page_cache_status = $this->get_page_cache_status( $id );
							if ( ! empty( $page_cache_status ) ) {
								$this->set_page_cache_status( $new_app_post_id, $page_cache_status );
							}

							// Was memcached enabled on the original site?  If so, the caching plugin was copied as well so add the meta here for that.
							$memcached_status = get_post_meta( $id, 'wpapp_memcached_status', true );
							if ( ! empty( $memcached_status ) ) {
								update_post_meta( $new_app_post_id, 'wpapp_memcached_status', $memcached_status );
							}

							// Was redis enabled on the original site?  If so, the caching plugin was copied as well so add the meta here for that.
							$redis_status = get_post_meta( $id, 'wpapp_redis_status', true );
							if ( ! empty( $redis_status ) ) {
								update_post_meta( $new_app_post_id, 'wpapp_redis_status', $redis_status );
							}

							// Update the PHP version to match the original version.
							switch ( $webserver_type ) {
								case 'ols':
								case 'ols-enterprise':
									$this->set_php_version_for_app( $new_app_post_id, $this->get_wpapp_default_php_version() );
									break;

								case 'nginx':
								default:
									$this->set_php_version_for_app( $new_app_post_id, $this->get_php_version_for_app( $id ) );
									break;
							}

							// Lets add a meta to indicate that this was a clone.
							update_post_meta( $new_app_post_id, 'wpapp_cloned_from', $this->get_domain_name( $id ) );

							// Add metas related to Multi-tenant.
							$mt_version      = get_post_meta( $id, 'wpapp_temp_mt_new_version', true );
							$mt_version_desc = get_post_meta( $id, 'wpapp_temp_mt_new_version_desc', true );
							$this->mt_add_mt_version_history( $id, $mt_version, $mt_version_desc, $new_domain, $new_app_post_id );
							$this->set_mt_version( $new_app_post_id, $mt_version );
							$this->set_mt_parent( $new_app_post_id, $id );
							$this->set_mt_site_type( $new_app_post_id, 'mt_version' );

							// Wrapup - let things hook in here - primarily the multisite add-on and the REST API.
							do_action( "wpcd_{$this->get_app_name()}_site_clone_new_post_completed", $new_app_post_id, $id, $name );
							do_action( "wpcd_{$this->get_app_name()}_site_mt_new_version_new_post_completed", $new_app_post_id, $id, $name );

						}
					}
				}
			} else {
				// Add action hook to indicate failure...
				$message = __( 'Multi-tenant: Create new version failed - check the command logs for more information.', 'wpcd' );
				do_action( "wpcd_{$this->get_app_name()}_mt_new_version_clone_site_failed", $id, $command_array[0], $message, array() );  // Keeping 4 parameters for the action hook to maintain consistency even though we have nothing for the last parameter.
			}

			// Delete the temporary metas specific to this operation.
			delete_post_meta( $id, 'wpapp_temp_mt_new_version_target_domain' );
			delete_post_meta( $id, 'wpapp_temp_mt_new_version' );
			delete_post_meta( $id, 'wpapp_temp_mt_new_version_desc' );
		}

		// if the command is to convert or upgrade an existing site we need to update some metas and delete some temporary ones among other things.
		// if ( in_array( $command_array[0], array( 'mt-convert-site', 'mt-upgrade-all-tenants', 'mt-upgrade-tenants-selected-versions', 'mt-upgrade-tenants-selected-app-group' ), true ) ) {
		if ( 'mt-convert-site' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'mt_convert_site.txt' );

			// Was this triggered by a pending task?
			$task_id = get_post_meta( $id, 'wpapp_pending_log_mt_apply_version', true );

			if ( true === $success ) {

				// Get Webserver Type.
				$webserver_type = $this->get_web_server_type( $id );

				// get data out of the temporary metas.
				$template_site_id = get_post_meta( $id, 'wpapp_temp_mt_template_id', true );
				$mt_version       = get_post_meta( $id, 'wpapp_temp_mt_version', true );

				if ( $template_site_id && $mt_version ) {

					/* get the app-post */
					$app_post = get_post( $id );

					if ( $app_post ) {

						// Update meta records.
						$this->set_mt_version( $id, $mt_version );
						$this->set_mt_parent( $id, $template_site_id );
						$this->set_mt_site_type( $id, 'mt_tenant' );

						// If this was triggered by a pending log task update the task as complete.
						if ( ! empty( $task_id ) ) {
							// Mark the task as complete.
							WPCD_POSTS_PENDING_TASKS_LOG()->update_task_state_by_id( $task_id, 'complete' ); // We don't have to to pass in any updated data so we can use update_task_state_by_id() instead of update_task_by_id().
						}

						// Maybe update the template site with a count of the tenants related to it?

						// Wrapup - let things hook in here - primarily the multisite add-on and the REST API.
						do_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_completed", $id, $name );

					}
				} else {
					// If this was triggered by a pending log task update the task as failed.
					if ( ! empty( $task_id ) ) {
						// Mark the task as complete.
						WPCD_POSTS_PENDING_TASKS_LOG()->update_task_state_by_id( $task_id, 'failed' ); // We don't have to to pass in any updated data so we can use update_task_state_by_id() instead of update_task_by_id().
					}
					$message = __( 'Multi-tenant: Site conversion failed - check the command logs for more information.', 'wpcd' );
					do_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_failed", $id, $command_array[0], $message, array() );  // Keeping 4 parameters for the action hook to maintain consistency even though we have nothing for the last parameter.
				}
			} else {
				// Failed.

				// If this was triggered by a pending log task update the task as failed.
				if ( ! empty( $task_id ) ) {
					// Mark the task as complete.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_state_by_id( $task_id, 'failed' ); // We don't have to to pass in any updated data so we can use update_task_state_by_id() instead of update_task_by_id().
				}

				// Add action hook to indicate failure...
				$message = __( 'Multi-tenant: Site conversion failed - check the command logs for more information.', 'wpcd' );
				do_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_failed", $id, $command_array[0], $message, array() );  // Keeping 4 parameters for the action hook to maintain consistency even though we have nothing for the last parameter.
			}

			// Delete the temporary metas specific to this operation.
			delete_post_meta( $id, 'wpapp_temp_mt_template_id' );
			delete_post_meta( $id, 'wpapp_temp_mt_version' );
			delete_post_meta( $id, 'wpapp_pending_log_mt_apply_version' );
		}

		// if the command is to push versions to a destination server, we just need to update the versions array.
		if ( 'mt-push-versions-to-server' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'site_sync.txt' );

			if ( true === $success ) {

				// Get Webserver Type.
				$webserver_type = $this->get_web_server_type( $id );

				// get data out of the temporary metas.
				$destination_server_id = get_post_meta( $id, 'wpapp_temp_mt_versions_push_server_destination_id', true );

				if ( $destination_server_id ) {

					/* get the app-post */
					$app_post = get_post( $id );

					if ( $app_post ) {

						// Update meta records - add the destination server to the tags/version array.
						$this->mt_add_destination_server_id_to_versions( $id, $destination_server_id );

						// Wrapup - let things hook in here - primarily the multisite add-on and the REST API.
						do_action( "wpcd_{$this->get_app_name()}_mt_push_versions_to_server_complete", $id, $destination_server_id, $name );

					}
				} else {
					$message = __( 'Multi-tenant: Push versions to remote server failed- check the command logs for more information.', 'wpcd' );
					do_action( "wpcd_{$this->get_app_name()}_mt_push_versions_to_server_failed", $id, $command_array[0], $message, array() );  // Keeping 4 parameters for the action hook to maintain consistency even though we have nothing for the last parameter.
				}

				// And delete the temporary metas.
				delete_post_meta( $id, 'wpapp_temp_mt_versions_push_server_destination_id' );

			} else {
				// Add action hook to indicate failure...
				$message = __( 'Multi-tenant: Push versions to remote server failed - check the command logs for more information.', 'wpcd' );
				do_action( "wpcd_{$this->get_app_name()}_mt_push_versions_to_server_failed", $id, $command_array[0], $message, array() );  // Keeping 4 parameters for the action hook to maintain consistency even though we have nothing for the last parameter.
			}
		}

		// Remove the general 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );

	}


	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'multitenant-site';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_multitenant_site_tab';
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
				'label' => __( 'Multi-tenant', 'wpcd' ),
				'icon'  => 'fa-duotone fa-rectangle-history-circle-user',
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
		$valid_actions = array(
			'mt-create-version',
			'mt-set-product-name',
			'mt-set-template-flag',
			'mt-set-default-version',
			'mt-convert-site',
			'mt-push-versions-to-server',
			'mt-upgrade-all-tenants',
			'mt-upgrade-tenants-selected-versions',
			'mt-upgrade-tenants-selected-app-group',
		);
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( in_array( $action, $valid_actions, true ) ) {
			// Allow a third party to determine if we should proceed.
			// Full filter name: wpcd_app_wordpress-app_tab_actions_general_security_check.
			$addl_security_check = apply_filters(
				"wpcd_app_{$this->get_app_name()}_tab_actions_general_security_check",
				array(
					'check' => true,
					'msg'   => '',
				),
				$this->get_tab_slug(),
				$action,
				$id
			);
			if ( ! $addl_security_check['check'] ) {
				if ( empty( $addl_security_check['msg'] ) ) {
					/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
					return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
				} else {
					return new \WP_Error( $addl_security_check['msg'] );
				}
			}
		}

		// Security checks passed, do all the things.
		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'mt-create-version':
					$result = $this->mt_create_version( $action, $id );
					break;
				case 'mt-set-product-name':
					$result = $this->mt_set_product_name( $action, $id );
					break;
				case 'mt-set-template-flag':
					$result = $this->mt_set_template_flag( $action, $id );
					break;
				case 'mt-set-default-version':
					$result = $this->mt_set_default_version( $action, $id );
					break;
				case 'mt-convert-site':
					$result = $this->mt_convert_site( $action, $id );
					break;
				case 'mt-push-versions-to-server':
					$result = $this->mt_push_versions_to_server( $action, $id );
					break;
				case 'mt-upgrade-all-tenants':
				case 'mt-upgrade-tenants-selected-versions':
				case 'mt-upgrade-tenants-selected-app-group':
					$result = $this->mt_upgrade_tenants( $action, $id );
					break;
			}
		}
		return $result;

	}

	/**
	 * Multitenant - create version.
	 *
	 * This is going to run two long-running command scripts back-to-back:
	 *  - clone site
	 *  - git control (fetch tag)
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function mt_create_version( $action, $id, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Bail if target domain is empty.
		if ( empty( $args['mt_new_domain'] ) ) {
			$message = __( 'The domain for the new version must be provided', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_mt_new_version_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$new_domain            = strtolower( sanitize_text_field( $args['mt_new_domain'] ) );
			$new_domain            = wpcd_clean_domain( $new_domain );
			$args['new_domain']    = $new_domain;
			$args['mt_new_domain'] = $new_domain;
		}

		// Bail if version is empty.
		if ( empty( $args['mt_new_version'] ) ) {
			$message = __( 'The new version must be provided', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_mt_new_version_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$args['mt_new_version'] = $this->sanitize_tag( $args['mt_new_version'] ); // Make sure we get the git tag into the correct format.
			$mt_new_version         = $args['mt_new_version'];
		}

		// Bail if description is empty.
		if ( empty( $args['mt_new_version_desc'] ) ) {
			$message = __( 'A description for the new version must be provided', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_mt_new_version_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$mt_new_version_desc = $args['mt_new_version_desc'];
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_mt_new_version_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Allow developers to validate the new domain and bailout if necessary.
		if ( ! apply_filters( 'wpcd_wpapp_validate_domain_on_mt_version', true, $args['new_domain'] ) ) {
			/* translators: %s is replaced with the internal action name. */
			$message = sprintf( __( 'The new domain has failed validation.  Please try again: %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_mt_new_version_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// sanitize the fields to allow them to be used safely on the bash command line.
		$args['new_domain']          = escapeshellarg( sanitize_text_field( $args['new_domain'] ) );
		$args['mt_new_domain']       = escapeshellarg( sanitize_text_field( $args['mt_new_domain'] ) );
		$args['mt_new_version']      = escapeshellarg( sanitize_text_field( $args['mt_new_version'] ) );
		$args['mt_new_version_desc'] = escapeshellarg( sanitize_text_field( $args['mt_new_version_desc'] ) );

		// Certain elements in the $args array need to be added or changed to match the values expected by the bash scripts.
		$args['git_tag'] = $args['mt_new_version'];

		// Get the domain we're working on (this is the template domain).
		$domain = $this->get_domain_name( $id );

		/**
		 * We've gotten this far, so lets try to configure the DNS for the new domain to point to the server.
		 */
		// 1. What's the server post id?
		$server_id = $this->get_server_id_by_app_id( $id );
		// 2. What's the IP of the server?
		$ipv4 = WPCD_SERVER()->get_ipv4_address( $server_id );
		$ipv6 = WPCD_SERVER()->get_ipv6_address( $server_id );
		// 3. Add the DNS
		$dns_success = WPCD_DNS()->set_dns_for_domain( $new_domain, $ipv4, $ipv6 );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		// NOTE: The control file for this operation, mt_clone_site.txt is going to run THREE operations in sequence.
		// This means that this control file will look a little different from the others.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'mt_clone_site.txt',
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
			$message = sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_mt_new_version_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Stamp some data we'll need later (in the command_completed function) onto the app records.
		update_post_meta( $id, 'wpapp_temp_mt_new_version_target_domain', $new_domain );
		update_post_meta( $id, 'wpapp_temp_mt_new_version', $mt_new_version );
		update_post_meta( $id, 'wpapp_temp_mt_new_version_desc', $mt_new_version_desc );

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;
	}

	/**
	 * Multitenant - convert a standard site to a multi-tenant site.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function mt_convert_site( $action, $id, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Make sure we get a template site.
		if ( empty( $args['mt_product_template'] ) ) {
			$message = __( 'The product template should be provided.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_failed_early", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Make sure we get a version.
		$git_tag = $this->sanitize_tag( $args['mt_version'] ); // Make sure we get the git tag into the correct format.
		if ( empty( $git_tag ) ) {
			$message = __( 'The version should be provided.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_failed_early", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$args['mt_version'] = $git_tag;
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_failed_early", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Get the domain we're working on (this is the template domain).
		$domain = $this->get_domain_name( $id );

		// What's the template domain name?
		$template_domain_id = $args['mt_product_template'];
		$template_domain    = $this->get_domain_name( $template_domain_id );

		if ( empty( $template_domain_id ) ) {
			$message = __( 'We were unable to get a domain for the product template.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_failed_early", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Stuff the domain name in the $args array.  It's needed by the bash scripts.
		$args['domain']             = $domain;
		$args['mt_template_domain'] = $template_domain;

		// Certain elements in the $args array need to be added or changed to match the values expected by the bash scripts.
		$args['git_tag'] = escapeshellarg( $args['mt_version'] );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// Set up var to hold value of bash action - which is different from the incoming action string.
		$bash_action = 'mt_convert_site';

		// construct the run command.
		// NOTE: The control file for this operation, mt_clone_site.txt is going to run THREE operations in sequence.
		// This means that this control file will look a little different from the others.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'mt_convert_site.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $bash_action,
					'domain'  => $domain,
				)
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* translators: %s is replaced with the internal action name. */
			$message = sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_mt_site_conversion_failed_early", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Stamp some data we'll need later (in the command_completed function) onto the app records.
		update_post_meta( $id, 'wpapp_temp_mt_template_id', $template_domain_id );
		update_post_meta( $id, 'wpapp_temp_mt_version', $git_tag );

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;

	}

	/**
	 * Multitenant - push all versions for the template site to a remote server.
	 *
	 * Note: A lot of this code is replicated in the site_sync tab.
	 * Significant changes here probably should be made there as well.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function mt_push_versions_to_server( $action, $id, $in_args = array() ) {

		// Save the $action value.
		$original_action = $action;

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Make sure we get a destination server.
		if ( empty( $args['site_sync_destination'] ) ) {
			$message = __( 'A destination server is required.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Get server post corresponding to the passed in app id...
		$source_server_post = $this->get_server_by_app_id( $id );

		// Set some source and destination vars to try to make things clearer for future readers of this script.
		$source_app_id  = $id;
		$source_id      = $source_server_post->ID;
		$destination_id = (int) $args['site_sync_destination'];

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
				do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $args );
				return new \WP_Error( $msg );
			}

			// Bail if the source server is not something the user is authorized to use!
			if ( ! in_array( $source_id, wpcd_get_posts_by_permission( 'view_server', 'wpcd_app_server' ) ) ) {
				$msg = __( 'Sorry but you are not allowed to copy sites from the specified source server.', 'wpcd' );
				do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
				do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $args );
				return new \WP_Error( $msg );
			}
		}

		// Bail if the source and destination servers are the same!
		if ( $destination_id === $source_id ) {
			$msg = __( 'Sorry but it looks like you are trying to copy the site to the same server where it currently resides. If you would like to do that, use the CLONE SITE tab instead.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $args );
			return new \WP_Error( $msg );
		}

		// Get the domain we're working on (this is the template domain).
		$domain = $this->get_domain_name( $id );

		// Bail if no domain...
		if ( empty( $domain ) ) {
			$msg = __( 'Sorry but we were unable to obtain the domain name for this app.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $args, false );  // Note that we are passing $args instead of a $instance var because we do not have a $instance var yet.
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $args );
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
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// Bail if error for destination server.
		if ( is_wp_error( $destination_instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Unable to execute this request because we cannot get the destination server instance details for action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $destination_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $destination_instance );
			return new \WP_Error( $msg );
		}

		// Extract some data from the source and destination instances and check to make sure they are valid.
		$ipv4_source      = $source_instance['ipv4'];
		$ipv4_destination = $destination_instance['ipv4'];
		if ( empty( $ipv4_source ) || empty( $ipv4_destination ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Oops - either the source or destination server is missing an ipv4 address - action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// Lets get the login user for the source server.
		$source_ssh_user = WPCD_SERVER()->get_root_user_name( $source_id );
		if ( empty( $source_ssh_user ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Oops - unable to get the login user name for the source server - action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// Lets get the login user for the destination server.
		$destination_ssh_user = WPCD_SERVER()->get_root_user_name( $destination_id );
		if ( empty( $destination_ssh_user ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Oops - unable to get the login user name for the destination server - action %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $destination_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $destination_instance );
			return new \WP_Error( $msg );
		}

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
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $source_instance );
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
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $destination_instance );
			return new \WP_Error( $msg );
		} else {
			// Command successful - update some metas on the app record to make sure that we have data to use later.
			update_post_meta( $source_app_id, 'wpcd_wpapp_site_sync_destination_ipv4_temp', $ipv4_destination );
			update_post_meta( $source_app_id, 'wpcd_wpapp_site_sync_destination_id_temp', $destination_id );
			update_post_meta( $source_app_id, 'wpcd_wpapp_site_sync_domain_temp', $domain );
		}

		/**
		 * At this point, both origin and destination servers are configured.
		 */

		// Reset the action var to the original passed in value.
		$action      = $original_action;
		$bash_action = 'site-sync-mt-version';

		// Add the template domain to the args array.
		$args['mt_template_domain'] = $domain;

		// Setup unique command name.
		if ( empty( $action ) ) {
			$msg = __( 'The $action variable is empty - returning false from site-sync routine.', 'wpcd' );
			do_action( 'wpcd_log_error', sprintf( '%s: %s', $msg, print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $source_instance );
			return false;
		}

		$command                    = sprintf( '%s---%s---%d', $action, $domain, time() );
		$source_instance['command'] = $command;
		$source_instance['app_id']  = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$source_instance,
			'site_sync.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $bash_action,
					'domain'  => $domain,
				)
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* translators: %s is replaced with the internal action name. */
			$msg = sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action );
			do_action( 'wpcd_log_error', sprintf( "$msg: %s", print_r( $args, true ) ), 'trace', __FILE__, __LINE__, $source_instance, false );
			do_action( "wpcd_{$this->get_app_name()}_mt_push_version_to_server_failed", $id, $action, $msg, $source_instance );
			return new \WP_Error( $msg );
		}

		// We might need to add an item to the PENDING TASKS LOG.
		// @TODO: Right now there is no use-case for this so this is all speculative.
		if ( isset( $in_args['pending_tasks_type'] ) ) {
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $destination_id, $in_args['pending_tasks_type'], $command, $args, 'not-ready', $id, __( 'Waiting For Product Template Versions Copy To Complete.', 'wpcd' ) );
		}

		// Stamp some data we'll need later (in the command_completed function) onto the app records.
		update_post_meta( $id, 'wpapp_temp_mt_versions_push_server_destination_id', $destination_id );

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $source_instance, $action );

		return $return;

	}

	/**
	 * Multitenant - set product name
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function mt_set_product_name( $action, $id, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Bail if product name is empty.
		$new_product_name = sanitize_text_field( $args['mt_product_name'] );
		if ( empty( $new_product_name ) ) {
			$message = __( 'The product name must be provided.', 'wpcd' );
			return new \WP_Error( $message );
		}

		// Set the product name.
		$this->set_product_name( $id, $new_product_name );

		$result = array( 'refresh' => 'yes' );

		return $result;
	}

	/**
	 * Multitenant - set template flag
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function mt_set_template_flag( $action, $id, $in_args = array() ) {

		// We're not going to examine the incoming args - we're just going to flip the stored meta value for the flag.
		// i.e.: if the stored meta is false, we'll flip to true and vice-versa.
		$current_flag = $this->wpcd_is_template_site( $id );

		if ( ! empty( $current_flag ) ) {
			$new_flag = false;
		} else {
			$new_flag = true;
		}

		// Set the flag.
		$this->wpcd_set_template_flag( $id, $new_flag );

		$result = array( 'refresh' => 'yes' );

		return $result;
	}

	/**
	 * Multitenant - set default version meta.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function mt_set_default_version( $action, $id, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Bail if default version is empty.
		$new_default_version = sanitize_text_field( $args['mt_default_version'] );
		if ( empty( $new_default_version ) ) {
			$message = __( 'The default version must be provided.', 'wpcd' );
			return new \WP_Error( $message );
		}

		// Set the version.
		$this->set_mt_default_version( $id, $new_default_version );

		$result = array( 'refresh' => 'yes' );

		return $result;
	}

	/**
	 * Multitenant - Upgrade Tenants
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function mt_upgrade_tenants( $action, $id, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Bail if target version is empty.
		$mt_target_version = sanitize_text_field( $args['mt_version'] );
		if ( empty( $mt_target_version ) ) {
			$message = __( 'The version that tenants should be upgrade to is required.', 'wpcd' );
			return new \WP_Error( $message );
		}

		// Depending on the upgrade action, certain fields are required.
		// Make sure they're available.
		switch ( $action ) {
			case 'mt-upgrade-all-tenants':
				// No validation required here.
				break;
			case 'mt-upgrade-tenants-selected-versions':
				$mt_existing_version = sanitize_text_field( $args['mt_existing_version'] );
				if ( empty( $mt_existing_version ) ) {
					$message = __( 'The version that tenants should be upgrade FROM is required.', 'wpcd' );
					return new \WP_Error( $message );
				}
				break;
			case 'mt-upgrade-tenants-selected-app-group':
				$mt_app_group = sanitize_text_field( $args['mt_app_group'] );
				if ( empty( $mt_app_group ) ) {
					$message = __( 'The APP GROUP is required for this tenant upgrade request.', 'wpcd' );
					return new \WP_Error( $message );
				}
				break;
		}

		// Depending on the action we need a different set of args to wp_query.
		switch ( $action ) {
			case 'mt-upgrade-all-tenants':
				$query_args = array(
					'post_type'   => 'wpcd_app',
					'post_status' => 'private',
					'numberposts' => -1,
					'meta_query'  => array(
						'relation' => 'AND',
						array(
							'key'   => 'wpcd_app_mt_parent',
							'value' => $id,
						),
						array(
							'key'   => 'wpcd_app_mt_site_type',
							'value' => 'mt_tenant',
						),
					),
				);
				break;
			case 'mt-upgrade-tenants-selected-versions':
				$query_args = array(
					'post_type'   => 'wpcd_app',
					'post_status' => 'private',
					'numberposts' => -1,
					'meta_query'  => array(
						'relation' => 'AND',
						array(
							'key'   => 'wpcd_app_mt_parent',
							'value' => $id,
						),
						array(
							'key'   => 'wpcd_app_mt_site_type',
							'value' => 'mt_tenant',
						),
						array(
							'key'   => 'wpcd_app_mt_version',
							'value' => $args['mt_existing_version'],
						),

					),
				);
				break;
			case 'mt-upgrade-tenants-selected-app-group':
				$query_args = array(
					'post_type'   => 'wpcd_app',
					'post_status' => 'private',
					'numberposts' => -1,
					'meta_query'  => array(
						'relation' => 'AND',
						array(
							'key'   => 'wpcd_app_mt_parent',
							'value' => $id,
						),
						array(
							'key'   => 'wpcd_app_mt_site_type',
							'value' => 'mt_tenant',
						),
					),
					'tax_query'   => array(
						array(
							'taxonomy' => 'wpcd_app_group',
							'field'    => 'term_id',
							'terms'    => array( $args['mt_app_group'] ),
						),
					),
				);

				break;
		}

		// Get posts.
		$posts = get_posts( $query_args );

		// Loop through posts and add to pending tasks.
		if ( $posts ) {

			// Id for this batch.
			$batch = wpcd_generate_uuid();

			// Loop through posts and add to pending tasks.
			foreach ( $posts as $post ) {
				$args_for_pending_tasks['mt_version']          = $args['mt_version']; // required by the bash script.
				$args_for_pending_tasks['mt_product_template'] = $id; // required by the bash script.
				$args_for_pending_tasks['action_hook']         = 'wpcd_pending_log_mt_apply_version';
				$task_key                                      = $this->get_domain_name( $post->ID );
				WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $post->ID, 'mt-upgrade-tenant', $task_key, $args_for_pending_tasks, 'ready', $batch, __( 'Apply New Version To Tenant.', 'wpcd' ) );
			}

			// Save last batch id.
			$this->set_mt_last_upgrade_tenant_batch_id( $id, $batch );

		}

		/* Translators: %s is the count of posts/tenants scheduled to get a new version. */
		$result = new \WP_Error( sprintf( __( '%s tenants have been scheduled to get this new version.', 'wpcd' ), count( $posts ) ) );

		return $result;
	}



	/**
	 * Convert a site or apply a version - triggered via a pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_pending_log_mt_apply_version
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site on which this action apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function wpcd_pending_log_mt_apply_version( $task_id, $site_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		// Add a postmeta to the site we can use later.
		update_post_meta( $site_id, 'wpapp_pending_log_mt_apply_version', $task_id );

		do_action( 'wpcd_wordpress-app_do_mt_apply_version', $site_id, $args );

	}

	/**
	 * Trigger site conversion from an action hook.
	 *
	 * Note that applying a version is the same as converting an existing site.
	 * The difference is that conversion is supposed to be done on a site that
	 * is not already a tenant while "apply" is going to re-run the process
	 * on a site that is already a tenant - usually to upgrade to a new version.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_do_mt_apply_version | wpcd_wordpress-app_do_mt_apply_version
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the change domain function needs.
	 */
	public function do_mt_apply_version( $id, $args ) {
		$this->mt_convert_site( 'mt-convert-site', $id, $args );
	}

	/**
	 * Handle the situation where a site conversion / apply version has failed
	 * before the bash script can be called.
	 *
	 * Primarily, we'll be updating the pending log record as failed.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_mt_site_conversion_failed_early | wpcd_wordpress-app_mt_site_conversion_failed_early
	 *
	 * @param int    $id     Post ID of the site.
	 * @param int    $action String indicating the action name.
	 * @param string $message Failure message if any.
	 * @param array  $args       All args that were passed in to the mt-convert-site action.  Sometimes this can be an empty array.
	 */
	public function handle_mt_site_conversion_failed_early( $id, $action, $message, $args ) {

		$site_post = get_post( $id );

		// Bail if not a post object.
		if ( ! $site_post || is_wp_error( $site_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_app_type( $id ) ) {
			return;
		}

		// This only matters if we doing a site conversion or upgrade.  If not, then bail.
		if ( 'mt-convert-site' !== $action ) {
			return;
		}

		if ( 'wpcd_app' === get_post_type( $id ) ) {

			// Was this action triggered by a pending task?
			$task_id = get_post_meta( $id, 'wpapp_pending_log_mt_apply_version', true );

			// If this was triggered by a pending log task update the task as failed.
			if ( ! empty( $task_id ) ) {
				// Mark the task as failed.
				WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, false, 'failed', false, false, false, $message );
			}
		}

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
			return array_merge( $fields, $this->get_disabled_header_field( $this->get_tab_slug() ) );
		}

		/* What type of web server are we running? */
		$webserver_type = $this->get_web_server_type( $id );

		// Get HTTP2 status since we cannot clone a site with HTTP2 turned on.
		$http2_status = $this->http2_status( $id );
		if ( 'on' === $http2_status && 'nginx' === $webserver_type ) {
			$desc = __( 'You cannot create a new version of this site at this time because HTTP2 is enabled. Please disable it before attempting this operation.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'Multi-tenant', 'wpcd' ),
				'tab'  => $this->get_tab_slug(),
				'type' => 'heading',
				'desc' => $desc,
			);

			return $fields;
		}

		// If GIT is not installed on the server, get out - nothing can be done.
		// However, going to allow for now since certain operations don't require git.
		/*
		$server_id = $this->get_server_id_by_app_id( $id );
		if ( true !== $this->get_git_status( $id ) ) {

			$desc = __( 'Git is not enabled on the server where this site is located. Multi-tenant operations cannot be performed on this site.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'Multi-tenant', 'wpcd' ),
				'tab'  => $this->get_tab_slug(),
				'type' => 'heading',
				'desc' => $desc,
			);

			return $fields;

		}
		*/

		// What type of site is this?  (See the get_mt_site_type function the multi-tenant-app.php traits file for a list of valid types).
		$site_type = $this->get_mt_site_type( $id );

		// Is the site a GIT site?
		if ( 'template' === $site_type ) {
			if ( true !== $this->get_git_status( $id ) ) {

				$desc = __( 'Git is not enabled for this template - multi-tenant operations cannot be performed on this site.', 'wpcd' );

				$fields[] = array(
					'name' => __( 'Multi-tenant', 'wpcd' ),
					'tab'  => $this->get_tab_slug(),
					'type' => 'heading',
					'desc' => $desc,
				);

				return $fields;

			}
		}

		// Fields shown to standard sites.
		if ( 'standard' === $site_type ) {
			$site_conversion_fields = $this->get_site_conversion_fields( $id );
			$fields                 = array_merge( $fields, $site_conversion_fields );
		}

		// Fields shown to template sites.
		if ( true === $this->wpcd_is_template_site( $id ) ) {
			$create_new_version_fields      = $this->get_create_new_version_fields( $id );
			$production_version_fields      = $this->get_production_version_fields( $id );
			$version_fields                 = $this->get_fields_for_version_list( $id );
			$product_name_fields            = $this->get_product_name_fields( $id );
			$push_versions_to_server_fields = $this->get_push_versions_to_server_fields( $id );
			$upgrade_tenant_fields          = $this->get_upgrade_tenant_fields( $id );
			$fields                         = array_merge( $fields, $create_new_version_fields, $production_version_fields, $version_fields, $push_versions_to_server_fields, $upgrade_tenant_fields, $product_name_fields );
		}

		// Fields shown to template sites and standard sites.
		if ( in_array( $site_type, array( 'standard', 'template' ), true ) ) {
			$template_flag_fields = $this->get_template_flag_fields( $id );
			$fields               = array_merge( $fields, $template_flag_fields );
		}

		// Fields shown to tenants .
		if ( in_array( $site_type, array( 'mt_tenant' ), true ) ) {
			$site_conversion_fields = $this->get_site_conversion_fields( $id );
			$fields                 = array_merge( $fields, $site_conversion_fields );
		}

		// Fields shown to other types (for now).
		if ( in_array( $site_type, array( 'mt_version', 'mt_version_clone', 'mt_template_clone' ), true ) ) {
			$selected_site_type_fields = $this->get_selected_site_type_fields( $id );
			$fields                    = array_merge( $fields, $selected_site_type_fields );
		}

		return $fields;

	}

	/**
	 * Gets the fields to be shown on the 'create new version' section.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_create_new_version_fields( $id ) {

		/* What type of web server are we running? */
		$webserver_type = $this->get_web_server_type( $id );

		// Header description.
		$desc  = __( 'Create a new version of this product template.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'After a new version is created you can then push it to one or more existing tenants, set it as the default for new WooCommerce sales or create new WooCommerce products.', 'wpcd' );

		$default_domain = WPCD_DNS()->get_full_temp_domain();

		$fields[] = array(
			'name' => __( 'Create New Version', 'wpcd' ),
			'tab'  => $this->get_tab_slug(),
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'        => __( 'Domain For This Version', 'wpcd' ),
			'id'          => 'wpcd_app_mt_new_version_domain',
			'tab'         => $this->get_tab_slug(),
			'type'        => 'text',
			'save_field'  => false,
			'attributes'  => array(
				'maxlength'      => '32',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_new_domain',
			),
			'size'        => 90,
			'placeholder' => __( 'Domain without www or http - e.g: mydomain.com.', 'wpcd' ),
			'desc'        => __( 'Each new version gets its own subdomain, e.g: v112.yourdomain.com.', 'wpcd' ),
			'std'         => $default_domain,
		);
		$fields[] = array(
			'name'        => __( 'New version', 'wpcd' ),
			'id'          => 'wpcd_app_mt_new_version',
			'tab'         => $this->get_tab_slug(),
			'type'        => 'text',
			'save_field'  => false,
			'attributes'  => array(
				'maxlength'      => '32',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_new_version',
			),
			'size'        => 45,
			'placeholder' => __( 'v1.2.3', 'wpcd' ),
		);
		$fields[] = array(
			'name'        => __( 'New version Description', 'wpcd' ),
			'id'          => 'wpcd_app_mt_new_version_desc',
			'tab'         => $this->get_tab_slug(),
			'type'        => 'text',
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_new_version_desc',
			),
			'placeholder' => __( 'Enter a short description for this new version (e.g: Added kadence theme to template)', 'wpcd' ),
		);

		$clone_desc = '';
		if ( 'yes' === $this->is_remote_db( $id ) ) {
			$clone_desc .= '<b>' . __( 'Warning: This site appears to be using a remote database server.  The server on which this site resides should have a local database server since the database server will be switched to localhost when making a new version of this product template.', 'wpcd' ) . '</b>';
		}
		$fields[] = array(
			'id'         => 'wpcd_app_mt_new_version_action',
			'name'       => '',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'button',
			'std'        => __( 'Create New Version', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-create-version',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_mt_new_version_domain', '#wpcd_app_mt_new_version', '#wpcd_app_mt_new_version_desc' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to create a new version of this template product?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to create a new version of this template product on the specified domain...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Gets the fields to be shown in the 'product name' section of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_product_name_fields( $id ) {

		// Header description.
		$desc  = __( 'Set a product name for this template.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'The product name will be shown in places like WooCommerce and makes it easier to remember what a template site does.', 'wpcd' );

		$current_product_name = $this->get_product_name( $id );

		$fields[] = array(
			'name' => __( 'Product Name', 'wpcd' ),
			'tab'  => $this->get_tab_slug(),
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'       => __( 'Product Name', 'wpcd' ),
			'id'         => 'wpcd_app_mt_product_name',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'text',
			'save_field' => false,
			'attributes' => array(
				'maxlength'      => '32',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_product_name',
			),
			'std'        => $current_product_name,
			'tooltip'    => array(
				'content'  => __( 'The product name set here is a synonym for the template domain and can be used to reference this template in WooCommerce products and other locations.', 'wpcd ' ),
				'position' => 'right',
			),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_mt_product_name_action',
			'name'       => '',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'button',
			'std'        => __( 'Set Product Name', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-set-product-name',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_mt_product_name' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to set a new product name for this template product?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Gets the fields to be shown in the 'set production' section of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_production_version_fields( $id ) {

		// Header description.
		$desc = __( 'Which version should be the default for new sites?', 'wpcd' );

		$current_default_version = $this->get_mt_default_version( $id );

		$fields[] = array(
			'name' => __( 'Production Version', 'wpcd' ),
			'tab'  => $this->get_tab_slug(),
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'       => __( 'Choose Default Version', 'wpcd' ),
			'id'         => 'wpcd_app_mt_default_version',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'select',
			'options'    => $this->get_mt_versions( $id ),
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_default_version',
			),
			'std'        => $current_default_version,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_mt_default_version_action',
			'name'       => '',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'button',
			'std'        => __( 'Set Default Version', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-set-default-version',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_mt_default_version' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to reset the default version for this template product?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Gets the fields to be shown in the 'Template Flag' section of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_template_flag_fields( $id ) {

		// Header description.
		$header_desc = '';

		// Is the site already a template site?
		$current_template_flag = $this->wpcd_is_template_site( $id );

		// Change the name of the toggle depending on whether the site is already a template site.
		if ( true === $current_template_flag ) {
			$name = __( 'This site is a product template - click to remove the template designation', 'wpcd' );
		} else {
			$name = __( 'Make This Site A Product Template', 'wpcd' );
		}

		// The header field.
		$fields[] = array(
			'name' => __( 'Template Flag', 'wpcd' ),
			'tab'  => $this->get_tab_slug(),
			'type' => 'heading',
			'desc' => $header_desc,
		);

		// The toggle field.
		$fields[] = array(
			'name'       => $name,
			'id'         => 'wpcd_app_mt_template_flag',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'switch',
			'on_label'   => __( 'Enabled', 'wpcd' ),
			'off_label'  => __( 'Disabled', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-set-template-flag',
				// the id.
				'data-wpcd-id'                  => $id,
				// the key of the field (the key goes in the request).
				'data-wpcd-name'                => 'mt_template_flag',
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to update the template status for this site?', 'wpcd' ),
			),
			'std'        => $current_template_flag,
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Get fields that displays the list of current versions (tags) we know about.
	 *
	 * @param int $id The post id of the site we're working with.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_version_list( $id ) {

		$header_msg = __( 'These are the versions for this product template.', 'wpcd' );

		$return  = '<div class="wpcd_mt_version_list">';
		$return .= '<div class="wpcd_mt_version_list_inner_wrap">';

		$tags = $this->get_mt_version_history( $id );

		foreach ( array_reverse( $tags ) as $tag => $tag_array ) {

			$domain         = ! empty( $tag_array['domain'] ) ? $tag_array['domain'] : __( 'Error! No Associated Domain!', 'wpcd' );
			$domain_post_id = ! empty( $tag_array['app_id'] ) ? $tag_array['app_id'] : '';
			$desc           = ! empty( $tag_array['desc'] ) ? $tag_array['desc'] : __( 'No description available', 'wpcd' );
			$create_date    = ! empty( $tag_array['reporting_time_human_utc'] ) ? $tag_array['reporting_time_human_utc'] : __( '1901-01-01 (unknown)', 'wpcd' );

			$return .= '<div class="wpcd_mt_version_value">';
			$return .= '<span class="wpcd_mt_version_value_inline">' . $tag . '</span>';
			$return .= '<br />' . $domain;
			if ( ! empty( $domain_post_id ) ) {
				$editlink = ( is_admin() ? get_edit_post_link( $domain_post_id ) : get_permalink( $domain_post_id ) );
				$return  .= ' ' . sprintf( '<a href=%s>' . __( 'Edit', 'wpcd' ) . '</a>', $editlink );
			}
			$return .= '<br />' . $desc;
			$return .= '<br />' . $create_date . ' ' . __( 'UTC', 'wpcd' );

			if ( ! empty( $tag_array['destination_servers'] ) ) {
				$return .= '<br />' . __( 'Copies also located on servers:', 'wpcd' );
				foreach ( $tag_array['destination_servers'] as $destination_server_id ) {
					$server_name = WPCD_SERVER()->get_server_name( $destination_server_id );
					$editlink    = ( is_admin() ? get_edit_post_link( $destination_server_id ) : get_permalink( $destination_server_id ) );
					$return     .= '<br />' . sprintf( '<a href=%s>' . $server_name . '</a>', $editlink );
				}
			}

			$return .= '</div>';

		}
		$return .= '</div>';
		$return .= '</div>';

		$actions[] = array(
			'id'   => 'wpcd_app_mt_list_tags',
			'name' => __( 'Existing Versions', 'wpcd' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'wpcd_app_mt_view_tags',
			'name'       => '',
			'std'        => $return,
			'type'       => 'custom_html',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Gets the fields to be shown in the 'push versions to server' section of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_push_versions_to_server_fields( $id ) {

		// Header description.
		$desc = __( 'Push all versions to a server', 'wpcd' );

		$destination_servers = $this->get_list_of_destination_servers( $id );

		$fields[] = array(
			'name' => __( 'Push Versions To Remote Server', 'wpcd' ),
			'tab'  => $this->get_tab_slug(),
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'       => __( 'Choose Server', 'wpcd' ),
			'id'         => 'wpcd_app_mt_destination_server',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'post',
			'post_type'  => 'wpcd_app_server',
			'query_args' => array(
				/* @TODO: need to restrict this list to only server posts of type wp server and drop existing destination and source servers */
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post__in'       => empty( $destination_servers ) ? array( -1 ) : $destination_servers,
			),
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'site_sync_destination',
			),
			'tooltip'    => array(
				'content'  => __( 'All versions of this product template will be pushed to the selected server. Please make sure that server has enough space to accomodate all the files!', 'wpcd' ),
				'position' => 'right',
			),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_mt_push_to_server_action',
			'name'       => '',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'button',
			'std'        => __( 'Push all versions to the specified server', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-push-versions-to-server',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_mt_destination_server' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to push all versions of this product template to the selected remote server?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to push all versions of this product template to the specified remote server...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Gets the fields to be shown in the 'upgrade tenants' section of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_upgrade_tenant_fields( $id ) {

		// Header description.
		$desc = '';

		$current_default_version = $this->get_mt_default_version( $id );

		$fields[] = array(
			'name' => __( 'Upgrade Tenants', 'wpcd' ),
			'tab'  => $this->get_tab_slug(),
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'       => __( 'Upgrade Tenants To This Version', 'wpcd' ),
			'id'         => 'wpcd_app_mt_version',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'select',
			'options'    => $this->get_mt_versions( $id ),
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_version',
			),
			'std'        => $current_default_version,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_mt_upgrade_all_tenants',
			'name'       => '',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'button',
			'std'        => __( 'Upgrade All Tenants', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-upgrade-all-tenants',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_mt_version' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to upgrade all tenants to the selected version?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		$fields[] = array(
			'name'       => __( 'Upgrade Only Tenants Who Have This Version', 'wpcd' ),
			'id'         => 'wpcd_app_mt_existing_version',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'select',
			'options'    => $this->get_mt_versions( $id ),
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_existing_version',
			),
			'std'        => $current_default_version,
			'columns'    => 6,
		);

		$fields[] = array(
			'name'       => __( 'Upgrade Only Tenants With This App Group', 'wpcd' ),
			'id'         => 'wpcd_app_mt_app_group',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'taxonomy',
			'field_type' => 'select',
			'taxonomy'   => 'wpcd_app_group',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_app_group',
			),
			'std'        => $current_default_version,
			'columns'    => 6,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_mt_upgrade_tenants_with_selected_versions',
			'name'       => '',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'button',
			'std'        => __( 'Upgrade', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-upgrade-tenants-selected-versions',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_mt_existing_version', '#wpcd_app_mt_version' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to upgrade some tenants to the selected version?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 6,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_mt_upgrade_tenants_with_selected_app_group',
			'name'       => '',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'button',
			'std'        => __( 'Upgrade', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-upgrade-tenants-selected-app-group',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_mt_app_group', '#wpcd_app_mt_version' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to upgrade tenants with this APP GROUP to the selected version?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 6,
		);

		return $fields;

	}

	/**
	 * Gets a list of appropriate destination servers.
	 *
	 * @TODO: This logic is duplicated in the site-sync.php file/tab
	 * in the get_fields() function.  We should consolidate and
	 * make a central function.
	 *
	 * @param int $id The id of the current site we're working with.
	 *
	 * @return array Array of servers.
	 */
	public function get_list_of_destination_servers( $id ) {

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

		return $post__in;
	}

	/**
	 * Gets the fields to be shown in the 'convert a site' section of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_site_conversion_fields( $id ) {

		// What type of site is this?  (See the get_mt_site_type function the multi-tenant-app.php traits file for a list of valid types).
		$site_type = $this->get_mt_site_type( $id );

		if ( 'mt_tenant' === $site_type ) {
			// Header description for site that has already been converted.
			$desc  = __( 'This operation will not impact your database - it will only apply the files from the product template to this site.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'This means that it is possible your database will be out of sync with the product template after this operation - unless the template includes a custom plugin or script to update the database as well.', 'wpcd' );

			$name        = __( 'Upgrade or Downgrade', 'wpcd' );
			$button_text = __( 'Apply New Version', 'wpcd' );
			$button_desc = '';
		} else {
			// Header description for site that has not been converted yet.
			$desc  = __( 'This operation will not impact your database - it will only apply the files from the product template to this site.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'This means that it is possible your database will be out of sync with the product template after this operation - unless the template includes a custom plugin or script to update the database as well.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'If you would like the conversion to impact the database, use the COPY TO EXISTING SITE function first to copy the database and files to this site.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'Then you can apply the conversion function located here.', 'wpcd' );

			$name        = __( 'Convert Site To A Tenant', 'wpcd' );
			$button_text = __( 'Convert', 'wpcd' );
			$button_desc = __( 'Add this site as a tenant for the above selected product template.', 'wpcd' );
		}

		$current_version = $this->get_mt_version( $id );
		$curent_parent   = $this->get_mt_parent( $id );

		$fields[] = array(
			'name' => $name,
			'tab'  => $this->get_tab_slug(),
			'type' => 'heading',
			'desc' => $desc,
		);

		$fields[] = array(
			'name'        => __( 'Choose Product Template', 'wpcd' ),
			'id'          => 'wpcd_app_mt_product_template',
			'tab'         => $this->get_tab_slug(),
			'type'        => 'post',
			'post_type'   => 'wpcd_app',
			'query_args'  => array(
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'meta_key'       => 'wpcd_is_template_site',
				'meta_value'     => '1',
			),
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_product_template',
			),
			'std'         => $curent_parent,
			'placeholder' => __( 'Choose a product template...', 'wpcd' ),
		);

		/*
		$fields[] = array(
			'name'       => __( 'Choose Version', 'wpcd' ),
			'id'         => 'wpcd_app_mt_version',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'select',
			'options'    => $this->get_mt_versions( $id ),
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_version',
			),
			'std'        => $current_version,
		);
		*/

		$fields[] = array(
			'name'       => __( 'Enter Version', 'wpcd' ),
			'id'         => 'wpcd_app_mt_version',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'text',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'mt_version',
			),
			'std'        => $current_version,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_mt_default_version_action',
			'name'       => '',
			'tab'        => $this->get_tab_slug(),
			'type'       => 'button',
			'std'        => $button_text,
			'desc'       => $button_desc,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'mt-convert-site',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_mt_product_template', '#wpcd_app_mt_version' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to add this site as a tenant for the selected product template?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to convert this site to a tenant...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Gets the fields to be shown for certain site types.
	 *  - mt_version
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_selected_site_type_fields( $id ) {

		// What type of site is this?  (See the get_mt_site_type function the multi-tenant-app.php traits file for a list of valid types).
		$site_type = $this->get_mt_site_type( $id );

		if ( 'mt_version' === $site_type ) {
			$desc = __( 'There are no options available for sites that are versions of a product template..', 'wpcd' );
		}

		if ( 'mt_version_clone' === $site_type ) {
			$desc = __( 'There are no options available for sites that are clones of a version of a product template.', 'wpcd' );
		}

		if ( 'mt_template_clone' === $site_type ) {
			$desc = __( 'There are no options available for sites that are clones of a template.', 'wpcd' );
		}

			$fields[] = array(
				'name' => __( 'Multi-tenant', 'wpcd' ),
				'tab'  => $this->get_tab_slug(),
				'type' => 'heading',
				'desc' => $desc,
			);

			return $fields;
	}

	/**
	 * Takes a string and removes everything except alphanumeric
	 * characters, dashes and periods.
	 *
	 * @param string $tag The tag to clean.
	 */
	public function sanitize_tag( $tag ) {
		return wpcd_clean_alpha_numeric_dashes_periods_underscores( $tag );
	}

	/**
	 * Add to an array of versions (tags) created.
	 *
	 * @param int    $id Post id of site we're working with.
	 * @param string $new_tag The new tag (version) created or added.
	 * @param string $new_tag_desc Description of the new tag (version).
	 * @param string $domain The domain on which this new version was created.
	 * @param int    $domain_id The post id of the domain ($domain above).
	 */
	public function mt_add_mt_version_history( $id, $new_tag, $new_tag_desc, $domain, $domain_id ) {

		// Get current tag list.
		$tags = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_app_mt_version_history', true ) );

		// Make sure we have something in the tags array otherwise create a blank one.
		if ( empty( $tags ) ) {
			$tags = array();
		}
		if ( ! is_array( $tags ) ) {
			$tags = array();
		}

		// Add to array.
		if ( empty( $tags[ $new_tag ] ) ) {
			// Tag does not yet exist in the array so add it.
			$tags[ $new_tag ] = array(
				'reporting_time'           => time(),
				'reporting_time_human'     => date( 'Y-m-d H:i:s', time() ),
				'reporting_time_human_utc' => gmdate( 'Y-m-d H:i:s' ),
				'desc'                     => $new_tag_desc,
				'domain'                   => $domain,
				'app_id'                   => $domain_id,
			);
		} else {
			// Perhaps update the time here? Or add other history?  We really shouldn't get here though.
			$tags[ $new_tag ]['last_pull_reporting_time']           = time();
			$tags[ $new_tag ]['last_pull_reporting_time_human']     = date( 'Y-m-d H:i:s', time() );
			$tags[ $new_tag ]['last_pull_reporting_time_human_utc'] = gmdate( 'Y-m-d H:i:s' );
		}

		// Push back to database.
		return update_post_meta( $id, 'wpcd_app_mt_version_history', $tags );

	}

	/**
	 * Add a destination server id to the array of versions (tags).
	 *
	 * @param int $app_id Post id of site we're working with.
	 * @param int $destination_server_id Post ID of the destination server.
	 */
	public function mt_add_destination_server_id_to_versions( $app_id, $destination_server_id ) {

		// Get current tag list.
		$tags = wpcd_maybe_unserialize( get_post_meta( $app_id, 'wpcd_app_mt_version_history', true ) );

		// Make sure we have something in the tags array otherwise create a blank one.
		if ( empty( $tags ) ) {
			$tags = array();
		}
		if ( ! is_array( $tags ) ) {
			$tags = array();
		}

		// Loop through each tag/version and add the destination_server_id value to an array of ids.
		foreach ( $tags as $tag => $tag_details ) {
			if ( ! empty( $tags[ $tag ]['destination_servers'] ) ) {
				// Add to existing array.
				$tags[ $tag ]['destination_servers'] = array_merge( $tags[ $tag ]['destination_servers'], array( $destination_server_id ) );
			} else {
				$tags[ $tag ]['destination_servers'] = array( $destination_server_id );
			}
		}

		// Push back to database.
		return update_post_meta( $app_id, 'wpcd_app_mt_version_history', $tags );

	}

	/**
	 * Return the list of MT versions for this site as a 2D array.
	 *
	 * @param int $id Post id of site we're working with.
	 *
	 * @return array.
	 */
	public function get_mt_versions( $id ) {

		$versions = $this->get_mt_version_history( $id );

		$return_versions = array();

		foreach ( array_reverse( $versions ) as $version => $version_array ) {

			$return_versions[ $version ] = ! empty( $version_array['desc'] ) ? $version . ' - ' . $version_array['desc'] : $version . ' - ' . __( 'No description available', 'wpcd' );

		}

		return $return_versions;

	}

	/**
	 * Return the mt version history meta value.
	 *
	 * @param int $id Post id of site we're working with.
	 *
	 * @return array.
	 */
	public function get_mt_version_history( $id ) {

		// Get current tag list.
		$versions = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_app_mt_version_history', true ) );

		// Make sure we have something in the logs array otherwise create a blank one.
		if ( empty( $versions ) ) {
			$versions = array();
		}
		if ( ! is_array( $versions ) ) {
			$versions = array();
		}

		return $versions;

	}

	/**
	 * Returns the wpcd product name set for the template site.
	 *
	 * @param int $id The post id of the site we're working with.
	 *
	 * @return string
	 */
	public function get_product_name( $id ) {
		return get_post_meta( $id, 'wpcd_app_mt_template_product_name', true );
	}

	/**
	 * Sets the wpcd product name for the template site.
	 *
	 * @param int    $id The post id of the site we're working with.
	 * @param string $name The new product name to set for the site.
	 */
	public function set_product_name( $id, $name ) {
		update_post_meta( $id, 'wpcd_app_mt_template_product_name', $name );
	}

	/**
	 * When upgrading tenants we set a batch id for the group of tenants
	 * selected for an upgrade.  This function updates the template
	 * site with that batch.
	 *
	 * For now, it just keeps track of the last batch used.  Later we'll
	 * probably keep track of all batches.
	 *
	 * @param int    $id The post id of the template site.
	 * @param string $batch The batch uuid.
	 */
	public function set_mt_last_upgrade_tenant_batch_id( $id, $batch ) {
		return update_post_meta( $id, 'wpcd_app_mt_last_upgrade_tenant_batch_id', $batch );
	}

	/**
	 * When upgrading tenants we set a batch id for the group of tenants
	 * selected for an upgrade.  This function gets the last batch
	 * id used.
	 *
	 * @param int $id The post id of the template site.
	 *
	 * @return string
	 */
	public function get_mt_last_upgrade_tenant_batch_id( $id ) {
		return get_post_meta( $id, 'wpcd_app_mt_last_upgrade_tenant_batch_id', true );

	}


}

new WPCD_WORDPRESS_TABS_MULTITENANT_SITE();
