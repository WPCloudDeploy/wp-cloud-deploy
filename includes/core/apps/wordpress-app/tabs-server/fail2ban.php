<?php
/**
 * Fail2ban
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_FAIL2BAN
 */
class WPCD_WORDPRESS_TABS_SERVER_FAIL2BAN extends WPCD_WORDPRESS_TABS {

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

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'fail2ban';
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
		if ( true === $this->wpcd_wpapp_server_user_can( 'view_wpapp_server_fail2ban_tab', $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Fail2ban', 'wpcd' ),
				'icon'  => 'fad fa-axe-battle',
			);
		}
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the FAIL2BAN tab.
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

		// Perform actions if allowed to do so.
		if ( $this->wpcd_wpapp_server_user_can( 'view_wpapp_server_fail2ban_tab', $id ) && $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'fail2ban-install':
					$action = 'fail2ban_install';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-remove':
					$action = 'fail2ban_remove';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-purge':
					$action = 'fail2ban_purge';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-change-default-parameters':
					$action = 'fail2ban_parameter';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-upgrade':
					$action = 'fail2ban_force_update';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-add-protocol':
					$action = 'fail2ban_add_protocol';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-remove-protocol':
					$action = 'fail2ban_remove_protocol';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-ban-ip':
					$action = 'fail2ban_ban';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-unban-ip':
					$action = 'fail2ban_unban';
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-metas-add':
					$result = $this->manage_fail2ban( $id, $action );
					break;
				case 'fail2ban-metas-remove':
					$result = $this->manage_fail2ban( $id, $action );
					break;
			}

			// This one handles the protocol specific actions that are created dynamically.
			if ( strpos( $action, 'fail2ban-change-protocol-' ) !== false ) {
				$action = 'fail2ban_update_protocol';
				$result = $this->manage_fail2ban( $id, $action );
			}
			if ( strpos( $action, 'fail2ban-remove-protocol-' ) !== false ) {
				$action = 'fail2ban_remove_protocol';
				$result = $this->manage_fail2ban( $id, $action );
			}
			if ( strpos( $action, 'fail2ban-remove-protocol-meta-' ) !== false ) {
				$action = 'fail2ban-remove-protocol-meta';
				$result = $this->manage_fail2ban( $id, $action );
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the FAIL2BAN tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_fail2ban_fields( $id );

	}

	/**
	 * Return a string that can be used in the header of the fields screen.
	 *
	 * @param string $type type.
	 */
	private function get_field_header_desc( $type ) {

		switch ( $type ) {
			case 1:
				$desc  = __( 'Fail2ban scans log files (e.g. /var/log/nginx/error_log) and bans IPs that show malicious signs -- too many password failures, seeking for exploits, etc.<br />  However, it is not currently installed on the server.<br />  To install it just click the install button.', 'wpcd' );
				$desc .= '<br />' . '<b>' . __( 'Warning: This is an advanced tool and, when misused can easily lock you out of your server with NO RECOURSE!', 'wpcd' ) . '</b>';
				$desc .= '<br />' . __( 'Do not try to use it if you do not know what you are doing!', 'wpcd' );
				break;
			case 2:
				$desc = __( 'Global options are used when none are set for individual protocols.  Because our screens above force you to set values for individual protocols you should never need to change these options.', 'wpcd' );
				break;
			case 3:
				$desc = __( 'Fail2Ban is installed.', 'wpcd' );
				break;
			default:
				$desc = '';
				break;
		}

		return $desc;

	}

	/**
	 * Gets the fields for the services to be shown in the FAIL2BAN tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_fail2ban_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// is fail2ban installed?
		$fail2ban_status = get_post_meta( $id, 'wpcd_wpapp_fail2ban_installed', true );

		if ( empty( $fail2ban_status ) ) {
			// fail2ban is not installed.

			$actions['fail2ban-header-main'] = array(
				'label'          => __( 'Fail2ban', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $this->get_field_header_desc( 1 ),
				),
			);

			// Since fail2ban is not installed show only install button and collect information related to the installation.
			$actions['fail2ban-install'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Install Fail2ban', 'wpcd' ),
					'desc'                => __( 'Click the button to start installing Fail2ban on the server.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to install the Fail2ban service?', 'wpcd' ),
				),
				'type'           => 'button',
			);

		}

		if ( 'yes' === $fail2ban_status ) {
			/* fail2ban is installed and active - instead of painting a header, we'll skip the header to save screen real estate.  So we're commenting out the header code below for now. */
			/**
			$actions['fail2ban-header-main'] = array(
				'label'          => __( 'Fail2ban', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $this->get_field_header_desc( 3 ),
				),
			);
			 */

