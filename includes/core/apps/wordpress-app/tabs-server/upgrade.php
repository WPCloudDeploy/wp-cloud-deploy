<?php
/**
 * Upgrade Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class WPCD_WORDPRESS_TABS_SERVER_UPGRADE
 */
class WPCD_WORDPRESS_TABS_SERVER_UPGRADE extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		// Add bulk action option to the server list screen to run linux updates.
		add_filter( 'bulk_actions-edit-wpcd_app_server', array( $this, 'wpcd_add_new_bulk_actions_server' ) );

		// Action hook to handle bulk actions for server. For example to run linux updates.
		add_filter( 'handle_bulk_actions-edit-wpcd_app_server', array( $this, 'wpcd_bulk_action_handler_server_app' ), 10, 3 );

		// Allow the run_updates_cron action to be triggered via an action hook.  Will primarily be used by the woocommerce add-ons & Bulk Actions.
		add_action( 'wpcd_wordpress-upgrade_linux_all', array( $this, 'upgrade_linux_all' ), 10, 2 );

		/* Pending Logs Background Task: Trigger all linux updates. */
		add_action( 'pending_log_apply_all_linux_updates', array( $this, 'pending_log_apply_all_linux_updates' ), 10, 3 );

		/* Pending Logs Background Task: Trigger linux security updates. */
		add_action( 'pending_log_apply_security_linux_updates', array( $this, 'pending_log_apply_linux_security_updates' ), 10, 3 );

		/* Handle callback success and tag the pending log record as successful */
		add_action( 'wpcd_server_wordpress-app_upgrade_linux_all_action_successful', array( $this, 'handle_upgrade_linux_all_success' ), 10, 3 );
		add_action( 'wpcd_server_wordpress-app_upgrade_linux_security_action_successful', array( $this, 'handle_upgrade_linux_all_success' ), 10, 3 );

		/* Handle callback failures and tag the pending log record as failed */
		add_action( 'wpcd_server_wordpress-app_upgrade_linux_security_action_failed', array( $this, 'handle_upgrade_linux_all_failed' ), 10, 3 );
		add_action( 'wpcd_server_wordpress-app_upgrade_linux_all_action_failed', array( $this, 'handle_upgrade_linux_all_failed' ), 10, 3 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'server_upgrade';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_upgrade_tab';
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
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Upgrades', 'wpcd' ),
				'icon'  => 'fad fa-chart-network',
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
	 * Gets the fields to be shown in the UPGRADE tab.
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
			/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		// Skipping security action check that would allow us to show the user a nice message if it failed.
		// If user is not permitted to do something and actually somehow ends up here, it will fall through the SWITCH statement below and silently fail.

		// Perform actions if allowed to do so.
		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'run-linux-updates-all':
					$action = 'run_updates_cron';  // The bash script expects this.
					$result = $this->upgrade_linux_all( $id, $action );
					break;
				case 'run-linux-updates-security':
					$action = 'run_security_updates_cron';  // The bash script expects this.
					$result = $this->upgrade_linux_all( $id, $action );
					break;

				case 'server-upgrade-290':
					$result = $this->upgrade_290( $id, $action );
					break;
				case 'server-upgrade-290-meta':
					$result = $this->upgrade_290_meta_only( $id, $action );
					break;

				case 'server-upgrade-460':
					$result = $this->upgrade_460( $id, $action );
					break;
				case 'server-upgrade-460-meta':
					$result = $this->upgrade_460_meta_only( $id, $action );
					break;

				case 'server-upgrade-461':
					$result = $this->upgrade_461( $id, $action );
					break;
				case 'server-upgrade-461-meta':
					$result = $this->upgrade_461_meta_only( $id, $action );
					break;

				case 'server-upgrade-462':
					$result = $this->upgrade_462( $id, $action );
					break;
				case 'server-upgrade-462-meta':
					$result = $this->upgrade_462_meta_only( $id, $action );
					break;

				case 'server-upgrade-php81':
					$result = $this->install_php81( $id, $action );
					break;

				case 'server-upgrade-7g':
					$result = $this->upgrade_7g( $id, $action );
					break;

				case 'server-upgrade-wpcli':
					$result = $this->upgrade_wpcli( $id, $action );
					break;

				case 'server-install-phpintl':
					$result = $this->install_php_intl( $id, $action );
					break;

				case 'server-upgrade-delete-meta':
					$result = $this->remove_upgrade_meta( $id, $action );
					break;

			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the UPGRADE tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_upgrade_fields( $id );

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields( $id ) {

		$webserver_type = $this->get_web_server_type( $id );

		$upgrade_check = $this->wpapp_upgrade_must_run_check( $id );

		switch ( $upgrade_check ) {
			case 290:
				$actions = $this->get_upgrade_fields_290( $id );
				break;
			case 460:
				$actions = $this->get_upgrade_fields_460( $id );
				break;
			case 461:
				$actions = $this->get_upgrade_fields_461( $id );
				break;
			case 462:
				$actions = $this->get_upgrade_fields_462( $id );
				break;
			default:
				$actions = $this->get_upgrade_fields_default( $id );
		}

		// 7G Firewall Upgrade Options.  Only applies to NGINX
		if ( ! $this->is_7g16_installed( $id ) && 'nginx' === $webserver_type ) {
			$upgrade_7g_fields = $this->get_upgrade_fields_7g( $id );
			$actions           = array_merge( $actions, $upgrade_7g_fields );
		}

		// PHP 8.1 install options.  Only applies to NGINX since all OLS servers will have it installed already.
		if ( ! $this->is_php_81_installed( $id ) && 'nginx' === $webserver_type ) {
			$upgrade_php_81_fields = $this->get_upgrade_fields_php81( $id );
			$actions               = array_merge( $actions, $upgrade_php_81_fields );
		}

		// WP-CLI Upgrade Options.
		if ( ! $this->is_wpcli27_installed( $id ) ) {
			$upgrade_wpcli_fields = $this->get_upgrade_fields_wpcli( $id );
			$actions              = array_merge( $actions, $upgrade_wpcli_fields );
		}

		// PHP INTL module install options.  Only applies to NGINX since all OLS servers should have it installed already.
		if ( ! $this->is_php_intl_module_installed( $id ) && 'nginx' === $webserver_type ) {
			$upgrade_php_intl_fields = $this->get_upgrade_fields_php_intl( $id );
			$actions                 = array_merge( $actions, $upgrade_php_intl_fields );
		}

		// Linux Updates.
		$upgrade_linux_fields = $this->get_upgrade_fields_linux( $id );
		$actions              = array_merge( $actions, $upgrade_linux_fields );

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen.
	 * These fields are for LINUX level upgrades.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_linux( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'Set a cron on the server to immediately run all pending LINUX updates. There will be no interactive feedback as the updates are run in the background.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'If you need interactive feedback you should log into the server via SSH and run the updates from the command line.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'To view the results of the updates you will also need to log into the server via SSH and examine the log files.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'Please note: Security updates are usually run automatically every night. Therefore you should only use this option to force them to run immediately or to run all updates.', 'wpcd' );

		// Get the number of updates pending...
		// To do this, first get server status data from meta.
		$server_status_items = get_post_meta( $id, 'wpcd_server_status_push', true );

		$pending_updates          = '';
		$secupdates               = '';
		$pending_security_updates = '';
		if ( ! empty( $server_status_items ) ) {

			if ( isset( $server_status_items['total_memory'] ) ) {
				$memory = $server_status_items['total_memory'];

				if ( (int) $memory > 0 ) {
					// We have RAM information which means the server status callback reported at least once and we can safely assume that other status information is available.

					if ( isset( $server_status_items['security_updates'] ) ) {
						$secupdates = $server_status_items['security_updates'];
						/* translators: %s is replaced with the number of security updates available. */
						$pending_security_updates .= sprintf( __( 'There are %s Security Updates ', 'wpcd' ), $secupdates );

						if ( isset( $server_status_items['total_updates'] ) ) {
							/* translators: %s is replaced with the total number of updates available. */
							$pending_updates .= sprintf( __( ' There are %s updates pending.', 'wpcd' ), $server_status_items['total_updates'] );
						}
					}
				}
			}
		}
		// End get number of updates pending.

		$actions['linux-updates-header'] = array(
			'label'          => __( 'Run Linux Updates', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['run-linux-updates-all'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Run All Linux Updates Now', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to run the linux updates for this server?', 'wpcd' ),
				'desc'                => $pending_updates,
			),
			'type'           => 'button',
			'desc'           => $pending_updates,
		);

		$actions['run-linux-updates-security'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Run Linux Security Updates Only', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to run the linux security updates for this server?', 'wpcd' ),
				'desc'                => $pending_security_updates,
			),
			'type'           => 'button',
			'desc'           => $pending_updates,
		);

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * when upgrading to version 2.9.0;
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_290( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'This server needs an upgrade to be compatible with this version of the console (V 2.9.0).', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'This update also includes very important security updates.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'Please click the UPGRADE button below to proceed.', 'wpcd' );

		$actions['server-upgrade-header'] = array(
			'label'          => __( 'Upgrade Server', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-290'] = array(
			'label'          => __( 'Upgrade This Server', 'wpcd' ),
			'raw_attributes' => array(
				'std'                 => __( 'Upgrade This Server', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to upgrade this server?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		$actions['server-upgrade-290-meta'] = array(
			'label'          => __( 'Skip Upgrade', 'wpcd' ),
			'raw_attributes' => array(
				'std'                 => __( 'Do Not Upgrade', 'wpcd' ),
				'desc'                => __( 'Tag server as upgraded without running the upgrade process.  NOT RECOMMENDED - this will likely break functions! Choose this option only when directed by our technical support staff.', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * when upgrading to version 4.6.0;
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_460( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'This server needs an upgrade to be compatible with this version of the console (V 4.6.0).', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'It will insert new NGINX directives into your NGINX.CONF file and create new sub-files in /etc/nginx/common.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= '<br />';
		$upg_desc .= '<span style="color:red;"><b>' . __( 'WARNING: If you have modified your configuration files to enable GZIP, added headers for: X-XSS-Protection, X-Content-Type-Options, Referrer-Policy, X-Download-Options and Strict-Transport-Security, enabled OPCACHE in php.ini or applied ssl_stapling directives then you should NOT run this upgrade and should apply changes manually!', 'wpcd' ) . '</b></span>';
		$upg_desc .= '<br />';
		$upg_desc .= '<br />';
		$upg_desc .= '<span style="color:red;"><b>' . __( 'Please check our website for documentation on manually applying the upgrades for this version.', 'wpcd' ) . '</b></span>';
		$upg_desc .= '<br />';
		$upg_desc .= '<br />';
		$upg_desc .= __( 'After you have backed up your server, please click the UPGRADE button below to proceed with the automatic upgrade.', 'wpcd' );

		$actions['server-upgrade-header'] = array(
			'label'          => __( 'Upgrade Server', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-460'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Upgrade This Server', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to upgrade this server?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		$actions['server-upgrade-notes'] = array(
			'label'          => '',
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std' => sprintf( '<a href="%s">' . __( 'Learn more in the upgrade documentation', 'wpcd' ) . '</a>', 'https://wpclouddeploy.com/documentation/more/technical-upgrade-notes-for-v-4-6-0/' ),
			),
		);

		$actions['server-upgrade-header-skip'] = array(
			'label'          => __( 'Skip Upgrade', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Danger Zone!', 'wpcd' ),
			),
		);

		$actions['server-upgrade-460-meta'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Do Not Upgrade', 'wpcd' ),
				'desc'                => __( 'Tag server as upgraded without running the upgrade process.  NOT RECOMMENDED - this will likely break functions! Choose this option only when directed by our technical support staff.', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * when upgrading to version 4.6.1;
	 *
	 * Version 4.6.1 upgrades are the SNAP modules for Lets Encrypt.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_461( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'This server needs an upgrade to be compatible with this version of the console (V 4.6.1).', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'It will upgrade your LETSENCRYPT stack to use SNAP modules. ', 'wpcd' );
		$upg_desc .= __( 'Note that it is possible that you will get an AJAX timeout while performing this upgrade if you are using a proxy service such as CloudFlare.  This is because the proxy service restricts running scripts to period of time far less than 300 seconds. ', 'wpcd' );
		$upg_desc .= __( 'If the timeout occurs, the process should still complete in the background and you should see this tab showing a successful upgrade after about 2 or 3 minutes.  If not then try the operation again.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= '<br />';
		$upg_desc .= __( 'After you have backed up your server, please click the UPGRADE button below to proceed with the upgrade.', 'wpcd' );

		$actions['server-upgrade-header'] = array(
			'label'          => __( 'Upgrade Server', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-461'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Upgrade This Server', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to upgrade this server?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		$actions['server-upgrade-notes'] = array(
			'label'          => '',
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std' => sprintf( '<a href="%s">' . __( 'Learn more in the upgrade documentation - see part 2', 'wpcd' ) . '</a>', 'https://wpclouddeploy.com/documentation/more/technical-upgrade-notes-for-v-4-6-0/' ),
			),
		);

		$actions['server-upgrade-header-skip'] = array(
			'label'          => __( 'Skip Upgrade', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Danger Zone!', 'wpcd' ),
			),
		);

		$actions['server-upgrade-461-meta'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Do Not Upgrade', 'wpcd' ),
				'desc'                => __( 'Tag server as upgraded without running the upgrade process.  NOT RECOMMENDED - this will likely break functions! Choose this option only when directed by our technical support staff.', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * when upgrading to version 4.6.2;
	 *
	 * Version 4.6.2 upgrades is the 7G firewall files.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_462( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'This server needs an upgrade to be compatible with this version of the console (V 4.6.2).', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'It will add some files needed for the 7G firewall in future versions of WPCD.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= '<br />';
		$upg_desc .= __( 'After you have backed up your server, please click the UPGRADE button below to proceed with the upgrade.', 'wpcd' );

		$actions['server-upgrade-header'] = array(
			'label'          => __( 'Upgrade Server', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-462'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Upgrade This Server', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to upgrade this server?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		$actions['server-upgrade-notes'] = array(
			'label'          => '',
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std' => sprintf( '<a href="%s">' . __( 'Learn more in the upgrade documentation - see part 3', 'wpcd' ) . '</a>', 'https://wpclouddeploy.com/documentation/more/technical-upgrade-notes-for-v-4-6-0/' ),
			),
		);

		$actions['server-upgrade-header-skip'] = array(
			'label'          => __( 'Skip Upgrade', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Danger Zone!', 'wpcd' ),
			),
		);

		$actions['server-upgrade-462-meta'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Do Not Upgrade', 'wpcd' ),
				'desc'                => __( 'Tag server as upgraded without running the upgrade process.  NOT RECOMMENDED - this will likely break functions! Choose this option only when directed by our technical support staff.', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * if PHP 8.1 needs to be installed.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_php81( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'Use this button to install PHP 8.1 if it was released after your server was created. WPCD V 4.13 and later automatically installs PHP 8.1 on new servers. But older servers will not have it.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'Before running this, you should check to see if your server needs to be restarted because of prior upgrades.  If so, please restart your server before using this option to install PHP 8.1', 'wpcd' );

		$actions['server-upgrade-header-php81'] = array(
			'label'          => __( 'Install PHP 8.1', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-php81'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Install PHP 8.1 on this server', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to install PHP 8.1 on this server?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		/*
		$actions['server-upgrade-php81-meta'] = array(
		'label'          => '',
		'raw_attributes' => array(
			'std'                 => __( 'Remove PHP 8.1 Install Option', 'wpcd' ),
			'desc'                => __( 'Tag server as having PHP 8.1 installed.', 'wpcd' ),
			// make sure we give the user a confirmation prompt.
			'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'wpcd' ),
		),
		'type'           => 'button',
		);
		*/

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * if the 7G firewall needs to be upgraded.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_7g( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'Use this button to install the latest version of the 7G Firewall rules (V1.6).', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'This will OVERWRITE any customizations you have made to the default 7G rules file.', 'wpcd' );

		$actions['server-upgrade-header-7g'] = array(
			'label'          => __( 'Upgrade 7G Firewall Rules', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-7g'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Upgrade 7G Rules', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to upgrade the 7G Firewall rules on this server? It will overwrite any changes you might have made to the default rules file.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		/*
		$actions['server-upgrade-7g-meta'] = array(
		'label'          => '',
		'raw_attributes' => array(
			'std'                 => __( 'Remove 7G Upgrade Option', 'wpcd' ),
			'desc'                => __( 'Tag server as having 7G upgraded.', 'wpcd' ),
			// make sure we give the user a confirmation prompt.
			'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'wpcd' ),
		),
		'type'           => 'button',
		);
		*/

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * when wpcli needs to be upgraded.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_wpcli( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'Use this button to upgrade WP-CLI to v2.7.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'If your server already has the latest version this will have no effect.', 'wpcd' );

		$actions['server-upgrade-header-wpcli'] = array(
			'label'          => __( 'Upgrade WPCLI', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-wpcli'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Upgrade WPCLI', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to upgrade WPCLI on this server?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		/*
		$actions['server-upgrade-wpcli-meta'] = array(
		'label'          => '',
		'raw_attributes' => array(
			'std'                 => __( 'Remove WPCLI Upgrade Option', 'wpcd' ),
			'desc'                => __( 'Tag server as having WPCLI upgraded.', 'wpcd' ),
			// make sure we give the user a confirmation prompt.
			'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'wpcd' ),
		),
		'type'           => 'button',
		);
		*/

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * if the PHP INTL module needs to be installed.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_php_intl( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'Use this button to install the PHP INTL module on your pre-wpcd-4.16.3 server. New servers installed with V4.16.3 or later should already have this installed.', 'wpcd' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'Before running this, you should check to see if your server needs to be restarted because of prior upgrades.  If so, please restart your server before using this option.', 'wpcd' );
		$upg_desc .= '<br /><b>';
		$upg_desc .= __( 'You will need to run this TWICE.  The first time you WILL get an AJAX error.  Run it again and it will run to completion.', 'wpcd' );
		$upg_desc .= '</b>';

		$actions['server-install-header-phpintl'] = array(
			'label'          => __( 'Install the PHP INTL Module', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-install-phpintl'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Install PHP INTL module on this server', 'wpcd' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to install the PHP INTL module on this server?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		return $actions;
	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * when there are no upgrades to be done.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_default( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'There are no configuration updates required for this server.', 'wpcd' );
		$upg_desc .= '<br />';

		$actions['server-upgrade-header'] = array(
			'label'          => __( 'Upgrade Server', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		if ( defined( 'WPCD_SHOW_REMOVE_UPGRADE_META' ) && WPCD_SHOW_REMOVE_UPGRADE_META ) {
			// Show the option to completely delete the upgrade meta from the server.
			$actions['server-upgrade-delete-meta'] = array(
				'label'          => __( 'Remove Upgrade Meta', 'wpcd' ),
				'raw_attributes' => array(
					'std'                 => __( 'Remove Upgrade Meta', 'wpcd' ),
					'desc'                => __( 'Removing the upgrade meta from this server will give you the option to rerun updates. This can (and will likely) break your server!!! So, please choose this option only when directed by our technical support staff.', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to remove the upgrade metas from this server and return all updates?', 'wpcd' ),
				),
				'type'           => 'button',
			);
		}

		return $actions;

	}

	/**
	 * Run upgrade script for a server to upgrade to V 290.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_290( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrades_290.txt',
			array(
				'action' => $action,
				'domain' => $domain,
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'run_upgrades_290.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
				update_post_meta( $id, 'wpcd_last_upgrade_done', 290 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'Upgrade completed - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Run upgrade script for a server to upgrade to V 460.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_460( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrades_460.txt',
			array(
				'action' => $action,
				'domain' => $domain,
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// evaluate results.
		if ( strpos( $result, 'journalctl -xe' ) !== false ) {
			// Looks like there was a problem with restarting the NGINX - So update completion meta and return message.
			update_post_meta( $id, 'wpcd_last_upgrade_done', 460 );
			/* translators: %s is replaced with the text of the result of the operation. */
			return new \WP_Error( sprintf( __( 'There was a problem restarting the nginx server after the upgrade - here is the full output of the upgrade process: %s', 'wpcd' ), $result ) );
		}

		// If we're here, we know that the nginx server restarted ok so let's do standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrades_460.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'wpcd_last_upgrade_done', 460 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'Upgrade completed - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Run upgrade script for a server to upgrade to V 461.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_461( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrades_461.txt',
			array(
				'action'      => $action,
				'interactive' => 'no',
			)
		);

		// log  (PHPcs warning normally issued because of print_r).
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// evaluate results.
		if ( strpos( $result, 'journalctl -xe' ) !== false ) {
			// Looks like there was a problem with restarting the NGINX - So update completion meta and return message.
			update_post_meta( $id, 'wpcd_last_upgrade_done', 461 );
			/* translators: %s is replaced with the text of the result of the operation. */
			return new \WP_Error( sprintf( __( 'There was a problem restarting the nginx server after the upgrade - here is the full output of the upgrade process: %s', 'wpcd' ), $result ) );
		}

		// If we're here, we know that the nginx server restarted ok so let's do standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrades_461.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'wpcd_last_upgrade_done', 461 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'Upgrade completed - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Run upgrade script for a server to upgrade to V 462.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_462( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrades_462.txt',
			array(
				'action'      => $action,
				'interactive' => 'no',
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// evaluate results.
		if ( strpos( $result, 'journalctl -xe' ) !== false ) {
			// Looks like there was a problem with restarting the NGINX - So update completion meta and return message.
			update_post_meta( $id, 'wpcd_last_upgrade_done', 462 );
			/* translators: %s is replaced with the text of the result of the operation. */
			return new \WP_Error( sprintf( __( 'There was a problem restarting the nginx server after the upgrade - here is the full output of the upgrade process: %s', 'wpcd' ), $result ) );
		}

		// If we're here, we know that the nginx server restarted ok so let's do standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrades_462.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'wpcd_last_upgrade_done', 462 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'Upgrade completed - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Run install script for PHP 8.1
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function install_php81( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrade_install_php_81.txt',
			array(
				'action'      => $action,
				'interactive' => 'no',
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Make sure we don't have a wp_error object being returned...
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( __( 'There was a problem installing PHP 8.1- please check the server logs for more information.', 'wpcd' ) );
		}

		// evaluate results.
		if ( strpos( $result, 'journalctl -xe' ) !== false ) {
			// Looks like there was a problem with restarting the NGINX - So update completion meta and return message.
			update_post_meta( $id, 'wpcd_server_php81_installed', 1 );
			/* translators: %s is replaced with the text of the result of the operation. */
			return new \WP_Error( sprintf( __( 'There was a problem restarting the nginx server after the upgrade - here is the full output of the upgrade process: %s', 'wpcd' ), $result ) );
		}

		// If we're here, we know that the nginx server restarted ok so let's do standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrade_install_php_81.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'wpcd_server_php81_installed', 1 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'PHP 8.1 install has been completed - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Run upgrade script for 7G firewall rules
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_7g( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get Webserver Type.
		$webserver_type = $this->get_web_server_type( $id );

		// Bail if not an NGINX server.
		if ( 'nginx' !== $webserver_type ) {
			// We really shouldn't get here - if we do it likely means someone has bypassed a bunch of security checks.
			return new \WP_Error( __( 'This action cannot be run on a server running OLS.  It can only be run on server running NGINX.', 'wpcd' ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrade_7g.txt',
			array(
				'action'      => $action,
				'interactive' => 'no',
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Make sure we don't have a wp_error object being returned...
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( __( 'There was a problem upgrading the 7G firewall rules - please check the server logs for more information.', 'wpcd' ) );
		}

		// evaluate results.
		if ( strpos( $result, 'journalctl -xe' ) !== false ) {
			// Looks like there was a problem with restarting the webserver - So update completion meta and return message.
			update_post_meta( $id, 'wpcd_server_7g_upgrade', 1.6 );
			/* translators: %s is replaced with the text of the result of the operation. */
			return new \WP_Error( sprintf( __( 'There was a problem restarting the web server after the upgrade - here is the full output of the upgrade process: %s', 'wpcd' ), $result ) );
		}

		// If we're here, we know that the nginx server restarted ok so let's do standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrade_7g.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'wpcd_server_7g_upgrade', 1.6 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'The upgrade to 7G has been completed - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Run upgrade script for WPCLI.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_wpcli( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrade_wpcli.txt',
			array(
				'action'      => $action,
				'interactive' => 'no',
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Make sure we don't have a wp_error object being returned...
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( __( 'There was a problem upgrading WPCLI - please check the server logs for more information.', 'wpcd' ) );
		}

		// Standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrade_wpcli.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'wpcd_server_wpcli_upgrade', 2.7 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'The WPCLI upgrade has been completed - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Run install script for PHP INTL module
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function install_php_intl( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrade_install_php_intl.txt',
			array(
				'action'      => $action,
				'interactive' => 'no',
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Make sure we don't have a wp_error object being returned...
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( __( 'There was a problem installing the PHP INTL module - please check the server logs for more information.', 'wpcd' ) );
		}

		// evaluate results.
		if ( strpos( $result, 'journalctl -xe' ) !== false ) {
			// Looks like there was a problem with restarting the NGINX - So update completion meta and return message.
			update_post_meta( $id, 'wpcd_server_phpintl_upgrade', 1 );
			/* translators: %s is replaced with the text of the result of the operation. */
			return new \WP_Error( sprintf( __( 'There was a problem restarting the nginx server after the install - here is the full output of the upgrade process: %s', 'wpcd' ), $result ) );
		}

		// If we're here, we know that the nginx server restarted ok so let's do standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrade_install_php_intl.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'wpcd_server_phpintl_upgrade', 1 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'The PHP INTL module install has been completed - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Tag a server as being upgraded to V 290.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_290_meta_only( $id, $action ) {

		// update server field to tag server as being upgraded.
		update_post_meta( $id, 'wpcd_last_upgrade_done', 290 );

		// Let user know command is complete and force a page rfresh.
		$result = array(
			'msg'     => __( 'Upgrade completed - this page will now refresh', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Tag a server as being upgraded to V 460.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_460_meta_only( $id, $action ) {

		// update server field to tag server as being upgraded.
		update_post_meta( $id, 'wpcd_last_upgrade_done', 460 );

		// Let user know command is complete and force a page rfresh.
		$result = array(
			'msg'     => __( 'Upgrade completed to V 4.6.0 - this page will now refresh', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Tag a server as being upgraded to V 461.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_461_meta_only( $id, $action ) {

		// update server field to tag server as being upgraded.
		update_post_meta( $id, 'wpcd_last_upgrade_done', 461 );

		// Let user know command is complete and force a page rfresh.
		$result = array(
			'msg'     => __( 'Upgrade completed to V 4.6.1 - this page will now refresh', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Tag a server as being upgraded to V 462.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_462_meta_only( $id, $action ) {

		// update server field to tag server as being upgraded.
		update_post_meta( $id, 'wpcd_last_upgrade_done', 462 );

		// Let user know command is complete and force a page rfresh.
		$result = array(
			'msg'     => __( 'Upgrade completed to V 4.6.2 - this page will now refresh', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Remove the upgrade meta from the server.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function remove_upgrade_meta( $id, $action ) {

		// update server field to tag server as being upgraded.
		delete_post_meta( $id, 'wpcd_last_upgrade_done' );

		// Remove the global - this could affect all servers.
		delete_option( 'wpcd_last_upgrade_done' );

		// Let user know command is complete and force a page rfresh.
		$result = array(
			'msg'     => __( 'The upgrade meta has been removed - this page will now refresh', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Add a cron to the server to force all updates to run right away.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_linux_all( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		if ( ! empty( $_POST['params'] ) ) {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = array();
		}

		// get the callback url.
		$callback_name                  = 'server_status';  // Note: we are using the same callback name as the regular server_status callback since the script is returning a subset of that data after the updates are run.
		$args['callback_server_status'] = $this->get_command_url( $id, $callback_name, 'completed' );

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command( $instance, 'server_update.txt', array_merge( $args, array( 'action' => $action ) ) );

		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );  //PHPcs warning normally issued because of print_r

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'server_update.txt' );
		if ( ! $success ) {
			switch ( $action ) {
				case 'run_updates_cron':
					do_action( "wpcd_server_{$this->get_app_name()}_upgrade_linux_all_action_failed", $id, $action, $success );
					break;
				case 'run_security_updates_cron':
					do_action( "wpcd_server_{$this->get_app_name()}_upgrade_linux_security_action_failed", $id, $action, $success );
					break;
			}

			// send default error message.
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {
			/* Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS */
			switch ( $action ) {
				case 'run_updates_cron':
					do_action( "wpcd_server_{$this->get_app_name()}_upgrade_linux_all_action_successful", $id, $action, $success );
					break;
				case 'run_security_updates_cron':
					do_action( "wpcd_server_{$this->get_app_name()}_upgrade_linux_security_action_successful", $id, $action, $success );
					break;
			}

			$success = array(
				'msg'     => __( 'We have scheduled your LINUX server updates to run via CRON which should begin shortly.', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $success;

	}

	/**
	 * Add new bulk options in server list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function wpcd_add_new_bulk_actions_server( $bulk_array ) {

		if ( wpcd_is_admin() ) {
			$bulk_array['wpcd_apply_all_linux_updates']      = __( 'Apply All Linux Updates', 'wpcd' );
			$bulk_array['wpcd_apply_security_linux_updates'] = __( 'Apply Only Linux Security Updates', 'wpcd' );
			return $bulk_array;
		}

	}

	/**
	 * Handle bulk actions for server.
	 *
	 * @param string $redirect_url  redirect url.
	 * @param string $action        action.
	 * @param array  $post_ids      all post ids.
	 */
	public function wpcd_bulk_action_handler_server_app( $redirect_url, $action, $post_ids ) {
		// Let's remove query args first for redirect url.
		$redirect_url = remove_query_arg( array( 'wpcd_apply_all_linux_updates' ), $redirect_url );

		// Lets make sure we're an admin otherwise return an error.
		if ( ! wpcd_is_admin() ) {
			do_action( 'wpcd_log_error', 'Someone attempted to run a function that required admin privileges.', 'security', __FILE__, __LINE__ );

			// Show error message to user at the top of the admin list as a dismissible notice.
			wpcd_global_add_admin_notice( __( 'You attempted to run a function that requires admin privileges.', 'wpcd' ), 'error' );

			return $redirect_url;
		}

		// Apply all Linux updates.
		if ( 'wpcd_apply_all_linux_updates' === $action ) {

			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $server_id ) {
					$args['action_hook'] = 'pending_log_apply_all_linux_updates';
					WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'apply-all-linux-updates', $server_id, $args, 'ready', $server_id, __( 'Schedule Running All Linux Updates From Bulk Operation', 'wpcd' ) );
				}

				// Add message to be displayed in admin header.
				wpcd_global_add_admin_notice( __( 'Updates have been scheduled for the selected servers. You can view the progress in the PENDING TASKS screen.', 'wpcd' ), 'success' );

			}
		}

		// Apply only Linux security updates.
		if ( 'wpcd_apply_security_linux_updates' === $action ) {

			// @todo: need to ask for confirmation here first (or maybe before we get here).

			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $server_id ) {
					$args['action_hook'] = 'pending_log_apply_security_linux_updates';
					WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'apply-linux-security-updates', $server_id, $args, 'ready', $server_id, __( 'Schedule Running Linux SECURITY Updates only From Bulk Operation', 'wpcd' ) );
				}

				// Add message to be displayed in admin header.
				wpcd_global_add_admin_notice( __( 'Updates have been scheduled for the selected servers. You can view the progress in the PENDING TASKS screen.', 'wpcd' ), 'success' );

			}

			// @todo: show confirmation message in a dialog box or at the top of the admin screen as a dismissible notice.
		}

		return $redirect_url;
	}

	/**
	 * Apply all linux updates - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: pending_log_apply_all_linux_updates
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function pending_log_apply_all_linux_updates( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Apply all linux updates */
		do_action( 'wpcd_wordpress-upgrade_linux_all', $server_id, 'run_updates_cron' );

	}

	/**
	 * Apply linux security updates - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: pending_log_apply_linux_security_updates
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function pending_log_apply_linux_security_updates( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Apply just linux security updates - the  wpcd_wordpress-upgrade_linux_all action hook can take either a 'run_updates_cron' or 'run_security_updates_cron' parameter.  */
		do_action( 'wpcd_wordpress-upgrade_linux_all', $server_id, 'run_security_updates_cron' );

	}

	/**
	 * Handle scheduling of all linux updates successful
	 *
	 * Action Hook: wpcd_server_{$this->get_app_name()}_upgrade_linux_all_action_successful || wpcd_server_wordpress-app_upgrade_linux_all_action_successful
	 * Action Hook: wpcd_server_{$this->get_app_name()}_upgrade_linux_security_action_successful || wpcd_server_wordpress-app_upgrade_linux_security_action_successful
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 */
	public function handle_upgrade_linux_all_success( $server_id, $action, $success_msg_array ) {
		$this->handle_upgrade_linux_all_success_or_failure( $server_id, $action, $success_msg_array, 'success' );
	}

	/**
	 * Handle scheduling of all linux updates failed
	 *
	 * Action Hook: wpcd_server_{$this->get_app_name()_upgrade_linux_all_action_failed || wpcd_server_wordpress-app_upgrade_linux_all_action_failed
	 * Action Hook: wpcd_server_{$this->get_app_name()_upgrade_linux_security_action_failed || wpcd_server_wordpress-app_upgrade_linux_security_action_failed
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 */
	public function handle_upgrade_linux_all_failed( $server_id, $action, $success_msg_array ) {
		$this->handle_upgrade_linux_all_success_or_failure( $server_id, $action, $success_msg_array, 'failed' );
	}

	/**
	 * Handle scheduling of all linux updates successful or failed when being processed from pending logs / via action hooks.
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 * @param boolean $success              Was the scheduling of the linux updates a sucesss or failure.
	 */
	public function handle_upgrade_linux_all_success_or_failure( $server_id, $action, $success_msg_array, $success ) {

		$server_post = get_post( $server_id );

		// Bail if not a post object.
		if ( ! $server_post || is_wp_error( $server_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_server_type( $server_id ) ) {
			return;
		}

		// This only matters if we were running linux updates.  If not, then bail.
		if ( 'run_updates_cron' !== $action && 'run_security_updates_cron' !== $action ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_instance_details( $server_id );

		if ( 'wpcd_app_server' === get_post_type( $server_id ) ) {

				// Now check the pending tasks table for a record where the key=$server_id and type='apply-all-linux-updates' and state='in-process'
				// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
			switch ( $action ) {
				case 'run_updates_cron':
					$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'apply-all-linux-updates' );
					break;
				case 'run_security_updates_cron':
					$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'apply-linux-security-updates' );
					break;
			}

			if ( $posts ) {

				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				// And mark it as successful or failed.
				if ( 'failed' === $success ) {
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );
				} else {
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );
				}
			}
		}

	}

}

new WPCD_WORDPRESS_TABS_SERVER_UPGRADE();
