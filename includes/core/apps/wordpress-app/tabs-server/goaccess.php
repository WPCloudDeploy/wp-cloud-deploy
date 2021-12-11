<?php
/**
 * Goaccess
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_GOACCESS
 */
class WPCD_WORDPRESS_TABS_SERVER_GOACCESS extends WPCD_WORDPRESS_TABS {

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
		return 'goaccess';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_goaccess_tab';
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
		if ( true === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			$tabs['goaccess'] = array(
				'label' => __( 'Goaccess', 'wpcd' ),
				'icon'  => 'fad fa-user-chart',
			);
		}
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the GOACCESS tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {
		return $this->get_fields_for_tab( $fields, $id, 'goaccess' );

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
		$valid_actions = array( 'goaccess-install', 'goaccess-remove', 'goaccess-disable', 'goaccess-update', 'goaccess-add-auth', 'goaccess-remove-auth', 'goaccess-change-auth', 'goaccess-toggle-ssl', 'goaccess-metas-add', 'goaccess-metas-remove' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'goaccess-install':
					$action = 'goaccess_install';
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-remove':
					$action = 'goaccess_remove';
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-disable':
					$action = 'goaccess_disable';
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-upgrade':
					$action = 'goaccess_update';
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-add-auth':
					$action = 'goaccess_auth_add';
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-remove-auth':
					$action = 'goaccess_auth_remove';
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-change-auth':
					$action = 'goaccess_auth_change';
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-toggle-ssl':
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-metas-add':
					$result = $this->manage_goaccess( $id, $action );
					break;
				case 'goaccess-metas-remove':
					$result = $this->manage_goaccess( $id, $action );
					break;

			}
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the GOACCESS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_goaccess_fields( $id );

	}

	/**
	 * Gets the fields for the services to be shown in the GOACCESS tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_goaccess_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// is goaccess installed?
		$goaccess_status = get_post_meta( $id, 'wpcd_wpapp_goaccess_installed', true );

		if ( empty( $goaccess_status ) ) {
			// goaccess is not installed.
			$desc = __( 'GoAccess is an open source real-time web log analyzer and interactive viewer.  It provides fast and valuable HTTP statistics for system administrators that require a visual server report on the fly.  However, it is not currently installed on the server.<br />  To install it, fill out the domain name below and click the install button.', 'wpcd' );

			$actions['goaccess-header'] = array(
				'label'          => __( 'goaccess', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// Since goaccess is not installed show only install button and collect information related to the installation.
			$actions['goaccess-domain']          = array(
				'label'          => __( 'Domain', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'std'            => '',
					'desc'           => sprintf( __( 'GoAccess needs to be accessed via a domain or subdomain that points to the server - <b>%1$s</b> on ip <b>%2$s</b>.', 'wpcd' ), $this->get_server_name( $id ), $this->get_ipv4_address( $id ) ),
					'size'           => 120,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'goaccess_domain',
				),
			);
			$actions['goaccess-basic-auth-user'] = array(
				'label'          => __( 'User Name', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'User name to use to log into the GoAccess dashboard', 'wpcd' ),
					'size'           => 60,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'goaccess_auth_user',
				),

			);
			$actions['goaccess-basic-auth-pw'] = array(
				'label'          => __( 'Password', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Password to use when accessing the GoAccess dashboard', 'wpcd' ),
					'size'           => 60,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'goaccess_auth_pass',
				),
			);
			$actions['goaccess-install']       = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Install GoAccess', 'wpcd' ),
					'desc'                => __( 'Click the button to start installing GoAccess on the server.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to install the GoAccess service?', 'wpcd' ),
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_goaccess-domain', '#wpcd_app_action_goaccess-basic-auth-user', '#wpcd_app_action_goaccess-basic-auth-pw' ) ),
				),
				'type'           => 'button',
			);

		}

		if ( 'yes' === $goaccess_status ) {
			/* goaccess is installed and active */
			$desc = __( 'GoAccess is an open source real-time web log analyzer and interactive viewer.  It provides fast and valuable HTTP statistics for system administrators that require a visual server report on the fly.', 'wpcd' );

