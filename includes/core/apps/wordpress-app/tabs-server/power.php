<?php
/**
 * Power Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_POWER.
 */
class WPCD_WORDPRESS_TABS_SERVER_POWER extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		// Add bulk action option to the server list screen to soft reboot.
		add_filter( 'bulk_actions-edit-wpcd_app_server', array( $this, 'wpcd_add_new_bulk_actions_server' ) );

		// Action hook to handle bulk actions for server. For example to soft reboot the server.
		add_filter( 'handle_bulk_actions-edit-wpcd_app_server', array( $this, 'wpcd_bulk_action_handler_server_app' ), 10, 3 );

		// Allow the reboot_soft action to be triggered via an action hook.  Will primarily be used by the woocommerce add-ons & Bulk Actions.
		add_action( 'wpcd_wordpress-reboot_soft', array( $this, 'reboot_soft' ), 10, 2 );

		/* Pending Logs Background Task: Trigger server reboot - soft */
		add_action( 'wpcd_pending_log_soft_reboot', array( $this, 'pending_log_soft_reboot' ), 10, 3 );

		/* Handle callback success and tag the pending log record as successful */
		add_action( 'wpcd_server_wordpress-app_soft_reboot_action_successful', array( $this, 'handle_soft_reboot_success' ), 10, 3 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'svr_power';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_power_tab';
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
				'label' => __( 'Power', 'wpcd' ),
				'icon'  => 'fad fa-plug',
			);
		}
		return $tabs;
	}

	/**
	 * Checks whether or not the user can view the current tab.
	 *
	 * @param int $id The post ID of the server.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		return ( true === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Gets the fields to be shown in the POWER tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		return $this->get_fields_for_tab( $fields, $id, $this->get_tab_slug() );

	}

	/**
	 * Called when an action needs to be performed on the tab.
	 *
	 * @param mixed  $result The default value of the result.
	 * @param string $action The action to be performed.
	 * @param int    $id The post ID of the server.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the server before proceeding to do anything else */
		if ( ! $this->wpcd_user_can_view_wp_server( $id ) ) {
			/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'server-reboot-soft', 'server-schedule-reboot-soft', 'server-reboot-hard-provider', 'server-graceful-shutdown', 'server-hard-shutdown', 'server-turn-on' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'server-reboot-soft':
					$result = $this->reboot_soft( $id, $action );
					break;
				case 'server-schedule-reboot-soft':
					$result = $this->reboot_soft_schedule( $id, $action );
					break;
				case 'server-reboot-hard-provider':
					$result = $this->reboot_hard_provider( $id, $action );
					break;
				case 'server-graceful-shutdown':
					$result = $this->graceful_shutdown( $id, $action );
					break;
				case 'server-hard-shutdown':
					$result = $this->hard_shutdown( $id, $action );
					break;
				case 'server-turn-on':
					$result = $this->turn_on( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the POWER tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_power_fields( $id );

	}

	/**
	 * Gets the fields to shown in the POWER tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_power_fields( $id ) {

		$actions = array();

		/**
		 * Soft Reboot
		 */

		// Start new card.
		$actions[] = wpcd_start_one_third_card( $this->get_tab_slug() );

		$desc  = __( 'Send a reboot command to the server - this will be the equivalent of typing "reboot" on the command line.', 'wpcd' );
		$desc .= '<br/>';
		$desc .= __( 'If this does not work, you can try using other power options below. Or you might need to log into the server provider\'s console to use the power options there.', 'wpcd' );
		$desc  = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $desc );

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to restart this server?', 'wpcd' );

		$actions['server-reboot-soft-header'] = array(
			'label'          => '<i class="fa-duotone fa-plug-circle-check"></i> ' . __( 'Soft Restart', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['server-reboot-soft'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Restart', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
				'desc'                => '',
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * Provider Restart / Hard Reboot.
		 */

		// Start new card.
		$actions[] = wpcd_start_one_third_card( $this->get_tab_slug() );

		$desc = __( 'Use the server provider api to attempt to restart the server.  This is usually the equivalent of pulling the power plug while the server is running. So use only as a last resort.', 'wpcd' );
		$desc = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $desc );

		$actions['server-reboot-hard-provider-header'] = array(
			'label'          => '<i class="fa-duotone fa-plug-circle-bolt"></i> ' . __( 'Hard Provider API Restart', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['server-reboot-hard-provider'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Hard Restart', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * Graceful Poweroff
		 */

		// Start new card.
		$actions[] = wpcd_start_one_third_card( $this->get_tab_slug() );

		$desc  = __( 'Send a shutdown command to the server - this will be the equivalent of typing "shutdown" on the command line.', 'wpcd' );
		$desc .= '<br/>';
		$desc .= __( 'If this does not work, you can try using other power options below. Or you might need to log into the server provider\'s console to use the power options there.', 'wpcd' );
		$desc  = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $desc );

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to turn off this server?', 'wpcd' );

		$actions['server-shutdown-soft-header'] = array(
			'label'          => '<i class="fa-duotone fa-power-off"></i> ' . __( 'Graceful Powerdown', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['server-graceful-shutdown'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Graceful Shutdown', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
				'desc'                => '',
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * Schedule Soft Reboot
		 */

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		$actions['server-reboot-schedule-soft-header'] = array(
			'label'          => '<i class="fa-duotone fa-calendar-days"></i> ' . __( 'Schedule A Soft Restart', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'You can schedule a soft restart at a future time using these options. The date and time specified is in the timezone of the server - usually UTC.', 'wpcd' ),
			),
		);

		$actions['server-schedule-reboot-soft-date'] = array(
			'label'          => __( 'Reboot Date', 'wpcd' ),
			'raw_attributes' => array(
				'js_options'     => array(
					'dateFormat'      => 'yy-mm-dd',
					'showButtonPanel' => false,
				),
				// Display inline?
				'inline'         => false,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'reboot_date',
				'columns'        => 4,
			),
			'type'           => 'date',
		);
		$actions['server-schedule-reboot-soft-time'] = array(
			'label'          => __( 'Reboot Time', 'wpcd' ),
			'raw_attributes' => array(
				'js_options'     => array(
					'stepMinute'      => 5,
					'showButtonPanel' => false,
					'oneLine'         => true,
				),
				// Display inline?
				'inline'         => false,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'reboot_time',
				'columns'        => 4,
			),
			'type'           => 'time',
		);

		$actions['server-schedule-reboot-soft'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Schedule Restart', 'wpcd' ),
				'confirmation_prompt' => __( 'Are you sure you would like to schedule this server to be restarted?', 'wpcd' ),
				'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_server-schedule-reboot-soft-date', '#wpcd_app_action_server-schedule-reboot-soft-time' ) ),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * Forced Poweroff via the Cloud Providers' API
		 */

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to turn off this server? In some cases it will be like pulling the powercord while the server is turned on which could result in loss of data!', 'wpcd' );

		$actions['server-shutdown-hard-header'] = array(
			'label'          => '<i class="fa-duotone fa-plug-circle-xmark"></i> ' . __( 'Hard Powerdown', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Send a shutdown command to the server using the providers API - in some cases this will be the equivalent of ripping the powercord out of the socket.', 'wpcd' ),
			),
		);

		$actions['server-hard-shutdown'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Hard Shutdown', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * Turn on the server
		 */

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to turn on this server?', 'wpcd' );

		$actions['server-turn-on-header'] = array(
			'label'          => '<i class="fa-duotone fa-plug-circle-plus"></i> ' . __( 'Power On', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Turn on the server.', 'wpcd' ),
			),
		);

		$actions['server-turn-on'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Power On', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
				'desc'                => __( 'If this does not work you might need to log into the server provider\'s console to use the power options there.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * After reboot instructions.
		 */

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		$instructions  = __( 'After a power-on or reboot event, the server status should update automatically if CALLBACKS have been installed on the server.', 'wpcd' );
		$instructions .= '<br />' . __( 'If callbacks are not installed you can check the status of the reboot by going to the ALL CLOUD SERVERS list and clicking on the UPDATE REMOTE STATE link for the server.  In this case the server will not be available for further operations until you click that link to update the server status.', 'wpcd' );

		$actions['server-reboot-notes-header'] = array(
			'label'          => '<i class="fa-duotone fa-note"></i> ' . __( 'After-restart Instructions', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $instructions,
			),
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		return $actions;

	}

	/**
	 * Send soft reboot command
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts IF bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function reboot_soft( $id, $action ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Execute command for reboot */
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo service mysql stop; sudo echo "sudo reboot" |at now + 1 minutes ' ) );

		/* Update server meta to show operation in progress */
		update_post_meta( $id, 'wpcd_server_current_state', 'in-progress' );  // @TODO: 'in-progress' should be a constant from the main PROVIDER ancestor class.

		/**
		 * Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS.
		 * Note that we are assuming success always because we have no way of knowing if the thing worked or failed.
		 * So there is no corresponding action hook for failure.
		*/
		$success = true;
		do_action( "wpcd_server_{$this->get_app_name()}_soft_reboot_action_successful", $id, $action, $success );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'A REBOOT command has been sent to the server. If this does not restart the server then you will need to log into your server provider dashboard and use their tools to restart the server.', 'wpcd' ) );

	}

	/**
	 * Use the server provider's api to do a hard restart!
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function reboot_hard_provider( $id, $action ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Get the provider object */
		$provider     = WPCD_SERVER()->get_server_provider( $id );
		$provider_api = WPCD()->get_provider_api( $provider );

		/* Call the restart function on the api */
		if ( $provider_api && is_object( $provider_api ) ) {
			$provider_api->call( 'reboot', $instance );
		}

		/* Update server meta to show operation in progress */
		update_post_meta( $id, 'wpcd_server_current_state', 'in-progress' );  // @TODO: 'in-progress' should be a constant from the main PROVIDER ancestor class.

		/**
		 * Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS.
		 * Note that we are assuming success always because we have no way of knowing if the thing worked or failed.
		 * So there is no corresponding action hook for failure.
		*/
		$success = true;
		do_action( "wpcd_server_{$this->get_app_name()}_soft_reboot_provider_action_successful", $id, $action, $success );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'We have requested a hard reboot via the server provider API. If this does not restart the server then you will need to log into your server provider dashboard and use their tools to restart the server.', 'wpcd' ) );

	}

	/**
	 * Schedule soft reboot command on the server.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function reboot_soft_schedule( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		// Sanitize arguments array.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Bail if either date or time is empty...
		if ( empty( $args['reboot_date'] ) || empty( $args['reboot_time'] ) ) {
			return new \WP_Error( __( 'Both date and time must be provided.', 'wpcd' ) );
		}

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command( $instance, 'schedule_server_reboot.txt', array_merge( $args, array( 'action' => $action ) ) );

		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'debug', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'schedule_server_reboot.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {
			$success = array(
				'msg'     => __( 'Server restart has been scheduled!', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $success;

	}

	/**
	 * Send soft shutdown command (aka graceful reboot)
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts IF bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function graceful_shutdown( $id, $action ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Execute command for reboot */
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo service mysql stop; sudo echo "sudo shutdown" |at now + 1 minutes ' ) );

		/* Update server meta to show operation in progress */
		update_post_meta( $id, 'wpcd_server_current_state', 'off' );  // @TODO: 'off' should be a constant from the main PROVIDER ancestor class.

		/**
		 * Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS.
		 * Note that we are assuming success always because we have no way of knowing if the thing worked or failed.
		 * So there is no corresponding action hook for failure.
		*/
		$success = true;
		do_action( "wpcd_server_{$this->get_app_name()}_graceful_shutdown_action_successful", $id, $action, $success );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'A SHUTDOWN command has been sent to the server. If this does not restart the server then you will need to log into your server provider dashboard and use their tools to restart the server.', 'wpcd' ) );

	}

	/**
	 * Use the server provider's api to do turn off the server
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function hard_shutdown( $id, $action ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Get the provider object */
		$provider     = WPCD_SERVER()->get_server_provider( $id );
		$provider_api = WPCD()->get_provider_api( $provider );

		/* Call the restart function on the api */
		if ( $provider_api && is_object( $provider_api ) ) {
			$provider_api->call( 'off', $instance );
		}

		/* Update server meta to show operation in progress */
		update_post_meta( $id, 'wpcd_server_current_state', 'off' );  // @TODO: 'off' should be a constant from the main PROVIDER ancestor class.

		/**
		 * Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS.
		 * Note that we are assuming success always because we have no way of knowing if the thing worked or failed.
		 * So there is no corresponding action hook for failure.
		*/
		$success = true;
		do_action( "wpcd_server_{$this->get_app_name()}_hard_shutdown_provider_action_successful", $id, $action, $success );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'We have requested a hard shutdown via the server provider API. If this does not restart the server then you will need to log into your server provider dashboard and use their tools to restart the server.', 'wpcd' ) );
	}

	/**
	 * Use the server provider's api to do turn on the server
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function turn_on( $id, $action ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Get the provider object */
		$provider     = WPCD_SERVER()->get_server_provider( $id );
		$provider_api = WPCD()->get_provider_api( $provider );

		/* Call the restart function on the api */
		if ( $provider_api && is_object( $provider_api ) ) {
			$provider_api->call( 'on', $instance );
		}

		/* Update server meta to show operation in progress */
		update_post_meta( $id, 'wpcd_server_current_state', 'in-progress' );  // @TODO: 'in-progress' should be a constant from the main PROVIDER ancestor class.

		/**
		 * Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS.
		 * Note that we are assuming success always because we have no way of knowing if the thing worked or failed.
		 * So there is no corresponding action hook for failure.
		*/
		$success = true;
		do_action( "wpcd_server_{$this->get_app_name()}_power_on_provider_action_successful", $id, $action, $success );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'We have requested a power-on event via the server provider API. If this does not restart the server then you will need to log into your server provider dashboard and use their tools to restart the server.', 'wpcd' ) );

	}

	/**
	 * Add new bulk options in server list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function wpcd_add_new_bulk_actions_server( $bulk_array ) {

		if ( wpcd_is_admin() ) {
			$bulk_array['wpcd_soft_reboot'] = __( 'Restart Server (Soft Reboot)', 'wpcd' );
			return $bulk_array;
		}

	}

	/**
	 * Handle bulk actions for server.
	 *
	 * @param string $redirect_url  redirect url.
	 * @param string $action        bulk action slug/id - this is not the WPCD action key.
	 * @param array  $post_ids      all post ids.
	 */
	public function wpcd_bulk_action_handler_server_app( $redirect_url, $action, $post_ids ) {
		// Let's remove query args first for redirect url.
		$redirect_url = remove_query_arg( array( 'wpcd_soft_reboot' ), $redirect_url );

		// Lets make sure we're an admin otherwise return an error.
		if ( ! wpcd_is_admin() ) {
			do_action( 'wpcd_log_error', 'Someone attempted to run a function that required admin privileges.', 'security', __FILE__, __LINE__ );

			// Show error message to user at the top of the admin list as a dismissible notice.
			wpcd_global_add_admin_notice( __( 'You attempted to run a function that requires admin privileges.', 'wpcd' ), 'error' );

			return $redirect_url;
		}

		// Schedule Server Reboot.
		if ( 'wpcd_soft_reboot' === $action ) {

			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $server_id ) {
					$args['action_hook'] = 'wpcd_pending_log_soft_reboot';
					WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'soft-reboot-server', $server_id, $args, 'ready', $server_id, __( 'Schedule a Server Reboot From Bulk Operation', 'wpcd' ) );
				}

				// Add message to be displayed in admin header.
				wpcd_global_add_admin_notice( __( 'A soft reboot has been scheduled for the selected servers. You can view the progress in the PENDING TASKS screen.', 'wpcd' ), 'success' );

			}

			// @todo: show confirmation message in a dialog box or at the top of the admin screen as a dismissible notice.
		}

		return $redirect_url;
	}

	/**
	 * Soft reboot a single server - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_pending_log_soft_reboot
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which this action apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function pending_log_soft_reboot( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Soft reboot the designated server */
		do_action( 'wpcd_wordpress-reboot_soft', $server_id, 'server-reboot-soft' );

	}

	/**
	 * Handle server soft reboot successful when being processed from pending logs / via action hooks.
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from reboot function (reboot_soft above).
	 */
	public function handle_soft_reboot_success( $server_id, $action, $success_msg_array ) {

		$server_post = get_post( $server_id );

		// Bail if not a post object.
		if ( ! $server_post || is_wp_error( $server_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_server_type( $server_id ) ) {
			return;
		}

		// This only matters if we are soft-rebooting the server.  If not, then bail.
		if ( 'server-reboot-soft' !== $action ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_instance_details( $server_id );

		if ( 'wpcd_app_server' === get_post_type( $server_id ) ) {

				// Now check the pending tasks table for a record where the key=$server_id and type='soft-reboot-server' and state='in-process'
				// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
				$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'soft-reboot-server' );

			if ( $posts ) {

				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );

			}
		}

	}

}

new WPCD_WORDPRESS_TABS_SERVER_POWER();
