<?php
/**
 * Callbacks Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_CALLBACKS.
 */
class WPCD_WORDPRESS_TABS_SERVER_CALLBACKS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

		// Add bulk action option to the server list screen to install callbacks.
		add_filter( 'bulk_actions-edit-wpcd_app_server', array( $this, 'wpcd_add_new_bulk_actions_server' ) );

		// Action hook to handle bulk actions for server. For example to bulk install callbacks.
		add_filter( 'handle_bulk_actions-edit-wpcd_app_server', array( $this, 'wpcd_bulk_action_handler_server_app' ), 10, 3 );

		// Allow the manage_server_status_callback action to be triggered via an action hook.  Will primarily be used by the woocommerce add-ons & Bulk Actions.
		add_action( 'wpcd_wordpress-manage_server_status_callback', array( $this, 'manage_server_status_callback' ), 10, 2 );

		/* Pending Logs Background Task: Trigger installation of callbacks on a server */
		add_action( 'pending_log_install_a_callback', array( $this, 'pending_log_install_a_callback' ), 10, 3 );

		/* Handle callback success and tag the pending log record as successful */
		add_action( 'wpcd_server_wordpress-app_server_status_callback_action_successful', array( $this, 'handle_server_status_callback_install_success' ), 10, 3 );

		/* Handle callback failures and tag the pending log record as failed */
		add_action( 'wpcd_server_wordpress-app_server_status_callback_second_action_failed', array( $this, 'handle_server_status_callback_install_failed' ), 10, 3 );
		add_action( 'wpcd_server_wordpress-app_server_status_callback_first_action_failed', array( $this, 'handle_server_status_callback_install_failed' ), 10, 3 );

		/* Pending Logs Background Task: Run callback for the first time on a server after they're installed */
		add_action( 'run_server_callbacks', array( $this, 'run_server_callbacks' ), 10, 3 );

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

		if ( get_post_type( $id ) !== 'wpcd_app_server' ) {
			return;
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
		$tabs['callbacks'] = array(
			'label' => __( 'Callbacks', 'wpcd' ),
			'icon'  => 'far fa-phone-office',
		);
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the CALLBACKS tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {
		return $this->get_fields_for_tab( $fields, $id, 'callbacks' );
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

		switch ( $action ) {
			case 'server-status-callback-install':
				$action = 'install_status_cron';
				$result = $this->manage_server_status_callback( $id, $action );
				break;
			case 'server-status-callback-remove':
				$action = 'remove_status_cron';
				$result = $this->manage_server_status_callback( $id, $action );
				break;
			case 'server-status-callback-run':
				$action = 'run_status_cron_background';
				$result = $this->manage_server_status_callback( $id, $action );
				break;
			case 'server-status-callback-clear-history-meta':
				$result = $this->manage_server_status_callback( $id, $action );
				break;
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the CALLBACKS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_callback_fields( $id );

	}

	/**
	 * Gets the fields for the services to be shown in the CALLBACKS tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_callback_fields( $id ) {

		$actions = array();

		$actions = $this->get_callback_fields_for_server_status( $id );

		return $actions;

	}

	/**
	 * Gets the fields for the SERVER STATUS callback to be shown in the CALLBACKS tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_callback_fields_for_server_status( $id ) {

		// Set up metabox items.
		$actions = array();

		// Is the server status & restart callbacks installed?
		$server_status_callback_status = get_post_meta( $id, 'wpcd_wpapp_server_status_callback_installed', true );

		if ( empty( $server_status_callback_status ) ) {
			// The server status callback is not installed.
			$desc = __( 'Install two callback scripts so that the server can push some basic data elements to this console about reboots, software updates and more.', 'wpcd' );

			$actions['server-status-callback-header'] = array(
				'label'          => __( 'Server Status Callbacks', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// Since the server status callback is not installed show only install button.
			$actions['server-status-callback-install'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Install', 'wpcd' ),
					'desc'                => '', // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to install these callbacks?', 'wpcd' ),
				),
				'type'           => 'button',
			);

		}

		if ( 'yes' === $server_status_callback_status ) {
			/* The server status callback is installed and active */
			$desc = __( 'When the server starts pushing information to this console you will see it here.', 'wpcd' );

			$actions['server-status-callback-header'] = array(
				'label'          => __( 'Server Status Callback', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			$actions['server-status-callback-data-display'] = array(
				'type'           => 'custom_html',
				'label'          => '',
				'raw_attributes' => array(
					'std' => $this->get_formatted_server_status_callback_data_for_display( $id ),
				),
			);

			// Run now.
			$actions['server-status-callback-run'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Run Now', 'wpcd' ),
					'desc'                => __( 'Execute this callback immediately.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to run these callbacks immediately?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);

			// allow removal of callback.
			$actions['server-status-callback-remove'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Remove', 'wpcd' ),
					'desc'                => __( 'Uninstall this callback agent from your server.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to remove this callback?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);

			// clear history meta.
			$actions['server-status-callback-clear-history-meta'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Clear History', 'wpcd' ),
					'desc'                => __( 'Clear the history of data received.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to clear the history of this callback?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);

		}

		// What are callbacks?
		$desc  = __( 'Callbacks are little pieces of linux scripts that live and run on your server to periodically collect and push information to this console.  Normally, when you interact with your server you are actively pulling data from it.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'With callbacks, you can allow the server to actively push data to this console as events occur - even when you are not logged in or working with the server record.', 'wpcd' );

		$actions['server-status-callback-explanation'] = array(
			'label'          => __( 'What Are Callbacks?', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		return $actions;

	}

	/**
	 * Install / manage server status callback & restart callback.
	 *
	 * This function will actually chain TWO callbacks/
	 * 1. The server status callback (bash script #24) &
	 * 2. The restart callback (bash script #28).
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_server_status_callback( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		// Grab parameters but there should be nothing here for this callback.
		if ( ! empty( $_POST['params'] ) ) {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = array();
		}
		if ( empty( $args ) ) {
			$args = array();
		}

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'install_status_cron':
				// construct the callbacks.
				$command_name                   = 'server_status';
				$args['callback_server_status'] = $this->get_command_url( $id, $command_name, 'completed' );

				$command_name                  = 'sites_status';
				$args['callback_sites_status'] = $this->get_command_url( $id, $command_name, 'completed' );
				break;

			case 'remove_status_cron':
				// do nothing.
				break;

			case 'run_status_cron':
				// do nothing.
				break;

			case 'run_status_cron_background':
				// do nothing.
				break;

			case 'server-status-callback-clear-history-meta':
				delete_post_meta( $id, 'wpcd_server_status_push_history' );
				delete_post_meta( $id, 'wpcd_server_restart_push_history' );
				$success = array(
					'msg'     => __( 'History has been cleared for this callback.', 'wpcd' ),
					'refresh' => 'yes',
				);
				return $success;

		}

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		if ( ! empty( $args ) ) {
			$args = array_map(
				function( $item ) {
					return escapeshellarg( $item );
				},
				$args
			);
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command( $instance, 'server_status_callback.txt', array_merge( $args, array( 'action' => $action ) ) );

		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// Execute command and check result.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Make sure that $result is not a wp_error object.
		if ( is_wp_error( $result ) ) {
			do_action( "wpcd_server_{$this->get_app_name()}_server_status_callback_first_action_failed", $id, $action, false );
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result->get_error_message() ) );
		}

		// Verify the success or failure of the actual bash command.
		$success = $this->is_ssh_successful( $result, 'server_status_callback.txt' );

		if ( ! $success ) {
			do_action( "wpcd_server_{$this->get_app_name()}_server_status_callback_first_action_failed", $id, $action, $success );
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {
			if ( 'install_status_cron' === $action ) {
				// Run the second script.
				$success = $this->manage_server_restart_callback( $id, $action );

				// If error returned from the second script break right away.
				if ( ( ! $success ) || is_wp_error( $success ) ) {
					do_action( "wpcd_server_{$this->get_app_name()}_server_status_callback_second_action_failed", $id, $action, $success );
					return $success;
				}
			}
		}

		// Both scripts have been run.
		if ( ! $success ) {
			// we really shouldn't get here since we would have handled errors after either the first or second script above.
			do_action( "wpcd_server_{$this->get_app_name()}_server_status_callback_action_failed", $id, $action, $success );
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {

				case 'install_status_cron':
					update_post_meta( $id, 'wpcd_wpapp_server_status_callback_installed', 'yes' );
					$success = array(
						'msg'     => __( 'The server status and server restart callbacks have been installed. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'remove_status_cron':
					delete_post_meta( $id, 'wpcd_wpapp_server_status_callback_installed' );
					delete_post_meta( $id, 'wpcd_server_status_push' );
					$success = array(
						'msg'     => __( 'The callbacks have been removed.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'run_status_cron':
				case 'run_status_cron_background':
					$success = array(
						'msg'     => __( 'The callbacks have been scheduled execution and should begin shortly. Check back here or view your server list HEALTH column in a few minutes to see the updated information.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

			}

			/* Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS */
			do_action( "wpcd_server_{$this->get_app_name()}_server_status_callback_action_successful", $id, $action, $success );

		}

		return $success;

	}

	/**
	 * Install / manage server restart callback.
	 *
	 * Unlike other similar functions, this one is part of a "chain" of two routines.
	 * The first one calls this one if it's successful and it will be the one to evalauate
	 * the output of this one to determine if the full chain is successful.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_server_restart_callback( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		// Grab parameters but there should be nothing here for this callback.
		if ( ! empty( $_POST['params'] ) ) {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = array();
		}
		if ( empty( $args ) ) {
			$args = array();
		}

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'install_status_cron':
				// Since this is the second action in a chain of two actions, we need to change the action name to match what this second script requires.
				$action = 'install_callback_notify';

				// construct the callback.
				$command_name            = 'server_restart';
				$args['callback_notify'] = $this->get_command_url( $id, $command_name, 'completed' );
				break;

			case 'remove_status_cron':
				// Since this is the second action in a chain of two actions, we need to change the action name to match what this second script requires.
				$action = 'remove_callback_notify';
				break;

			case 'run_status_cron':
			case 'run_status_cron_background':
				// Since this is the second action in a chain of two actions, we need to change the action name to match what this second script requires.
				$action = 'run_callback_notify';
				break;

		}

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		if ( ! empty( $args ) ) {
			$args = array_map(
				function( $item ) {
					return escapeshellarg( $item );
				},
				$args
			);
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command( $instance, 'server_restart_callback.txt', array_merge( $args, array( 'action' => $action ) ) );

		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'server_restart_callback.txt' );

		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// Successful.
			$success = true; // this is all we need since the final output will be handled by the first calling function in the chain.
		}

		return $success;

	}

	/**
	 * Take the most current data and format it for a nice display
	 *
	 * @param int $id id.
	 */
	public function get_formatted_server_status_callback_data_for_display( $id ) {

		// setup return variable.
		$return = '';

		// get data from server record.
		$server_status_items = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_server_status_push', true ) );

		// if no data return nothing.
		if ( empty( $server_status_items ) ) {
			$return = '<div class="wpcd_no_data wpcd_server_status_push_no_data">' . __( 'No data has been received yet.', 'wpcd' ) . '</div>';
			return $return;
		}

		// ok, we've got data - format it out into variables.
		if ( isset( $server_status_items['reporting_time'] ) ) {
			$reporting_time = $server_status_items['reporting_time'];
		} else {
			$reporting_time = 0;
		}

		if ( isset( $server_status_items['restart'] ) ) {
			$restart = $server_status_items['restart'];
		} else {
			$restart = __( 'unknown', 'wpcd' );
		}

		if ( isset( $server_status_items['total_updates'] ) ) {
			$total_updates = $server_status_items['total_updates'];
		} else {
			$total_updates = 0;
		}

		if ( isset( $server_status_items['security_updates'] ) ) {
			$security_updates = $server_status_items['security_updates'];
		} else {
			$security_updates = 0;
		}

		if ( isset( $server_status_items['unattended_package_num'] ) ) {
			$unattended_package_num = $server_status_items['unattended_package_num'];
		} else {
			$unattended_package_num = 0;
		}

		// Format the data.
		$return = '<div class="wpcd_push_data wpcd_server_status_push_data">';
			/* translators: %s is replaced with date of the report. */
			$return .= '<p class="wpcd_push_data_reporting_time">' . sprintf( __( 'Data current as of: %s ', 'wpcd' ), wp_date( 'Y-m-d H:i:s', (int) $reporting_time ) ) . '</p>';
			$return .= '<div class="wpcd_push_data_inner_wrap wpcd_server_status_push_data_inner_wrap">';

				/* server restart data */
				$return     .= '<div class="wpcd_push_data_label_item wpcd_server_status_push_data_label_item">';
					$return .= __( 'Does Server Require Restart:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_server_status_push_data_value_item">';
					$return .= esc_html( $restart );
				$return     .= '</div>';

				/* total updates needed */
				$return     .= '<div class="wpcd_push_data_label_item wpcd_server_status_push_data_label_item">';
					$return .= __( 'Total number of packages needing updates:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_server_status_push_data_value_item">';
					$return .= esc_html( $total_updates );
				$return     .= '</div>';

				/* total security updates needed */
				$return     .= '<div class="wpcd_push_data_label_item wpcd_server_status_push_data_label_item">';
					$return .= __( 'Number of packages needing security updates:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_server_status_push_data_value_item">';
					$return .= esc_html( $security_updates );
				$return     .= '</div>';

				/* total unattended updates needed */
				$return     .= '<div class="wpcd_push_data_label_item wpcd_server_status_push_data_label_item">';
					$return .= __( 'Number of unattended update packages pending:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_server_status_push_data_value_item">';
					$return .= esc_html( $unattended_package_num );
				$return     .= '</div>';

			$return .= '</div>';
		$return     .= '</div>';

		return $return;
	}

	/**
	 * Add new bulk options in server list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function wpcd_add_new_bulk_actions_server( $bulk_array ) {

		if ( wpcd_is_admin() ) {
			$bulk_array['wpcd_install_callbacks'] = __( 'Install Callbacks', 'wpcd' );
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

		// Lets make sure we're an admin otherwise return an error.
		if ( ! wpcd_is_admin() ) {
			do_action( 'wpcd_log_error', 'Someone attempted to run a function that required admin privileges.', 'security', __FILE__, __LINE__ );

			// Show error message to user at the top of the admin list as a dismissible notice.
			wpcd_global_add_admin_notice( __( 'You attempted to run a function that requires admin privileges.', 'wpcd' ), 'error' );

			return $redirect_url;
		}
		// End admin checks.

		// Schedule installation of callbacks.
		if ( 'wpcd_install_callbacks' === $action ) {

			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $server_id ) {
					$args['action_hook'] = 'pending_log_install_a_callback';
					WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'install-server-callback', $server_id, $args, 'ready', $server_id, __( 'Install Callbacks From Bulk Operation', 'wpcd' ) );
				}

				// Add message to be displayed in admin header.
				wpcd_global_add_admin_notice( __( 'Server callbacks have been scheduled for installation. You can view the progress in the PENDING LOG screen.', 'wpcd' ), 'success' );

			}

			// @todo: show confirmation message in a dialog box or at the top of the admin screen as a dismissible notice.
		}

		return $redirect_url;
	}

	/**
	 * Install server callback for a single server - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: pending_log_install_a_callback
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function pending_log_install_a_callback( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Install callbacks on the designated server */
		do_action( 'wpcd_wordpress-manage_server_status_callback', $server_id, 'install_status_cron' );

	}

	/**
	 * Handle callback install successful
	 *
	 * Action Hook: wpcd_server_{$this->get_app_name()}_server_status_callback_action_successful || wpcd_server_wordpress-app_server_status_callback_action_successful
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 */
	public function handle_server_status_callback_install_success( $server_id, $action, $success_msg_array ) {
		$this->handle_server_status_callback_install_success_or_failure( $server_id, $action, $success_msg_array, 'success' );
	}

	/**
	 * Handle callback install failed
	 *
	 * Action Hook: wpcd_server_{$this->get_app_name()}_server_status_callback_first_action_failed || wpcd_server_wordpress-app_server_status_callback_first_action_failed
	 * Action Hook: wpcd_server_{$this->get_app_name()}_server_status_callback_second_action_failed || wpcd_server_wordpress-app_server_status_callback_second_action_failed
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 */
	public function handle_server_status_callback_install_failed( $server_id, $action, $success_msg_array ) {
		$this->handle_server_status_callback_install_success_or_failure( $server_id, $action, $success_msg_array, 'failed' );
	}

	/**
	 * Handle server status callback install successful or failed when being processed from pending logs / via action hooks.
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 * @param boolean $success              Was the callback installation a sucesss or failure.
	 */
	public function handle_server_status_callback_install_success_or_failure( $server_id, $action, $success_msg_array, $success ) {

		$server_post = get_post( $server_id );

		// Bail if not a post object.
		if ( ! $server_post || is_wp_error( $server_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_server_type( $server_id ) ) {
			return;
		}

		// This only matters if we were installing the callbacks.  If not, then bail.
		if ( 'install_status_cron' !== $action ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_instance_details( $server_id );

		if ( 'wpcd_app_server' === get_post_type( $server_id ) ) {

				// Now check the pending tasks table for a record where the key=$server_id and type='install-server-callbacks' and state='in-process'
				// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
				$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'install-server-callback' );

			if ( $posts ) {

				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				// And mark it as successful or failed.
				if ( 'failed' === $success ) {
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );
				} else {
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );

					// Since we have successfully installed the callbacks, we can run them once!
					$instance['action_hook'] = 'run_server_callbacks';
					WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'run-server-callbacks', $server_id, $instance, 'ready', $server_id, __( 'Run Callbacks For The First Time', 'wpcd' ) );

				}
			}
		}

	}

	/**
	 * Run server callback.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wc_run_server_callbacks
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function run_server_callbacks( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Run callbacks on the designated server */
		do_action( 'wpcd_wordpress-manage_server_status_callback', $server_id, 'run_status_cron_background' );

		/* Mark the task complete */
		WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );

	}

}

new WPCD_WORDPRESS_TABS_SERVER_CALLBACKS();
