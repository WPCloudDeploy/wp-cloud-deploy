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
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
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

		// if the command is to replace the domain name and the domain was changed we need to update the domain records...
		if ( 'create-staging-site' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'clone_site.txt' );

			if ( true == $success ) {
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

							// Make sure we tag the new site as a staging site.
							update_post_meta( $new_app_post_id, 'wpapp_is_staging', 1 );

							// And tag the original site with the domain and id of the staging site.
							update_post_meta( $id, 'wpapp_staging_domain', $new_domain );
							update_post_meta( $id, 'wpapp_staging_domain_id', $new_app_post_id );

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
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['staging'] = array(
			'label' => __( 'Staging', 'wpcd' ),
			'icon'  => 'fad fa-folders',
		);
		return $tabs;
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
		 * We've gotten this far, so lets try to configure the DNS to point to the server.
		 */
		// 1. What's the server post id?
		$server_id = $this->get_server_id_by_app_id( $id );
		// 2. What's the IP of the server?
		$ipv4 = WPCD_SERVER()->get_ipv4_address( $server_id );
		// 3. Add the DNS
		$dns_success = WPCD_DNS()->set_dns_for_domain( $new_domain, $ipv4 );

		// we want to make sure this command runs only once in a "swatch beat" for a domain.
		// e.g. 2 manual backups cannot run for the same domain at the same time (time = swatch beat).
		// although technically only one command can run per domain (e.g. backup and restore cannot run at the same time).
		// we are appending the Swatch beat to the command name because this command can be run multiple times.
		// over the app's lifetime.
		// but within a swatch beat, it can only be run once.
		$command             = sprintf( '%s---%s---%d', $action, $domain, date( 'B' ) );
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

		// we want to make sure this command runs only once in a "swatch beat" for a domain.
		// e.g. 2 manual backups cannot run for the same domain at the same time (time = swatch beat).
		// although technically only one command can run per domain (e.g. backup and restore cannot run at the same time).
		// we are appending the Swatch beat to the command name because this command can be run multiple times.
		// over the app's lifetime.
		// but within a swatch beat, it can only be run once.
		$command             = sprintf( '%s---%s---%d', $action, $domain, date( 'B' ) );
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

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'staging' ) );
		}

		// Get HTTP2 status since we cannot clone a site with HTTP2 turned on.
		$http2_status = $this->http2_status( $id );
		if ( 'on' === $http2_status ) {
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
		} else {
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

			$fields[] = array(
				'id'         => 'wpcd_app_staging_site',
				'name'       => '',
				'tab'        => 'staging',
				'type'       => 'button',
				'std'        => (bool) $existing_staging_site ? __( 'Create a New Staging Site', 'wpcd' ) : __( 'Create Staging Site', 'wpcd' ),
				'desc'       => '',
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
		}

		return $fields;

	}

}

new WPCD_WORDPRESS_TABS_STAGING();
