<?php
/**
 * Firewall tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_UFW_FIREWALL
 */
class WPCD_WORDPRESS_TABS_SERVER_UFW_FIREWALL extends WPCD_WORDPRESS_TABS {

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
		return 'firewall';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_firewall_tab';
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
				'label' => __( 'Firewall', 'wpcd' ),
				'icon'  => 'fal fa-shield-virus',
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
		$valid_actions = array( 'ufw-open-port', 'ufw-close-port' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'ufw-open-port':
				case 'ufw-close-port':
					$result = $this->ufw_open_close_ports( $id, $action );
					break;
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

		return $this->get_ufw_fields( $id );

	}

	/**
	 * Gets the fields to shown in the FIREWALL tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_ufw_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// Description to be used in the first heading section of this tab.
		$ufw_top_desc = __( 'Open and close firewall ports.', 'wpcd' );

		// Description to be used in the footer of this tab.
		$ufw_footer_desc  = __( 'UFW is a Linux based firewall - also known as the UNCOMPLICATED FIREWALL. It is installed and turned on by default on your server, opening ports for HTTP, HTTPS and SSH. All other ports are closed by default.', 'wpcd' );
		$ufw_footer_desc .= '<br />';
		$ufw_footer_desc .= sprintf( __( 'Technically, UFW is not a firewall - it\'s just a nice front-end to the core Linux netfilter firewall.  But, for all intents and purposes it is the firewall because it is the thing that most people interact with. Learn more about %s', 'wpcd' ), '<a href="https://wiki.ubuntu.com/UncomplicatedFirewall">' . __( 'UFW', 'wpcd' ) . '</a>' );
		$ufw_footer_desc .= '<br />';
		$ufw_footer_desc .= '<br />';
		$ufw_footer_desc .= __( 'For most use-cases, you should not have to open or close any additional ports. The only common use case where you might need this is when you are importing emails from an external email-server in order to get them into your helpdesk plugin or your discussion forum.', 'wpcd' );
		$ufw_footer_desc .= '<br />';
		$ufw_footer_desc .= '<br />';
		$ufw_footer_desc .= __( 'Should you need to, you can use the controls above to open or close ports.  You will not be allowed to close or open ports 80, 443 and 22 since these are the HTTP, HTTPS and SSH ports.', 'wpcd' );
		$ufw_footer_desc .= '<br />';
		$ufw_footer_desc .= '<br />';
		$ufw_footer_desc .= __( 'NOTE: If you are using a service that automatically installs an external firewall for you then you might consider turning off the UFW firewall  which you can do on the services tab.  EC2 and LIGHTSAIL both include an external firewall so you might not need the UFW firewall.', 'wpcd' );

		$ufw_footer_desc = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $ufw_footer_desc );

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		$actions['ufw-ports-header'] = array(
			'label'          => __( 'Open or Close UFW Firewall Ports', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $ufw_top_desc,
			),
		);

		$actions['ufw-port-to-add-remove-label'] = array(
			'label'          => __( 'Which port would you like to open or close?', 'wpcd' ),
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std'     => '',
				'columns' => 12,
			),
		);

		$actions['ufw-port-to-add-remove'] = array(
			'label'          => '',
			'type'           => 'number',
			'raw_attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'ufw_port_to_open_or_close',
				'columns'        => 12,
				'size' => 6,
			),
		);

		$actions['ufw-open-port'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Open Port', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to open this port?', 'wpcd' ),
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_ufw-port-to-add-remove' ) ),
				'columns'             => 6,
			),
			'type'           => 'button',
		);

		$actions['ufw-close-port'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Close Port', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to close this port?', 'wpcd' ),
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_ufw-port-to-add-remove' ) ),
				'columns'             => 6,
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * Show Current Ports
		 */

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		$ufw_current_ports_managed_string = sprintf( '<i>%s</i>', __( 'We are not currently aware of any ports being opened or closed via this dashboard. If ports were opened or closed directly on the server using the command line they will not show up here.', 'wpcd' ) );
		$ufw_managed_ports                = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_ufw_managed_ports', true ) );

		if ( ! empty( $ufw_managed_ports ) ) {
			$ufw_current_ports_managed_string = __( 'We are currently aware of the following ports being opened or closed via this dashboard:', 'wpcd' );

			foreach ( $ufw_managed_ports as $key => $ufw_port_status ) {
				$ufw_current_ports_managed_string .= '<br />' . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . (string) $key . ' / ' . $ufw_port_status;
			}
		}

		$actions['ufw-current-ports-header'] = array(
			'label'          => __( 'Ports Being Managed', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'List of ports currently being managed on this screen.', 'wpcd' ),
			),
		);

		$actions['ufw-current-ports'] = array(
			'label'          => '',
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std' => $ufw_current_ports_managed_string,
			),
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * Learn more / Reading materials.
		 */

		$actions[] = wpcd_start_two_thirds_card( $this->get_tab_slug() );

		// Show a lengthy text description of what this tab does.
		$actions['ufw-footer-desc'] = array(
			'label'          => __( 'About the UFW Firewall', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $ufw_footer_desc,
			),
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		return $actions;

	}

	/**
	 * Open or close a port
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function ufw_open_close_ports( $id, $action ) {

		// Get port from _POST sent via AJAX.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// error out if trying to close or open our standard ports.
		$port = (int) $args['ufw_port_to_open_or_close'];
		if ( in_array( $port, array( 80, 443, 22 ) ) ) {
			return new \WP_Error( 'You are not allowed to change these ports: 80, 443, 22', 'wpcd' );
		}

		// error out if no valid port is passed.
		if ( empty( $port ) ) {
			return new \WP_Error( 'You must provide a port number.', 'wpcd' );
		}

		// basic checks passed, construct a command.
		switch ( $action ) {
			case 'ufw-open-port':
				$command = 'sudo ufw allow ' . (string) $port;
				break;
			case 'ufw-close-port':
				$command = 'sudo ufw deny ' . (string) $port;
				break;
		}

		// send the command.
		$result = $this->submit_generic_server_command( $id, $action, $command, true );  // notice the last parm is true to force the function to return the raw results to us for evaluation instead of a wp-error object.

		// If not error, then add or update in our database the list of ports that are opened or closed.
		if ( ( ! is_wp_error( $result ) ) && $result ) {
			// Get the meta from the database.
			$ufw_managed_ports = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_ufw_managed_ports', true ) );
			if ( empty( $ufw_managed_ports ) ) {
				$ufw_managed_ports = array();
			}

			switch ( $action ) {
				case 'ufw-open-port':
					$ufw_managed_ports[ $port ] = 'open';
					break;
				case 'ufw-close-port':
					$ufw_managed_ports[ $port ] = 'closed';
					break;
			}

			update_post_meta( $id, 'wpcd_wpapp_ufw_managed_ports', $ufw_managed_ports );

		}

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

		return $result;

	}

}

new WPCD_WORDPRESS_TABS_SERVER_UFW_FIREWALL();
