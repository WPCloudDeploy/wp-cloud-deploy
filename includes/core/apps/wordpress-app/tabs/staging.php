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
 * Class WPCD_WORDPRESS_TABS_CLONE_SITE
 */
class WPCD_WORDPRESS_TABS_STAGING extends WPCD_WORDPRESS_TABS {

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

		// if the command is to create a staging site, we need to do a few things...
		if ( 'create-staging-site' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'clone_site.txt' );

			if ( true == $success ) {

				// Get Webserver Type.
				$webserver_type = $this->get_web_server_type( $id );

				// get new domain from temporary meta.
				$new_domain = get_post_meta( $id, 'wpapp_domain_clone_site_target_domain', true );

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
							if ( true === $this->get_app_is_memcached_installed( $id ) ) {
								$this->set_app_memcached_installed_status( $new_app_post_id, true );
							} else {
								$this->set_app_memcached_installed_status( $new_app_post_id, false );
							}

							// Was redis enabled on the original site?  If so, the caching plugin was copied as well so add the meta here for that.
							if ( true === $this->get_app_is_redis_installed( $id ) ) {
								$this->set_app_redis_installed_status( $new_app_post_id, true );
							} else {
								$this->set_app_redis_installed_status( $new_app_post_id, false );
							}

							/*
							 *** Note: 6G/7G Flags and http auth status flags are not copied here.
							 * This is because the clone bash script creates new vhost configuration files instead
							 * of copying from the original site.
							 */

							// Update the PHP version to match the original version.
							// This only changes the metas. In the future we might want to run the script to change the PHP version.
							// In that case, look to the site-sync tab for how that should be done. It's a bit tricky.
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

							// Make sure we tag the new site as a staging site.
							update_post_meta( $new_app_post_id, 'wpapp_is_staging', 1 );

							// And tag the original site with the domain and id of the staging site.
							update_post_meta( $id, 'wpapp_staging_domain', $new_domain );
							update_post_meta( $id, 'wpapp_staging_domain_id', $new_app_post_id );

							// Copy multi-tenant related metas.
							$this->clone_mt_metas( $id, $new_app_post_id );  // Function located in traits file multi-tenant-app.php.

							// Finally, lets add a meta to indicate that this was a clone.
							update_post_meta( $new_app_post_id, 'wpapp_cloned_from', $this->get_domain_name( $id ) );
							update_post_meta( $new_app_post_id, 'wpapp_cloned_from_id', $id );

							// Wrapup - let things hook in here - primarily the multisite add-on.
							do_action( "wpcd_{$this->get_app_name()}_site_staging_new_post_completed", $new_app_post_id, $id, $name );

						}
					}
				}

				// And delete the temporary meta.
				delete_post_meta( $id, 'wpapp_domain_clone_site_target_domain' );

			}
		}

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'staging';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_staging_tab';
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
				'label' => __( 'Staging', 'wpcd' ),
				'icon'  => 'fad fa-folders',
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
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'create-staging-site', 'copy-to-live' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'create-staging-site':
					$action = 'create-staging-site'; // action is not used by the bash script right now.
					$result = $this->clone_site( $action, $id );
					break;
				case 'copy-to-live':
					$action = 'copy_to_existing_site_copy_full';
					$result = $this->copy_to_existing_site_stub( $action, $id );
					break;
			}
		}

		return $result;

	}

	/**
	 * Clone a site to another site on the same server.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function clone_site( $action, $id ) {

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Trigger an action hook to delete any existing staging site (if any).
		$staging_site_id = $this->get_companion_staging_site_id( $id );
		if ( ! empty( $staging_site_id ) ) {
			do_action( 'wpcd_app_delete_wp_site', $staging_site_id, 'remove_full' );

			// Update the metas on this site to remove the staging site info.
			update_post_meta( $id, 'wpapp_staging_domain', '' );
			update_post_meta( $id, 'wpapp_staging_domain_id', '' );
		}

		// Bail if certain things are empty...
		$new_domain = $this->get_staging_domain( $id, $args );
		if ( empty( $new_domain ) ) {
			return new \WP_Error( __( 'We were unable to obtain a staging domain name.', 'wpcd' ) );
		} else {
			$args['new_domain'] = $new_domain;
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// sanitize the fields to allow them to be used safely on the bash command line.
		$args['new_domain'] = escapeshellarg( sanitize_text_field( $args['new_domain'] ) );

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		/**
		 * If multi-tenant is active, check to see if the original site is a multi-tenant site
		 * and if so, pass that info to the bash script so that things like the
		 * openbasedirective can be updated.
		 * For now, the only site type that is affected is a tenant site ('mt_tenant').
		 * Versioned sites, version clones, template sites, template clones etc. are not stamped
		 * and will be treated as regular sites after a clone.
		 */
		if ( in_array( $this->get_mt_site_type( $id ), array( 'mt_tenant' ), true ) ) {
			$mt_version                 = $this->get_mt_version( $id );
			$mt_parent_domain_post_id   = $this->get_mt_parent( $id );
			$mt_template_domain         = $this->get_domain_name( $mt_parent_domain_post_id );
			$args['mt_template_domain'] = $mt_template_domain;
			$args['mt_version']         = $mt_version;
		} else {
			$args['mt_template_domain'] = '';
			$args['mt_version']         = '';
		}

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
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'clone_site.txt',
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
			return new \WP_Error( sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action ) );
		}

		// Stamp some data we'll need later (in the command_completed function) onto the app records.
		update_post_meta( $id, 'wpapp_domain_clone_site_target_domain', $new_domain );

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;
	}

	/**
	 * Copy a site over an existing site on the same server.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function copy_to_existing_site_stub( $action, $id ) {
		$post_args['action']        = $action;
		$post_args['target_domain'] = $this->get_live_domain_for_staging_site( $id );
		return $this->copy_to_existing_site( $action, $id, $post_args );
	}

	/**
	 * Copy a site over an existing site on the same server.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $post_args An array of arguments for the bash script.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function copy_to_existing_site( $action, $id, $post_args = array() ) {

		// Get data from the POST request.
		if ( empty( $post_args ) ) {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $post_args;
		}

		// Bail if certain things are empty...
		if ( empty( $args['target_domain'] ) ) {
			return new \WP_Error( __( 'The target domain must be provided', 'wpcd' ) );
		} else {
			$target_domain         = strtolower( sanitize_text_field( $args['target_domain'] ) );
			$target_domain         = wpcd_clean_domain( $target_domain );
			$args['target_domain'] = $target_domain;
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Add some items to the args array.
		$args['source_domain'] = $domain;
		$args['wpconfig']      = 'N';

		// sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'copy_site_to_existing_site.txt',
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
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action ) );
		}

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;
	}

	/**
	 * Gets the domain name for a new staging site.
	 *
	 * @param int   $id The post id of the current site we're looking to push to staging.
	 * @param array $args A array of already sanitized _POST args.
	 *
	 * @return string A string containing a domain name or blank if we can't get one.
	 */
	public function get_staging_domain( $id, $args ) {

		$domain = '';
		if ( ! empty( $args['new_domain'] ) ) {

			$new_domain = strtolower( sanitize_text_field( $args['new_domain'] ) );
			$new_domain = wpcd_clean_domain( $new_domain );
			$domain     = $new_domain;

		}

		/**
		 * If we still need a domain name check to see if the the site already has a staging domain name.
		 * It will already have an associated domain name if the user has pushed to staging at least once
		 * and the staging site was not deleted.
		 * NOTE: Sometimes the staging site will be deleted before this function is called in which case
		 * this block of code is likely to also result in an empty domain name.
		 */
		if ( empty( $domain ) ) {
			$domain = get_post_meta( $id, 'wpapp_staging_domain', true );
		}

		/**
		 * If we still need a domain name, get it from our default DNS provider.
		 */
		if ( empty( $domain ) ) {
			$domain = WPCD_DNS()->get_full_temp_domain();
		}

		return $domain;

	}

	/**
	 * Gets the fields to be shown.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
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
			return array_merge( $fields, $this->get_disabled_header_field( 'staging' ) );
		}

		// If the number of sites allowed on the server have been exceeded we will not show the staging buttons.
		// BUT, if a staging site already exists, we have to show the buttons so that the site can be pushed back and forth between staging and production.
		if ( ! $this->is_staging_site( $id ) && empty( $this->get_companion_staging_site_domain( $id ) ) ) {
			if ( $this->get_has_server_exceeded_sites_allowed( $id ) && ! wpcd_is_admin() ) {
				return array_merge( $fields, $this->get_max_sites_exceeded_header_field( 'staging' ) );
			}
		}

		/* What type of web server are we running? */
		$webserver_type = $this->get_web_server_type( $id );

		// Get HTTP2 status since we cannot clone a site with HTTP2 turned on.
		$http2_status = $this->http2_status( $id );
		if ( 'on' === $http2_status && 'nginx' === $webserver_type ) {
			$desc = __( 'You cannot clone this site at this time because HTTP2 is enabled. Please disable it before attempting this operation.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'Staging', 'wpcd' ),
				'tab'  => 'staging',
				'type' => 'heading',
				'desc' => $desc,
			);

			return $fields;
		}

		// Grab var to see if this is already a staging site.
		$is_staging = $this->is_staging_site( $id );

		if ( true === $is_staging ) {

			// Start new card.
			$fields[] = wpcd_start_half_card( $this->get_tab_slug() );

			// This is a staging site so only show options that allow it to be pushed back to live.
			$desc = __( 'Push this site to live.', 'wpcd' );

			// Variable that contains the domain for the live site.
			$live_domain = $this->get_live_domain_for_staging_site( $id );

			if ( ! empty( $live_domain ) ) {
				$desc .= '<br />';
				/* Translators: %s: The domain of the live site. */
				$desc .= sprintf( __( 'The live domain associated with this staging site is: %s.', 'wpcd' ), '<b>' . $live_domain . '</b>' );
			}

			$fields[] = array(
				'name' => __( 'Staging', 'wpcd' ),
				'tab'  => 'staging',
				'type' => 'heading',
				'desc' => $desc,
			);

			$fields[] = array(
				'id'         => 'wpcd_app_staging_site',
				'name'       => '',
				'tab'        => 'staging',
				'type'       => 'button',
				'std'        => __( 'Push To Live', 'wpcd' ),
				'desc'       => '',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'copy-to-live',
					// the id.
					'data-wpcd-id'                  => $id,
					// fields that contribute data for this action.
					// 'data-wpcd-fields'              => json_encode( array( '#wpcd_app_clone_site_domain_new_domain' ) ),
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to overwrite your live site?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to push this site to staging...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			// Close up prior card.
			$fields[] = wpcd_end_card( $this->get_tab_slug() );
		} else {

			// Start new card.
			$fields[] = wpcd_start_half_card( $this->get_tab_slug() );

			// We got here so ok to show fields related to cloning the site to staging.
			$desc = __( 'Make a copy of this site for development, testing and trouble-shooting.', 'wpcd' );

			// Variable that indicates if an existing staging site has already been created.
			$existing_staging_site = $this->get_companion_staging_site_domain( $id );

			if ( ! empty( $existing_staging_site ) ) {
				$desc .= '<br />';
				/* Translators: %s: The domain of the companion staging site. */
				$desc .= sprintf( __( 'A companion staging site already exists at: %s.', 'wpcd' ), '<b>' . $existing_staging_site . '</b>' );
				$desc .= '<br />';
				$desc .= __( 'If you create a new staging site, the old one will be deleted.', 'wpcd' );
			}

			$fields[] = array(
				'name' => __( 'Staging', 'wpcd' ),
				'tab'  => 'staging',
				'type' => 'heading',
				'desc' => $desc,
			);

			$staging_desc = '';
			if ( 'yes' === $this->is_remote_db( $id ) ) {
				$staging_desc .= '<b>' . __( 'Warning: This site appears to be using a remote database server.  The server on which this site resides should have a local database server since the database server will be switched to localhost for staging operations.', 'wpcd' ) . '</b>';
			}
			$fields[] = array(
				'id'         => 'wpcd_app_staging_site',
				'name'       => '',
				'tab'        => 'staging',
				'type'       => 'button',
				'std'        => (bool) $existing_staging_site ? __( 'Create a New Staging Site', 'wpcd' ) : __( 'Create Staging Site', 'wpcd' ),
				'desc'       => $staging_desc,
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'create-staging-site',
					// the id.
					'data-wpcd-id'                  => $id,
					// fields that contribute data for this action.
					// 'data-wpcd-fields'              => json_encode( array( '#wpcd_app_clone_site_domain_new_domain' ) ),
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => (bool) $existing_staging_site ? __( 'Are you sure you would like to overwrite your existing staging site?', 'wpcd' ) : __( 'Are you sure you would like to create a new staging site?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to push this site to staging...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			// Close up prior card.
			$fields[] = wpcd_end_card( $this->get_tab_slug() );

		}

		return $fields;

	}

}

new WPCD_WORDPRESS_TABS_STAGING();
