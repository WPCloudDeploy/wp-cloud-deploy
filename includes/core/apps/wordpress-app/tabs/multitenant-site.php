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

		// Allow the clone action to be triggered via an action hook.  Will primarily be used by the REST API.
		add_action( "wpcd_{$this->get_app_name()}_do_mt_create_version", array( $this, 'do_mt_create_version_action' ), 10, 2 ); // Hook: wpcd_wordpress-app_do_mt_create_version.

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

			if ( true == $success ) {

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

							// Explicitly NOT adding other metas related to multi-tenant here.
							// We can't add these until the 2nd part of the operation completes - git pull tag.

							// Wrapup - let things hook in here - primarily the multisite add-on and the REST API.
							do_action( "wpcd_{$this->get_app_name()}_site_clone_new_post_completed", $new_app_post_id, $id, $name );
							do_action( "wpcd_{$this->get_app_name()}_site_mt_new_version_new_post_completed", $new_app_post_id, $id, $name );

						}
					}
				}

				// Explicitly NOT deleting the temporary metas here (unlike standard staging and cloning operations).
				// This is because we have a 2nd operation that will trigger immediately after this.
				// delete_post_meta( $id, 'wpapp_temp_mt_new_version_target_domain' );
				// delete_post_meta( $id, 'wpapp_temp_mt_new_version' );
				// delete_post_meta( $id, 'wpapp_temp_mt_new_version_desc' );

			} else {
				// Add action hook to indicate failure...
				$message = __( 'Clone Site command failed - check the command logs for more information.', 'wpcd' );
				do_action( "wpcd_{$this->get_app_name()}_mt_new_version_clone_site_failed", $id, $command_array[0], $message, array() );  // Keeping 4 parameters for the action hook to maintain consistency even though we have nothing for the last parameter.
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
				'label' => __( 'Multitenant', 'wpcd' ),
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
		$valid_actions = array( 'mt-create-version' );
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
			$mt_new_version = $args['mt_new_version'];
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

		// Get the domain we're working on.
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
	 * Trigger the create version function from an action hook.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_do_mt_create_version | wpcd_wordpress-app_do_mt_create_version
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the change domain function needs.
	 */
	public function do_mt_create_version_action( $id, $args ) {
		$this->mt_create_version( 'mt-create-version', $id, $args );
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

		// Is the site a template site?
		if ( true !== wpcd_is_template_site( $id ) ) {
			$desc = __( 'This is not a template site - multi-tenant operations cannot be performed on this site.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'Multi-tenant', 'wpcd' ),
				'tab'  => $this->get_tab_slug(),
				'type' => 'heading',
				'desc' => $desc,
			);

			return $fields;
		}

		// Is the site a GIT site?
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

		$create_new_version_fields = $this->get_create_new_version_fields( $fields, $id );

		$fields = $create_new_version_fields;

		return $fields;

	}

	/**
	 * Gets the fields to be shown on the 'create new version' section.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_create_new_version_fields( array $fields, $id ) {

		/* What type of web server are we running? */
		$webserver_type = $this->get_web_server_type( $id );

		// We got here so ok to show fields related to creating a new version of this site.
		$desc .= __( 'Create a new version of this product template.', 'wpcd' );
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
			'required'    => true,
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
			'required'    => true,
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
			'required'    => true,
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
			'desc'       => $clone_desc,
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

}

new WPCD_WORDPRESS_TABS_MULTITENANT_SITE();
