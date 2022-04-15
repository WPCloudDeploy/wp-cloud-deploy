<?php
/**
 * Users Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_USERS
 */
class WPCD_WORDPRESS_TABS_SERVER_USERS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'server-users';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_backup_tab';
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
				'label' => __( 'Users', 'wpcd' ),
				'icon'  => 'fad fa-user-unlock',
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
	 * Gets the fields to be shown in the USERS tab.
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

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'users-root-create-pw', 'users-root-reset-pw', 'users-root-enable-pw-auth', 'users-root-disable-pw-auth' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		// Perform actions if allowed to do so.
		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'users-root-create-pw':
				case 'users-root-reset-pw':
					$result = $this->manage_root_user_password( $id, $action );
					break;
				case 'users-root-enable-pw-auth':
				case 'users-root-disable-pw-auth':
					$result = $this->manage_root_user_pw_auth( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the USERS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_user_fields( $id );

	}

	/**
	 * Gets the fields to shown in the USER tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_user_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// Description to be used in the first heading section of this tab.
		$root_user_top_desc = __( 'Set or Reset Root Password.', 'wpcd' );

		// Description to be used in the footer of this tab.
		$root_user_footer_desc  = __( 'We do not set a password for your root or primary user.  However, you might want to set one so that you can login via your providers\' console.', 'wpcd' );
		$root_user_footer_desc .= '<br />';
		$root_user_footer_desc .= __( 'This can be useful if you are ever locked out from your server - eg: if you are locked out via fail2ban or crowdsec.', 'wpcd' );
		$root_user_footer_desc .= '<br />';
		$root_user_footer_desc .= __( 'Your providers\' console will not be able to use your ssh key pair so a password will be your only option. ', 'wpcd' );
		$root_user_footer_desc .= '<br />';

		// Get root user name.
		$root_user = $this->get_root_user_name( $id );
		if ( empty( $root_user ) ) {
			$root_user_top_desc .= '<br /><b>' . __( 'Unable to locate the root user name for this server', 'wpcd' ) . '</b>';
		}

		// Get base provider slug.
		$base_provider = $this->get_base_provider( $id );

		// Certain server providers should not allow for changing of the password...
		if ( in_array( $base_provider, array( 'awsec2', 'awslightsail' ) ) ) {

			$actions['users-root-header-not-applicable'] = array(
				'label'          => __( 'Manage Root User Attributes', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Unfortunately you cannot change the primary root/sudo password or other related attributes for this server provider', 'wpcd' ),
				),
			);

			return $actions;

		}

		// We have a root user so do root user things...
		if ( ! empty( $root_user ) ) {

			// Paint header.
			$actions['users-root-header'] = array(
				'label'          => __( 'Manage Root User Password', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $root_user_top_desc,
				),
			);

			// Is there an existing password?
			$root_user_pw = get_post_meta( $id, 'wpcd_server_root_user_pw', true );
			if ( empty( $root_user_pw ) ) {
				// show option to create root user password.
				$actions['users-root-create-pw'] = array(
					'label'          => __( 'Create Password', 'wpcd' ),
					'raw_attributes' => array(
						'std'                 => __( 'Create', 'wpcd' ),
						'desc'                => sprintf( __( 'You have not created a password for the user: %s.  Click this button to do it now.', 'wpcd' ), $root_user ),
						// make sure we give the user a confirmation prompt.
						'confirmation_prompt' => __( 'Are you sure you would like to create a root user password?', 'wpcd' ),
						'tooltip'             => __( 'We will create a 32 character alpha-numeric root password and store it encrypted in the database.', 'wpcd' ),
					),
					'type'           => 'button',
				);

			} else {
				// Show the root user password and offer to reset it.
				$decrypted_pw = WPCD()->decrypt( $root_user_pw );

				$actions['users-root-show-pw'] = array(
					'label'          => __( 'Current Password', 'wpcd' ),
					'type'           => 'custom_html',
					'raw_attributes' => array(
						'std' => $decrypted_pw,
					),
				);

				$actions['users-root-reset-pw'] = array(
					'label'          => __( 'Reset Password', 'wpcd' ),
					'raw_attributes' => array(
						'std'                 => __( 'Reset', 'wpcd' ),
						// make sure we give the user a confirmation prompt.
						'confirmation_prompt' => __( 'Are you sure you would like to reset the root user password?', 'wpcd' ),
						'tooltip'             => __( 'We will create a 32 character alpha-numeric root password and store it encrypted in the database.', 'wpcd' ),
					),
					'type'           => 'button',
				);

			}

			/**
			 * Enable/Disable Password Authentication
			 */
			$actions['users-root-pw-auth-header']  = array(
				'label'          => __( 'Manage Root User Password Authentication', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Enable or disable sFTP password authentication for the primary root or sudo user.', 'wpcd' ),
				),
			);
			$actions['users-root-enable-pw-auth']  = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Enable Password Authentication', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to enable password authentication for the primary root/sudo user?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);
			$actions['users-root-disable-pw-auth'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Disable Password Authentication', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to disable password authentication for the primary root/sudo user?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);

		}

		return $actions;

	}

	/**
	 * Get the root user name from the server api.
	 *
	 * @param int $id The id of the server post.
	 *
	 * @return string The root user for the server provider or that is stored on the server post record.
	 *
	 * @TODO: Need to replace this function with the one in the class-wpcd-server.php file.
	 */
	public function get_root_user_name( $id ) {

		// Is a root user specified on the server post?  If so, return it.
		$server_root_user = get_post_meta( $id, 'wpcd_server_ssh_root_user', true );
		if ( ! empty( $server_root_user ) ) {
			return $server_root_user;
		}

		// Get the default root user from the provider.
		$provider     = get_post_meta( $id, 'wpcd_server_provider', true );
		$provider_api = WPCD()->get_provider_api( $provider );
		if ( $provider_api ) {
			$root_user = WPCD()->get_provider_api( $provider )->get_root_user();
		} else {
			$root_user = '';
		}
		return $root_user;

	}

	/**
	 * Get the base provider from the server api.
	 *
	 * @param int $id The id of the server post.
	 *
	 * @return string   The root user for the server provider or that is stored on the server post record.
	 *
	 * Note: This function might need to go up to the WPCD class.  Right now it's logic is only
	 * needed here but I can see where it might be needed elsewhere in the future.
	 */
	public function get_base_provider( $id ) {

		$provider     = get_post_meta( $id, 'wpcd_server_provider', true );
		$provider_api = WPCD()->get_provider_api( $provider );
		if ( $provider_api ) {
			$base_provider = WPCD()->get_provider_api( $provider )->get_base_provider_slug();
		} else {
			$base_provider = '';
		}
		return $base_provider;

	}

	/**
	 * Set or Reset Root User password
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean  success/failure/other
	 */
	private function manage_root_user_password( $id, $action ) {

		// Get the root user name.
		$root_user = $this->get_root_user_name( $id );

		// Get a new password.
		$new_pw = wpcd_random_str( 32, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' );

		// Construct command.
		$command = "echo -e '$new_pw\n$new_pw' | passwd $root_user && echo '\nPassword Successfully Updated' ";

		// send the command.
		$result = $this->submit_generic_server_command( $id, $action, $command, true );  // notice the last parm is true to force the function to return the raw results to us for evaluation instead of a wp-error object.

		// If not error, then add or update in our database the list of ports that are opened or closed.
		if ( ( ! is_wp_error( $result ) ) && $result ) {

			if ( strpos( $result, 'Password Successfully Updated' ) !== false ) {
				update_post_meta( $id, 'wpcd_server_root_user_pw', WPCD()->encrypt( $new_pw ) );
				$msg    = __( 'Password Successfully Updated.', 'wpcd' );
				$result = array(
					'msg'     => $msg,
					'refresh' => 'yes',
				);
			} else {
				$result = sprintf( __( 'Error encountered when attempting to change password: %s', 'wpcd' ), $result );
				$result = array(
					'msg'     => $result,
					'refresh' => 'yes',
				);
			}
		} else {

			// Make sure we handle errors.
			if ( is_wp_error( $result ) ) {
				return new \WP_Error( sprintf( __( 'Unable to execute this request because an error ocurred: %s', 'wpcd' ), $result->get_error_message() ) );
			} else {
				// Construct an appropriate return message.
				// Right now '$result' is just a string.
				// Need to turn it into an array for consumption by the JS AJAX beast.
				$result = array(
					'msg'     => $result,
					'refresh' => 'yes',
				);
			}
		}

		return $result;

	}


	/**
	 * Enable or disable password authentication for the root user.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean  success/failure/other
	 */
	private function manage_root_user_pw_auth( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the root user name.
		$root_user = $this->get_root_user_name( $id );

		// Bail if no root user.
		if ( empty( $root_user ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we could not find the primary root / sudo user. Action: %s', 'wpcd' ), $action ) );
		}

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'users-root-enable-pw-auth':
				// Reset the action name that is expected by the bash script.
				$action = 'enable_passauth_ssh';
				break;
			case 'users-root-disable-pw-auth':
				// Reset the action name that is expected by the bash script.
				$action = 'disable_passauth_ssh';
				break;
		}

		// Setup an args array.
		$args['ssh_user'] = $root_user;

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
		$run_cmd = $this->turn_script_into_command( $instance, 'toggle_password_auth_misc.txt', array_merge( $args, array( 'action' => $action ) ) );

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'toggle_password_auth_misc.txt' );

		// Check for success.
		if ( ! $success ) {
			// we really shouldn't get here since we would have handled errors after either the first or second script above.
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {

				case 'enable_passauth_ssh':
					$success = array(
						'msg'     => __( 'Password authentication has been ENABLED for the primary root/sudo user for this server.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_passauth_ssh':
					$success = array(
						'msg'     => __( 'Password authentication has been DISABLED for the primary root/sudo user for this server.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;
			}
		}

		return $success;

	}

}

new WPCD_WORDPRESS_TABS_SERVER_USERS();
