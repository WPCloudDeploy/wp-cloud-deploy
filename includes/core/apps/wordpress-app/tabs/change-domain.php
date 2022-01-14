<?php
/**
 * Change domain tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_CHANGE_DOMAIN
 */
class WPCD_WORDPRESS_TABS_CHANGE_DOMAIN extends WPCD_WORDPRESS_TABS {

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

		// Allow the change domain (full live) action to be triggered via an action hook.  Will primarily be used by the woocommerce add-on and REST API.
		add_action( 'wpcd_wordpress-app_do_change_domain_full_live', array( $this, 'do_change_domain_live_action' ), 10, 2 );

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the app cpt (old domain).
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
		if ( 'replace_domain' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'change_domain_full.txt' );

			if ( true == $success ) {

				// get old domain.
				$old_domain = get_post_meta( $id, 'wpapp_domain', true );

				// get new domain from temporary meta.
				$new_domain = get_post_meta( $id, 'wpapp_domain_change_new_target_domain', true );

				if ( $new_domain ) {

					// update the domain.
					$this->set_domain_name( $id, $new_domain );

					// update the title of the post.
					$post_data = array(
						'ID'         => $id,
						'post_title' => $new_domain,
					);
					wp_update_post( $post_data );

					// Wrapup - let things hook in here - primarily the multisite and WC add-ons.
					do_action( "wpcd_{$this->get_app_name()}_site_change_domain_completed", $id, $old_domain, $new_domain, $name );

				}

					// And delete the temporary meta.
					delete_post_meta( $id, 'wpapp_domain_change_new_target_domain' );
			} else {
				// Add action hook to indicate failure...
				$message = __( 'Change domain command failed - check the command logs for more information.', 'wpcd' );
				do_action( "wpcd_{$this->get_app_name()}_site_change_domain_failed", $id, $command_array[0], $message, array() );  // Keeping 4 parameters for the action hook to maintain consistency even though we have nothing for the last parameter.
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
		return 'change-domain';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_change_domain_tab';
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
		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Change Domain', 'wpcd' ),
				'icon'  => 'fad fa-browser',
			);
		}
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

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'change-domain-quick-change', 'change-domain-full-dry-run', 'change-domain-full-live-run', 'change-domain-record-only', 'search-and-replace-db' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'change-domain-quick-change':
					$action = 'domain_only';
					$result = $this->change_domain_only( $action, $id );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'change-domain-full-dry-run':
					$action = 'dry_run';
					$result = $this->change_domain_dry_run( $action, $id );
					break;
				case 'change-domain-full-live-run':
					$action = 'replace_domain';
					$result = $this->change_domain_live_run( $action, $id );
					break;
				case 'change-domain-record-only':
					$action = 'record_only';
					$result = $this->change_domain_record_only( $action, $id );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'search-and-replace-db':
					$action = 'search_and_replace_db';
					$result = $this->search_and_replace_db( $action, $id );

			}
		}

		return $result;

	}

	/**
	 * Change the domain only in the main WP tables.
	 * No search and replace will be done.
	 *
	 * @param string $action The action key to send to the bash script.  This is actually the key of the drop-down select.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function change_domain_only( $action, $id ) {

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Bail if certain things are empty...
		if ( empty( $args['new_domain'] ) ) {
			$message = __( 'The new domain must be provided', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_site_change_domain_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$new_domain = strtolower( sanitize_text_field( $args['new_domain'] ) );
		}

		// remove https/http/www. to make the domain a consistent NAME.TLD.
		$new_domain         = wpcd_clean_domain( $new_domain );
		$args['new_domain'] = $new_domain;

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_site_change_domain_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// sanitize the fields to allow them to be used safely on the bash command line.
		$args['new_domain'] = escapeshellarg( sanitize_text_field( $args['new_domain'] ) );

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'change_domain_quick.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		// log.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'change_domain_quick.txt' );
		if ( ! $success ) {
			$message = sprintf( __( 'Unable to perform action %1$s for site: %2$s', 'wpcd' ), $action, $result );
			do_action( "wpcd_{$this->get_app_name()}_site_change_domain_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Now that we know we're successful, lets change the domain name in our meta.
		$set_cpt = $this->set_domain_name( $id, $new_domain );

		// update the title of the post.
		$post_data = array(
			'ID'         => $id,
			'post_title' => $new_domain,
		);
		wp_update_post( $post_data );

		// Wrapup - let things hook in here - primarily the multisite and WC add-ons.
		do_action( "wpcd_{$this->get_app_name()}_site_quick_change_domain_completed", $id, $domain, $new_domain );

		// Disable the ssl flag on the cpt - user can turn it on manually later.
		// Note that it will be disabled even if there is an SSL certificate already issued.
		update_post_meta( $id, 'wpapp_ssl_status', 'off' );

		// If domain not set on the CPT , let user know.
		if ( ! $set_cpt ) {
			return new \WP_Error( __( 'The domain was changed on the site but we were unable to change it on our WordPress META records. Please contact the WPCloud Deploy Tech Support team to assist you with updating your meta records.', 'wpcd' ) );
		}

		// However, not all things might have been successful.  This is one of those cases where you might have partial success so you want to let the user know...
		if ( strpos( $result, 'Challenges failed for all domains' ) !== false ) {
			return new \WP_Error( __( 'It seems not all actions were successful. We were unable to automatically issue an SSL certificate for the new domain.', 'wpcd' ) );
		}

		return $result;

	}

	/**
	 * Change the domain - dry run or live run.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args args.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function change_domain_full( $action, $id, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Bail if certain things are empty...
		if ( empty( $args['new_domain'] ) ) {
			$message = __( 'The new domain must be provided', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_site_change_domain_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$new_domain = strtolower( sanitize_text_field( $args['new_domain'] ) );
		}

		// remove https/http/www. to make the domain a consistent NAME.TLD.
		$new_domain         = wpcd_clean_domain( $new_domain );
		$args['new_domain'] = $new_domain;

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_site_change_domain_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// sanitize the fields to allow them to be used safely on the bash command line.
		$args['new_domain'] = escapeshellarg( sanitize_text_field( $args['new_domain'] ) );

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// we want to make sure this command runs only once in a "swatch beat" for a domain.
		// e.g. 2 manual backups cannot run for the same domain at the same time (time = swatch beat)
		// although technically only one command can run per domain (e.g. backup and restore cannot run at the same time).
		// we are appending the Swatch beat to the command name because this command can be run multiple times
		// over the app's lifetime
		// but within a swatch beat, it can only be run once.
		$command             = sprintf( '%s---%s---%d', $action, $domain, date( 'B' ) );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'change_domain_full.txt',
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
			$message = sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_site_change_domain_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Stamp some data we'll need later (in the command_completed function) onto the app records.
		if ( 'replace_domain' === $action ) {
			update_post_meta( $id, 'wpapp_domain_change_new_target_domain', $new_domain );
		}

		// We might need to add an item to the PENDING TASKS LOG (generally because we're calling this from a WC order).
		if ( isset( $in_args['pending_tasks_type'] ) ) {
			$server_id = $this->get_server_id_by_app_id( $id );
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, $in_args['pending_tasks_type'], $command, $args, 'not-ready', $id, __( 'Change Domain On Cloned Template Site To Complete', 'wpcd' ) );
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
	 * Change the domain - dry run only.
	 * No real search and replace will be done.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function change_domain_dry_run( $action, $id ) {
		return $this->change_domain_full( $action, $id ); // $action should be 'dry_run'.
	}

	/**
	 * Change the domain - Live Run
	 * No real search and replace will be done.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function change_domain_live_run( $action, $id ) {
		return $this->change_domain_full( $action, $id );  // $action should be 'replace_domain'.
	}


	/**
	 * Trigger the change domain function from an action hook.
	 *
	 * Action Hook: wpcd_wordpress-app_do_change_domain_full_live
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the change domain function needs.
	 */
	public function do_change_domain_live_action( $id, $args ) {
		$this->change_domain_full( 'replace_domain', $id, $args );
	}

	/**
	 * Update the meta for the domain name.
	 *
	 * @param string $action The action to be performed 'enable' or 'disable'  (this matches the string required in the bash scripts).
	 * @param int    $id     The postID of the app cpt.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function change_domain_record_only( $action, $id ) {

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Bail if certain things are empty...
		if ( empty( $args['new_domain'] ) ) {
			return new \WP_Error( __( 'The new domain must be provided', 'wpcd' ) );
		} else {
			$new_domain = strtolower( sanitize_text_field( $args['new_domain'] ) );
		}

		// remove https/http/www. to make the domain a consistent NAME.TLD.
		$new_domain         = wpcd_clean_domain( $new_domain );
		$args['new_domain'] = $new_domain;

		// Change the record.
		if ( $new_domain ) {

			// update the domain.
			$this->set_domain_name( $id, $new_domain );

			// update the title of the post.
			$post_data = array(
				'ID'         => $id,
				'post_title' => $new_domain,
			);
			wp_update_post( $post_data );

		}

		// Return an error so that it can be displayed in a dialog box...
		return new \WP_Error( __( 'Domain name on this record has been updated to the new domain.', 'wpcd' ) );
	}

	/**
	 * General search and replace in the database
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function search_and_replace_db( $action, $id ) {

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Bail if certain things are empty...
		if ( empty( $args['search_term'] ) ) {
			return new \WP_Error( __( 'The search term must be provided', 'wpcd' ) );
		} else {
			$search_term = sanitize_text_field( $args['search_term'] );
		}

		if ( empty( $args['replace_term'] ) ) {
			return new \WP_Error( __( 'The replacement term must be provided', 'wpcd' ) );
		} else {
			$replace_term = sanitize_text_field( $args['replace_term'] );
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// sanitize the fields to allow them to be used safely on the bash command line.
		$args['search_term']  = escapeshellarg( $search_term );
		$args['replace_term'] = escapeshellarg( $replace_term );

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// we want to make sure this command runs only once in a "swatch beat" for a domain.
		// e.g. 2 manual backups cannot run for the same domain at the same time (time = swatch beat)
		// although technically only one command can run per domain (e.g. backup and restore cannot run at the same time).
		// we are appending the Swatch beat to the command name because this command can be run multiple times
		// over the app's lifetime
		// but within a swatch beat, it can only be run once.
		$command             = sprintf( '%s---%s---%d', $action, $domain, date( 'B' ) );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		// Note that the pre-run command script, 'search_and_replace_db.txt', contains some extra quotes to allow for spaces in the search and replace terms!
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'search_and_replace_db.txt',
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

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'change-domain' ) );
		}

		// Get HTTP2 status since we cannot change domain with HTTP2 turned on.
		$http2_status = $this->http2_status( $id );
		if ( 'on' === $http2_status ) {
			$desc = __( 'You cannot change your site domain at this time because HTTP2 is enabled. Please disable it before attempting this operation.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'Change Domain', 'wpcd' ),
				'tab'  => 'change-domain',
				'type' => 'heading',
				'desc' => $desc,
			);

			return $fields;
		}

		// We got here so ok to show fields related to changing domain.
		$desc = __( 'Change your site domain.  This is a destructive operation so you should take a backup before proceeding.<br />', 'wpcd' );

		$desc_ssl  = __( 'If you would like new SSL certificates to be issued as part of this operation please make sure you point your DNS for the new domain to to this server\'s IP address BEFORE you start this operation. ', 'wpcd' );
		$desc_ssl .= __( 'Otherwise you will need to request new certificates on the SSL tab after the operation is complete and you have updated your DNS.', 'wpcd' );
		$desc_ssl .= '<br />';
		$desc_ssl .= __( 'Note that we will only attempt to automatically issue an SSL certificate if the current domain already has an SSL certificate installed.', 'wpcd' );

		$fields[] = array(
			'name' => __( 'Change Domain', 'wpcd' ),
			'tab'  => 'change-domain',
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'        => __( 'New Domain', 'wpcd' ),
			'id'          => 'wpcd_app_change_domain_new_domain',
			'tab'         => 'change-domain',
			'type'        => 'text',
			'save_field'  => false,
			'attributes'  => array(
				'maxlength'      => '32',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'new_domain',
			),
			'size'        => 90,
			'placeholder' => __( 'Enter your new domain without the "http" or "www" prefix', 'wpcd' ),
		);

		if ( ! (bool) wpcd_get_early_option( 'wordpress_app_hide_change_domain_explanatory_text' ) ) {
			$fields[] = array(
				'name' => '',
				'tab'  => 'change-domain',
				'type' => 'custom-html',
				'std'  => $desc_ssl,
			);
		}

		$fields[] = array(
			'id'         => 'wpcd_app_change_domain_quick_change',
			'name'       => __( 'Quick Change', 'wpcd' ),
			'tab'        => 'change-domain',
			'type'       => 'button',
			'std'        => __( 'Quick Change', 'wpcd' ),
			'tooltip'    => __( 'Change just the domain name in the WordPress settings screen.  All other references to the old domain will remain in your content and other items in the database.', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'change-domain-quick-change',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_change_domain_new_domain' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to change the domain? Protect your data - make a backup before you start this operation!', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 2,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_change_domain_dry_run',
			'name'       => __( 'Full - Dry Run', 'wpcd' ),
			'tab'        => 'change-domain',
			'type'       => 'button',
			'std'        => __( 'Full - Dry Run', 'wpcd' ),
			'tooltip'    => __( 'Change all references from the old domain to the new domain across the entire database. This does a dry run so you can get an idea of what will be changed.', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'change-domain-full-dry-run',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_change_domain_new_domain' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to do a dry-run of a full database domain change? This might take a while!', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to start a dry-run of a full database domain change operation...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 2,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_change_domain_live_run',
			'name'       => __( 'Full Live', 'wpcd' ),
			'tab'        => 'change-domain',
			'type'       => 'button',
			'std'        => __( 'Full - Live', 'wpcd' ),
			'tooltip'    => __( 'Change all references from the old domain to the new domain across the entire database. This is the real deal - do a backup because you cannot undo this action once it has started!', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'change-domain-full-live-run',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_change_domain_new_domain' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to perform a full database domain change? This might take a while!', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to start a full database domain change operation. We hope that you took a full backup before starting this operation!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 2,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_change_domain_record_only',
			'name'       => __( 'Change Meta', 'wpcd' ),
			'tab'        => 'change-domain',
			'type'       => 'button',
			'std'        => __( 'Change Record Only', 'wpcd' ),
			'tooltip'    => __( 'Update the record in this plugin only. You might need to do this if a prior operation only partially succeeded or you changed the domain using another plugin such as UpdraftPlus.', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'change-domain-record-only',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_change_domain_new_domain' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to only update the local record with this domain name?  If done incorrectly this can cause future domain operations to fail!', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
			'columns'    => 2,
		);

		// Add some quick notes about the domain name change process.
		$note  = __( 'Quick Change: Change just the domain name in the WordPress settings screen.  All other references to the old domain will remain in your content and other items in the database.', 'wpcd' );
		$note .= '<br />';
		$note .= __( 'Full - Dry Run: Change all references from the old domain to the new domain across the entire database. This does a dry run so you can get an idea of what will be changed.', 'wpcd' );
		$note .= '<br />';
		$note .= __( 'Full - Live Run: Change all references from the old domain to the new domain across the entire database. This is the real deal - do a backup because you cannot undo this action once it has started!', 'wpcd' );

		if ( ! (bool) wpcd_get_early_option( 'wordpress_app_hide_change_domain_explanatory_text' ) ) {
			$fields[] = array(
				'name' => __( 'Notes', 'wpcd' ),
				'tab'  => 'change-domain',
				'type' => 'heading',
				'desc' => $note,
			);
		}

		// Add some reminders.
		$reminder  = __( 'After you have finished changing the domain name, you must remove any sFTP users and re-add them on the sFTP tab. Existing users are no longer valid for the new domain.', 'wpcd' );
		$reminder .= '<br />';
		$reminder .= __( 'You should also update your permalinks under the WordPress SETTINGS->PERMALINKS screen - just go there and click the update button; WordPress will reset the permalinks for your site.', 'wpcd' );

		if ( ! (bool) wpcd_get_early_option( 'wordpress_app_hide_change_domain_explanatory_text' ) ) {
			$fields[] = array(
				'name' => __( 'Reminders', 'wpcd' ),
				'tab'  => 'change-domain',
				'type' => 'heading',
				'desc' => $reminder,
			);
		}

		/* Start generic search and replace fields */
		$fields[] = array(
			'name' => '',
			'tab'  => 'change-domain',
			'type' => 'divider',
		);

		if ( ! (bool) wpcd_get_early_option( 'wordpress_app_hide_change_domain_explanatory_text' ) ) {
			$desc  = __( 'Generic search and replace.  This is a destructive operation so you should take a backup before proceeding.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'Search for a word in your database and replace it with another word.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'You can search for old domains or links and such and then replace them.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'However, NO SPECIAL CHARACTERS or SPACES are allowed in your search or replace terms!', 'wpcd' );
		} else {
			$desc = '';
		}

		$fields[] = array(
			'name' => __( 'Search & Replace', 'wpcd' ),
			'id'   => 'wpcd_search_and_replace_db_header',
			'tab'  => 'change-domain',
			'type' => 'heading',
			'desc' => $desc,
		);

		$fields[] = array(
			'name'        => __( 'What are you searching for?', 'wpcd' ),
			'id'          => 'wpcd_app_search_term',
			'tab'         => 'change-domain',
			'type'        => 'text',
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'search_term',
			),
			'size'        => 90,
			'placeholder' => __( 'Enter your new search term - no special characters allowed', 'wpcd' ),
		);
		$fields[] = array(
			'name'        => __( 'What are you replacing them with?', 'wpcd' ),
			'id'          => 'wpcd_app_replace_term',
			'tab'         => 'change-domain',
			'type'        => 'text',
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'replace_term',
			),
			'size'        => 90,
			'placeholder' => __( 'Enter your new replacement term - no special characters allowed', 'wpcd' ),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_search_and_replace',
			'name'       => __( 'Search & Replace', 'wpcd' ),
			'tab'        => 'change-domain',
			'type'       => 'button',
			'std'        => __( 'Run Search & Replace', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'search-and-replace-db',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_search_term', '#wpcd_app_replace_term' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'If you make a mistake there is no UNDO! Are you sure you would like to run this search & replace? ', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to start a full database search & replace operation. We hope that you took a full backup before starting this operation!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);
		/* End Start generic search and replace fields */

		/* Documentation Link to WPCloudDeploy Site */
		$doc_link = 'https://wpclouddeploy.com/documentation/wpcloud-deploy-user-guide/changing-a-domain/';
		$desc     = __( 'Read more about changing domains in our documentation.', 'wpcd' );
		$desc    .= '<br />';
		$desc    .= '<br />';
		$desc    .= sprintf( '<a href="%s">%s</a>', wpcd_get_documentation_link( 'wordpress-app-doc-change-domain', apply_filters( 'wpcd_documentation_links', $doc_link ) ), __( 'View our Documentation on Changing Domains', 'wpcd' ) );

		$fields[] = array(
			'name' => __( 'Change Domain Documentation', 'wpcd' ),
			'tab'  => 'change-domain',
			'type' => 'heading',
			'desc' => $desc,
		);
		/* End documentation Link to WPCloudDeploy Site */

		return $fields;

	}

}

new WPCD_WORDPRESS_TABS_CHANGE_DOMAIN();
