<?php
/**
 * SSL
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SSL
 */
class WPCD_WORDPRESS_TABS_SSL extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_SSL constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// Allow the toggle_ssl_status action to be triggered via an action hook.  Will primarily be used by the woocommerce add-on and REST API.
		add_action( 'wpcd_wordpress-app_do_toggle_ssl_status', array( $this, 'toggle_ssl_status_action' ), 10, 2 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'ssl';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_ssl_tab';
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
				'label' => __( 'SSL', 'wpcd' ),
				'icon'  => 'fad fa-lock-alt',
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
	 * Gets the fields to be shown in the SSL tab.
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
		$valid_actions = array( 'ssl-status', 'ssl-http2-status' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			if ( in_array( $action, $valid_actions, true ) ) {
				$result = $this->toggle_ssl_status_action( $id, $action );
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the SSL tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		$actions = array();

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Bail if the site is a multisite enabled with wildcard ssl options.
		$multisite_type = get_post_meta( $id, 'wpapp_multisite_type', true );
		if ( 'subdomain-wildcard-ssl' === $multisite_type ) {
			// Show a special header.
			$actions['ssl-status-header-wc-enabled'] = array(
				'name'           => '',
				'label'          => __( 'WildCard SSL Multisite', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'This site is a MULTISITE that is configured to use a wildcard SSL certificate. You need to manage SSL options under the MULTISITE tab.', 'wpcd' ),
				),
			);

			return $actions;
		}

		/**
		 * Basic checks passed, ok to proceed
		 */

		// What type of web server are we running?
		$webserver_type = $this->get_web_server_type( $id );

		// Get SSL status.
		$status = get_post_meta( $id, 'wpapp_ssl_status', true );
		if ( empty( $status ) ) {
			$status = 'off';
		}

		/* Set the confirmation prompt based on the the current status of this flag */
		$confirmation_prompt = '';
		if ( 'on' === $status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable SSL?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable SSL?', 'wpcd' );
		}

		// Get HTTP2 status.
		$http2_status = $this->http2_status( $id );

		/* SSL */
		$actions['ssl-status-header'] = array(
			'name'           => '',
			'label'          => __( 'SSL', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Manage your SSL certificates.', 'wpcd' ),
			),
		);

		if ( 'on' <> $status ) {
			$desc = __( 'Click to enable SSL. <br />Turning this on will result in an attempt to obtain a certificate from LETSEncrypt.  <br />If it fails, check the logs under the SSH LOG menu option. <br />Note that if you attempt to turn on SSL too many times in a row LETSEncrypt will block your domain for 7 days or more.', 'wpcd' );
		} else {
			$desc = __( 'Click to disable SSL', 'wpcd' );
		}

		$actions['ssl-status'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $status === 'on',
				'desc'                => $desc,
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'switch',
		);

		/* Show SSL notes if SSL is not turned on. */
		if ( 'on' <> $status ) {
			$actions['ssl-notes-heading'] = array(
				'type'           => 'heading',
				'label'          => __( 'Some things to be aware of before enabling SSL', 'wpcd' ),
				'raw_attributes' => array(
					'desc' => __( 'Please read before attempting to turn on SSL for your site!', 'wpcd' ),
				),
			);
			$actions['ssl-notes-1']       = array(
				'type'           => 'custom_html',
				'label'          => '',
				'raw_attributes' => array(
					'std' => __( '1. If you are behind a proxy service, you might need to turn it off temporarily prior to enabling ssl.  This allows LETSEncrypt to connect directly and verify your site. <b>CloudFlare</b> will usually work as-is but if you are having trouble, try turning it off as well.', 'wpcd' ),
				),
			);
			$actions['ssl-notes-2']       = array(
				'type'           => 'custom_html',
				'label'          => '',
				'raw_attributes' => array(
					'std' => __( '2. Certain providers automatically deploy a firewall or NAT. This means that you need to manually enable HTTPS/port 443 through the NAT/FIREWALL.  Such services include AWS EC2 and AWS LIGHTSAIL.  So please make sure you enable HTTPS through the firewall before enabling SSL here. Digital Ocean, Linode and Vultr do not automatically deploy a nat/firewall so you should not encounter any issues there.', 'wpcd' ),
				),
			);
		}

		/* If SSL is on, show HTTP 2 options */
		if ( 'on' === $status ) {

			// Check multisite status and do nothing if multisite is enabled.
			if ( 'on' <> get_post_meta( $id, 'wpapp_multisite_enabled', true ) ) {

				/* Set the HTTP2 title and other text based on the type of webserver we're running. */
				switch ( $webserver_type ) {
					case 'ols':
					case 'ols-enterprise':
						$http_fragment_text = __( 'HTTP2', 'wpcd' ); // This should be "HTTP2/HTTP3/SPDY" but I'm not convinced that lsws is actually handling this correctly.
						break;

					case 'nginx':
					default:
						$http_fragment_text = __( 'HTTP2', 'wpcd' );
						break;

				}

				/* Set the confirmation prompt based on the the current status of this flag */
				$confirmation_prompt = '';
				if ( 'on' === $http2_status ) {
					$confirmation_prompt_http2 = sprintf( __( 'Are you sure you would like to disable %s?', 'wpcd' ), $http_fragment_text );
				} else {
					$confirmation_prompt_http2 = sprintf( __( 'Are you sure you would like to enable %s?', 'wpcd' ), $http_fragment_text );
				}

				$actions['ssl-http2-header'] = array(
					'name'           => '',
					'label'          => sprintf( '%s', $http_fragment_text ),
					'type'           => 'heading',
					'raw_attributes' => array(
						'desc' => sprintf( __( 'Manage %s', 'wpcd' ), $http_fragment_text ),
					),
				);

				$actions['ssl-http2-status'] = array(
					'label'          => '',
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => $http2_status === 'on',
						'confirmation_prompt' => $confirmation_prompt_http2,
					),
					'type'           => 'switch',
				);

			}
		}

		return $actions;
	}

	/**
	 * Helper function to set some meta values before and after toggling SSL.
	 *
	 * Can be called directly or by an action hook.
	 *
	 * Action hook: wpcd_wordpress-app_do_toggle_ssl_status  (Optional).
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed  - 'ssl-status' or 'ssl-http2-status'.
	 *
	 * @return string|WP_Error
	 */
	public function toggle_ssl_status_action( $id, $action ) {

		// What type of web server are we running?
		$webserver_type = $this->get_web_server_type( $id );

		$result = '';

		switch ( $action ) {
			case 'ssl-status':
				$current_status = get_post_meta( $id, 'wpapp_ssl_status', true );
				if ( empty( $current_status ) ) {
					$current_status = 'off';
				}
				$result = $this->toggle_ssl_status( $id, $current_status === 'on' ? 'disable' : 'enable' );
				if ( ! is_wp_error( $result ) ) {

					// What's the new status?
					$new_ssl_status = 'on' === $current_status ? 'off' : 'on';

					// Update metas.
					$this->set_ssl_status( $id, $new_ssl_status );

					$result = array( 'refresh' => 'yes' );
				}
				break;

			case 'ssl-http2-status':
				$current_status = $this->http2_status( $id );
				$result         = $this->toggle_ssl_status( $id, $current_status === 'on' ? 'disable_http2' : 'enable_http2' );
				if ( ! is_wp_error( $result ) ) {
					update_post_meta( $id, 'wpapp_ssl_http2_status', $current_status === 'on' ? 'off' : 'on' );
					$result = array( 'refresh' => 'yes' );
				}
				break;

		}

		return $result;  // Will not matter in an action hook.

	}

	/**
	 * Construct the command to toggle SSL status and then send it via SSH to the server.
	 *
	 * Action hook: wpcd_wordpress-app_do_toggle_ssl_status
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'enable' or 'disable' for ssl and 'enable_http2' or 'disable_http2' for http.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	public function toggle_ssl_status( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// What type of web server are we running?
		$webserver_type = $this->get_web_server_type( $id );

		// Do not allow any changes to SSL if HTTP2 is turned on and the server is NGINX.
		if ( 'nginx' === $webserver_type ) {
			if ( 'on' === $this->http2_status( $id ) && ( 'enable' === $action || 'disable' === $action ) ) {
				return new \WP_Error( __( 'Please disable HTTP2 before attempting to change your SSL status', 'wpcd' ) );
			}
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_https.txt',
			array(
				'command' => "{$action}_https",
				'action'  => $action,
				'domain'  => get_post_meta( $id, 'wpapp_domain', true ),
				'email'   => get_post_meta(
					$id,
					'wpapp_email',
					true
				),
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_https.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}
		return $success;
	}

}

new WPCD_WORDPRESS_TABS_SSL();
