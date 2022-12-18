<?php
/**
 * Services Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_GIT_CONTROL
 */
class WPCD_WORDPRESS_TABS_SERVER_GIT_CONTROL extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

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

		if ( get_post_type( $id ) !== 'wpcd_app_server' ) {
			return;
		}

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905.
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run.
		// [1] => cf1110.wpvix.com.
		// [2] => 911.
		$command_array = explode( '---', $name );

		// if the command is to install memcached we need to make sure that we stamp the server record with the status indicating that memcached was installed.
		if ( 'git_install' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'git_control_server.txt' );

			if ( true === (bool) $success ) {
				// Update the meta on the server to indicate git is installed.
				$this->set_git_status( $id, true );
			}
		}

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );  // Should really only exist on an app.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );  // Should really only exist on an app.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );  // Should really only exist on an app.
		delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_status" );  // Should really only exist on a server.
		delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action" );  // Should really only exist on a server.
		delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_args" );  // Should really only exist on a server.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'git-server-control';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_git_control_tab';
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
				'label' => __( 'Git', 'wpcd' ),
				'icon'  => 'fa-duotone fa-code-merge',
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
	 * Get the fields to be shown in the GIT tab.
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

		return $this->get_fields_for_tab( $fields, $id, 'git-server-control' );

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

		// Skipping security action check that would allow us to show the user a nice message if it failed.
		// If user is not permitted to do something and actually somehow ends up here, it will fall through the SWITCH statement below and silently fail.

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'git-server-control-install':
					$action = 'git_install';
					$result = $this->git_install_server( $id, $action );
					break;
				case 'git-server-control-update':
					$action = 'git_update';
					$result = $this->git_upgrade_server( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the GIT tab.
	 *
	 * @param int $id id.
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_git_fields( $id );

	}

	/**
	 * Gets the fields for the services to be shown in the SERVICES tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_git_fields( $id ) {

		// Is git installed on the server?
		$git_server_status = $this->get_git_status( $id );

		// Set header message based on whether git is installed or not.
		if ( true === $git_server_status ) {
			$header_msg = __( 'Git is installed on this server. If you wish you can upgrade it using the options below', 'wpcd' );
		} else {
			$header_msg = __( 'Git is not installed on this server.', 'wpcd' );
		}

		// Set up metabox items.
		$actions = array();

		$actions['git-server-control-header'] = array(
			'label'          => __( 'Git', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $header_msg,
			),
		);
		if ( true === $git_server_status ) {
			$actions['git-server-control-update'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => __( 'Update Git To Latest Version', 'wpcd' ),
					'confirmation_prompt' => __( 'Are you sure you would like to upgrade to the latest version of Git on this server?', 'wpcd' ),
					// show log console?
					'log_console'         => true,
					// Initial console message.
					'console_message'     => __( 'Preparing to upgrade to the latest version of Git on this server!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the installation has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the installation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'type'           => 'button',
			);
		} else {
			$actions['git-server-control-install'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => __( 'Install Git', 'wpcd' ),
					'confirmation_prompt' => __( 'Are you sure you would like to install the Git on this server?', 'wpcd' ),
					// show log console?
					'log_console'         => true,
					// Initial console message.
					'console_message'     => __( 'Preparing to install the Git on this server!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the installation has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the installation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'type'           => 'button',
			);
		}

		return $actions;

	}

	/**
	 * Install Git.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case 'git_install').
	 *
	 * @return boolean  success/failure/other
	 */
	private function git_install_server( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		// Setup unique command name.
		$domain                = 'wpcd-dummy.com';
		$command               = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command']   = $command;
		$instance['app_id']    = $id;   // @todo - this is not really the app id - need to test to see if the process will work without this array element.
		$instance['server_id'] = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'git_control_server.txt',
			array(
				'command' => $command,
				'action'  => $action,
				'domain'  => $domain,
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* translators: %s is replaced with the internal action name. */
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
	 * Upgrade Git to the latest version.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case 'git_update').
	 *
	 * @return boolean  success/failure/other
	 */
	private function git_upgrade_server( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		// Setup unique command name.
		$domain                = 'wpcd-dummy.com';
		$command               = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command']   = $command;
		$instance['app_id']    = $id;   // @todo - this is not really the app id - need to test to see if the process will work without this array element.
		$instance['server_id'] = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'git_control_server.txt',
			array(
				'command' => $command,
				'action'  => $action,
				'domain'  => $domain,
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* translators: %s is replaced with the internal action name. */
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


}

new WPCD_WORDPRESS_TABS_SERVER_GIT_CONTROL();