			/* Get meta values for default settings - we'll just this later*/
			$bantime      = (int) get_post_meta( $id, 'wpcd_wpapp_fail2ban_ban_time', true );
			$findtime     = (int) get_post_meta( $id, 'wpcd_wpapp_fail2ban_find_time', true );
			$maxretry     = (int) get_post_meta( $id, 'wpcd_wpapp_fail2ban_max_retry', true );
			$whitelistips = get_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelist_ips', true );

			/* Ban/Unban IP Addresses */
			$actions['fail2ban-header-ban-unban'] = array(
				'label'          => __( 'Ban & Unban IP Addresses ', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => '',
				),
			);
			$actions['fail2ban-ban-ip-field']     = array(
				'label'          => __( 'Ban this IP', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => '',
					'std'            => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'banip',
					'size'           => 60,
					'columns'        => 6,

				),
			);
			$actions['fail2ban-unban-ip-field'] = array(
				'label'          => __( 'Whitelist (Unban) this IP', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => '',
					'std'            => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'unbanip',
					'size'           => 60,
					'columns'        => 6,

				),
			);

			$actions['fail2ban-ban-ip-desc'] = array(
				'label'          => __( 'Reason Or Note', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => '',
					'std'            => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'banip_reason',
					'size'           => 60,
					'columns'        => 6,

				),
			);
			$actions['fail2ban-unban-ip-desc'] = array(
				'label'          => __( 'Reason Or Note', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => '',
					'std'            => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'unbanip_reason',
					'size'           => 60,
					'columns'        => 6,

				),
			);

