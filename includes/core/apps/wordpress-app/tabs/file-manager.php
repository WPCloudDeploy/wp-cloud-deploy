<?php
/**
 * File Manager tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_FILE_MANAGER.
 */
class WPCD_WORDPRESS_TABS_FILE_MANAGER extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
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

		// if the command is to install/update details tiny file manager then we need to update some postmeta items in the app with the database user id, password and tiny file manager status.
		if ( 'install_tinyfilemanager' === $command_array[0] || 'change_auth_tinyfilemanager' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'manage_tinyfilemanager.txt' );

			if ( true === $success ) {

				// We need to parse the log files to find.
				// 1. user name.
				// 2. password.
				// 3. url string.

				// split the logs into an array.
				$logs_array = explode( "\n", $logs );

				// 1. user name.
				$searchword   = 'User:';
				$matches_user = array_filter(
					$logs_array,
					function( $var ) use ( $searchword ) {
						return strpos( $var, $searchword ) !== false;
					}
				);
				// 2. password.
				$searchword       = 'Password:';
				$matches_password = array_filter(
					$logs_array,
					function( $var ) use ( $searchword ) {
						return strpos( $var, $searchword ) !== false;
					}
				);

				update_post_meta( $id, 'wpapp_file_manager_status', 'on' );
				update_post_meta( $id, 'wpapp_file_manager_user_id', array_values( $matches_user )[0] );
				update_post_meta( $id, 'wpapp_file_manager_user_password', $this::encrypt( array_values( $matches_password )[0] ) );
			}
		}

		// if the command is to remove tiny file manager then we need to remove some postmeta items and update tiny file manager status.
		if ( 'remove_tinyfilemanager' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'manage_tinyfilemanager.txt' );

			if ( true === $success ) {

					update_post_meta( $id, 'wpapp_file_manager_status', 'off' );
					delete_post_meta( $id, 'wpapp_file_manager_user_id' );
					delete_post_meta( $id, 'wpapp_file_manager_user_password' );

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
		return 'file-manager';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_file_manager_tab';
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
				'label' => __( 'File Manager', 'wpcd' ),
				'icon'  => 'fad fa-file',
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
		$valid_actions = array( 'install-tinyfilemanager', 'update-tinyfilemanager', 'remove-tinyfilemanager', 'change-auth-tinyfilemanager' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'install-tinyfilemanager':
					$result = $this->manage_tinyfilemanager( 'install_tinyfilemanager', $id );
					break;
				case 'update-tinyfilemanager':
					$result = $this->manage_tinyfilemanager( 'upgrade_tinyfilemanager', $id );
					break;
				case 'remove-tinyfilemanager':
					$result = $this->manage_tinyfilemanager( 'remove_tinyfilemanager', $id );
					break;
				case 'change-auth-tinyfilemanager':
					$result = $this->manage_tinyfilemanager( 'change_auth_tinyfilemanager', $id );
					break;
			}
		}

		return $result;

	}

	/**
	 * Manage File Manager - add, remove, update/upgrade
	 *
	 * @param string $action The action key to send to the bash script.  This is actually the key of the drop-down select.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function manage_tinyfilemanager( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action id we're trying to execute. It is usually a string without spaces, not a number. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get params of fields.

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		switch ( $action ) {

			case 'install_tinyfilemanager':
				$user_name = $args['username_for_file_manager'];
				$pass      = $args['password_for_file_manager'];

				// Double-check just in case of errors.
				if ( empty( $user_name ) || empty( $pass ) ) {
					return new \WP_Error( __( 'Username & Password require for install file manager', 'wpcd' ) );
				}

				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_tinyfilemanager.txt',
					array(
						'command' => $command,
						'action'  => $action,
						'domain'  => $domain,
						'user'    => $user_name,
						'pass'    => $pass,
					)
				);
				break;
			case 'change_auth_tinyfilemanager':
				$user_name = $args['username_for_file_manager'];
				$pass      = $args['password_for_file_manager'];

				// Double-check just in case of errors.
				if ( empty( $user_name ) || empty( $pass ) ) {
					return new \WP_Error( __( 'Username & Password require for update details', 'wpcd' ) );
				}

				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_tinyfilemanager.txt',
					array(
						'command' => $command,
						'action'  => $action,
						'domain'  => $domain,
						'user'    => $user_name,
						'pass'    => $pass,
					)
				);
				break;
			default:
				/* Update & Remove */
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_tinyfilemanager.txt',
					array(
						'command' => $command,
						'action'  => $action,
						'domain'  => $domain,
					)
				);
		}

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* Translators: %s is the action id we're trying to execute. It is usually a string without spaces, not a number. */
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

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		// Manage fields base on installed file manager.
		$file_manager_status = $this->is_file_manager_installed( $id );

		// What is the status of File Manager?
		if ( empty( $file_manager_status ) ) {
			$file_manager_status = 'off';
		}

		if ( 'off' === $file_manager_status ) {
			$desc  = __( 'The File Manager is not installed.', 'wpcd' );
			$desc .= '<br />' . __( 'To install it please enter a username & password, then click the INSTALL button.', 'wpcd' );
		} else {
			$desc = '';
		}

		$fields['file-manager-main-heading'] = array(
			'name' => __( 'File Manager', 'wpcd' ),
			'tab'  => 'file-manager',
			'type' => 'heading',
			'desc' => $desc,
		);

		if ( 'off' === $file_manager_status ) {

			// File Manager is not installed on this site, so show button & fields to install it.
			$fields[] = array(
				'id'         => 'username-for-file-manager',
				'name'       => __( 'User Name:', 'wpcd' ),
				'tab'        => 'file-manager',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					'data-wpcd-action' => 'username-for-file-manager',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'username_for_file_manager',
				),
			);

			$fields[] = array(
				'id'         => 'password-for-file-manager',
				'name'       => __( 'Password:', 'wpcd' ),
				'tab'        => 'file-manager',
				'type'       => 'password',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'password-for-file-manager',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'password_for_file_manager',
				),
			);

			$fields[] = array(
				'id'         => 'install-tinyfilemanager',
				'name'       => '',
				'tab'        => 'file-manager',
				'type'       => 'button',
				'std'        => __( 'Install', 'wpcd' ),
				'desc'       => '',
				'attributes' => array(
					// Get User Name & Password.
					'data-wpcd-fields'              => json_encode( array( '#username-for-file-manager', '#password-for-file-manager' ) ),
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'install-tinyfilemanager',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to install the File Manager?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to install File Manager.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => true,
			);
		} else {

			// use custom html to show a launch link.
			$file_manager_user_id  = get_post_meta( $id, 'wpapp_file_manager_user_id', true );
			$file_manager_password = $this::decrypt( get_post_meta( $id, 'wpapp_file_manager_user_password', true ) );

			// Remove any "user:" and "Password:" phrases that might be embedded inside the user id and password strings.
			$file_manager_user_id  = str_replace( 'User:', '', $file_manager_user_id );
			$file_manager_password = str_replace( 'Password:', '', $file_manager_password );

			if ( true === $this->get_site_local_ssl_status( $id ) ) {
				$file_manager_url = 'https://' . $this->get_domain_name( $id ) . '/' . 'filemanager';
			} else {
				$file_manager_url = 'http://' . $this->get_domain_name( $id ) . '/' . 'filemanager';
			}

			$launch = sprintf( '<a href="%s" target="_blank">', $file_manager_url ) . __( 'Launch File Manager', 'wpcd' ) . '</a>';

			$fields[] = array(
				'tab'   => 'file-manager',
				'type'  => 'custom_html',
				'std'   => $launch,
				'class' => 'button',
			);
			$fields[] = array(
				'tab'  => 'file-manager',
				'type' => 'custom_html',
				'std'  => __( 'User Name: ', 'wpcd' ) . esc_html( $file_manager_user_id ) . '<br />' . __( 'Password: ', 'wpcd' ) . esc_html( $file_manager_password ),
			);

			// New fields section for change username & password.
			$fields[] = array(
				'name' => __( 'File Manager- Change Credentials', 'wpcd' ),
				'tab'  => 'file-manager',
				'type' => 'heading',
				'desc' => __( 'Set a new username and password for the file manager', 'wpcd' ),
			);

			$fields[] = array(
				'id'         => 'username-for-file-manager',
				'name'       => __( 'User Name:', 'wpcd' ),
				'tab'        => 'file-manager',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					'data-wpcd-action' => 'username-for-file-manager',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'username_for_file_manager',
				),
			);

			$fields[] = array(
				'id'         => 'password-for-file-manager',
				'name'       => __( 'Password:', 'wpcd' ),
				'tab'        => 'file-manager',
				'type'       => 'password',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'password-for-file-manager',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'password_for_file_manager',
				),
			);

			$fields[] = array(
				'id'         => 'change-auth-tinyfilemanager',
				'name'       => '',
				'tab'        => 'file-manager',
				'type'       => 'button',
				'std'        => __( 'Update', 'wpcd' ),
				'desc'       => '',
				'attributes' => array(
					// Get User Name & Password.
					'data-wpcd-fields'              => json_encode( array( '#username-for-file-manager', '#password-for-file-manager' ) ),
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'change-auth-tinyfilemanager',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to update details?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to update File Manager.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => true,
			);

			// Fields section for update and remove of File Manager.
			$fields[] = array(
				'name' => __( 'Upgrade File Manager', 'wpcd' ),
				'tab'  => 'file-manager',
				'type' => 'heading',
				'desc' => __( 'Update the File Manager to the latest version.', 'wpcd' ),
			);

			// Update File Manager.
			$fields[] = array(
				'id'         => 'update-tinyfilemanager',
				'name'       => '',
				'tab'        => 'file-manager',
				'type'       => 'button',
				'std'        => __( 'Update File Manager', 'wpcd' ),
				'desc'       => '',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'update-tinyfilemanager',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to upgrade the File Manager?', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to upgrade File Manager.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			$fields[] = array(
				'name' => __( 'Remove File Manager', 'wpcd' ),
				'tab'  => 'file-manager',
				'type' => 'heading',
				'desc' => '',
			);

			// Remove File Manager.
			$fields[] = array(
				'id'         => 'remove-tinyfilemanager',
				'name'       => '',
				'tab'        => 'file-manager',
				'type'       => 'button',
				'std'        => __( 'Remove File Manager', 'wpcd' ),
				'desc'       => '',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'remove-tinyfilemanager',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to remove the File Manager from this site?', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to remove File Manager.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

		}

		return $fields;

	}

	/**
	 * Check whether the file manager is installed.
	 *
	 * @param int $id     The postID of the server cpt.
	 * @return boolean    true/false
	 */
	public function is_file_manager_installed( $id ) {

		$file_manager_status = get_post_meta( $id, 'wpapp_file_manager_status', 'on' );

		if ( ! empty( $file_manager_status ) ) {

			return $file_manager_status;
		}

		return 'off';
	}

}

new WPCD_WORDPRESS_TABS_FILE_MANAGER();
