<?php
/**
 * SSH Console Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_SSH_CONSOLE
 */
class WPCD_WORDPRESS_TABS_SERVER_SSH_CONSOLE extends WPCD_WORDPRESS_TABS {

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
		return 'ssh_console';
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
		if ( true === $this->wpcd_wpapp_server_user_can( 'view_wpapp_server_ssh_console_tab', $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'SSH Console', 'wpcd' ),
				'icon'  => 'fad fa-terminal',
			);
		}
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the SERVICES tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {
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
		$valid_actions = array( 'ssh-console-execute', 'ssh-console-clear-history', 'ssh-console-toggle-tab' );
		if ( in_array( $action, $valid_actions ) ) {
			if ( false === $this->wpcd_wpapp_server_user_can( 'view_wpapp_server_ssh_console_tab', $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		// Perform actions if allowed to do so.
		if ( $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'ssh-console-execute':
					$result = $this->ssh_console_execute_command( $id, $action );
					break;
				case 'ssh-console-clear-history':
					$result = $this->ssh_console_clear_all_history( $id, $action );
					break;
				case 'ssh-console-toggle-tab':
					$result = $this->ssh_console_toggle_tab_visibility( $id, $action );
					break;

			}

			// Execute a command again - unfotunately we only know a part of.
			// the action string so we can't make it part of the switch statement.
			// above.
			if ( false !== strpos( $action, 'ssh-console-prior-cmd-exec-' ) ) {
				$result = $this->ssh_console_execute_command_again( $id, $action );
			}
			if ( false !== strpos( $action, 'ssh-console-prior-cmd-del-' ) ) {
				$result = $this->ssh_console_delete_command( $id, $action );
			}
		}

		return $result;

	}

	/**
	 * Gets the actions to be shown in the FIREWALL tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_ssh_console_fields( $id );

	}

	/**
	 * Gets the fields to shown in the SSH CONSOLE tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_ssh_console_fields( $id ) {

		// Set up metabox items.
		$actions    = array();
		$sshc_desc  = __( 'Danger Zone!', 'wpcd' );
		$sshc_desc .= '<br />';
		$sshc_desc .= __( 'This ssh "console" allows to send a single ssh command to your server and displays the results or output of that command.', 'wpcd' );
		$sshc_desc .= '<br />';
		$sshc_desc .= __( 'To prevent errors you should always send a command that is guaranteed to return a value. You can do this by using an "&" bash clause at the end of commands that have the potential to execute silently or return no data. ', 'wpcd' );
		$sshc_desc .= '<br />';
		$sshc_desc .= __( 'Please note that this tool is useful for executing a small number of of commands in a pinch.  If you need to execute a lot of ssh commands, you might be better served by logging into the server via your favorite ssh tool instead.', 'wpcd' );
		$sshc_desc .= '<br />';
		$sshc_desc .= __( 'Additionally, you cannot use this for long-running commands - each command is limited by the php script execution time.', 'wpcd' );

		$actions['ssh-console-header'] = array(
			'label'          => __( 'SSH Console', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $sshc_desc,
			),
		);

		$actions['ssh-console-cmd'] = array(
			'label'          => __( 'Command', 'wpcd' ),
			'type'           => 'textarea',
			'raw_attributes' => array(
				'label_description' => __( 'Enter a valid bash command that you would like the server to execute.', 'wpcd' ),
				'sanitize_callback' => 'none',
				// the key of the field (the key goes in the request).
				'data-wpcd-name'    => 'ssh_console_cmd',
				'placeholder'       => __( 'Enter your ssh command here', 'wpcd' ),
				'rows'              => 8,
			),
		);

		$actions['ssh-console-execute'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Execute', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to execute this command?', 'wpcd' ),
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_ssh-console-cmd' ) ),
				'columns'             => 1,
			),
			'type'           => 'button',
		);

		// Show list of prior commands...
		$prior_commands_notice = __( 'It seems that no prior commands are available.', 'wpcd' );
		$prior_commands        = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_ssh_console_cmds', true ) );

		if ( ! empty( $prior_commands ) ) {
			$prior_commands_notice = 'Here is a list of prior commands that were executed on this screen.';
		}

		$actions['ssh-console-prior-commands'] = array(
			'label'          => __( 'Prior Commands', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $prior_commands_notice,
			),
		);

		// Loop through and add each prior command to the array.
		if ( ! empty( $prior_commands ) ) {
			$prior_commands_reversed = array_reverse( $prior_commands );
			$cntr                    = 0;
			foreach ( $prior_commands_reversed as $prior_cmd ) {
				$cntr ++;
				$actions[ "ssh-console-prior-cmd-$cntr" ]      = array(
					'label'          => '',
					'type'           => 'textarea',
					'raw_attributes' => array(
						'columns'           => 8,
						'std'               => self::decrypt( $prior_cmd ),
						'rows'              => 1,
						'sanitize_callback' => 'none', // the key of the field (the key goes in the request).
						'data-wpcd-name'    => "ssh_console_prior_cmd-$cntr",
					),
				);
				$actions[ "ssh-console-prior-cmd-exec-$cntr" ] = array(
					'label'          => '',
					'type'           => 'button',
					'raw_attributes' => array(
						'columns'          => 2,
						'std'              => __( 'Execute', 'wpcd' ),
						// fields that contribute data for this action.
						'data-wpcd-fields' => json_encode( array( "#wpcd_app_action_ssh-console-prior-cmd-$cntr" ) ),
					),
				);
				$actions[ "ssh-console-prior-cmd-del-$cntr" ]  = array(
					'label'          => '',
					'type'           => 'button',
					'raw_attributes' => array(
						'columns'          => 2,
						'std'              => __( 'Delete', 'wpcd' ),
						// fields that contribute data for this action.
						'data-wpcd-fields' => json_encode( array( "#wpcd_app_action_ssh-console-prior-cmd-$cntr" ) ),
					),
				);
			}
		}

		// Add a clear history button.
		$actions['ssh-console-clear-history'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Clear All History', 'wpcd' ),
				'confirmation_prompt' => __( 'Are you sure you would like to clear all history?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		// Add a button to toggle the visibilty of the tab.
		// But only if the wp-config option is not set.
		if ( ! defined( 'WPCD_HIDE_SSH_CONSOLE' ) ) {

			// Security Warning.
			$sshc_sec_warning  = __( 'NONE of the fields on this tab are escaped or otherwise sanitized!  This makes this tab VERY dangerous if you allow other untrusted personnel to access this dashboard.', 'wpcd' );
			$sshc_sec_warning .= '<br />';
			$sshc_sec_warning .= __( 'You can quickly remove this tab from the screen by clicking the button below.', 'wpcd' );
			$sshc_sec_warning .= '<br />';
			$sshc_sec_warning .= __( 'Once you click this button you can only see this tab again by adding an entry to wp-config.php. ', 'wpcd' );
			$sshc_sec_warning .= '<br />';

			$actions['ssh-console-security-warning'] = array(
				'label'          => __( 'Security Warning', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $sshc_sec_warning,
				),
			);

			// Set prompts based on current status.
			$option_hide_ssh_console = get_option( 'wpcd_wpapp_ssh_console_hide' );
			if ( ! empty( $option_hide_ssh_console ) && true == boolval( $option_hide_ssh_console ) ) {
				$hide_ssh_console_label        = __( 'Keep Showing This Tab', 'wpcd' );
				$hide_ssh_console_label_prompt = __( 'Are you sure you would like to keep showing this tab?', 'wpcd' );
			} else {
				$hide_ssh_console_label        = __( 'Hide This Tab', 'wpcd' );
				$hide_ssh_console_label_prompt = __( 'Are you sure you would like to hide this tab? If you proceed you can only restore it with an entry in wp-config.php!', 'wpcd' );
			}

			// Define button.
			$actions['ssh-console-toggle-tab'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => $hide_ssh_console_label,
					'confirmation_prompt' => $hide_ssh_console_label_prompt,
				),
				'type'           => 'button',
			);

		} else {

			// Ends up showing a basic security warning message.
			$sshc_sec_warning                        = __( 'NONE of the fields on this tab are escaped or otherwise sanitized!  This makes this tab VERY dangerous if you allow other untrusted personnel to access this dashboard.', 'wpcd' );
			$sshc_sec_warning                       .= '<br />';
			$sshc_sec_warning                       .= __( 'You can remove this tab by using a wp-config.php entry - see documentation or contact technical support for details.', 'wpcd' );
			$actions['ssh-console-security-warning'] = array(
				'label'          => __( 'Security Warning', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $sshc_sec_warning,
				),
			);

		}

		return $actions;

	}

	/**
	 * Execute the specified SSH action
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	private function ssh_console_execute_command( $id, $action ) {

		// Get data from _POST sent via AJAX.
		// Warning: Lack of sanitization as we retrieve this field.  This is deliberate and should not be used as a template for other parts of this plugin!
		// Unlike other execute routines, we CANNOT use wp_parse_args here because it uses "&" as a delimited which can cause chained linux ssh commands to be incorrectly parsed.
		// So, we will find all "&" and replace them with "#!!!!!!!!!!#", parse and then restore the "&".
		$args                    = filter_input( INPUT_POST, 'params', FILTER_UNSAFE_RAW );
		$args                    = str_replace( '&', '#!!!!!!!!!!#', $args );
		$args                    = wp_parse_args( $args );
		$args['ssh_console_cmd'] = str_replace( '#!!!!!!!!!!#', '&', $args['ssh_console_cmd'] );

		// error out if no command.
		$ssh_cmd_to_execute = $args['ssh_console_cmd'];
		if ( empty( $ssh_cmd_to_execute ) ) {
			return new \WP_Error( 'You must provide a command to execute!', 'wpcd' );
		}

		// send the command.
		$result = $this->submit_generic_server_command( $id, $action, $ssh_cmd_to_execute, true );  // notice the last param is true to force the function to return the raw results to us for evaluation instead of a wp-error object.

		// Add or update in our database the list of commands that were executed.
		// Not that we're doing this regardless of whether the command was successful or failed.
		// Get the meta from the database.
		$prior_commands = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_ssh_console_cmds', true ) );

		if ( empty( $prior_commands ) ) {
			$prior_commands = array();
		}

		// If item is already in the array, remove it so that we can insert it back into the top.
		// Note that the key is just a hash of the command and by placing.
		// An "x" in front the of hash, forcing it to be recognized as a string
		// instead of an integer as array operations sometimes want to do if
		// the hash turns out to be all numbers.
		$arrkey = 'x' . (string) hash( 'md5', $ssh_cmd_to_execute );
		if ( isset( $prior_commands[ $arrkey ] ) ) {
			unset( $prior_commands[ $arrkey ] );
		}
		// Add command to the top of the array.
		$prior_commands[ $arrkey ] = self::encrypt( $ssh_cmd_to_execute );

		// Store the array.
		update_post_meta( $id, 'wpcd_wpapp_ssh_console_cmds', $prior_commands );

		// Make sure we handle errors from the executed command.
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because an error occured: %s', 'wpcd' ), $result->get_error_message() ) );
		} else {
			// Construct an appropriate return message.
			// Right now '$result' is just a string.
			// Need to turn it into an array for consumption by the JS AJAX beast.
			$result = array(
				'msg'     => $result,
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Execute the specified SSH action again
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	private function ssh_console_execute_command_again( $id, $action ) {

		// Get data from _POST sent via AJAX.
		// Unlike other execute routines, we CANNOT use wp_parse_args here because it uses "&" as a delimited which can cause chained linux ssh commands to be incorrectly parsed.
		// So, we will find all "&" and replace them with "#!!!!!!!!!!#", parse and then restore the "&".
		$args = filter_input( INPUT_POST, 'params', FILTER_UNSAFE_RAW );
		$args = str_replace( '&', '#!!!!!!!!!!#', $args );
		$args = wp_parse_args( $args );
		$args = str_replace( '#!!!!!!!!!!#', '&', $args );

		// error out if no command.
		if ( empty( $args ) ) {
			return new \WP_Error( 'It looks like there is no command to execute!', 'wpcd' );
		}

		// Get the first command in $args.  There should be only one!
		$ssh_cmd_to_execute = array_values( $args )[0];
		if ( empty( $ssh_cmd_to_execute ) ) {
			return new \WP_Error( 'You must provide a command to execute!', 'wpcd' );
		}

		// send the command.
		$result = $this->submit_generic_server_command( $id, $action, $ssh_cmd_to_execute, true );  // notice the last param is true to force the function to return the raw results to us for evaluation instead of a wp-error object.

		// Add or update in our database the list of commands that were executed.
		// Not that we're doing this regardless of whether the command was successful or failed.
		// Get the meta from the database.
		$prior_commands = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_ssh_console_cmds', true ) );

		if ( empty( $prior_commands ) ) {
			$prior_commands = array();
		}

		// If item is already in the array, remove it so that we can insert it back into the top.
		// Note that the key is just a hash of the command and by placing.
		// An "x" in front the of hash, forcing it to be recognized as a string
		// instead of an integer as array operations sometimes want to do if
		// the hash turns out to be all numbers.
		$arrkey = 'x' . (string) hash( 'md5', $ssh_cmd_to_execute );
		if ( isset( $prior_commands[ $arrkey ] ) ) {
			unset( $prior_commands[ $arrkey ] );
		}
		// Add command to the top of the array.
		$prior_commands[ $arrkey ] = self::encrypt( $ssh_cmd_to_execute );

		// Store the array.
		update_post_meta( $id, 'wpcd_wpapp_ssh_console_cmds', $prior_commands );

		// Make sure we handle errors from the executed command.
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because an error occured: %s', 'wpcd' ), $result->get_error_message() ) );
		} else {
			// Construct an appropriate return message.
			// Right now '$result' is just a string.
			// Need to turn it into an array for consumption by the JS AJAX beast.
			$result = array(
				'msg'     => $result,
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Delete a prior ssh action from the saved array
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	private function ssh_console_delete_command( $id, $action ) {

		// Get data from _POST sent via AJAX.
		// Warning: Lack of sanitization as we retrieve this field.  This is deliberate and should not be used as a template for other parts of this plugin!
		$args = wp_parse_args( $_POST['params'] );

		// error out if no command.
		if ( empty( $args ) ) {
			return new \WP_Error( 'It looks like there is no command to delete!', 'wpcd' );
		}

		// Get the first command in $args.  There should be only one!
		$ssh_cmd_to_delete = array_values( $args )[0];
		if ( empty( $ssh_cmd_to_delete ) ) {
			return new \WP_Error( 'You must provide a command to delete!', 'wpcd' );
		}

		// Retrieve array.
		$prior_commands = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_ssh_console_cmds', true ) );

		// What index should we delete from the array?
		$arrkey = 'x' . (string) hash( 'md5', $ssh_cmd_to_delete );

		// Delete it from the array.
		if ( isset( $prior_commands[ $arrkey ] ) ) {
			unset( $prior_commands[ $arrkey ] );

			// Save the new array.
			update_post_meta( $id, 'wpcd_wpapp_ssh_console_cmds', $prior_commands );
		}

		// Send back results.
		$result = array(
			'msg'     => __( 'Command removed.', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Delete all history
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	private function ssh_console_clear_all_history( $id, $action ) {

		// Save the new array.
		delete_post_meta( $id, 'wpcd_wpapp_ssh_console_cmds' );

		// Send back results.
		$result = array(
			'msg'     => __( 'History has been deleted.', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Toogle tab visibilty
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	private function ssh_console_toggle_tab_visibility( $id, $action ) {

		// Get current option.
		$current = get_option( 'wpcd_wpapp_ssh_console_hide' );

		// delete the option if it exists...
		delete_option( 'wpcd_wpapp_ssh_console_hide' );

		if ( true == boolval( $current ) ) {
			update_option( 'wpcd_wpapp_ssh_console_hide', false );
		} else {
			update_option( 'wpcd_wpapp_ssh_console_hide', true );
		}

		// Send back results.
		$result = array(
			'msg'     => __( 'Tab visibility has changed.', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

}

new WPCD_WORDPRESS_TABS_SERVER_SSH_CONSOLE();
