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
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		// Allow the server_cleanup_metas action to be triggered via an action hook.
		add_action( 'wpcd_wordpress-app_server_cleanup_metas', array( $this, 'server_cleanup_metas' ), 10, 2 );

	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['svr_tools'] = array(
			'label' => __( 'Tools', 'wpcd' ),
			'icon'  => 'fad fa-tools',
		);
		return $tabs;
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
		return $this->get_fields_for_tab( $fields, $id, 'svr_tools' );

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
			case 'remove-php80rc1-reset-imagick':
				$result = $this->remove_80rc1_reset_imagick( $id, $action );
				break;

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
				'desc' => __( 'The default PHP version should be PHP 7.4.  However some automatic security upgrades might set this to PHP 8.0 which will break things - badly!  Use this to reset the default PHP version to 7.4.', 'wpcd' ),
			),
		);

		$actions['reset-server-default-php-version-select'] = array(
			'label'          => __( 'PHP Version', 'wpcd' ),
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => array(
					'7.4' => '7.4',
					'7.3' => '7.3',
					'7.2' => '7.2',
					'7.1' => '7.1',
					'5.6' => '5.6',
					'8.0' => '8.0',
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

		/**
		 * Remove php 8.0 RC1 remnants and update image magic to version specific modules.
		 */
		$confirmation_prompt = __( 'Are you sure you would like to remove all remnants of PHP 8.0 RC1 and reset the PHP Imagick Module?', 'wpcd' );

		$actions['remove-php80rc1-reset-imagick-header'] = array(
			'label'          => __( 'Remove PHP 8.0 RC1 Remnants', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Certain automatic updates installed PHP 8.0 RC1.  This option removes PHP 8.0 RC1 and installs version specific modules for Imagick. DO NOT USE UNLESS ADVISED BY WPCD TECH SUPPORT!', 'wpcd' ),
			),
		);

		$actions['remove-php80rc1-reset-imagick'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Remove PHP 8.0 RC1 & Reset Imagick Module', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
				'desc'                => '',
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
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		delete_post_meta( $id, 'wpcd_server_wordpress-app_action' );
		delete_post_meta( $id, 'wpcd_server_wordpress-app_action_status' );
		delete_post_meta( $id, 'wpcd_temp_log_id' );
		delete_post_meta( $id, 'wpcd_server_action_status' );

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

		$args = wp_parse_args( sanitize_text_field( $_POST['params'] ) );

		// Bail if certain things are empty...
		if ( empty( $args['new_php_version'] ) ) {
			return new \WP_Error( __( 'You must specify a PHP version!', 'wpcd' ) );
		} else {
			$new_php_version = sanitize_text_field( $args['new_php_version'] );
		}

		// Check to make sure that the version is a valid version.
		if ( ! in_array( $new_php_version, array( '7.4', '7.3', '7.2', '7.1', '5.6', '8.0' ) ) ) {
			return new \WP_Error( __( 'You must specify a VALID PHP version!', 'wpcd' ) );
		}

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo php --version' ) );

		// Are we already at our desired PHP version?
		if ( strpos( $result, $new_php_version ) !== false ) {
			return new \WP_Error( __( 'It looks like your current default PHP version is already at your desired version. No changes were made.', 'wpcd' ) );
		} else {
			// Not at our desired php version - so change it.
			$result2 = $this->execute_ssh( 'generic', $instance, array( 'commands' => "sudo update-alternatives --set php /usr/bin/php$new_php_version" ) );  // notice double-quotes in the command and the embedded php variable!

			// And check version again.
			$result3 = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo php --version' ) );

			// Return the data as an error so it can be shown in a dialog box.
			$preamble1  = '========================' . PHP_EOL;
			$preamble1 .= __( 'This was your prior default server PHP version.' . PHP_EOL, 'wpcd' );
			$preamble1 .= '========================' . PHP_EOL;

			$preamble2  = '========================' . PHP_EOL;
			$preamble2 .= __( 'This is the result of attempting to change your php version..' . PHP_EOL, 'wpcd' );
			$preamble2 .= '========================' . PHP_EOL;

			/* $preamble = __( 'Please check the output below - it should show your desired PHP version somewhere in the text!' .PHP_EOL, 'wpcd' ) ; */
			$preamble3  = '========================' . PHP_EOL;
			$preamble3 .= __( 'This is your new default server PHP version.' . PHP_EOL, 'wpcd' );
			$preamble3 .= '========================' . PHP_EOL;

			// Set postmeta.
			update_post_meta( $id, 'wpcd_default_php_version', $new_php_version );

			return new \WP_Error( $preamble1 . $result . $preamble2 . $result2 . $preamble3 . $result3 );
		}

	}




	/**
	 * Remove PHP 8.0 RC1 and install php version specific imagick modules.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if used ).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function remove_80rc1_reset_imagick( $id, $action ) {

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo apt-get update' ) );
		$result2 = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo apt-get remove php8.0 php8.0-cli php8.0-common php8.0-imagick php8.0-opcache php8.0-readline libapache2-mod-php8.0 php-imagick -y' ) );
		$result3 = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo apt-get install php5.6-imagick php7.1-imagick php7.2-imagick php7.3-imagick php7.4-imagick -y' ) );

		return new \WP_Error( __( 'Process completed - please check the SSH logs to verify that the process was successful', 'wpcd' ) );

	}


}

new WPCD_WORDPRESS_TABS_SERVER_TOOLS();
