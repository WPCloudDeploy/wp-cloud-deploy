<?php
/**
 * Security Plugin tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This tab is a bit different from others because only users
 * who can manage a server is allowed to operate this tab.
 * System users have the whole run of the server so not good
 * to have non-server users be able to operate here.
 */

/**
 * Class WPCD_WORDPRESS_TABS_SITE_LOGS
 */
class WPCD_WORDPRESS_TABS_SITE_SECURITY extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_SITE_SYSTEM_USERS constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// Activate Logtivity via an action hook.
		add_action( 'wpcd_wordpress-app_do-activate_logtivity_for_site', array( $this, 'activate_logtivity_action' ), 10, 2 );

		// Remove Logtivity via an action hook.
		add_action( 'wpcd_wordpress-app_do-remove_logtivity_for_site', array( $this, 'remove_logtivity_action' ), 10, 2 );

		// Add bulk action option to the site list to add or remove logtivity from a site.
		if ( true === boolval( wpcd_get_early_option( 'wordpress_app_logtivity_enable_bulk_actions' ) ) ) {
			add_filter( 'bulk_actions-edit-wpcd_app', array( $this, 'wpcd_add_new_bulk_actions_site' ) );
		}

		// Action hook to handle bulk actions for site.
		if ( true === boolval( wpcd_get_early_option( 'wordpress_app_logtivity_enable_bulk_actions' ) ) ) {
			add_filter( 'handle_bulk_actions-edit-wpcd_app', array( $this, 'wpcd_bulk_action_handler_sites' ), 10, 3 );
		}

		/* Pending Logs Background Task: Activate Logtivity on a site */
		add_action( 'wpcd_wordpress-app_pending_log_activate_logtivity', array( $this, 'pending_log_activate_logtivity' ), 10, 3 );

		/* Pending Logs Background Task: Remove Logtivity from a site */
		add_action( 'wpcd_wordpress-app_pending_log_remove_logtivity', array( $this, 'pending_log_remove_logtivity' ), 10, 3 );
	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'site-security';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_security_tab';
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
				'label' => __( 'Security Plugin', 'wpcd' ),
				'icon'  => 'fa-duotone fa-shield-halved',
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
		$valid_actions = array( 'site-activate-solidwp-security', 'site-deactivate-solidwp-security' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'site-log-download':
					$result = array();
					$result = $this->do_site_log_actions( $action, $id );
					// most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
					if ( ! in_array( $action, array(), true ) && ! is_wp_error( $result ) ) {
						$result['refresh'] = 'yes';
					}
					break;

				case 'site-logtivity-toggle-install':
					$result    = array();
					$connected = $this->get_logtivity_connection_status( $id );
					if ( $connected ) {
						$result = $this->remove_logtivity( $action, $id );
					} else {
						$result = $this->install_activate_logtivity( $action, $id );
					}
					break;

			}
		}
		return $result;
	}

	/**
	 * Gets the fields to be shown in the LOGS tab.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 */
	public function get_fields( array $fields, $id ) {

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		return array_merge(
			$fields,
			$this->get_site_logs_fields( $id )
		);

	}

	/**
	 * Gets the fields to be shown in the LOGS area of the tab.
	 *
	 * @param int $id id.
	 */
	public function get_site_logs_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field( 'site-logs' );
		}

		// Get Fields.
		$download_logs_fields = $this->download_logs_fields( $id );
		$logtivity_fields     = $this->logtivity_fields( $id );

		// Setup return var.
		$fields = $download_logs_fields;

		// Add in certain groups that only admins should see.
		if ( wpcd_is_admin() ) {
			$fields = array_merge( $fields, $logtivity_fields );
		}

		// Return.
		return $fields;

	}

	/**
	 * Gets the fields to be shown in the DOWNLOAD LOGS area of the tab.
	 *
	 * @param int $id id.
	 */
	public function download_logs_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		/* Array variable to hold our field definitions */
		$fields = array();

		// Heading text.
		$desc = __( 'Download various log files for this site.', 'wpcd' );

		$fields[] = array(
			'name' => __( 'Download Logs', 'wpcd' ),
			'desc' => $desc,
			'tab'  => 'site-logs',
			'type' => 'heading',
		);

		// Get the domain name for this app - we'll need it later.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// List of logs for download.
		$fields[] = array(
			'name'       => __( 'Select Log', 'wpcd' ),
			'id'         => 'wpcd_app_site_log_name',
			'tab'        => 'site-logs',
			'type'       => 'select',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'site_log_name',
			),
			'options'    => $this->get_log_list( $id ),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_site_log_download_button',
			'tab'        => 'site-logs',
			'type'       => 'button',
			'std'        => __( 'Download', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'site-log-download',
				// the id.
				'data-wpcd-id'     => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_site_log_name' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		$fields[] = array(
			'name' => __( 'Warning', 'wpcd' ),
			'desc' => __( 'Attempting to download very large log files can cause your server memory to be exhausted which will likely cause your server to kill this process or, worse, crash. Use this download tool only if you are sure your logs are of a reasonable size. Otherwise connect via sFTP or ssh to download logs.', 'wpcd' ),
			'tab'  => 'site-logs',
			'type' => 'heading',
		);

		return $fields;

	}

	/**
	 * Gets the fields to be shown in the LOGTIVITY area of the tab.
	 *
	 * @param int $id id.
	 */
	public function logtivity_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		// Only admins allowed for these fields.
		if ( ! wpcd_is_admin() ) {
			return array();
		}

		// Is the site connected to logtivity?
		$connected = $this->get_logtivity_connection_status( $id );

		/* Array variable to hold our field definitions */
		$fields = array();

		// Heading text.
		$desc = __( 'Connect site to Logtivity.', 'wpcd' );

		// If no value is for logtivity teams api, set warning.
		if ( empty( WPCD()->decrypt( wpcd_get_early_option( 'wordpress_app_logtivity_teams_api_key' ) ) ) ) {
			$desc .= '<br/>' . __( 'Warning: No Logtivity API Key is configured in settings!', 'wpcd' );
		}

		$fields[] = array(
			'name' => __( 'Logtivity Connection', 'wpcd' ),
			'desc' => $desc,
			'tab'  => 'site-logs',
			'type' => 'heading',
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_site_logtivity_switch',
			'tab'        => 'site-logs',
			'type'       => 'switch',
			'std'        => $connected,
			'on_label'   => __( 'Connected', 'wpcd' ),
			'off_label'  => __( 'Not Connected', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'site-logtivity-toggle-install',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to connect this site to Logtivity?', 'wpcd' ),
				// fields that contribute data for this action.
				// 'data-wpcd-fields' => json_encode( array( '#wpcd_app_site_log_name' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Set whether or not site connected to logtivity
	 *
	 * @param int  $id post id of site record.
	 * @param bool $status true/false.
	 */
	public function set_logtivity_connection_status( $id, $status ) {

		update_post_meta( $id, 'wpcd_app_logtivity_connection_status', $status );

	}

	/**
	 * Is the site connected to logtivity?
	 *
	 * @param int $id post id of site record.
	 */
	public function get_logtivity_connection_status( $id ) {

		return boolval( get_post_meta( $id, 'wpcd_app_logtivity_connection_status', true ) );

	}

	/**
	 * Performs the SITE LOG action.
	 *
	 * @param array $action action.
	 * @param int   $id post id of site record.
	 */
	private function do_site_log_actions( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Grab the arguments sent from the front-end JS */
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Get the domain...
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		/* Make sure the log name has not been tampered with. We will not be escaping the log file name since we can validate it against our own known good list. */
		if ( ! isset( $this->get_log_list( $id )[ $args['site_log_name'] ] ) ) {
			return new \WP_Error( __( 'We were unable to validate the log file name - this might be a security concern!.', 'wpcd' ) );
		}

		// Make sure we actually have a domain name.
		if ( empty( $domain ) ) {
			return new \WP_Error( __( 'We were unable to get the domain needed for this action.', 'wpcd' ) );
		}

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Do the download...
		$result = $this->ssh()->do_file_download( $instance, $args['site_log_name'] );  // We have to send the unescaped file name.
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s. It is possible that the file does not exist or that it is empty. Error message: %2$s. Error code: %3$s', 'wpcd' ), $action, $result->get_error_message(), $result->get_error_code() ) );
		}

		// create log file and store it in temp folder.
		$log_file = wpcd_get_log_file_without_extension( $args['site_log_name'] ) . '_' . time() . '.txt';
		$temppath = trailingslashit( $this->get_script_temp_path() );

		/* Put the log file into the temp folder... */
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$filepath    = $temppath . $log_file;
		$file_result = $wp_filesystem->put_contents(
			$filepath,
			$result,
			false
		);

		/* Send the file name to the browser which will handle the download via JS */
		if ( $file_result ) {
			$file_url = trailingslashit( $this->get_script_temp_path_uri() ) . $log_file;
			$result   = array(
				'file_url'  => $file_url,
				'file_name' => $log_file,
				'file_data' => $result,
			);
		}

		return $result;

	}

	/**
	 * Activity and connect LOGTIVITY for this site.
	 *
	 * @param array $action action.
	 * @param int   $id post id of site record.
	 * @param array $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 */
	public function install_activate_logtivity( $action, $id, $in_args = array() ) {

		// If we don't have a LOGTIVITY TEAMS key, use return.
		$teams_api_key = WPCD()->decrypt( wpcd_get_option( 'wordpress_app_logtivity_teams_api_key' ) );

		if ( true === empty( $teams_api_key ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'There is no Logtivity Teams API Key set in your global settings - action %s', 'wpcd' ), $action ) );
		}

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Put incoming data into an array.
		$args = array();
		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			if ( ! empty( $_POST['params'] ) ) {
				$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
			}
		} else {
			$args = $in_args;
		}

		// Add the API key to the instance array.
		// Right now we're not collecting a key in the UI so there should be nothing in $args.
		// But we might pass in one via an action hook or add the ui later.
		if ( empty( $args['logtivity_teams_api_key'] ) ) {
			$args['logtivity_teams_api_key'] = $teams_api_key;
		}

		// Set the correct action.
		$action = 'logtivity_install';

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_logtivity.txt',
			array_merge(
				$args,
				array(
					'command' => "{$action}_site",
					'action'  => $action,
					'domain'  => $this->get_domain_name( $id ),
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_logtivity.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Tag logtivity as being connected.
		$this->set_logtivity_connection_status( $id, true );

		$result = array( 'refresh' => 'yes' );

		return $result;

	}

	/**
	 * Remove Logtivity from a site.
	 *
	 * @param array $action action.
	 * @param int   $id post id of site record.
	 */
	public function remove_logtivity( $action, $id ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Set the correct action.
		$action = 'logtivity_remove';

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_logtivity.txt',
			array_merge(
				$args,
				array(
					'command' => "{$action}_site",
					'action'  => $action,
					'domain'  => $this->get_domain_name( $id ),
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_logtivity.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Tag logtivity as being connected.
		$this->set_logtivity_connection_status( $id, false );

		$result = array( 'refresh' => 'yes' );

		return $result;

	}

	/**
	 * Return a key-value array of logs that we can retrieve for the site.
	 *
	 * @param int $id id.
	 */
	public function get_log_list( $id ) {
		$domain = get_post_meta( $id, 'wpapp_domain', true );
		return array(
			"/var/www/$domain/html/wp-content/debug.log" => __( 'debug.log', 'wpcd' ),
			'other'                                      => __( 'For Future Use', 'wpcd' ),
		);
	}

	/**
	 * Add new bulk options in site list screen.
	 *
	 * Filter Hook: bulk_actions-edit-wpcd_app
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function wpcd_add_new_bulk_actions_site( $bulk_array ) {

		if ( wpcd_is_admin() ) {
			$bulk_array['wpcd_sites_activate_logtivity'] = __( 'Activate Logtivity', 'wpcd' );
			$bulk_array['wpcd_sites_remove_logtivity']   = __( 'Remove Logtivity', 'wpcd' );
			return $bulk_array;
		}

		return $bulk_array;

	}

	/**
	 * Handle bulk actions for sites.
	 *
	 * Action Hook: handle_bulk_actions-edit-wpcd_app
	 *
	 * @param string $redirect_url  redirect url.
	 * @param string $action        bulk action slug/id - this is not the WPCD action key.
	 * @param array  $post_ids      all post ids.
	 */
	public function wpcd_bulk_action_handler_sites( $redirect_url, $action, $post_ids ) {

		// Lets make sure we're an admin otherwise return an error.
		if ( ! wpcd_is_admin() ) {
			do_action( 'wpcd_log_error', 'Someone attempted to run a function that required admin privileges.', 'security', __FILE__, __LINE__ );

			// Show error message to user at the top of the admin list as a dismissible notice.
			wpcd_global_add_admin_notice( __( 'You attempted to run a function that requires admin privileges.', 'wpcd' ), 'error' );

			return $redirect_url;
		}

		// Update themes and plugins.
		if ( in_array( $action, $this->get_valid_bulk_actions(), true ) ) {

			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $app_id ) {

					switch ( $action ) {
						case 'wpcd_sites_activate_logtivity':
							$args['action_hook'] = 'wpcd_wordpress-app_pending_log_activate_logtivity';
							$args['action']      = $action;
							$pending_log_type    = 'activate-logtivity';
							$pending_log_message = __( 'Bulk Action: Waiting to activate Logtivity.', 'wpcd' );
							break;

						case 'wpcd_sites_remove_logtivity':
							$args['action_hook'] = 'wpcd_wordpress-app_pending_log_remove_logtivity';
							$args['action']      = $action;
							$pending_log_type    = 'remove-logtivity';
							$pending_log_message = __( 'Bulk Action: Waiting to remove Logtivity.', 'wpcd' );
							break;
					}

					// Remove the 'wpcd_sites_' from the action string - we'll use it in the message we print in the pending logs table.
					$printed_action = str_replace( 'wpcd_sites_', '', $action );
					/* Translators: %s is an internal action name. */
					WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, $pending_log_type, $app_id, $args, 'ready', $app_id, $pending_log_message );
				}

				// Add message to be displayed in admin header.
				wpcd_global_add_admin_notice( __( 'Logtivity actions have been scheduled for the selected sites. You can view the progress in the PENDING TASKS screen.', 'wpcd' ), 'success' );

			}
		}

		return $redirect_url;
	}

	/**
	 * Returns an array of actions that is valid for the bulk actions menu.
	 */
	public function get_valid_bulk_actions() {
		return array( 'wpcd_sites_activate_logtivity', 'wpcd_sites_remove_logtivity' );
	}

	/**
	 * Activate logtivity for a site.
	 *
	 * Can be called directly or by an action hook.
	 *
	 * Action hook: wpcd_wordpress-app_do-activate_logtivity_for_site.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $teams_api_key The api key to use instead of the one in global settings (optional).
	 *
	 * @return string|WP_Error
	 */
	public function activate_logtivity_action( $id, $teams_api_key = '' ) {

		$action = 'logtivity_install';  // Action string doesn't matter - it's not used in the called function.

		$result = $this->install_activate_logtivity( $action, $id );

		return $result;  // Will not matter in an action hook.

	}

	/**
	 * Remove/deactivate logtivity for a site.
	 *
	 * Can be called directly or by an action hook.
	 *
	 * Action hook: wpcd_wordpress-app_do-activate_logtivity_for_site.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $teams_api_key The api key to use instead of the one in global settings (optional).
	 *
	 * @return string|WP_Error
	 */
	public function remove_logtivity_action( $id, $teams_api_key = '' ) {

		$action = 'logtivity_remove';  // Action string doesn't matter - it's not used in the called function.

		$result = $this->remove_logtivity( $action, $id );

		return $result;  // Will not matter in an action hook.

	}

	/**
	 * Activate logtivity for a site.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_wordpress-app_pending_log_activate_logtivity
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site involved in this action.
	 * @param array $args       All the data needed to handle this action.
	 */
	public function pending_log_activate_logtivity( $task_id, $site_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		$action = 'logtivity_install';  // Action string doesn't matter - it's not used in the called function.

		// Activate logtivity.
		$result = $this->install_activate_logtivity( $action, $site_id );

		$task_status = 'complete';  // Assume success.
		if ( is_array( $result ) ) {
			// We'll get an array from the install_activate_logtivity function.  So nothing to do here.
			// We'll just reset the $task_status to complete (which is the value it was initialized with) to avoid complaints by PHPcs about an empty if statement.
			$task_status = 'complete';
		} else {
			if ( false === (bool) $result || is_wp_error( $result ) ) {
				$task_status = 'failed';
			}
		}
		WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, $task_status );

	}

	/**
	 * Remove logtivity from a site.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_wordpress-app_pending_log_remove_logtivity
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site involved in this action.
	 * @param array $args       All the data needed to handle this action.
	 */
	public function pending_log_remove_logtivity( $task_id, $site_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		$action = 'logtivity_remove';  // Action string doesn't matter - it's not used in the called function.

		// Activate logtivity.
		$result = $this->remove_logtivity( $action, $site_id );

		$task_status = 'complete';  // Assume success.
		if ( is_array( $result ) ) {
			// We'll get an array from the remove_logtivity function.  So nothing to do here.
			// We'll just reset the $task_status to complete (which is the value it was initialized with) to avoid complaints by PHPcs about an empty if statement.
			$task_status = 'complete';
		} else {
			if ( false === (bool) $result || is_wp_error( $result ) ) {
				$task_status = 'failed';
			}
		}
		WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, $task_status );

	}

}

new WPCD_WORDPRESS_TABS_SITE_SECURITY();