			/* Get user id and password */
			$goaccess_user = $this::decrypt( get_post_meta( $id, 'wpcd_wpapp_goaccess_user', true ) );
			$goaccess_pass = $this::decrypt( get_post_meta( $id, 'wpcd_wpapp_goaccess_pass', true ) );

			$actions['goaccess-header'] = array(
				'label'          => __( 'GoAccess', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// Construct link to access GoAccess.
			if ( 'on' === get_post_meta( $id, 'wpcd_wpapp_goaccess_ssl', true ) ) {
				$goaccess_url = 'https://' . "$goaccess_user:$goaccess_pass@" . get_post_meta( $id, 'wpcd_wpapp_goaccess_domain', true );
			} else {
				$goaccess_url = 'http://' . "$goaccess_user:$goaccess_pass@" . get_post_meta( $id, 'wpcd_wpapp_goaccess_domain', true );
			}

			$launch = sprintf( '<a href="%s" target="_blank">', $goaccess_url ) . __( 'Launch GoAccess', 'wpcd' ) . '</a>';

			$actions['goaccess-launch'] = array(
				'label'          => __( 'Launch GoAccess', 'wpcd' ),
				'type'           => 'button',
				'raw_attributes' => array(
					'std'  => $launch,
					'desc' => 'Launch GoAccess located at ' . $goaccess_url,
				),
			);

			$actions[] = array(
				'label'          => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => __( 'User Id: ', 'wpcd' ) . $goaccess_user . '<br />' . __( 'Password: ', 'wpcd' ) . $goaccess_pass,
				),
			);

			/* goaccess SSL Options */
			$desc = __( 'Activate or deactivate ssl.' );

			$actions['goaccess-ssl-options'] = array(
				'label'          => __( 'GoAccess SSL Options', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// What's the current SSL status?
			$goaccess_ssl = get_post_meta( $id, 'wpcd_wpapp_goaccess_ssl', true );
			if ( empty( $goaccess_ssl ) ) {
				$goaccess_ssl = 'off';
			}

			// Set confirmation prompt based on current ssl status.
			$confirmation_prompt = '';
			if ( 'on' === $goaccess_ssl ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable SSL?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable SSL?', 'wpcd' );
			}

			$actions['goaccess-toggle-ssl'] = array(
				'label'          => __( 'SSL Status', 'wpcd' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'wpcd' ),
					'off_label'           => __( 'Disabled', 'wpcd' ),
					'std'                 => $goaccess_ssl === 'on',
					'desc'                => __( 'Enable or disable SSL', 'wpcd' ),
					'tooltip'             => __( 'Turning this on will result in an attempt to obtain a certificate from LETSEncrypt.  For this to be successful your DNS must be pointing to the domain used when you installed goaccess. <br />If the LETSEncrypt request fails, check the logs under the SSH LOG menu option. <br />Note that if you attempt to turn on SSL too many times in a row LETSEncrypt will block your domain for a period of time.', 'wpcd' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End goaccess SSL Options */

			/* Uninstall / Upgrade goaccess*/
			$actions['goaccess-uninstall-upgrade'] = array(
				'label'          => __( 'Uninstall or Upgrade goaccess', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Remove or upgrade goaccess', 'wpcd' ),
				),
			);

			$actions['goaccess-remove'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Uninstall GoAccess', 'wpcd' ),
					'desc'                => __( 'This option will completely remove GoAccess from the server.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to remove GoAccess from the server?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);

			$actions['goaccess-upgrade'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Upgrade GoAccess', 'wpcd' ),
					'desc'                => __( 'This option will upgrade GoAccess.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to upgrade GoAccess on the server?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);
			/* End uninstall / Upgrade goaccess*/

			/* Change basic auth user id / password */
			$actions['goaccess-change-auth-header'] = array(
				'label'          => __( 'Change Credentials', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Change the user id and password used to login to GoAccess.', 'wpcd' ),
				),
			);

			$actions['goaccess-basic-auth-user'] = array(
				'label'          => __( 'User Name', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'User name to use to log into the GoAccess dashboard', 'wpcd' ),
					'size'           => 60,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'goaccess_auth_user',
				),

			);
			$actions['goaccess-basic-auth-pw'] = array(
				'label'          => __( 'Password', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Password to use when accessing the GoAccess dashboard', 'wpcd' ),
					'size'           => 60,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'goaccess_auth_pass',
				),
			);
			$actions['goaccess-change-auth']   = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Change', 'wpcd' ),
					'desc'                => __( 'Click the button change your credentials for GoAccess', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to change the user ID and Password for GoAccess?', 'wpcd' ),
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_goaccess-basic-auth-user', '#wpcd_app_action_goaccess-basic-auth-pw' ) ),
				),
				'type'           => 'button',
			);
			/* End change basic auth user id / password */

		}

		if ( 'no' === $goaccess_status ) {
			/* goaccess is installed but not active */
			$desc = __( 'GoAccess is an open source real-time web log analyzer and interactive viewer.  However, it is installed but NOT active on your server at this time.', 'wpcd' );

			$actions['goaccess-header'] = array(
				'label'          => __( 'goaccess', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);
		}

		/* Toggle Metas */
		$actions['goaccess-metas-header'] = array(
			'label'          => __( 'Manage GoAccess Metas', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Sometimes things get out of sync between this dashboard and what is actually on the server.  Use these options to reset things', 'wpcd' ),
			),
		);

		$actions['goaccess-metas-remove'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Remove Metas', 'wpcd' ),
				'desc'                => __( 'This option will reset this dashboard so that it appears that GoAccess is not installed.', 'wpcd' ),              // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to remove metas?  This would reset this dashboard so that it appears that GoAccess is not installed.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		$actions['goaccess-metas-add'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Add Metas', 'wpcd' ),
				'desc'                => __( 'This option will reset this dashboard so that it appears that GoAccess is installed.', 'wpcd' ), // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to remove metas?  This would reset this dashboard so that it appears that GoAccess is installed on the server.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		/* 3rd party / limited support notice */
		$actions['goaccess-third-party-notice-header'] = array(
			'label'          => __( 'Important Notice', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'GoAccess is a 3rd party product and is provided as a convenience.  It is not a core component of this dashboard. Technical support is limited.', 'wpcd' ),
			),
		);

		return $actions;

	}

	/**
	 * Install / manage goaccess
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_goaccess( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'goaccess_install':
				// Make sure all three required fields have been provided and return error if not.
				if ( empty( $args['goaccess_domain'] ) ) {
					return new \WP_Error( __( 'Unable to setup GoAccess - no domain was was provided.', 'wpcd' ) );
				} else {
					$args['domain'] = $args['goaccess_domain']; // make sure that there is a 'domain' key in the args array.
				}

				if ( empty( $args['goaccess_auth_user'] ) ) {
					return new \WP_Error( __( 'Unable to setup GoAccess - no user name was was provided.', 'wpcd' ) );
				} else {
					$args['user'] = $args['goaccess_auth_user']; // make sure that there is a 'user' key in the args array.
				}

				if ( empty( $args['goaccess_auth_pass'] ) ) {
					return new \WP_Error( __( 'Unable to setup GoAccess - no password was was provided.', 'wpcd' ) );
				} else {
					$args['pass'] = $args['goaccess_auth_pass']; // make sure that there is a 'pass' key in the args array.
				}

				break;

			case 'goaccess_auth_change':
				// Make sure all required fields have been provided and return error if not.
				if ( empty( $args['goaccess_auth_user'] ) ) {
					return new \WP_Error( __( 'Unable to setup GoAccess - no user name was was provided.', 'wpcd' ) );
				} else {
					$args['user'] = $args['goaccess_auth_user']; // make sure that there is a 'user' key in the args array.
				}

				if ( empty( $args['goaccess_auth_pass'] ) ) {
					return new \WP_Error( __( 'Unable to setup GoAccess - no password was was provided.', 'wpcd' ) );
				} else {
					$args['pass'] = $args['goaccess_auth_pass']; // make sure that there is a 'pass' key in the args array.
				}

				break;

			case 'goaccess-toggle-ssl':
				// Action needs to set based on current status of SSL in the database.
				$goaccess_ssl = get_post_meta( $id, 'wpcd_wpapp_goaccess_ssl', true );
				if ( empty( $goaccess_ssl ) || 'off' === $goaccess_ssl ) {
					$action = 'goaccess_ssl_enable';
				} else {
					$action = 'goaccess_ssl_disable';
				}
				break;

			case 'goaccess-metas-remove':
				// Remove goaccess metas.
				$this->remove_metas( $id );
				$success = array(
					'msg'     => __( 'GoAccess metas have been reset. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
					'refresh' => 'yes',
				);
				return $success;
				break;

			case 'goaccess-metas-add':
				// Add goaccess metas.
				update_post_meta( $id, 'wpcd_wpapp_goaccess_installed', 'yes' );
				update_post_meta( $id, 'wpcd_wpapp_goaccess_domain', 'no-domain-provided' );
				$success = array(
					'msg'     => __( 'GoAccess metas have been reset. However, no domain, user or password has been set.  You should now be able to remove GoAccess if necessary and reinstall to set domain, user id and password. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
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
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'goaccess.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => $args['domain'],
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'goaccess.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {
				case 'goaccess_install':
					update_post_meta( $id, 'wpcd_wpapp_goaccess_installed', 'yes' );
					update_post_meta( $id, 'wpcd_wpapp_goaccess_domain', $original_args['domain'] );
					update_post_meta( $id, 'wpcd_wpapp_goaccess_user', self::encrypt( $original_args['goaccess_auth_user'] ) );
					update_post_meta( $id, 'wpcd_wpapp_goaccess_pass', self::encrypt( $original_args['goaccess_auth_pass'] ) );
					$success = array(
						'msg'     => __( 'GoAccess has been installed. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'goaccess_auth_change':
					update_post_meta( $id, 'wpcd_wpapp_goaccess_user', self::encrypt( $original_args['goaccess_auth_user'] ) );
					update_post_meta( $id, 'wpcd_wpapp_goaccess_pass', self::encrypt( $original_args['goaccess_auth_pass'] ) );
					$success = array(
						'msg'     => __( 'Credentials have been updated.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'goaccess_ssl_enable':
					update_post_meta( $id, 'wpcd_wpapp_goaccess_ssl', 'on' );
					$success = array(
						'msg'     => __( 'SSL has been enabled for GoAccess.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'goaccess_ssl_disable':
					update_post_meta( $id, 'wpcd_wpapp_goaccess_ssl', 'off' );
					$success = array(
						'msg'     => __( 'SSL has been disabled for GoAccess.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'goaccess_update':
					$success = array(
						'msg'     => __( 'GoAccess has been upgraded.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'goaccess_remove':
					$this->remove_metas( $id );
					$success = array(
						'msg'     => __( 'goaccess has been removed from the server.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

			}
		}

		return $success;

	}

	/**
	 * Remove all goaccess metas
	 *
	 * @param int $id post id of the server.
	 */
	public function remove_metas( $id ) {
		delete_post_meta( $id, 'wpcd_wpapp_goaccess_installed' );
		delete_post_meta( $id, 'wpcd_wpapp_goaccess_domain' );
		delete_post_meta( $id, 'wpcd_wpapp_goaccess_ssl' );
		delete_post_meta( $id, 'wpcd_wpapp_goaccess_user' );
		delete_post_meta( $id, 'wpcd_wpapp_goaccess_pass' );
	}

}

new WPCD_WORDPRESS_TABS_SERVER_GOACCESS();
