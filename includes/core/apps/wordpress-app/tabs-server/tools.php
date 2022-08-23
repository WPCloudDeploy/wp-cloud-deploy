<?php
/**
 * Tools Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_TOOLS
 */
class WPCD_WORDPRESS_TABS_SERVER_TOOLS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		// Allow the server_cleanup_metas action to be triggered via an action hook.
		add_action( 'wpcd_wordpress-app_server_cleanup_metas', array( $this, 'server_cleanup_metas' ), 10, 2 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'svr_tools';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_tools_tab';
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
				'label' => __( 'Tools', 'wpcd' ),
				'icon'  => 'fad fa-tools',
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
	 * Gets the fields to be shown in the TOOLS tab.
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
			/* Translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'server-cleanup-metas', 'server-cleanup-rest-api-test', 'reset-server-default-php-version' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				/* Translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'server-cleanup-metas':
					$result = $this->server_cleanup_metas( $id, $action );
					break;
				case 'server-cleanup-rest-api-test':
					$result = $this->test_rest_api( $id, $action );
					break;
				case 'reset-server-default-php-version':
					$result = $this->reset_php_default_version( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the TOOLS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_tools_fields( $id );

	}

	/**
	 * Gets the fields to shown in the TOOLS tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_tools_fields( $id ) {

		$actions = array();

		/**
		 * Reset Metas
		 */

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to reset the metas for this server?', 'wpcd' );

		$actions['server-cleanup-metas-header'] = array(
			'label'          => __( 'Cleanup WordPress Metas', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'If the server gets "stuck" for some reason and you don\'t see the button to add a new site, this tool will clean up the metas on the server and give you the ability to try to add sites again.', 'wpcd' ),
			),
		);

		$actions['server-cleanup-metas'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Cleanup Metas', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
				'desc'                => '',
			),
			'type'           => 'button',
		);

		/**
		 * Run a test REST API callback.
		 */
		$actions['server-cleanup-rest-api-test-header'] = array(
			'label'          => __( 'Test REST API Access', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Run a test to see if this server can talk to the WPCD plugin via REST.  A successful test will show up in the NOTIFICATIONS log.', 'wpcd' ),
			),
		);
		$actions['server-cleanup-rest-api-test']        = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'  => __( 'Test Now', 'wpcd' ),
				'desc' => '',
			),
			'type'           => 'button',
		);

		/**
		 * Set server php default version
		 */
		$confirmation_prompt = __( 'Are you sure you would like to set update the default php version for this server?', 'wpcd' );

		$default_php_version = get_post_meta( $id, 'wpcd_default_php_version', true );
		if ( empty( $default_php_version ) ) {
			$default_php_version = '7.4';
		}

		$actions['reset-server-default-php-version-header'] = array(
			'label'          => __( 'Set PHP Default Version', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'The server level default PHP version should be PHP 7.4. This is the PHP version used to run all WP-CLI commands or other server level PHP scripts not running directly inside WordPress. If you set this to something other than the default 7.4 you could potentially break things - badly!', 'wpcd' ),
			),
		);

		$actions['reset-server-default-php-version-select'] = array(
			'label'          => __( 'New Server Default PHP Version', 'wpcd' ),
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => array(
					'7.4' => '7.4',
					'7.3' => '7.3',
					'7.2' => '7.2',
					'7.1' => '7.1',
					'5.6' => '5.6',
					'8.0' => '8.0',
					'8.1' => '8.1',
				),
				'std'            => $default_php_version,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'new_php_version',
			),
		);

		$actions['reset-server-default-php-version'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Reset Default PHP Version', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
				'desc'                => '',
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_reset-server-default-php-version-select' ) ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Clear out some metas on the server record.
	 *
	 * Action Hook: wpcd_wordpress-app_server_cleanup_metas (optional - most times called directly and not via an action hook.)
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function server_cleanup_metas( $id, $action ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action we are attempting to perform. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		delete_post_meta( $id, 'wpcd_server_wordpress-app_action' );
		delete_post_meta( $id, 'wpcd_server_wordpress-app_action_status' );
		delete_post_meta( $id, 'wpcd_temp_log_id' );
		delete_post_meta( $id, 'wpcd_server_action_status' );

		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );  // Should really only exist on an app.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );  // Should really only exist on an app.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );  // Should really only exist on an app.
		delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_status" );  // Should really only exist on a server and it's a duplicate of the delete a few lines above.
		delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action" );  // Should really only exist on a server and it's a duplicate of the delete a few lines above.
		delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_args" );  // Should really only exist on a server.

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'In-progress server metas have been deleted.', 'wpcd' ) );

	}

	/**
	 * Run a test REST API command from the server to he plugin.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function test_rest_api( $id, $action ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action we are attempting to perform. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$call_back = $this->get_command_url( $id, 'test_rest_api', 'completed' );

		$call_back_command = 'sudo -E wget -q ' . $call_back . ' && echo "done;" ';

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $call_back_command ) );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'The test was initiated. You should see a TEST SUCCESSFUL notification in the NOTIFICATIONS log.  If you do not see this entry in the log then it means that the server cannot reliably talk to the WPCD plugin.', 'wpcd' ) );

	}

	/**
	 * Reset the PHP default version for the server - this is used by things such as wp-cli.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if used ).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function reset_php_default_version( $id, $action ) {

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Bail if certain things are empty...
		if ( empty( $args['new_php_version'] ) ) {
			return new \WP_Error( __( 'You must specify a PHP version!', 'wpcd' ) );
		} else {
			$new_php_version = sanitize_text_field( $args['new_php_version'] );
		}

		// Check to make sure that the version is a valid version.
		if ( ! in_array( $new_php_version, array( '7.4', '7.3', '7.2', '7.1', '5.6', '8.0', '8.1' ) ) ) {
			return new \WP_Error( __( 'You must specify a VALID PHP version!', 'wpcd' ) );
		}

		// Create a var with the new version without periods.
		$new_php_version_no_periods = str_replace( '.', '', $new_php_version );

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action we are attempting to perform. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo php --version' ) );

		// Are we already at our desired PHP version?
		if ( strpos( $result, $new_php_version ) !== false ) {
			return new \WP_Error( __( 'It looks like your current default PHP version is already at your desired version. No changes were made.', 'wpcd' ) );
		} else {
			/**
			 * Not at our desired php version - so change it.
			 * The change commands depend on the web server type - OLS has a more complex set.
			 */
			// What type of web server are we running?
			$webserver_type = $this->get_web_server_type( $id );

			// Based on the webserver type the actual php version we pass in is slightly different.
			$server_php_version = '';

			switch ( $webserver_type ) {
				case 'ols':
				case 'ols-enterprise':
					$server_php_version = 'lsphp' . $new_php_version_no_periods;
					break;

				case 'nginx':
				default:
					$server_php_version = $new_php_version;
					break;
			}

			// Add the new version var to the args array - this is what the bash script will expect in the environment vars.
			$args['server_php_version'] = $server_php_version;

			// Get the full command to be executed by ssh.
			$run_cmd = $this->turn_script_into_command( $instance, 'server_php_version.txt', array_merge( $args, array( 'action' => $action ) ) );

			// Log what we're doing.
			do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

			// Run the command.
			$result2_1 = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
			$success   = $this->is_ssh_successful( $result2_1, 'server_php_version.txt' );
			if ( ! $success ) {
				$result2 = $result2_1;
			} else {
				$result2 = __( 'It looks like the attempt to change the PHP version may have been successful - see SSH logs for the full log of this action.', 'wpcd' );
			}

			/*
			switch ( $webserver_type ) {
				case 'ols':
				case 'ols-enterprise':
					// notice double-quotes in the command and the embedded php variable!
					$result2_1 = $this->execute_ssh( 'generic', $instance, array( 'commands' => "sudo update-alternatives --install /usr/bin/php php /usr/local/lsws/lsphp$new_php_version_no_periods/bin/php 111 && echo 'Done Part 1'" ) );
					$result2_2 = $this->execute_ssh( 'generic', $instance, array( 'commands' => "sudo update-alternatives --install /usr/bin/phar phar /usr/local/lsws/lsphp$new_php_version_no_periods/bin/phar$new_php_version.phar 111 && echo 'Done Part 2'" ) );
					$result2_3 = $this->execute_ssh( 'generic', $instance, array( 'commands' => "sudo update-alternatives --install /usr/bin/phar.phar phar.phar /usr/local/lsws/lsphp$new_php_version_no_periods/bin/phar$new_php_version.phar 111 && echo 'Done Part 3'" ) );
					$result2_4 = $this->execute_ssh( 'generic', $instance, array( 'commands' => "sudo update-alternatives --install /usr/bin/pecl pecl /usr/local/lsws/lsphp$new_php_version_no_periods/bin/pecl 111 && echo 'Done Part 4'" ) );
					$result2_5 = $this->execute_ssh( 'generic', $instance, array( 'commands' => "sudo update-alternatives --install /usr/lib/pear pear /usr/local/lsws/lsphp$new_php_version_no_periods/bin/pear 111 && echo 'Done Part 5'" ) );
					if ( ! is_wp_error( $result2_1 ) ) {
						$result2 = $result2_1 . ' ' . $result2_2 . ' ' . $result2_3 . ' ' . result2_4 . ' ' . result2_5;
						if ( empty( $result2 ) ) {
							$result2 = __( 'It looks like no data was returned when trying to change PHP versions.  Check SSH and Error logs for more info.', 'wpcd' );
						}
					} else {
						$result2 = __( 'It looks like an error was thrown when trying to change PHP versions.  Check SSH and Error logs for more info.', 'wpcd' );
					}
					break;

				case 'nginx':
				default:
					$result2 = $this->execute_ssh( 'generic', $instance, array( 'commands' => "sudo update-alternatives --set php /usr/bin/php$new_php_version" ) );  // notice double-quotes in the command and the embedded php variable!
					break;

			}
			*/
			// And check version again.
			$result3 = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo php --version' ) );

			// Return the data as an error so it can be shown in a dialog box.
			$preamble1  = '========================' . PHP_EOL;
			$preamble1 .= __( 'This was your prior default server PHP version.' . PHP_EOL, 'wpcd' );
			$preamble1 .= '========================' . PHP_EOL;

			$preamble2  = '========================' . PHP_EOL;
			$preamble2 .= __( 'This is the result of attempting to change your php version..' . PHP_EOL, 'wpcd' );
			$preamble2 .= '========================' . PHP_EOL;

			$preamble3  = '========================' . PHP_EOL;
			$preamble3 .= __( 'This is your new default server PHP version.' . PHP_EOL, 'wpcd' );
			$preamble3 .= '========================' . PHP_EOL;

			// Set postmeta.  But only update it if the new version matches the requested version.
			if ( strpos( $result3, $new_php_version ) !== false ) {
				update_post_meta( $id, 'wpcd_default_php_version', $new_php_version );
			}

			return new \WP_Error( $preamble1 . $result . $preamble2 . $result2 . $preamble3 . $result3 );
		}

	}


}

new WPCD_WORDPRESS_TABS_SERVER_TOOLS();
