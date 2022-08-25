<?php
/**
 * OLS Console
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_OLS_CONSOLE
 */
class WPCD_WORDPRESS_TABS_SERVER_OLS_CONSOLE extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.
	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'ols_console';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_ols_console_tab';
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs   The default value.
	 * @param int   $id     The post ID of the server.
	 *
	 * @return array    $tabs   New array of tabs
	 */
	public function get_tab( $tabs, $id ) {
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'OLS Console', 'wpcd' ),
				'icon'  => 'far fa-phone-office',
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
	 * Gets the fields to be shown in the OLS Console tab.
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
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/**
		 * Unlike other tabs we're not checking for an array of actions here to return an error message.
		 * This is because some of the actions are really dynamic.
		 * Instead we'll just check below directly on the action case statement and fall-through if permissions are denied.
		 */

		// Perform actions if allowed to do so.
		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'enable-ols-console':
					$action = 'enable_ols_console';
					$result = $this->manage_ols_console( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the OLS Console tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_ols_console_fields( $id );

	}

	/**
	 * Return a string that can be used in the header of the fields screen.
	 *
	 * @param string $type type.
	 */
	private function get_field_header_desc( $type ) {

		switch ( $type ) {
			case 1:
				$desc = __( 'Set user/pass for enable ols console', 'wpcd' );
				break;
			default:
				$desc = '';
				break;
		}

		return $desc;

	}

	/**
	 * Gets the fields for the services to be shown in the OLS Console tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_ols_console_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// Check ols console enable or not.

		$check_ols_console_status = get_post_meta( $id, 'wpcd_wpapp_enable_ols_console', true );

		$actions['ols-console-header-main'] = array(
			'label'          => __( 'OLS Console', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $this->get_field_header_desc( 1 ),
			),
		);

		if ( ! empty( $check_ols_console_status ) ) {

			$actions['server-status-callback-data-display'] = array(
				'type'           => 'custom_html',
				'label'          => '',
				'raw_attributes' => array(
					'std' => $this->get_ols_console_enable_details( $id ),
				),
			);
		}

		$actions['username-for-ols-console'] = array(
			'label'          => __( 'User Name', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'desc'           => '',
				'std'            => '',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'username_for_ols_console',
			),
		);

		$actions['password-for-ols-console'] = array(
			'label'          => __( 'Password', 'wpcd' ),
			'type'           => 'password',
			'raw_attributes' => array(
				'desc'           => '',
				'std'            => '',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'password_for_ols_console',
			),
		);

		$actions['enable-ols-console'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Run Now', 'wpcd' ),
				'desc'                => '', // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure want to enable ols console?', 'wpcd' ),
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_username-for-ols-console', '#wpcd_app_action_password-for-ols-console' ) ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Take the most current data and format it for a nice display
	 *
	 * @param int $id id.
	 */
	public function get_ols_console_enable_details( $id ) {

		// setup return variable.
		$return = '';

		// get data from server record.
		$check_ols_console_status = get_post_meta( $id, 'wpcd_wpapp_enable_ols_console', true );
		$ols_console_username     = get_post_meta( $id, 'wpcd_wpapp_username_for_ols_console', true );
		$ols_console_pass         = get_post_meta( $id, 'wpcd_wpapp_password_for_ols_console', true );
		// Format the data.
		$return              = '<div class="wpcd_push_data wpcd_server_status_push_data">';
				$return     .= '<div class="wpcd_push_data_inner_wrap wpcd_server_status_push_data_inner_wrap">';
				$return     .= '<div class="wpcd_push_data_label_item wpcd_server_status_push_data_label_item">';
					$return .= __( 'Enable OLS Console:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_server_status_push_data_value_item">';
					$return .= ! empty( $check_ols_console_status ) ? esc_html( 'Yes' ) : 'No';
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_label_item wpcd_server_status_push_data_label_item">';
					$return .= __( 'Enable Console SSL:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_server_status_push_data_value_item">';
					$return .= esc_html( 'No' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_label_item wpcd_server_status_push_data_label_item">';
					$return .= __( 'OLS Admin User Name:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_server_status_push_data_value_item">';
					$return .= $ols_console_username;
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_label_item wpcd_server_status_push_data_label_item">';
					$return .= __( 'OLS Admin Password:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_server_status_push_data_value_item">';
					$return .= $ols_console_pass;
				$return     .= '</div>';

			$return .= '</div>';
		$return     .= '</div>';

		return $return;
	}

	/**
	 * Install / manage OLS Console
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_ols_console( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Make sure all four required fields have been provided and return error if not.
		if ( empty( $args['username_for_ols_console'] ) ) {
			return new \WP_Error( __( 'Please enter user name', 'wpcd' ) );
		}

		// Make sure all required fields have been provided and return error if not.
		if ( empty( $args['password_for_ols_console'] ) ) {
			return new \WP_Error( __( 'Please enter password', 'wpcd' ) );
		}

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'enable_ols_console':
				$command_name               = 'enable_ols_console';
				$args['enable_ols_console'] = $this->get_command_url( $id, $command_name, 'completed' );
				break;
		}

		
		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		$user_name = $original_args['username_for_ols_console'];
		$pass      = $original_args['password_for_ols_console'];

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'ols_manage_admin_console.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'user'   => $user_name,
					'pass'   => $pass,
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'ols_manage_admin_console.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {
				case 'enable_ols_console':
					update_post_meta( $id, 'wpcd_wpapp_enable_ols_console', 'yes' );
					update_post_meta( $id, 'wpcd_wpapp_username_for_ols_console', $user_name );
					update_post_meta( $id, 'wpcd_wpapp_password_for_ols_console', $pass );
					$success = array(
						'msg'     => __( 'OLS console successfully enable.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;
			}
		}

		return $success;

	}


}

new WPCD_WORDPRESS_TABS_SERVER_OLS_CONSOLE();
