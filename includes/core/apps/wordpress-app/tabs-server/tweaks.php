<?php
/**
 * Tweaks Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_TWEAKS
 */
class WPCD_WORDPRESS_TABS_SERVER_TWEAKS extends WPCD_WORDPRESS_TABS {

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
		return 'svr_tweaks';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_tweaks_tab';
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
				'label' => __( 'Tweaks', 'wpcd' ),
				'icon'  => 'fad fa-car-tilt',
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
	 * Gets the fields to be shown in the TWEAKS tab.
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
		$valid_actions = array( 'server-tweaks-gzip' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'server-tweaks-gzip':
					$result = $this->server_tweaks_gzip( $id, $action );
					break;
			}
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the TWEAKS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_tweaks_fields( $id );

	}

	/**
	 * Gets the fields to shown in the TWEAKS tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_tweaks_fields( $id ) {

		$actions = array();

		/**
		 * GZIP
		 */

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		$actions['server-tweaks-performance-gzip-header'] = array(
			'label'          => __( 'Gzip', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Enable or Disable Gzip for this server. If your sites have their own GZIP directives then those will override anything that is set or unset here.', 'wpcd' ),
			),
		);

		$gzip_status = $this->get_meta_value( $id, 'wpcd_wpapp_gzip_status', 'on' );

		/* Set the text of the confirmation prompt */
		$gzip_confirmation_prompt = 'on' === $gzip_status ? __( 'Are you sure you would like to disable GZIP for this server?', 'wpcd' ) : __( 'Are you sure you would like to enable GZIP for this server?', 'wpcd' );

		$actions['server-tweaks-gzip'] = array(
			'label'          => __( 'Gzip', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $gzip_status === 'on',
				'confirmation_prompt' => $gzip_confirmation_prompt,
			),
			'type'           => 'switch',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		// Add a divider.
		$actions[] = array(
			'type' => 'divider',
		);

		/**
		 * Add some footer comments to direct the user to other places
		 */
		$actions['server-tweaks-footer-1'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std' => __( 'Check out the TWEAKS tab on each of your sites for additional performance and security tweaks and customization options.', 'wpcd' ),
			),
			'type'           => 'custom_html',
		);

		return $actions;

	}

	/**
	 * Get the current value of an on/off meta value from the server field.
	 *
	 * This is just a get_post_meta but sets a default value if nothing exists.
	 *
	 * @param int    $id             postid of server record.
	 * @param string $meta_name      name of meta value to get.
	 * @param string $default_value  default value if meta isn't set.
	 *
	 * @return mixed|string
	 */
	private function get_meta_value( $id, $meta_name, $default_value = 'off' ) {

		$status = get_post_meta( $id, $meta_name, true );
		if ( empty( $status ) ) {
			$status = $default_value;
		}

		return $status;

	}

	/**
	 * Toggle the gzip status for the server.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function server_tweaks_gzip( $id, $action ) {

		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );

		switch ( $webserver_type ) {
			case 'ols':
			case 'ols-enterprise':
				$bridge_file = 'ols_options.txt';
				break;

			case 'nginx':
			default:
				$bridge_file = 'nginx_options.txt';
				break;

		}

		// What is the current gzip status?
		$gzip_status = $this->get_meta_value( $id, 'wpcd_wpapp_gzip_status', 'on' );

		// Figure out the proper action to send to the server script.
		if ( 'on' === $gzip_status ) {
			// currently on so turn it off.
			$action = 'disable_gzip';
		} else {
			$action = 'enable_gzip';
		}

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command( $instance, $bridge_file, array( 'action' => $action ) );

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Run the command.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, $bridge_file );

		// Check for success.
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s for site: %2$s', 'wpcd' ), $action, $result ) );
		}

		/* Update server meta to set the new meta state for gzip */
		if ( 'on' === $gzip_status ) {
			// currently on so turn it off.
			update_post_meta( $id, 'wpcd_wpapp_gzip_status', 'off' );
		} else {
			update_post_meta( $id, 'wpcd_wpapp_gzip_status', 'on' );
		}

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'The Gzip status on the server has been toggled.  Note that if your sites have a setting for it then it will override whatever is set here!', 'wpcd' ) );

	}

}

new WPCD_WORDPRESS_TABS_SERVER_TWEAKS();
