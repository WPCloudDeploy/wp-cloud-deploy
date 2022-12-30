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

							// Add metas related to Multi-tenant.
							$mt_version      = get_post_meta( $id, 'wpapp_temp_mt_new_version', true );
							$mt_version_desc = get_post_meta( $id, 'wpapp_temp_mt_new_version_desc', true );
							$this->mt_add_mt_version_history( $id, $mt_version, $mt_version_desc, $new_domain );
							$this->set_mt_version( $new_app_post_id, $mt_version );
							$this->set_mt_parent( $new_app_post_id, $id );
							$this->set_mt_site_type( $new_app_post_id, 'mt_version' );

							// Wrapup - let things hook in here - primarily the multisite add-on and the REST API.
							do_action( "wpcd_{$this->get_app_name()}_site_clone_new_post_completed", $new_app_post_id, $id, $name );
							do_action( "wpcd_{$this->get_app_name()}_site_mt_new_version_new_post_completed", $new_app_post_id, $id, $name );

						}
					}
				}

				// And delete the temporary metas.
				delete_post_meta( $id, 'wpapp_temp_mt_new_version_target_domain' );
				delete_post_meta( $id, 'wpapp_temp_mt_new_version' );
				delete_post_meta( $id, 'wpapp_temp_mt_new_version_desc' );

			} else {
				// Add action hook to indicate failure...
				$message = __( 'Multi-tenant: Create new version failed - check the command logs for more information.', 'wpcd' );
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
		$valid_actions = array( 'mt-create-version', 'mt-set-product-name', 'mt-set-template-flag', 'mt-set-default-version' );
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

		// Is the site a template site?
		if ( true !== $this->wpcd_is_template_site( $id ) ) {
			$desc = __( 'This is not a template site - multi-tenant operations cannot be performed on this site.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'Multi-tenant', 'wpcd' ),
				'tab'  => $this->get_tab_slug(),
				'type' => 'heading',
				'desc' => $desc,
			);

			$template_flag_fields = $this->get_template_flag_fields( $id );

			return array_merge( $fields, $template_flag_fields );
		}

		$create_new_version_fields = $this->get_create_new_version_fields( $id );
		$production_version_fields = $this->get_production_version_fields( $id );
		$version_fields            = $this->get_fields_for_version_list( $id );
		$product_name_fields       = $this->get_product_name_fields( $id );
		$template_flag_fields      = $this->get_template_flag_fields( $id );

		$fields = array_merge( $fields, $create_new_version_fields, $production_version_fields, $version_fields, $product_name_fields, $template_flag_fields );

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
			'required'   => true,
			'tooltip'    => __( 'The product name set here is a synonym for the template domain and can be used to reference this template in WooCommerce products and other locations.', 'wpcd ' ),
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
			'required'   => true,
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
		$desc = '';

		$current_template_flag = $this->wpcd_is_template_site( $id );

		$fields[] = array(
			'name' => __( 'Template Flag', 'wpcd' ),
			'tab'  => $this->get_tab_slug(),
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'       => __( 'Is This Site A Template?', 'wpcd' ),
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

			$domain      = ! empty( $tag_array['domain'] ) ? $tag_array['domain'] : __( 'Error! No Associated Domain!', 'wpcd' );
			$desc        = ! empty( $tag_array['desc'] ) ? $tag_array['desc'] : __( 'No description available', 'wpcd' );
			$create_date = ! empty( $tag_array['reporting_time_human_utc'] ) ? $tag_array['reporting_time_human_utc'] : __( '1901-01-01 (unknown)', 'wpcd' );

			$return .= '<div class="wpcd_mt_version_value">';
			$return .= '<span class="wpcd_mt_version_value_inline">' . $tag . '</span>';
			$return .= '<br />' . $domain;
			$return .= '<br />' . $desc;
			$return .= '<br />' . $create_date . ' ' . __( 'UTC', 'wpcd' );
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
	 */
	public function mt_add_mt_version_history( $id, $new_tag, $new_tag_desc, $domain ) {

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


}

new WPCD_WORDPRESS_TABS_MULTITENANT_SITE();
