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
class WPCD_WORDPRESS_TABS_CLONE_SITE extends WPCD_WORDPRESS_TABS {

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

		// Allow the clone action to be triggered via an action hook.  Will primarily be used by the REST API and WOOCOMMERCE.
		add_action( "wpcd_{$this->get_app_name()}_do_clone_site", array( $this, 'do_clone_site_action' ), 10, 2 ); // Hook: wpcd_wordpress-app_do_clone_site.

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

		// if the command is to clone a site we need to do a few things...
		if ( 'clone-site' === $command_array[0] ) {

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

							/**
							 * Handle multi-tenant metas (which includes the standard TEMPLATE flag.)
							 */
							$this->clone_mt_metas( $id, $new_app_post_id );  // Function located in traits file multi-tenant-app.php.

							// Lets add a meta to indicate that this was a clone.
							update_post_meta( $new_app_post_id, 'wpapp_cloned_from', $this->get_domain_name( $id ) );

							// Wrapup - let things hook in here - primarily the multisite add-on and the REST API.
							do_action( "wpcd_{$this->get_app_name()}_site_clone_new_post_completed", $new_app_post_id, $id, $name );

						}
					}
				}

				// And delete the temporary meta.
				delete_post_meta( $id, 'wpapp_domain_clone_site_target_domain' );

			} else {
				// Add action hook to indicate failure...
				$message = __( 'Clone Site command failed - check the command logs for more information.', 'wpcd' );
				do_action( "wpcd_{$this->get_app_name()}_clone_site_failed", $id, $command_array[0], $message, array() );  // Keeping 4 parameters for the action hook to maintain consistency even though we have nothing for the last parameter.
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
		return 'clone-site';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_clone_site_tab';
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
				'label' => __( 'Clone Site', 'wpcd' ),
				'icon'  => 'fad fa-clone',
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
		$valid_actions = array( 'clone-site' );
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
				case 'clone-site':
					$action = 'clone-site'; // action is not used by the bash script right now.
					$result = $this->clone_site( $action, $id );
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
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function clone_site( $action, $id, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Bail if certain things are empty...
		if ( empty( $args['new_domain'] ) ) {
			$message = __( 'The new domain must be provided', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$new_domain         = strtolower( sanitize_text_field( $args['new_domain'] ) );
			$new_domain         = wpcd_clean_domain( $new_domain );
			$args['new_domain'] = $new_domain;
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Allow developers to validate the new domain and bailout if necessary.
		if ( ! apply_filters( 'wpcd_wpapp_validate_domain_on_clone', true, $args['new_domain'] ) ) {
			/* translators: %s is replaced with the internal action name. */
			$message = sprintf( __( 'The new domain has failed validation.  Please try again: %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
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
			/* translators: %s is replaced with the internal action name. */
			$message = sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_clone_site_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
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
	 * Trigger the site clone from an action hook.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_do_clone_site | wpcd_wordpress-app_do_clone_site
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the change domain function needs.
	 */
	public function do_clone_site_action( $id, $args ) {
		$this->clone_site( 'clone-site', $id, $args ); // Note that the 'clone-site' action string is not actually used by the bash script. It will, however, be used by success/failure action hooks.
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
			return array_merge( $fields, $this->get_disabled_header_field( 'clone-site' ) );
		}

		// If the number of sites allowed on the server have been exceeded, return.
		if ( $this->get_has_server_exceeded_sites_allowed( $id ) && ! wpcd_is_admin() ) {
			return array_merge( $fields, $this->get_max_sites_exceeded_header_field( 'clone-site' ) );
		}

		// Allow a third party to show a different set of fields instead.
		// This can be useful if a custom plugin determines that clone operations are not allowed.
		// For example, the WOOCOMMERCE add-on uses this to disable cloning when the number of sites a user is allowed is exceeded.
		// Full filter name: wpcd_app_wordpress-app_clone-site_get_fields.
		$over_ride_fields = apply_filters( "wpcd_app_{$this->get_app_name()}_clone-site_get_fields", array(), $fields, $id );
		if ( ! empty( $over_ride_fields ) ) {
			return array_merge( $fields, $over_ride_fields );
		}

		/* What type of web server are we running? */
		$webserver_type = $this->get_web_server_type( $id );

		// Get HTTP2 status since we cannot clone a site with HTTP2 turned on.
		$http2_status = $this->http2_status( $id );
		if ( 'on' === $http2_status && 'nginx' === $webserver_type ) {
			$desc = __( 'You cannot clone this site at this time because HTTP2 is enabled. Please disable it before attempting this operation.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'Clone Site', 'wpcd' ),
				'tab'  => 'clone-site',
				'type' => 'heading',
				'desc' => $desc,
			);

			return $fields;
		}

		// We got here so ok to show fields related to cloning the site.
		$desc  = __( 'Make a copy of this site to a new domain name.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'If you would like new SSL certificates to be issued as part of this operation please make sure you point your DNS for the new site to this server\'s IP address BEFORE you start the clone operation. ', 'wpcd' );
		$desc .= __( 'Otherwise you will need to request new certificates on the SSL tab after the operation is complete and you have updated your DNS.', 'wpcd' );

		$default_domain = WPCD_DNS()->get_full_temp_domain();

		$fields[] = array(
			'name' => __( 'Clone Site', 'wpcd' ),
			'tab'  => 'clone-site',
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'        => __( 'New Domain', 'wpcd' ),
			'id'          => 'wpcd_app_clone_site_domain_new_domain',
			'tab'         => 'clone-site',
			'type'        => 'text',
			'save_field'  => false,
			'attributes'  => array(
				'maxlength'      => '32',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'new_domain',
			),
			'size'        => 90,
			'placeholder' => __( 'Domain without www or http - e.g: mydomain.com', 'wpcd' ),
			'std'         => $default_domain,
		);

		$clone_desc = '';
		if ( 'yes' === $this->is_remote_db( $id ) ) {
			$clone_desc .= '<b>' . __( 'Warning: This site appears to be using a remote database server.  The server on which this site resides should have a local database server since the database server will be switched to localhost when cloning this site.', 'wpcd' ) . '</b>';
		}
		$fields[] = array(
			'id'         => 'wpcd_app_clone_site',
			'name'       => '',
			'tab'        => 'clone-site',
			'type'       => 'button',
			'std'        => __( 'Clone Site', 'wpcd' ),
			'desc'       => $clone_desc,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'clone-site',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_clone_site_domain_new_domain' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to copy this site to a new domain?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to clone this site to a new site at the specified domain...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

}

new WPCD_WORDPRESS_TABS_CLONE_SITE();
