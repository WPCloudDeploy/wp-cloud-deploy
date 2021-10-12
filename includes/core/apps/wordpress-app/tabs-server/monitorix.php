<?php
/**
 * Monitorix Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_MONITORIX
 */
class WPCD_WORDPRESS_TABS_SERVER_MONITORIX extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
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
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['monitorix'] = array(
			'label' => __( 'Monitorix', 'wpcd' ),
			'icon'  => 'far fa-traffic-light-stop',
		);
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the MONITORIX tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {
		return $this->get_fields_for_tab( $fields, $id, 'monitorix' );
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
			case 'monitorix-install':
				$action = 'install_monitorix';
				$result = $this->manage_monitorix( $id, $action );
				break;
			case 'monitorix-remove':
				$action = 'remove_monitorix';
				$result = $this->manage_monitorix( $id, $action );
				break;
			case 'monitorix-upgrade':
				$action = 'upgrade_monitorix';
				$result = $this->manage_monitorix( $id, $action );
				break;
			case 'monitorix-toggle-memcached':
				$result = $this->manage_monitorix( $id, $action );
				break;
			case 'monitorix-toggle-nginx':
				$result = $this->manage_monitorix( $id, $action );
				break;
				break;
			case 'monitorix-toggle-mysql':
				$result = $this->manage_monitorix( $id, $action );
				break;
			case 'monitorix-toggle-ssl':
				$result = $this->manage_monitorix( $id, $action );
				break;
			case 'monitorix-metas-add':
				$result = $this->manage_monitorix( $id, $action );
				break;
			case 'monitorix-metas-remove':
				$result = $this->manage_monitorix( $id, $action );
				break;

		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the MONITORIX tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_monitorix_fields( $id );

	}

	/**
	 * Gets the fields for the services to be shown in the MONITORIX tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_monitorix_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// is monitorix installed?
		$monitorix_status = get_post_meta( $id, 'wpcd_wpapp_monitorix_installed', true );

		if ( empty( $monitorix_status ) ) {
			// Monitorix is not installed.
			$desc = __( 'Monitorix provides a graphical UI in a web browser where you can monitor the resources on your server.  However, it is not currently installed on the server.<br />  To install it, fill out the domain name below and click the install button.', 'wpcd' );

			$actions['monitorix-header'] = array(
				'label'          => __( 'Monitorix', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// Since Monitorix is not installed show only install button and collect information related to the installation.
			$actions['monitorix-domain']          = array(
				'label'          => __( 'Domain', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'std'            => '',
					'desc'           => sprintf( __( 'Monitorix needs to be accessed via a domain or subdomain that points to the server - <b>%1$s</b> on ip <b>%2$s</b>.', 'wpcd' ), $this->get_server_name( $id ), $this->get_ipv4_address( $id ) ),
					'size'           => 120,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'monitorix_domain',
				),
			);
			$actions['monitorix-basic-auth-user'] = array(
				'label'          => __( 'User Name', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'User name to use to log into the Monitorix dashboard', 'wpcd' ),
					'size'           => 60,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'monitorix_auth_user',
				),

			);

			$actions['monitorix-basic-auth-pw'] = array(
				'label'          => __( 'Password', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Password to use when accessing the Monitorix dashboard', 'wpcd' ),
					'size'           => 60,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'monitorix_auth_pass',
				),
			);
			$actions['monitorix-install']       = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Install Monitorix', 'wpcd' ),
					'desc'                => __( 'Click the button to start installing Monitorix on the server.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to install the Monitorix service?', 'wpcd' ),
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_monitorix-domain', '#wpcd_app_action_monitorix-basic-auth-user', '#wpcd_app_action_monitorix-basic-auth-pw' ) ),
				),
				'type'           => 'button',
			);

		}

		if ( 'yes' === $monitorix_status ) {
			/* Monitorix is installed and active */
			$desc = __( 'Monitorix provides a graphical UI in a web browser where you can monitor the resources on your server.', 'wpcd' );

			/* Get user id and password */
			$monitorix_user = $this::decrypt( get_post_meta( $id, 'wpcd_wpapp_monitorix_user', true ) );
			$monitorix_pass = $this::decrypt( get_post_meta( $id, 'wpcd_wpapp_monitorix_pass', true ) );

			$actions['monitorix-header'] = array(
				'label'          => __( 'Monitorix', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// Construct link to access monitorix.
			if ( 'on' === get_post_meta( $id, 'wpcd_wpapp_monitorix_ssl', true ) ) {
				$monitorix_url = 'https://' . "$monitorix_user:$monitorix_pass@" . get_post_meta( $id, 'wpcd_wpapp_monitorix_domain', true ) . '/' . 'monitorix';
			} else {
				$monitorix_url = 'http://' . "$monitorix_user:$monitorix_pass@" . get_post_meta( $id, 'wpcd_wpapp_monitorix_domain', true ) . '/' . 'monitorix';
			}

			$launch = sprintf( '<a href="%s" target="_blank">', $monitorix_url ) . __( 'Launch Monitorix', 'wpcd' ) . '</a>';

			$actions['monitorix-launch'] = array(
				'label'          => __( 'Launch Monitorix', 'wpcd' ),
				'type'           => 'button',
				'raw_attributes' => array(
					'std'  => $launch,
					'desc' => 'Launch Monitorix located at ' . $monitorix_url,
				),
			);

			$actions[] = array(
				'label'          => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => __( 'User Id: ', 'wpcd' ) . $monitorix_user . '<br />' . __( 'Password: ', 'wpcd' ) . $monitorix_pass,
				),
			);

			/* Monitorix SSL Options */
			$desc = __( 'Activate or deactivate ssl.' );

			$actions['monitorix-ssl-options'] = array(
				'label'          => __( 'Monitorix SSL Options', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// What's the current SSL status?
			$monitorix_ssl = get_post_meta( $id, 'wpcd_wpapp_monitorix_ssl', true );
			if ( empty( $monitorix_ssl ) ) {
				$monitorix_ssl = 'off';
			}

			// Set confirmation prompt based on current ssl status.
			$confirmation_prompt = '';
			if ( 'on' === $monitorix_ssl ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable SSL?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable SSL?', 'wpcd' );
			}

			$actions['monitorix-toggle-ssl'] = array(
				'label'          => __( 'SSL Status', 'wpcd' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'wpcd' ),
					'off_label'           => __( 'Disabled', 'wpcd' ),
					'std'                 => $monitorix_ssl === 'on',
					'desc'                => __( 'Enable or disable SSL', 'wpcd' ),
					'tooltip'             => __( 'Turning this on will result in an attempt to obtain a certificate from LETSEncrypt.  For this to be successful your DNS must be pointing to the domain used when you installed Monitorix. <br />If the LETSEncrypt request fails, check the logs under the SSH LOG menu option. <br />Note that if you attempt to turn on SSL too many times in a row LETSEncrypt will block your domain for a period of time.', 'wpcd' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monitorix SSL Options */

			/* Monitorix is installed and active - provide some options */
			$desc = __( 'Activate or deactivate some Monitorix components.' );

			$actions['monitorix-options'] = array(
				'label'          => __( 'Monitorix Options', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			/**
			 * Monitorix Nginx Options
			 */
			// Start with What's the current Nginx status?
			$monitorix_nginx = get_post_meta( $id, 'wpcd_wpapp_monitorix_nginx', true );
			if ( empty( $monitorix_nginx ) ) {
				$monitorix_nginx = 'on';
			}

			// Set confirmation prompt based on current nginx status.
			$confirmation_prompt = '';
			if ( 'on' === $monitorix_nginx ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable Nginx monitoring?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable Nginx montioring?', 'wpcd' );
			}

			$actions['monitorix-toggle-nginx'] = array(
				'label'          => __( 'Nginx Status', 'wpcd' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'wpcd' ),
					'off_label'           => __( 'Disabled', 'wpcd' ),
					'std'                 => $monitorix_nginx === 'on',
					'desc'                => __( 'Enable or disable Nginx monitoring.', 'wpcd' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monitorix Nginx Options */

			/**
			 * Monitorix Mariadb/Mysql Options
			 */
			// Start with What's the current MYSQL status?
			$monitorix_mysql = get_post_meta( $id, 'wpcd_wpapp_monitorix_mysql', true );
			if ( empty( $monitorix_mysql ) ) {
				$monitorix_mysql = 'on';
			}

			// Set confirmation prompt based on current mysql status.
			$confirmation_prompt = '';
			if ( 'on' === $monitorix_mysql ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable MYSQL monitoring?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable MYSQL montioring?', 'wpcd' );
			}

			$actions['monitorix-toggle-mysql'] = array(
				'label'          => __( 'MYSQL/MariaDB Status', 'wpcd' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'wpcd' ),
					'off_label'           => __( 'Disabled', 'wpcd' ),
					'std'                 => $monitorix_mysql === 'on',
					'desc'                => __( 'Enable or disable MYSQL (database) monitoring.', 'wpcd' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monitorix MYSQL Options */

			/**
			 * Monitorix Memcached Options
			 */
			// Start with What's the current Memcached status?
			$monitorix_memcached = get_post_meta( $id, 'wpcd_wpapp_monitorix_memcached', true );
			if ( empty( $monitorix_memcached ) ) {
				$monitorix_memcached = 'off';
			}

			// Set confirmation prompt based on current ssl status.
			$confirmation_prompt = '';
			if ( 'on' === $monitorix_memcached ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable Memcached monitoring?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable Memcached montioring?', 'wpcd' );
			}

			$actions['monitorix-toggle-memcached'] = array(
				'label'          => __( 'Memcached Status', 'wpcd' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'wpcd' ),
					'off_label'           => __( 'Disabled', 'wpcd' ),
					'std'                 => $monitorix_memcached === 'on',
					'desc'                => __( 'Enable or disable Memcached monitoring.', 'wpcd' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monitorix Memcached Options */

			/* Uninstall / Upgrade Monitorix*/
			$actions['monitorix-uninstall-upgrade'] = array(
				'label'          => __( 'Uninstall or Upgrade Monitorix', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Remove or upgrade Monitorix', 'wpcd' ),
				),
			);

			$actions['monitorix-remove'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Uninstall Monitorix', 'wpcd' ),
					'desc'                => __( 'This option will completely remove Monitorix from the server.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to remove Monitorix from the server?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);

			$actions['monitorix-upgrade'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Upgrade Monitorix', 'wpcd' ),
					'desc'                => __( 'This option will upgrade Monitorix.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to upgrade Monitorix on the server?', 'wpcd' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);
			/* End uninstall / Upgrade Monitorix*/

		}

		if ( 'no' === $monitorix_status ) {
			/* Monitorix is installed but not active */
			$desc = __( 'Monitorix provides a graphical UI in a web browser where you can monitor the resources on your server.  However, it is NOT active on your server at this time.', 'wpcd' );

			$actions['monitorix-header'] = array(
				'label'          => __( 'Monitorix', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);
		}

		/* Toggle Metas */
		$actions['monitorix-metas-header'] = array(
			'label'          => __( 'Manage Monitorix Metas', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Sometimes things get out of sync between this dashboard and what is actually on the server.  Use these options to reset things', 'wpcd' ),
			),
		);

		$actions['monitorix-metas-remove'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Remove Metas', 'wpcd' ),
				'desc'                => __( 'This option will reset this dashboard so that it appears that Monitorix is not installed.', 'wpcd' ), // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to remove metas?  This would reset this dashboard so that it appears that Monitorix is not installed.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		$actions['monitorix-metas-add'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Add Metas', 'wpcd' ),
				'desc'                => __( 'This option will reset this dashboard so that it appears that Monitorix is installed.', 'wpcd' ),                 // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to remove metas?  This would reset this dashboard so that it appears that Monitorix is installed on the server.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		/* 3rd party / limited support notice */
		$actions['monitorix-third-party-notice-header'] = array(
			'label'          => __( 'Important Notice', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Monitorix is a 3rd party product and is provided as a convenience.  It is not a core component of this dashboard. Technical support is limited.', 'wpcd' ),
			),
		);

		return $actions;

	}

	/**
	 * Install / manage Monitorix
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_monitorix( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = wp_parse_args( sanitize_text_field( $_POST['params'] ) );

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'install_monitorix':
				// Make sure all three required fields have been provided and return error if not.
				if ( empty( $args['monitorix_domain'] ) ) {
					return new \WP_Error( __( 'Unable to setup monitorix - no domain was was provided.', 'wpcd' ) );
				} else {
					$args['domain'] = $args['monitorix_domain']; // make sure that there is a 'domain' key in the args array.
				}

				if ( empty( $args['monitorix_auth_user'] ) ) {
					return new \WP_Error( __( 'Unable to setup monitorix - no user name was was provided.', 'wpcd' ) );
				} else {
					$args['user'] = $args['monitorix_auth_user']; // make sure that there is a 'user' key in the args array.
				}

				if ( empty( $args['monitorix_auth_pass'] ) ) {
					return new \WP_Error( __( 'Unable to setup monitorix - no password was was provided.', 'wpcd' ) );
				} else {
					$args['pass'] = $args['monitorix_auth_pass']; // make sure that there is a 'pass' key in the args array.
				}

				break;

			case 'monitorix-toggle-nginx':
				// Action needs to set based on current status of NGINX in the database.
				$monitorix_nginx = get_post_meta( $id, 'wpcd_wpapp_monitorix_nginx', true );
				if ( empty( $monitorix_nginx ) || 'on' === $monitorix_nginx ) {
					$action = 'disable_nginx_monitorix';
				} else {
					$action = 'enable_nginx_monitorix';
				}
				break;

			case 'monitorix-toggle-mysql':
				// Action needs to set based on current status of MYSQL in the database.
				$monitorix_mysql = get_post_meta( $id, 'wpcd_wpapp_monitorix_mysql', true );
				if ( empty( $monitorix_mysql ) || 'on' === $monitorix_mysql ) {
					$action = 'disable_mysql_monitorix';
				} else {
					$action = 'enable_mysql_monitorix';
				}
				break;

			case 'monitorix-toggle-memcached':
				// Action needs to set based on current status of MEMCACHED in the database.
				$monitorix_memcached = get_post_meta( $id, 'wpcd_wpapp_monitorix_memcached', true );
				if ( empty( $monitorix_memcached ) || 'on' === $monitorix_memcached ) {
					$action = 'disable_memcached_monitorix';
				} else {
					$action = 'enable_memcached_monitorix';
				}
				break;

			case 'monitorix-toggle-ssl':
				// Action needs to set based on current status of SSL in the database.
				$monitorix_ssl = get_post_meta( $id, 'wpcd_wpapp_monitorix_ssl', true );
				if ( empty( $monitorix_ssl ) || 'off' === $monitorix_ssl ) {
					$action = 'enable_monitorix_ssl';
				} else {
					$action = 'disable_monitorix_ssl';
				}
				break;

			case 'monitorix-metas-remove':
				// Remove Monitorix metas.
				$this->remove_metas( $id );
				$success = array(
					'msg'     => __( 'Monitorix metas have been reset. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
					'refresh' => 'yes',
				);
				return $success;
				break;

			case 'monitorix-metas-add':
				// Add Monitorix metas.
				update_post_meta( $id, 'wpcd_wpapp_monitorix_installed', 'yes' );
				update_post_meta( $id, 'wpcd_wpapp_monitorix_domain', 'no-domain-provided' );
				$success = array(
					'msg'     => __( 'Monitorix metas have been reset. However, no domain, user or password has been set.  You should now be able to remove Monitorix if necessary and reinstall to set domain, user id and password. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
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
			'monitorix.txt',
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
		$success = $this->is_ssh_successful( $result, 'monitorix.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {
				case 'install_monitorix':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_installed', 'yes' );
					update_post_meta( $id, 'wpcd_wpapp_monitorix_domain', $original_args['domain'] );
					update_post_meta( $id, 'wpcd_wpapp_monitorix_user', self::encrypt( $original_args['monitorix_auth_user'] ) );
					update_post_meta( $id, 'wpcd_wpapp_monitorix_pass', self::encrypt( $original_args['monitorix_auth_pass'] ) );
					$success = array(
						'msg'     => __( 'Monitorix has been installed. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_nginx_monitorix':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_nginx', 'on' );
					$success = array(
						'msg'     => __( 'NGINX monitoring has been enabled for Monitorix.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_nginx_monitorix':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_nginx', 'off' );
					$success = array(
						'msg'     => __( 'NGINX monitoring has been disabled for Monitorix.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_mysql_monitorix':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_mysql', 'on' );
					$success = array(
						'msg'     => __( 'MYSQL monitoring has been enabled for Monitorix.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_mysql_monitorix':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_mysql', 'off' );
					$success = array(
						'msg'     => __( 'MYSQL monitoring has been disabled for Monitorix.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_memcached_monitorix':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_memcached', 'on' );
					$success = array(
						'msg'     => __( 'MEMCACHED monitoring has been enabled for Monitorix.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_memcached_monitorix':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_memcached', 'off' );
					$success = array(
						'msg'     => __( 'MEMCACHED monitoring has been disabled for Monitorix.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_monitorix_ssl':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_ssl', 'on' );
					$success = array(
						'msg'     => __( 'SSL has been enabled for Monitorix.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_monitorix_ssl':
					update_post_meta( $id, 'wpcd_wpapp_monitorix_ssl', 'off' );
					$success = array(
						'msg'     => __( 'SSL has been disabled for Monitorix.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'upgrade_monitorix':
					$success = array(
						'msg'     => __( 'Monitorix has been upgraded.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'remove_monitorix':
					$this->remove_metas( $id );
					$success = array(
						'msg'     => __( 'Monitorix has been removed from the server.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

			}
		}

		return $success;

	}

	/**
	 * Remove all Monitorix metas
	 *
	 * @param int $id post id of the server.
	 */
	public function remove_metas( $id ) {
		delete_post_meta( $id, 'wpcd_wpapp_monitorix_installed' );
		delete_post_meta( $id, 'wpcd_wpapp_monitorix_domain' );
		delete_post_meta( $id, 'wpcd_wpapp_monitorix_ssl' );
		delete_post_meta( $id, 'wpcd_wpapp_monitorix_nginx' );
		delete_post_meta( $id, 'wpcd_wpapp_monitorix_mysql' );
		delete_post_meta( $id, 'wpcd_wpapp_monitorix_memcached' );
		delete_post_meta( $id, 'wpcd_wpapp_monitorix_user' );
		delete_post_meta( $id, 'wpcd_wpapp_monitorix_pass' );
	}

}

new WPCD_WORDPRESS_TABS_SERVER_MONITORIX();