			$actions['fail2ban-ban-ip']   = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Ban IP', 'wpcd' ),
					'desc'                => __( 'Click the button to ban this IP.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to ban this IP?', 'wpcd' ),
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_fail2ban-ban-ip-field', '#wpcd_app_action_fail2ban-ban-ip-desc' ) ),
					'columns'             => 6,
				),
				'type'           => 'button',
			);
			$actions['fail2ban-unban-ip'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Whitelist/Unban IP', 'wpcd' ),
					'desc'                => __( 'Click the button to whitelist this IP.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to unban this IP?', 'wpcd' ),
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_fail2ban-unban-ip-field', '#wpcd_app_action_fail2ban-unban-ip-desc' ) ),
					'columns'             => 6,
				),
				'type'           => 'button',
			);

			$actions['fail2ban-banned-ips']      = array(
				'label'          => __( 'IPs You Have Banned', 'wpcd' ),
				'raw_attributes' => array(
					'std'     => $this->construct_ips_display_text( $id, 'banned' ),
					'columns' => 6,
				),
				'type'           => 'custom_html',
			);
			$actions['fail2ban-whitelisted-ips'] = array(
				'label'          => __( 'Whitelisted IPs', 'wpcd' ),
				'raw_attributes' => array(
					'std'     => $this->construct_ips_display_text( $id, 'whitelisted' ),
					'columns' => 6,
				),
				'type'           => 'custom_html',
			);

			/* End Ban/Unban IP Addresses */

			/* Data for installed protocols that we know about */
			$protocols = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols', true ) );

			/* Fail2Ban Individual Protocols */
			foreach ( $protocols as $protocol => $protocol_parms ) {

				$protocol_ban_time  = $protocol_parms['ban_time'];
				$protocol_find_time = $protocol_parms['find_time'];
				$protocol_max_retry = $protocol_parms['max_retry'];

				$actions[ "fail2ban-header-$protocol" ] = array(
					'label'          => $protocol,
					'type'           => 'heading',
					'raw_attributes' => array(
						'desc' => sprintf( __( '%s Jail Settings', 'wpcd' ), strtoupper( $protocol ) ),
					),
				);

				$actions[ "fail2ban-new-ban-time-$protocol" ]  = array(
					'label'          => __( 'Ban Time', 'wpcd' ),
					'type'           => 'number',
					'raw_attributes' => array(
						'desc'           => __( 'Duration (in seconds) for an IP to be banned.', 'wpcd' ),
						'std'            => $protocol_ban_time,
						// the key of the field (the key goes in the request).
						'data-wpcd-name' => 'bantime_new',
						'columns'        => 6,
					),
				);
				$actions[ "fail2ban-new-find-time-$protocol" ] = array(
					'label'          => __( 'Find Time', 'wpcd' ),
					'type'           => 'number',
					'raw_attributes' => array(
						'desc'           => __( 'The MAX RETRY counter is set to zero if no match is found within this time period.', 'wpcd' ),
						'std'            => $protocol_find_time,
						// the key of the field (the key goes in the request).
						'data-wpcd-name' => 'findtime_new',
						'columns'        => 6,
					),
				);
				$actions[ "fail2ban-new-max-retry-$protocol" ] = array(
					'label'          => __( 'Max Retry', 'wpcd' ),
					'type'           => 'number',
					'raw_attributes' => array(
						'desc'           => __( 'Number of matches which triggers ban action on the IP.', 'wpcd' ),
						'std'            => $protocol_max_retry,
						// the key of the field (the key goes in the request).
						'data-wpcd-name' => 'maxretry_new',
						'columns'        => 6,
					),
				);
				$actions[ "fail2ban-new-$protocol" ]           = array(
					'label'          => __( 'Protocol', 'wpcd' ),
					'type'           => 'text',
					'raw_attributes' => array(
						'desc'           => __( 'DO NOT CHANGE!', 'wpcd' ),
						'std'            => $protocol,
						// the key of the field (the key goes in the request).
						'data-wpcd-name' => 'protocol_update',
						'columns'        => 6,
					),
				);

				$actions[ "fail2ban-change-protocol-$protocol" ] = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'                 => __( 'Change', 'wpcd' ),
						'desc'                => __( 'Click the button to change the data for this protocol.', 'wpcd' ), // make sure we give the user a confirmation prompt.
						'confirmation_prompt' => sprintf( __( 'Are you sure you would like to change the parameters for the %s protocol on the Fail2Ban service?', 'wpcd' ), $protocol ),
						'data-wpcd-fields'    => json_encode( array( "#wpcd_app_action_fail2ban-new-$protocol", "#wpcd_app_action_fail2ban-new-ban-time-$protocol", "#wpcd_app_action_fail2ban-new-find-time-$protocol", "#wpcd_app_action_fail2ban-new-max-retry-$protocol" ) ),
						'columns'             => 4,
					),
					'type'           => 'button',
				);

				// Show remove and delete meta buttons if protocol is not sshd or nginx.
				if ( ! in_array( $protocol, array_keys( $this->get_default_protocols() ) ) ) {
					$actions[ "fail2ban-remove-protocol-$protocol" ] = array(
						'label'          => '',
						'raw_attributes' => array(
							'std'                 => __( 'Remove Protocol', 'wpcd' ),
							'desc'                => __( 'Click the button to disable this protocol in fail2ban.', 'wpcd' ), // make sure we give the user a confirmation prompt.
							'confirmation_prompt' => sprintf( __( 'Are you sure you would like to disable the %s protocol on the Fail2Ban service?', 'wpcd' ), $protocol ),
							'data-wpcd-fields'    => json_encode( array( "#wpcd_app_action_fail2ban-new-$protocol" ) ),
							'columns'             => 4,
						),
						'type'           => 'button',
					);

					$actions[ "fail2ban-remove-protocol-meta-$protocol" ] = array(
						'label'          => '',
						'raw_attributes' => array(
							'std'                 => __( 'Remove Metas', 'wpcd' ),
							'desc'                => __( 'Click the button to remove metas. No changes will be made to the server.', 'wpcd' ), // make sure we give the user a confirmation prompt.
							'confirmation_prompt' => sprintf( __( 'Are you sure you would like to remove metas for the %s protocol?', 'wpcd' ), $protocol ),
							'data-wpcd-fields'    => json_encode( array( "#wpcd_app_action_fail2ban-new-$protocol" ) ),
							'columns'             => 4,
						),
						'type'           => 'button',
					);
				}
			}
			/* End Fail2Ban Individual Protocols */

			/* Fail2ban General/Global Options */
			$actions['fail2ban-header-General'] = array(
				'label'          => __( 'Fail2ban Global Options', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $this->get_field_header_desc( 2 ),
				),
			);

			$actions['fail2ban-new-ban-time']  = array(
				'label'          => __( 'New Ban Time', 'wpcd' ),
				'type'           => 'number',
				'raw_attributes' => array(
					'desc'           => __( 'Duration (in seconds) for an IP to be banned.', 'wpcd' ),
					'std'            => $bantime,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'bantime_new',
					'columns'        => 4,
				),
			);
			$actions['fail2ban-new-find-time'] = array(
				'label'          => __( 'New Find Time', 'wpcd' ),
				'type'           => 'number',
				'raw_attributes' => array(
					'desc'           => __( 'The MAX RETRY counter is set to zero if no match is found within this time period.', 'wpcd' ),
					'std'            => $findtime,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'findtime_new',
					'columns'        => 4,
				),
			);
			$actions['fail2ban-new-max-retry'] = array(
				'label'          => __( 'New Max Retry', 'wpcd' ),
				'type'           => 'number',
				'raw_attributes' => array(
					'desc'           => __( 'Number of matches which triggers ban action on the IP.', 'wpcd' ),
					'std'            => $maxretry,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'maxretry_new',
					'columns'        => 4,
				),
			);

			$actions['fail2ban-change-default-parameters'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Change', 'wpcd' ),
					'desc'                => __( 'Click the button to change your default parameters for Fail2ban.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to change default parameters for the Fail2Ban service?', 'wpcd' ),
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_fail2ban-new-ban-time', '#wpcd_app_action_fail2ban-new-find-time', '#wpcd_app_action_fail2ban-new-max-retry' ) ),
				),
				'type'           => 'button',
			);
			/* End fail2ban General/Global Options */

			/* Uninstall / Upgrade fail2ban*/
			$actions['fail2ban-uninstall-upgrade'] = array(
				'label'          => __( 'Uninstall or Upgrade fail2ban', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Remove or upgrade fail2ban.', 'wpcd' ),
				),
			);

			$actions['fail2ban-remove'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Uninstall Fail2ban', 'wpcd' ),
					'desc'                => __( 'This option will completely remove Fail2ban from the server.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to remove Fail2ban from the server?', 'wpcd' ),
					'columns'             => 4,
				),
				'type'           => 'button',
			);

			$actions['fail2ban-purge'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Purge Fail2ban', 'wpcd' ),
					'desc'                => __( 'This option will completely remove Fail2ban AND its configuration files.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to PURGE Fail2ban from the server?', 'wpcd' ),
					'columns'             => 4,
				),
				'type'           => 'button',
			);

			$actions['fail2ban-upgrade'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Upgrade Fail2ban', 'wpcd' ),
					'desc'                => __( 'This option will run a forced upgrade on Fail2ban.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to FORCIBLY upgrade Fail2ban on the server?', 'wpcd' ),
					'tooltip'             => __( 'You should not usually need to use this option - normal overnight background updates will usually upgrade Fail2Ban for you.', 'wpcd' ),
					'columns'             => 4,
				),
				'type'           => 'button',
			);
			/* End uninstall / Upgrade fail2ban*/

		}

		if ( 'no' === $fail2ban_status ) {
			/* fail2ban is installed but not active */
			$desc = __( 'Fail2ban scans log files (e.g. /var/log/nginx/error_log) and bans IPs that show malicious signs -- too many password failures, seeking for exploits, etc. However, it is installed but NOT active on your server at this time.', 'wpcd' );

			$actions['fail2ban-header'] = array(
				'label'          => __( 'fail2ban', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);
		}

		/* Toggle Metas */
		$actions['fail2ban-metas-header'] = array(
			'label'          => __( 'Manage Fail2ban Metas', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Sometimes things get out of sync between this dashboard and what is actually on the server.  Use these options to reset things', 'wpcd' ),
			),
		);

		$actions['fail2ban-metas-remove'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Remove Metas', 'wpcd' ),
				'desc'                => __( 'This option will reset this dashboard so that it appears that Fail2ban is not installed.', 'wpcd' ), // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to remove metas?  This would reset this dashboard so that it appears that Fail2ban is not installed.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		$actions['fail2ban-metas-add'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Add Metas', 'wpcd' ),
				'desc'                => __( 'This option will reset this dashboard so that it appears that Fail2ban is installed.', 'wpcd' ), // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to remove metas?  This would reset this dashboard so that it appears that Fail2ban is installed on the server.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		/* 3rd party / limited support notice */
		$actions['fail2ban-third-party-notice-header'] = array(
			'label'          => __( 'Important Notice', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Fail2ban is a 3rd party product and is provided as a convenience.  It is not a core component of this dashboard. Technical support is limited.', 'wpcd' ),
			),
		);

		return $actions;

	}

	/**
	 * Install / manage fail2ban
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_fail2ban( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'fail2ban_install':
				// nothing to do - no parameters needed for the install.
				break;

			case 'fail2ban_parameter':  // Updates global parameters for fail2ban
				// Make sure all four required fields have been provided and return error if not.
				if ( empty( $args['bantime_new'] ) ) {
					return new \WP_Error( __( 'Unable to setup Fail2ban - no new Ban Time was was provided.', 'wpcd' ) );
				}

				if ( empty( $args['findtime_new'] ) ) {
					return new \WP_Error( __( 'Unable to setup Fail2ban - no user Find Time was was provided.', 'wpcd' ) );
				}

				if ( empty( $args['maxretry_new'] ) ) {
					return new \WP_Error( __( 'Unable to setup Fail2ban - no Max Retry limit was was provided.', 'wpcd' ) );
				}

				if ( empty( $args['ignoreip_new'] ) ) {
					$args['ignoreip_new'] = 'n/a';
				}

				break;

			case 'fail2ban_update_protocol':
				// Make sure all required fields have been provided and return error if not.
				if ( empty( $args['protocol_update'] ) ) {
					return new \WP_Error( __( 'Unable to update protocol - no PROTOCOL was provided.', 'wpcd' ) );
				}

				if ( empty( $args['bantime_new'] ) ) {
					return new \WP_Error( __( 'Unable to update protocol - no BAN TIME was provided.', 'wpcd' ) );
				}

				if ( empty( $args['findtime_new'] ) ) {
					return new \WP_Error( __( 'Unable to update protocol - no FIND TIME was provided.', 'wpcd' ) );
				}
				break;

			if ( empty( $args['maxretry_new'] ) ) {
				return new \WP_Error( __( 'Unable to update protocol - no MAX RETRY parameter was provided.', 'wpcd' ) );
			}
				break;

			case 'fail2ban_remove_protocol':
				if ( empty( $args['protocol_update'] ) ) {
					return new \WP_Error( __( 'Unable to remove protocol - no PROTOCOL was provided.', 'wpcd' ) );
				}
				$args['protocol_delete'] = $args['protocol_update'];  // The array doesn't have the delete element that the bash script is expecting so make sure it gets one.
				break;

			case 'fail2ban-remove-protocol-meta':
				// Remove a single item from the fail2ban protocols meta.
				$protocols = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols', true ) );
				unset( $protocols[ $original_args['protocol_update'] ] );
				update_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols', $protocols );
				$success = array(
					'msg'     => __( 'The metas were removed for the selected protocol.', 'wpcd' ),
					'refresh' => 'yes',
				);
				break;

			case 'fail2ban_ban':
				if ( empty( $args['banip'] ) ) {
					return new \WP_Error( __( 'Please provide an IP address to ban.', 'wpcd' ) );
				}
				if ( ( ! filter_var( $args['banip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) && ( ! filter_var( $args['banip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) ) {
					return new \WP_Error( __( 'Please provide a valid IP address.', 'wpcd' ) );
				}
				break;

			case 'fail2ban_unban':
				if ( empty( $args['unbanip'] ) ) {
					return new \WP_Error( __( 'Please provide an IP address to unban/whitelist.', 'wpcd' ) );
				}
				if ( ( ! filter_var( $args['unbanip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) && ( ! filter_var( $args['unbanip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) ) {
					return new \WP_Error( __( 'Please provide a valid IP address.', 'wpcd' ) );
				}
				break;

			case 'fail2ban-metas-remove':
				// Remove all fail2ban metas.
				$this->remove_metas( $id );
				$success = array(
					'msg'     => __( 'Fail2ban metas have been reset. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
					'refresh' => 'yes',
				);
				return $success;
				break;

			case 'fail2ban-metas-add':
				// Add fail2ban metas.
				$this->update_default_protocol_metas( $id );
				$success = array(
					'msg'     => __( 'Fail2ban metas have been reset to their defaults and might not match the settings on your server! You should now be able to remove Fail2ban if necessary and reinstall as needed. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
					'refresh' => 'yes',
				);
				return $success;
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
		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command( $instance, 'fail2ban.txt', array_merge( $args, array( 'action' => $action ) ) );

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'fail2ban.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {
				case 'fail2ban_install':
					$this->update_default_protocol_metas( $id );
					$success = array(
						'msg'     => __( 'Fail2ban has been installed. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'fail2ban_parameter':
					// Change global parameters.
					update_post_meta( $id, 'wpcd_wpapp_fail2ban_ban_time', $original_args['bantime_new'] );
					update_post_meta( $id, 'wpcd_wpapp_fail2ban_find_time', $original_args['findtime_new'] );
					update_post_meta( $id, 'wpcd_wpapp_fail2ban_max_retry', $original_args['maxretry_new'] );
					update_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelist_ips', $original_args['ignoreip_new'] );
					$success = array(
						'msg'     => __( 'Global defaults for fail2ban have been updated.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'fail2ban_update_protocol':
					$protocols = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols', true ) );
					$protocols[ $original_args['protocol_update'] ]['ban_time']  = $original_args['bantime_new'];
					$protocols[ $original_args['protocol_update'] ]['find_time'] = $original_args['findtime_new'];
					$protocols[ $original_args['protocol_update'] ]['max_retry'] = $original_args['maxretry_new'];
					update_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols', $protocols );
					$success = array(
						'msg'     => __( 'Protocol parameters have been updated.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'fail2ban_remove_protocol':
					$protocols = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols', true ) );
					unset( $protocols[ $original_args['protocol_delete'] ] );
					update_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols', $protocols );
					$success = array(
						'msg'     => __( 'The protocol was removed.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'fail2ban_ban':
					$this->add_remove_banned_ips( $id, $original_args['banip'], $original_args['banip_reason'], $action );
					$success = array(
						'msg'     => __( 'IP was successfully banned.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'fail2ban_unban':
					$this->add_remove_banned_ips( $id, $original_args['unbanip'], $original_args['unbanip_reason'], $action );
					$success = array(
						'msg'     => __( 'IP was successfully removed from the banned list.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'fail2ban_force_update':
					$success = array(
						'msg'     => __( 'Fail2ban has been updated.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'fail2ban_remove':
					$this->remove_metas( $id );
					$success = array(
						'msg'     => __( 'fail2ban has been removed from the server.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'fail2ban_purge':
					$this->remove_metas( $id );
					$success = array(
						'msg'     => __( 'fail2ban and its configuration files have been purged from the server.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

			}
		}

		return $success;

	}

	/**
	 * Remove all fail2ban metas
	 *
	 * @param int $id post id of the server.
	 */
	public function remove_metas( $id ) {
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_installed' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_ban_time' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_find_time' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_max_retry' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelist_ips' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_banned_ips' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelisted_ips' );

		// These are old metas used in early development - can delete these anytime.
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_bantime' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_findtime' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_maxretry' );
		delete_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelistips' );
	}

	/**
	 * Return a list of default protocols that are installed when we install fail2ban.
	 */
	public function get_default_protocols() {

		return array(
			'sshd'            => array(
				'ban_time'  => 600,
				'find_time' => 600,
				'max_retry' => 3,
			),
			'nginx-http-auth' => array(
				'ban_time'  => 600,
				'find_time' => 600,
				'max_retry' => 3,
			),
			'nginx-limit-req' => array(
				'ban_time'  => 600,
				'find_time' => 600,
				'max_retry' => 3,
			),
			'nginx-botsearch' => array(
				'ban_time'  => 600,
				'find_time' => 600,
				'max_retry' => 3,
			),
		);

	}

	/**
	 * Insert the default metas for fail2ban that would apply to a new install.
	 *
	 * @param int $id     Server/post id to update.
	 */
	public function update_default_protocol_metas( $id ) {
		$protocols = $this->get_default_protocols();
		update_post_meta( $id, 'wpcd_wpapp_fail2ban_installed', 'yes' );
		update_post_meta( $id, 'wpcd_wpapp_fail2ban_ban_time', '600' );
		update_post_meta( $id, 'wpcd_wpapp_fail2ban_find_time', '600' );
		update_post_meta( $id, 'wpcd_wpapp_fail2ban_max_retry', '3' );
		update_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelist_ips', '127.0.0.1' );
		update_post_meta( $id, 'wpcd_wpapp_fail2ban_protocols', $protocols );
	}

	/**
	 * Add/remove IPs from the list stored in the banned meta.
	 *
	 * @param int    $id     Server/post id to update.
	 * @param string $ip     Ip address to ban/unban.
	 * @param string $reason Reason for ban/unban.
	 * @param string $action fail2ban_ban / fail2ban_unban.
	 */
	public function add_remove_banned_ips( $id, $ip, $reason, $action ) {

		$banned_ips      = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_fail2ban_banned_ips', true ) );
		$whitelisted_ips = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelisted_ips', true ) );

		// Initialize if blank.
		if ( ! $banned_ips ) {
			$banned_ips = array();
		}
		if ( ! $whitelisted_ips ) {
			$whitelisted_ips = array();
		}

		// Reinitialize if not an array!
		if ( ! is_array( $banned_ips ) ) {
			$banned_ips = array();
		}
		if ( ! is_array( $whitelisted_ips ) ) {
			$whitelisted_ips = array();
		}

		// Add or remove from each of the arrays depending on whether we're banning or unbanning.
		switch ( $action ) {

			case 'fail2ban_unban':
				$whitelisted_ips[ $ip ] = $reason;
				if ( isset( $banned_ips[ $ip ] ) ) {
					unset( $banned_ips[ $ip ] );
				}
				break;
			case 'fail2ban_ban':
				$banned_ips[ $ip ] = $reason;
				if ( isset( $whitelisted_ips[ $ip ] ) ) {
					unset( $whitelisted_ips[ $ip ] );
				}
				break;

		}

		// Write back to record.
		update_post_meta( $id, 'wpcd_wpapp_fail2ban_banned_ips', $banned_ips );
		update_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelisted_ips', $whitelisted_ips );

	}

	/**
	 * Construct a string that is suitable for display the list of ips.
	 *
	 * @param int    $id id.
	 * @param string $type type.
	 */
	public function construct_ips_display_text( $id, $type ) {

		$ips    = array();
		$return = '';

		switch ( $type ) {
			case 'banned':
				$ips = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_fail2ban_banned_ips', true ) );
				break;
			case 'whitelisted':
				$ips = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_fail2ban_whitelisted_ips', true ) );
				break;
		}

		if ( ! empty( $ips ) ) {
			foreach( $ips as $ip => $reason ) {
				$return .= $ip . ' / ' . $reason . '<br />';
			}
		}

		return $return ;
	}

}

new WPCD_WORDPRESS_TABS_SERVER_FAIl2BAN();
