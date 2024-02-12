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
 * Class WPCD_WORDPRESS_TABS_SERVER_SERVICES
 */
class WPCD_WORDPRESS_TABS_SERVER_SERVICES extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		/* Run after-install commands for memcached and redis */
		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed_memcached' ), 10, 2 );
		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed_redis' ), 10, 2 );

		/* Run after-install commands for activating ubuntu pro */
		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed_ubuntu_pro' ), 10, 2 );

		/* Pending Logs Background Task: Trigger refresh services. Hook: wpcd_wordpress-app_server_refresh_services. */
		add_action( "wpcd_{$this->get_app_name()}_server_refresh_services", array( $this, 'pending_log_refresh_services_status' ), 10, 3 );

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the server cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed_memcached( $id, $name ) {

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
		if ( 'install_memcached' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'install_memcached.txt' );

			if ( true === (bool) $success ) {

				// Update the meta on the server to indicate memcached is installed.
				update_post_meta( $id, 'wpcd_wpapp_memcached_installed', 'yes' );

				// Refresh services status metas for all services (except php).
				$action = 'services_status_update';
				$result = $this->refresh_services_status( $id, $action );
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
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the server cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed_redis( $id, $name ) {

		if ( get_post_type( $id ) !== 'wpcd_app_server' ) {
			return;
		}

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run
		// [1] => cf1110.wpvix.com
		// [2] => 911
		$command_array = explode( '---', $name );

		// if the command is to install redis we need to make sure that we stamp the server record with the status indicating that redis was installed.
		if ( 'install_redis' == $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'install_redis.txt' );

			if ( true == $success ) {

				// Update the meta on the server to indicate redis is installed.
				update_post_meta( $id, 'wpcd_wpapp_redis_installed', 'yes' );

				// Refresh services status metas for all services (except php).
				$action = 'services_status_update';
				$result = $this->refresh_services_status( $id, $action );

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
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the server cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed_ubuntu_pro( $id, $name ) {

		if ( get_post_type( $id ) !== 'wpcd_app_server' ) {
			return;
		}

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run
		// [1] => cf1110.wpvix.com
		// [2] => 911
		$command_array = explode( '---', $name );

		// if the command is to activate ubuntu pro we need to make sure that we stamp the server record with the status indicating that ubunut pro was activated.
		if ( 'ubuntu_pro_apply_token' == $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'ubuntu_pro_activate.txt' );

			if ( true === $success ) {

				// Update the meta on the server to indicate ubuntu pro was successfully activated.
				$this->set_ubuntu_pro_status( $id, true );

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
		return 'services';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_services_tab';
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
				'label' => __( 'Services', 'wpcd' ),
				'icon'  => 'far fa-concierge-bell',
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

		return $this->get_fields_for_tab( $fields, $id, 'services' );

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
				case 'services-status-update':
					$action = 'services_status_update';
					$result = $this->refresh_services_status( $id, $action );
					break;
				case 'web-server-restart':
					// $result = $this->submit_generic_server_command( $id, $action, 'sudo service nginx restart && echo "' . __( 'The Nginx Service has restarted', 'wpcd' ) . '"' );
					$result = $this->restart_webserver( $id, $action );
					break;
				case 'db-server-restart':
					// $result = $this->submit_generic_server_command( $id, $action, 'sudo service mariadb restart && echo "' . __( 'The MariaDB database Service has restarted', 'wpcd' ) . '"' );
					$result = $this->restart_database_server( $id, $action );
					break;
				case 'ufw-restart':
					// $result = $this->submit_generic_server_command( $id, $action, 'sudo service ufw restart && echo "' . __( 'The UFW firewall Service has restarted', 'wpcd' ) . '"' );
					$result = $this->restart_ufw( $id, $action );
					break;
				case 'ufw-state-toggle':
					$result = $this->do_ufw_toggle( $id, $action );
					break;
				case 'redis-do-install':
					$action = 'install_redis'; // script expects this action keyword.
					$result = $this->do_redis_install( $id, $action );
					break;
				case 'redis-restart':
					$action = 'redis_restart'; // script expects this action keyword.
					$result = $this->manage_redis( $id, $action );
					break;
				case 'redis-clear-cache':
					$action = 'redis_clear'; // script expects this action keyword.
					$result = $this->manage_redis( $id, $action );
					break;
				case 'redis-remove':
					$action = 'remove_redis'; // script expects this action keyword.
					$result = $this->manage_redis( $id, $action );
					break;
				case 'memcached-do-install':
					$action = 'install_memcached'; // script expects this action keyword.
					$result = $this->do_memcached_install( $id, $action );
					break;
				case 'memcached-restart':
					$action = 'memcached_restart'; // script expects this action keyword.
					$result = $this->manage_memcached( $id, $action );
					break;
				case 'memcached-clear_cache':
					$action = 'memcached_clear'; // script expects this action keyword.
					$result = $this->manage_memcached( $id, $action );
					break;
				case 'memcached-remove':
					$action = 'remove_memcached'; // script expects this action keyword.
					$result = $this->manage_memcached( $id, $action );
					break;
				case 'email-gateway-smtp-install':
					$action = 'setup_email';  // script expects this action keyword.
					$result = $this->email_gateway_setup( $id, $action );
					break;
				case 'email-gateway-load-defaults':
					$action = 'setup_email_load_defaults';  // script expects this action keyword.
					$result = $this->email_gateway_load_defaults( $id, $action );
					break;
				case 'email-gateway-smtp-test':
					$action = 'test_email';  // script expects this action keyword.
					$result = $this->email_gateway_test( $id, $action );
					break;
				case 'email-gateway-remove':
					$action = 'remove_email_gateway';  // script expects this action keyword.
					$result = $this->email_gateway_remove( $id, $action );
					break;
				case 'maldet-install':
					$action = 'antivirus_install';
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'maldet-restart':
					$action = 'antivirus_restart';
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'maldet-remove':
					$action = 'antivirus_remove';
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'maldet-update':
					$action = 'antivirus_update';
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'maldet-enable-cron':
					$action = 'antivirus_enable_cron';
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'maldet-disable-cron':
					$action = 'antivirus_disable_cron';
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'maldet-purge':
					$action = 'antivirus_purge';
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'maldet-clear-history':
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'maldet-clear-all-metas':
					$result = $this->manage_maldet( $id, $action );
					break;
				case 'services-status-update-php':
					$result = $this->refresh_services_status_php( $id, $action );
					break;
				case 'php-server-restart-php56':
				case 'php-server-restart-php70':
				case 'php-server-restart-php71':
				case 'php-server-restart-php72':
				case 'php-server-restart-php73':
				case 'php-server-restart-php74':
				case 'php-server-restart-php80':
				case 'php-server-restart-php81':
				case 'php-server-restart-php82':
					$result = $this->do_php_restart( $id, $action );
					break;
				case 'php-server-activate-php56':
				case 'php-server-activate-php70':
				case 'php-server-activate-php71':
				case 'php-server-activate-php72':
				case 'php-server-activate-php73':
				case 'php-server-activate-php74':
				case 'php-server-activate-php80':
				case 'php-server-activate-php81':
				case 'php-server-activate-php82':
					$result = $this->do_php_activation_toggle( $id, $action );
					break;
				case 'php-server-deactivate-php56':
				case 'php-server-deactivate-php70':
				case 'php-server-deactivate-php71':
				case 'php-server-deactivate-php72':
				case 'php-server-deactivate-php73':
				case 'php-server-deactivate-php74':
				case 'php-server-deactivate-php80':
				case 'php-server-deactivate-php81':
				case 'php-server-deactivate-php82':
					$result = $this->do_php_activation_toggle( $id, $action );
					break;
				case 'ubuntu-pro-toggle':
					// Is ubuntu pro enabled or disabled?
					$ubuntu_pro_status = $this->get_ubuntu_pro_status( $id );
					if ( false === $ubuntu_pro_status ) {
						$result = $this->activate_ubuntu_pro( $id, $action );
					} else {
						$result = $this->deactivate_ubuntu_pro( $id, $action );
					}
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the SERVICES tab.
	 *
	 * @param int $id id.
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_services_fields( $id );

	}

	/**
	 * Gets the fields for the services to be shown in the SERVICES tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_services_fields( $id ) {

		// Initialize some services status vars.
		$default_status   = __( 'Installed - Click REFRESH SERVICES to get running status.', 'wpcd' );
		$webserver_status = $default_status;
		$mariadb_status   = $default_status;
		$redis_status     = $default_status;
		$memcached_status = $default_status;
		$ufw_status       = $default_status;

		// retrieve service status from server meta.
		$services_status     = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_services_status', true ) );
		$last_services_check = get_post_meta( $id, 'wpcd_wpapp_services_status_date', true );
		if ( empty( $last_services_check ) ) {
			$last_services_check = __( 'The status of services has never been checked on this screen.', 'wpcd' );
		}

		if ( ! empty( $services_status ) ) {
			if ( isset( $services_status['webserver'] ) ) {
				$webserver_status = $services_status['webserver'];
			}
			if ( isset( $services_status['mariadb'] ) ) {
				$mariadb_status = $services_status['mariadb'];
			}
			if ( isset( $services_status['memcached'] ) ) {
				$memcached_status = $services_status['memcached'];
			}
			if ( isset( $services_status['ufw'] ) ) {
				$ufw_status = $services_status['ufw'];
			}
			if ( isset( $services_status['redis'] ) ) {
				$redis_status = $services_status['redis'];
			}
		}

		// Set up metabox items.
		$actions = array();

		/**
		 * Refresh Services
		 */

		// Start new card.
		$actions[] = wpcd_start_one_third_card( $this->get_tab_slug() );

		$actions['services-header'] = array(
			'label'          => '<i class="fa-duotone fa-bell-concierge"></i> ' . __( 'Services', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Control the Core Services that allow your application(s) to run.', 'wpcd' ),
			),
		);

		$actions['services-status-update'] = array(
			'label'          => '',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std' => sprintf( __( '%s Refresh Services Status', 'wpcd' ), '<i class="fa-solid fa-arrows-rotate"></i>' ),
			),
			'type'           => 'button',
		);

		$actions['services-check-date-label'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std' => $last_services_check,
			),
			'type'           => 'custom_html',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * WEBSERVER: NGINX/OLS
		 */

		// Start new card.
		$actions[] = wpcd_start_one_third_card( $this->get_tab_slug() );

		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );

		$desc = '';
		$desc = __( 'This is your web server.  If you restart it all sites will be temporarily disabled until the restart is complete.', 'wpcd' );
		$desc = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $desc );

		$actions['services-status-header-webserver'] = array(
			/* Translators: %s is the web server type eg, NGINX, OLS etc. */
			'label'          => '<i class="fa-duotone fa-lighthouse"></i> ' . sprintf( __( '%s Service Status', 'wpcd' ), $webserver_type_name ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['web-server-status'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std' => $webserver_status,
			),
			'type'           => 'custom_html',
		);

		$actions['web-server-restart'] = array(
			'label'          => __( 'Actions', 'wpcd' ),
			'raw_attributes' => array(
				'std' => $this->get_restart_button_label(),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * MARIA DB DATABASE SERVER
		 */

		// Start new card.
		$actions[] = wpcd_start_one_third_card( $this->get_tab_slug() );

		$desc = '';
		$desc = __( 'This is your database server.  If you restart it all sites will be temporarily disabled until the restart is complete.', 'wpcd' );
		$desc = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $desc );

		$actions['services-status-header-database'] = array(
			/* Translators: %s is the web server type eg, NGINX, OLS etc. */
			'label'          => '<i class="fa-duotone fa-lighthouse"></i> ' . sprintf( __( '%s Service Status', 'wpcd' ), 'MariaDB' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['db-server-status'] = array(
			'label'          => __( 'Status', 'wpcd' ),
			'raw_attributes' => array(
				'std' => $mariadb_status,
			),
			'type'           => 'custom_html',
		);

		$actions['db-server-restart'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std' => $this->get_restart_button_label(),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * REDIS OBJECT CACHE SERVER
		 */

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		$redis_desc  = __( 'Redis is high-performance OBJECT cache service that can help speed up duplicated database queries.  Once the service is installed here, you can activate it for each site that needs it.', 'wpcd' );
		$redis_desc .= '<br />';
		/* Translators: %s is an external link to more information about redis and memcached caches. */
		$redis_desc .= sprintf( __( 'Learn more about %s', 'wpcd' ), '<a href="https://scalegrid.io/blog/redis-vs-memcached-2021-comparison/">' . __( 'Redis and MemCached Object Caches', 'wpcd' ) . '</a>' );

		$redis_desc = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $redis_desc );

		$actions['redis-status-header'] = array(
			'label'          => '<i class="fa-duotone fa-objects-column"></i> ' . __( 'Redis', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $redis_desc,
			),
		);

		if ( false === $this->is_redis_installed( $id ) ) {
			// Redis not installed so show only install button.
			$actions['redis-do-install'] = array(
				'label'          => __( '', 'wpcd' ),
				'raw_attributes' => array(
					'std'                 => __( 'Install Redis', 'wpcd' ),
					'desc'                => __( 'It appears that Redis is not installed on this server.  Click the button to start the installation process.', 'wpcd' ),                   // make sure we give the user a confirmation prompt
					'confirmation_prompt' => __( 'Are you sure you would like to install the REDIS service?', 'wpcd' ),
					// show log console?
					'log_console'         => true,
					// Initial console message.
					'console_message'     => __( 'Preparing to install the REDIS service!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the installation has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the installation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'type'           => 'button',
			);
		} else {
			// Redis is installed so show status and options to disable and enable.
			$actions['redis-status'] = array(
				'label'          => __( 'Status', 'wpcd' ),
				'raw_attributes' => array(
					'std' => $redis_status,
				),
				'type'           => 'custom_html',
			);

			$actions['redis-restart'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'     => $this->get_restart_button_label(),
					'columns' => 6,
				),
				'type'           => 'button',
			);

			$actions['redis-clear-cache'] = array(
				'label'          => '',
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'std'     => sprintf( __( '%s Clear Cache', 'wpcd' ), '<i class="fa-solid fa-trash-xmark"></i>' ),
					'columns' => 6,
				),
				'type'           => 'button',
			);

			if ( wpcd_get_early_option( 'wordpress_app_show_notes_on_server_services_tab' ) ) {
				$actions['redis-desc'] = array(
					'label'          => __( 'Notes', 'wpcd' ),
					'raw_attributes' => array(
						'std'     => __( '', 'wpcd' ),
						'desc'    => __( 'Clearing the cache clears it for all sites on this server.', 'wpcd' ),
						'columns' => 5,
					),
					'type'           => 'custom_html',
				);
			}

			$actions['redis-divider-before-remove-option'] = array(
				'raw_attributes' => array(
					'std' => '<hr/>',
				),
				'type'           => 'custom_html',
			);

			$actions['redis-remove'] = array(
				'label'          => __( 'Remove Redis', 'wpcd' ),
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'std'               => sprintf( __( '%s Un-Install Redis', 'wpcd' ), '<i class="fa-solid fa-trash-can-slash"></i>' ),
					'label_description' => __( 'Please verify that none of your sites have Redis enabled before removing it from the server!', 'wpcd' ),
				),
				'type'           => 'button',
			);

			$actions['redis-divider-after-remove-option'] = array(
				'raw_attributes' => array(
					'std' => '<hr/>',
				),
				'type'           => 'custom_html',
			);

		}

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * UFW FIREWALL
		 */

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		// What is the state of the firewall?
		$ufw_toggle_state = $this->get_ufw_state( $id );

		$ufw_desc  = __( 'UFW is a Linux based firewall - also known as the UNCOMPLICATED FIREWALL. It is installed and turned on by default on your server, opening ports for HTTP, HTTPS and SSH. All other ports are closed by default.', 'wpcd' );
		$ufw_desc .= '<br />';
		/* translators: %s is a URL. */
		$ufw_desc .= sprintf( __( 'Technically, UFW is not a firewall - its just a nice front-end to the core Linux netfilter firewall.  But, for all intents and purposes it is the firewall because it is the thing that most people interact with. Learn more about %s', 'wpcd' ), '<a href="https://wiki.ubuntu.com/UncomplicatedFirewall">' . __( 'UFW', 'wpcd' ) . '</a>' );
		$ufw_desc .= '<br />';
		$ufw_desc  = __( 'You can manage ports on the FIREWALL tab.', 'wpcd' );
		$ufw_desc .= '<br />';
		$ufw_desc .= '<br />';
		$ufw_desc .= __( 'Note: Ports for HTTP,HTTPS and SSH are opened by default. Open additional ports on the FIREWALL tab.', 'wpcd' );
		$ufw_desc  = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $ufw_desc );

		$actions['ufw-status-header'] = array(
			'label'          => '<i class="fa-duotone fa-block-brick-fire"></i> ' . __( 'UFW Firewall', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $ufw_desc,
			),
		);

		$actions['ufw-state-toggle'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'on_label'  => __( 'Enabled', 'wpcd' ),
				'off_label' => __( 'Disabled', 'wpcd' ),
				'std'       => ( 'on' === $ufw_toggle_state ? true : false ),
			),
			'type'           => 'switch',
		);

		$actions['ufw-divider-after-toggle'] = array(
			'raw_attributes' => array(
				'std' => '<hr/>',
			),
			'type'           => 'custom_html',
		);

		$actions['ufw-status'] = array(
			'label'          => __( 'Status', 'wpcd' ),
			'raw_attributes' => array(
				'std' => $ufw_status,
			),
			'type'           => 'custom_html',
		);

		$actions['ufw-divider-before-restart-button'] = array(
			'raw_attributes' => array(
				'std' => '<hr/>',
			),
			'type'           => 'custom_html',
		);

		$actions['ufw-restart'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std' => $this->get_restart_button_label(),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		/**
		 * MALWRE / ANTIVIRUS
		 */
		$actions = array_merge( $actions, $this->get_maldet_fields( $id ) );

		/* Email Gateway */
		$eg_desc  = __( 'Most cloud servers restrict the user of their servers for sending emails.  Therefore to send general emails you can configure an email gateway to send emails using your own SMTP server.', 'wpcd' );
		$eg_desc .= '<br />';
		$eg_desc .= __( 'This is completely optional.  The biggest benefit of configuring this is that you do not have to install an email gateway plugin on each of your sites.', 'wpcd' );
		$eg_desc .= '<br />';
		$eg_desc .= __( 'Another benefit is that you can get password reset emails and notifications from newly installed sites on the server.', 'wpcd' );
		$eg_desc .= '<br />';
		$eg_desc .= __( 'The disadvantage is that all your emails will be sent from a single server and account for all sites which could affect deliverability if all your sites are not part of the same root domain.', 'wpcd' );

		// get any existing email gateway data stored.
		$gateway_data = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_email_gateway', true ) );
		if ( ! empty( $gateway_data ) ) {
			$smtp_server      = $gateway_data['smtp_server'];
			$smtp_user        = $gateway_data['smtp_user'];
			$smtp_pass        = self::decrypt( $gateway_data['smtp_pass'] );
			$smtp_domain      = $gateway_data['domain'];
			$smtp_hostname1   = $gateway_data['hostname1'];
			$smtp_usetls      = $gateway_data['usetls'];
			$smtp_usestarttls = $gateway_data['usestarttls'];
			$smtp_note        = $gateway_data['note'];

			/* Translators: %s is a fontawesome or similar icon. */
			$smtp_gateway_button_txt = sprintf( __( '%s Reinstall Email Gateway', 'wpcd' ), '<i class="fa-solid fa-rectangle-history-circle-plus"></i>' );

			$eg_desc .= '<br /><br />';
			$eg_desc .= __( 'The email gateway has already been installed. You can reinstall it with new parameters by clicking the reinstall button below.', 'wpcd' );

		} else {
			$smtp_server      = '';
			$smtp_user        = '';
			$smtp_pass        = '';
			$smtp_domain      = '';
			$smtp_hostname1   = '';
			$smtp_usetls      = 'YES';
			$smtp_usestarttls = 'YES';
			$smtp_note        = '';

			/* Translators: %s is a fontawesome or similar icon. */
			$smtp_gateway_button_txt = sprintf( __( '%s Install Email Gateway', 'wpcd' ), '<i class="fa-solid fa-rectangle-history-circle-plus"></i>' );
		}

		// email gateway form fields.
		$actions['email-gateway-header'] = array(
			'label'          => '<i class="fa-duotone fa-inbox-out"></i> ' . __( 'Email Gateway', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $eg_desc,
			),
		);

		$actions['email-gateway-smtp-server']   = array(
			'label'          => __( 'SMTP Server & Port', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $smtp_server,
				'desc'           => __( 'Enter the url/address for your outgoing email server - usually in the form of a subdomain.domain.com:port - eg: <i>smtp.ionos.com:587</i>.', 'wpcd' ),
				'xcolumns'       => 4,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'smtp_server',
			),
		);
		$actions['email-gateway-smtp-user']     = array(
			'label'          => __( 'User Name', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $smtp_user,
				'desc'           => __( 'Your user id for connecting to the smtp server', 'wpcd' ),
				'columns'        => 6,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'smtp_user',
				'spellcheck'     => 'false',
			),
		);
		$actions['email-gateway-smtp-password'] = array(
			'label'          => __( 'Password', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $smtp_pass,
				'desc'           => __( 'Your password for connecting to the smtp server', 'wpcd' ),
				'columns'        => 6,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'smtp_pass',
				'spellcheck'     => 'false',
			),
		);
		$actions['email-gateway-smtp-domain']   = array(
			'label'          => __( 'From Domain', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $smtp_domain,
				'desc'           => __( 'The default domain for sending messages', 'wpcd' ),
				'columns'        => 6,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'domain',
			),
		);
		$actions['email-gateway-smtp-hostname'] = array(
			'label'          => __( 'FQDN Hostname', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $smtp_hostname1,
				'desc'           => __( 'FQDN for the server.', 'wpcd' ),
				'tooltip'        => __( 'Some SMTP servers will require this to be a working domain name (example: server1.myblog.com)', 'wpcd' ),
				'columns'        => 6,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'hostname1',
			),
		);
		$actions['email-gateway-smtp-usetls']   = array(
			'label'          => __( 'Use TLS', 'wpcd' ),
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => array(
					'YES' => 'Yes',
					'NO'  => 'No',
				),
				'std'            => $smtp_usetls,
				'desc'           => __( 'Warning! Turning this off has security implications!', 'wpcd' ),
				'columns'        => 4,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'usetls',
			),
		);
		$actions['email-gateway-smtp-usestarttls'] = array(
			'label'          => __( 'Use STARTTLS', 'wpcd' ),
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => array(
					'YES' => 'Yes',
					'NO'  => 'No',

				),
				'std'            => $smtp_usestarttls,
				'desc'           => __( 'Warning! Turning this off has security implications!', 'wpcd' ),
				'columns'        => 4,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'usestarttls',
			),
		);
		$actions['email-gateway-smtp-note']    = array(
			'label'          => __( 'Brief Note', 'wpcd' ),
			'type'           => 'textarea',
			'raw_attributes' => array(
				'std'            => $smtp_note,
				'desc'           => __( 'Just a note in case you need a reminder about the details of this email gateway setup.', 'wpcd' ),
				'columns'        => 4,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'note',
			),
		);
		$actions['email-gateway-smtp-install'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => $smtp_gateway_button_txt,
				'columns'             => 3,
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to install or update the email gateway service?', 'wpcd' ),
				'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_email-gateway-smtp-server', '#wpcd_app_action_email-gateway-smtp-user', '#wpcd_app_action_email-gateway-smtp-password', '#wpcd_app_action_email-gateway-smtp-domain', '#wpcd_app_action_email-gateway-smtp-hostname', '#wpcd_app_action_email-gateway-smtp-usetls', '#wpcd_app_action_email-gateway-smtp-usestarttls', '#wpcd_app_action_email-gateway-smtp-note' ) ),
			),
			'type'           => 'button',
		);

		if ( wpcd_is_admin() ) {
			$actions['email-gateway-load-defaults'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Load Defaults', 'wpcd' ),
					'columns'             => 3,
					'confirmation_prompt' => __( 'Are you sure you would like to populate these fields with your global defaults from settings?', 'wpcd' ),
				),
				'type'           => 'button',
			);
		}

		/* Email gateway test */
		if ( ! empty( $gateway_data ) ) {
			$actions['email-gateway-test-email-header']    = array(
				'label'          => __( 'Email Gateway: Send Test Email', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => '',
				),
			);
			$actions['email-gateway-smtp-test-from-email'] = array(
				'label'          => __( 'Test From Email', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Send test email from this address when you click the test email button below.', 'wpcd' ),
					'size'           => 60,
					'columns'        => 6,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'from',
				),
			);
			$actions['email-gateway-smtp-test-to-email']   = array(
				'label'          => __( 'Test To Email', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Send test email to this address when you click the test email button below.', 'wpcd' ),
					'size'           => 60,
					'columns'        => 6,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'to',
				),
			);
			$actions['email-gateway-smtp-test']            = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'              => __( 'Send Test Email', 'wpcd' ),
					'columns'          => 12,
					'data-wpcd-fields' => wp_json_encode( array( '#wpcd_app_action_email-gateway-smtp-test-to-email', '#wpcd_app_action_email-gateway-smtp-test-from-email' ) ),
				),
				'type'           => 'button',
			);
		}
		/* Remove email gateway */
		if ( ! empty( $gateway_data ) ) {
			$actions['email-gateway-remove-header'] = array(
				'label'          => __( 'Email Gateway: Remove', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Check to see if the EMAIL GATEWAY is installed on the server and, if so, remove it.', 'wpcd' ),
				),
			);
			$actions['email-gateway-remove']        = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'     => __( 'Remove Email Gatweay', 'wpcd' ),
					'columns' => 12,
				),
				'type'           => 'button',
			);
		}

		/* Memcached */
		if ( $this->is_memcached_installed( $id ) ) {
			$mc_desc = __( 'A performance-oriented object cache.', 'wpcd' );
		} else {
			$mc_desc  = __( 'Memcached is an OBJECT cache service that can help speed up duplicated database queries.  Once the service is installed here, you can activate it for each site that needs it.', 'wpcd' );
			$mc_desc .= '<br />';
			/* translators: %s is a string "Memcached and Redis Object Caches" and is handled separately. */
			$mc_desc .= sprintf( __( 'Learn more about %s', 'wpcd' ), '<a href="https://scalegrid.io/blog/redis-vs-memcached-2021-comparison/">' . __( 'Memcached and Redis Object Caches', 'wpcd' ) . '</a>' );
		}

		$actions['memcached-status-header'] = array(
			'label'          => '<i class="fa-duotone fa-object-intersect"></i> ' . __( 'Memcached', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $mc_desc,
			),
		);

		if ( 'yes' !== get_post_meta( $id, 'wpcd_wpapp_memcached_installed', true ) ) {
			// Memcached not installed so show only install button.
			$actions['memcached-do-install'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Install MemCached', 'wpcd' ),
					'desc'                => __( 'It appears that MemCached is not installed on this server.  Click the button to start the installation process.', 'wpcd' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to install the MemCached service?', 'wpcd' ),
					// show log console?
					'log_console'         => true,
					// Initial console message.
					'console_message'     => __( 'Preparing to install the MemCached service!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the installation has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the installation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'type'           => 'button',
			);
		} else {
			// memcached is installed so show status and options to disable and enable.

			$actions['memcached-label'] = array(
				'label'          => __( 'Service', 'wpcd' ),
				'raw_attributes' => array(
					'std'     => __( 'MemCached', 'wpcd' ),
					'columns' => 3,
				),
				'type'           => 'custom_html',
			);

			$actions['memcached-status'] = array(
				'label'          => __( 'Status', 'wpcd' ),
				'raw_attributes' => array(
					'std'     => $memcached_status,
					'columns' => wpcd_get_early_option( 'wordpress_app_show_notes_on_server_services_tab' ) ? 2 : 5,
				),
				'type'           => 'custom_html',
			);

			$actions['memcached-restart'] = array(
				'label'          => __( 'Actions', 'wpcd' ),
				'raw_attributes' => array(
					'std'     => $this->get_restart_button_label(),
					'columns' => wpcd_get_early_option( 'wordpress_app_show_notes_on_server_services_tab' ) ? 1 : 2,
				),
				'type'           => 'button',
			);

			$actions['memcached-clear_cache'] = array(
				'label'          => __( 'Clear', 'wpcd' ),
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'std'     => sprintf( __( '%s Clear Cache', 'wpcd' ), '<i class="fa-solid fa-trash-xmark"></i>' ),
					'columns' => wpcd_get_early_option( 'wordpress_app_show_notes_on_server_services_tab' ) ? 1 : 2,
				),
				'type'           => 'button',
			);

			if ( wpcd_get_early_option( 'wordpress_app_show_notes_on_server_services_tab' ) ) {
				$actions['memcached-desc'] = array(
					'label'          => __( 'Notes', 'wpcd' ),
					'raw_attributes' => array(
						'std'     => '',
						'desc'    => __( 'Clearing the cache clears it for all sites on this server.', 'wpcd' ),
						'columns' => 5,
					),
					'type'           => 'custom_html',
				);
			};

			$actions['memcached-divider-before-remove-option'] = array(
				'raw_attributes' => array(
					'std' => '<hr/>',
				),
				'type'           => 'custom_html',
			);

			$actions['memcached-remove'] = array(
				'label'          => __( 'Remove MemCached', 'wpcd' ),
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'std'               => sprintf( __( '%s Un-Install Memcached', 'wpcd' ), '<i class="fa-solid fa-trash-can-slash"></i>' ),
					'label_description' => __( 'Please verify that none of your sites have MemCached enabled before removing it from the server!', 'wpcd' ),
				),
				'type'           => 'button',
			);

			$actions['memcached-divider-after-remove-option'] = array(
				'raw_attributes' => array(
					'std' => '<hr/>',
				),
				'type'           => 'custom_html',
			);

		}

		/* PHP Processes */
		if ( 'nginx' === $webserver_type ) {
			$actions = array_merge( $actions, $this->get_php_fields( $id ) );
		}

		/* Ubuntu PRO Fields */
		$actions = array_merge( $actions, $this->get_ubuntupro_fields( $id ) );

		return $actions;

	}

	/**
	 * Gets the fields for the UBUNTU PRO section to be shown in the SERVICES tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_ubuntupro_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// Is ubuntu pro enabled or disabled?
		$ubuntu_pro_status = $this->get_ubuntu_pro_status( $id );

		// Header description depending on state of ubuntu pro.
		if ( true === $ubuntu_pro_status ) {
			$desc = __( 'Ubuntu Pro is enabled on this server. Click the toggle to disable it.', 'wpcd' );
		} else {
			$desc = __( 'Ubuntu Pro is not enabled on this server. Enter your UBUNTU PRO token and click the toggle to enable it.', 'wpcd' );
		}

		$actions['services-status-ubunutu-pro'] = array(
			'label'          => '<i class="fa-duotone fa-umbrella"></i> ' . __( 'Ubuntu Pro', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		if ( true === $ubuntu_pro_status ) {
			// Show ubuntu pro toggle switch - it should be ON right now.
			$actions['ubuntu-pro-toggle'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'wpcd' ),
					'off_label'           => __( 'Disabled', 'wpcd' ),
					'std'                 => ( $ubuntu_pro_status ? true : false ),
					'confirmation_prompt' => $ubuntu_pro_status ? __( 'Are you sure you would like to disable Ubuntu Pro?', 'wpcd' ) : __( 'Are you sure you would like to enable Ubuntu Pro?', 'wpcd' ),
				),
				'type'           => 'switch',
			);

		} else {
			// Add in field to collect ubuntu pro token.
			$actions['services-status-ubuntu-pro-token'] = array(
				'label'          => __( 'Ubuntu Pro Token', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'tooltip'        => __( 'The Ubuntu Pro token to be applied to this server.', 'wpcd' ),
					'columns'        => 6,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'ubuntu_pro_token',
				),
			);

			// Show ubuntu pro toggle switch - it should be OFF right now.
			$actions['ubuntu-pro-toggle'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'wpcd' ),
					'off_label'           => __( 'Disabled', 'wpcd' ),
					'std'                 => ( $ubuntu_pro_status ? true : false ),
					// show log console?
					'log_console'         => true,
					// Initial console message.
					'console_message'     => __( 'Preparing to manage Ubuntu PRO on this server!<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the installation has been completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the installation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to enable Ubuntu Pro?', 'wpcd' ),
					'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_services-status-ubuntu-pro-token' ) ),
				),
				'type'           => 'switch',
			);

		}

		return $actions;

	}

	/**
	 * Gets the fields for the PHP SERVICES section to be shown in the SERVICES tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_php_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		$desc = __( 'PHP Services.', 'wpcd' );

		// Default status text.
		$default_status = __( 'Installed - Click REFRESH SERVICES to get running status.', 'wpcd' );
		$php56_status   = $default_status;
		$php70_status   = $default_status;
		$php71_status   = $default_status;
		$php72_status   = $default_status;
		$php73_status   = $default_status;
		$php74_status   = $default_status;
		$php80_status   = $default_status;
		$php81_status   = $default_status;
		$php82_status   = $default_status;

		// retrieve php service status from server meta.
		$services_status     = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_services_php_status', true ) );
		$last_services_check = get_post_meta( $id, 'wpcd_wpapp_services_php_status_date', true );
		if ( empty( $last_services_check ) ) {
			$last_services_check = __( 'The status of php services has never been checked on this screen.', 'wpcd' );
		}

		// Create an indexed array to hold the php services status.
		$php_services_status = array(
			'php74' => $default_status,
			'php80' => $default_status,
			'php81' => $default_status,
			'php82' => $default_status,
		);

		// Unset php80 and 81 elements as necessary.
		if ( ! $this->is_php_80_installed( $id ) ) {
			unset( $php_services_status['php80'] );
		}
		if ( ! $this->is_php_81_installed( $id ) ) {
			unset( $php_services_status['php81'] );
		}

		if ( ! $this->is_php_82_installed( $id ) ) {
			unset( $php_services_status['php82'] );
		}

		// Add in old php versions (5.6, 7.1, 7.2, 7.3) as necessary.
		if ( $this->is_old_php_version_installed( $id, '56' ) ) {
			$php_services_status['php56'] = $default_status;
		}

		if ( $this->is_old_php_version_installed( $id, '71' ) ) {
			$php_services_status['php71'] = $default_status;
		}

		if ( $this->is_old_php_version_installed( $id, '72' ) ) {
			$php_services_status['php72'] = $default_status;
		}

		if ( $this->is_old_php_version_installed( $id, '73' ) ) {
			$php_services_status['php73'] = $default_status;
		}

		// Loop through the $services_status array and update the $php_services_status array for any entries present in $services_status array.
		if ( ! empty( $services_status ) ) {
			foreach ( $php_services_status as $services_key => $service_status ) {
				if ( isset( $services_status[ $services_key ] ) ) {
					$php_services_status[ $services_key ] = $services_status[ $services_key ];
				}
			}
		}

		$actions['services-status-php'] = array(
			'label'          => '<i class="fa-duotone fa-arrow-progress"></i> ' . __( 'PHP Processes', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => '',
			),
		);

		$actions['services-status-update-php'] = array(
			'label'          => '',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std' => sprintf( __( '%s Refresh PHP Services', 'wpcd' ), '<i class="fa-solid fa-arrows-rotate"></i>' ),
			),
			'type'           => 'button',
		);

		$actions['services-check-date-label-php'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std' => $last_services_check,
			),
			'type'           => 'custom_html',
		);

		foreach ( $php_services_status as $services_key => $service_status ) {
			$actions[ "php-server-label-$services_key" ] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'     => $services_key,
					'columns' => 3,
				),
				'type'           => 'custom_html',
			);

			$actions[ "php-server-status-$services_key" ] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'     => $service_status,
					'columns' => 4,
				),
				'type'           => 'custom_html',
			);

			// Check to see if the version of PHP in this loop iteration is active.
			$is_php_version_active = $this->is_php_version_active( $id, $services_key );

			if ( true === $is_php_version_active ) {
				// Maybe allow this php version to be deactivated.
				$actions[ "php-server-restart-$services_key" ] = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'     => $this->get_restart_button_label(),
						'columns' => 3,
					),
					'type'           => 'button',
				);

				$default_php_server_version = 'php' . $this->get_default_php_version_no_period( $id );
				if ( $default_php_server_version === $services_key ) {
					// Do not allow php 7.4 or the the default version to be deactivated since it's the default for the server.
					$actions[ "php-server-deactivate-$services_key" ] = array(
						'label'          => '',
						'raw_attributes' => array(
							'std'     => __( 'n/a', 'wpcd' ),
							'columns' => 2,
						),
						'type'           => 'custom_html',
					);
				} else {
					// Allow this version of php to be deactivated.
					$actions[ "php-server-deactivate-$services_key" ] = array(
						'label'          => '',
						'raw_attributes' => array(
							'std'     => __( 'Deactivate', 'wpcd' ),
							'columns' => 2,
						),
						'type'           => 'button',
					);
				}
			} else {
				// Allow this php version to be activated.
				$actions[ "php-server-restart-$services_key" ]  = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'     => __( 'n/a', 'wpcd' ),
						'columns' => 3,
					),
					'type'           => 'custom_html',
				);
				$actions[ "php-server-activate-$services_key" ] = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'     => __( 'Activate', 'wpcd' ),
						'columns' => 2,
					),
					'type'           => 'button',
				);

			}
		}

		return $actions;

	}

	/**
	 * Gets the fields for the MALWARE/MALDET section to be shown in the SERVICES tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_maldet_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		$desc = __( 'Malware scanning using LMD and CLAMAV.', 'wpcd' );

		$desc .= '<br /><br />';
		$desc .= __( 'The scanner will run once per day and send an email of the scan results.  Note that most cloud providers will only send email if the email gateway software is installed as well - for security reasons many of them block emails from being sent directly from their servers.', 'wpcd' );

		$data = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_maldet_config', true ) );
		if ( ! empty( $data ) ) {

			$email_id = $data['email_id'];

			$desc .= '<br /><br />';
			$desc .= __( 'Malware scanning is already activated. Use the options below to manage it.', 'wpcd' );

		} else {

			$email_id = '';

			$button_txt = __( 'Install Malware Virus Scanner', 'wpcd' );

			$desc .= '<br /><br />';
			$desc .= '<b>' . __( 'Malware scanning is not activated for this server. ', 'wpcd' ) . '</b>';
			$desc .= __( 'If your server has 1 GB or more of memory it can likely run the scanner. Though, of course, the more memory the better. You can use the form below to get started.', 'wpcd' );

		}

		$desc = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $desc );

		// Start new card.
		if ( empty( $email_id ) ) {
			// Maldet not installed, need smaller card.
			$actions[] = wpcd_start_two_thirds_card( $this->get_tab_slug() );
		} else {
			// Maldet is installed, need larger card.
			$actions[] = wpcd_start_full_card( $this->get_tab_slug() );
		}

		$actions['maldet-header'] = array(
			'label'          => '<i class="fa-duotone fa-virus-slash"></i> ' . __( 'Malware & Virus Scanner', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		// If Malware scanning isn't already installed, just show that button.
		if ( empty( $email_id ) ) {

			$actions['maldet-install-to-id'] = array(
				'label'          => __( 'Notification Email', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Who should receive the results of scans?', 'wpcd' ),
					'size'           => 60,
					'columns'        => 6,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'emailid',
				),
			);

			$actions['maldet-install'] = array(
				'label'          => '',
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'std'                 => sprintf( __( '%s Install', 'wpcd' ), '<i class="fa-solid fa-grid-2-plus"></i>' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to install the Malware Scanner?', 'wpcd' ),
					'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_maldet-install-to-id' ) ),
				),
				'type'           => 'button',
			);

		} else {

			$actions['maldet_scan-callback-data-display'] = array(
				'type'           => 'custom_html',
				'label'          => '',
				'raw_attributes' => array(
					'std' => $this->get_formatted_maldet_scan_callback_data_for_display( $id ),
				),
			);

			$actions['maldet-restart'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Restart Services', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to restart the Malware Scanner services/daemons?', 'wpcd' ),
					'columns'             => 2,
				),
				'type'           => 'button',
			);

			$actions['maldet-remove'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Remove Scanner', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to remove the Malware Scanner?', 'wpcd' ),
					'columns'             => 2,
				),
				'type'           => 'button',
			);

			$actions['maldet-update'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Update Software', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to update the Malware Scanner software?', 'wpcd' ),
					'columns'             => 2,
				),
				'type'           => 'button',
			);

			$actions['maldet-disable-cron'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Disable Cron', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to DISABLE the Cron job?', 'wpcd' ),
					'columns'             => 2,
				),
				'type'           => 'button',
			);

			$actions['maldet-enable-cron'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Enable Cron', 'wpcd' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to ENABLE the Cron job?', 'wpcd' ),
					'columns'             => 2,
				),
				'type'           => 'button',
			);

			$actions['maldet-purge'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Purge', 'wpcd' ),
					/* make sure we give the user a confirmation prompt. */
					'confirmation_prompt' => __( 'Are you sure that you would like Maldet to purge the historical scan data from the server?', 'wpcd' ),
					'columns'             => 2,
					'tooltip'             => __( 'This option causes Maldet to clear logs, quarantine queue, session and temporary data on the server', 'wpcd' ),
				),
				'type'           => 'button',
			);

		}

		$actions['maldet-divider-before-misc'] = array(
			'raw_attributes' => array(
				'std' => '<hr/>',
			),
			'type'           => 'custom_html',
		);

		$actions['maldet-clear-history'] = array(
			'label'          => '',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std'                 => sprintf( __( '%s Clear History', 'wpcd' ), '<i class="fa-solid fa-trash-xmark"></i>' ),
				/* make sure we give the user a confirmation prompt. */
				'confirmation_prompt' => __( 'Delete malware scan history?', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'button',
		);

		$actions['maldet-clear-all-metas'] = array(
			'label'          => '',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std'                 => sprintf( __( '%s Clear All Metas', 'wpcd' ), '<i class="fa-solid fa-trash-xmark"></i>' ),
				/* make sure we give the user a confirmation prompt. */
				'confirmation_prompt' => __( 'Are you sure you would like to clear all Malware metas including history?', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		return $actions;

	}


	/**
	 * Install Redis via SSH
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used).
	 *
	 * @return boolean  success/failure/other
	 */
	private function do_redis_install( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		// Setup unique command name.
		$command               = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command']   = $command;
		$instance['app_id']    = $id;   // @todo - this is not really the app id - need to test to see if the process will work without this array element.
		$instance['server_id'] = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'install_redis.txt',
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
		 * Run the constructed commmand
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;
	}


	/**
	 * Restart redis or clear the cache
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used).
	 *
	 * @return boolean success/failure/other
	 */
	private function manage_redis( $id, $action ) {

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
			'manage_redis.txt',
			array(
				'action' => $action,
				'domain' => $domain,
			)
		);

		// log.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'debug', __FILE__, __LINE__, $instance, true );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_redis.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			if ( 'redis_clear' == $action ) {
				return new \WP_Error( __( 'The REDIS cache has been cleared!', 'wpcd' ) );
			} elseif ( 'redis_restart' == $action ) {
				return new \WP_Error( __( 'The REDIS server has been restarted!', 'wpcd' ) );
			} elseif ( 'remove_redis' == $action ) {
				// Remove the meta indicating redis is installed.
				delete_post_meta( $id, 'wpcd_wpapp_redis_installed' );
				return new \WP_Error( __( 'The REDIS server has been removed!', 'wpcd' ) );
			}
		}

		return $result;

	}

	/**
	 * Install Memcached
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean  success/failure/other
	 */
	private function do_memcached_install( $id, $action ) {

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
			'install_memcached.txt',
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
	 * Send a series of commands to the server to get the status of various components
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function refresh_services_status( $id, $action ) {

		// Array to hold status of services.
		$services_status = array();

		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );

		switch ( $webserver_type ) {
			case 'ols':
			case 'ols-enterprise':
				$webserver_service = 'lsws';
				break;

			case 'nginx':
			default:
				$webserver_service = 'nginx';
				break;

		}

		// get webserver status.
		$command    = 'sudo service ' . $webserver_service . ' status';
		$raw_status = $this->submit_generic_server_command( $id, $action, $command, true );
		if ( is_wp_error( $raw_status ) ) {
			$services_status['webserver'] = __( 'still unknown - last status request errored', 'wpcd' );
		} else {
			if ( ( strpos( $raw_status, 'Started A high performance web server' ) !== false ) || ( strpos( $raw_status, 'active (running) since' ) !== false ) ) {
				$services_status['webserver'] = 'running';
			} else {
				$services_status['webserver'] = 'errored';
			}
		}

		// get mariadb status.
		$command    = 'sudo service mariadb status';
		$raw_status = $this->submit_generic_server_command( $id, $action, $command, true );
		if ( is_wp_error( $raw_status ) ) {
			$services_status['mariadb'] = __( 'still unknown - last status request errored', 'wpcd' );
		} else {
			if ( strpos( $raw_status, 'Active: active (running)' ) !== false ) {
				$services_status['mariadb'] = 'running';
			} else {
				$services_status['mariadb'] = 'errored';
			}
		}

		// get ufw status.
		$command    = 'sudo service ufw status';
		$raw_status = $this->submit_generic_server_command( $id, $action, $command, true );
		if ( is_wp_error( $raw_status ) ) {
			$services_status['ufw'] = __( 'still unknown - last status request errored', 'wpcd' );
		} else {
			if ( strpos( $raw_status, 'Active: active' ) !== false ) {
				$services_status['ufw'] = 'running';
			} elseif ( strpos( $raw_status, 'Active: inactive' ) !== false ) {
				$services_status['ufw'] = 'off';
			} else {
				$services_status['ufw'] = 'errored';
			}
		}

		// Get redis status.
		$services_status = $this->set_redis_status_in_status_array( $id, $action, $services_status );

		// Get memcached status.
		$services_status = $this->set_memcached_status_in_status_array( $id, $action, $services_status );

		// Allow plugins to store their data in the array as well.
		$services_status = apply_filters( 'wpcd_wpapp_server_services_status', $services_status, $id, $action );

		// write the entire status array to the server cpt.
		update_post_meta( $id, 'wpcd_wpapp_services_status', $services_status );
		update_post_meta( $id, 'wpcd_wpapp_services_status_date', 'Last checked on ' . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );

		// Let user know command is complete and force a page refresh.
		$result = array(
			'msg'     => __( 'Request completed - this page will now refresh', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Set the REDIS status in the passed array.
	 *
	 * This is a helper function for refresh_services_status() above
	 * to help reduce the size of that function.
	 *
	 * @param int|string $id Post id of server record.
	 * @param string     $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 * @param array      $services_status array to be updated.
	 */
	public function set_redis_status_in_status_array( $id, $action, $services_status ) {

		// Get redis status.
		if ( $this->is_redis_installed( $id ) ) {
			$command    = 'sudo service redis status';
			$raw_status = $this->submit_generic_server_command( $id, $action, $command, true );
			if ( is_wp_error( $raw_status ) ) {
				$services_status['redis'] = __( 'still unknown - last status request errored', 'wpcd' );
			} else {
				if ( strpos( $raw_status, 'Active: active (running)' ) !== false ) {
					$services_status['redis'] = 'running';
				} else {
					$services_status['redis'] = 'errored';
				}
			}
		} else {
			$services_status['redis'] = __( 'REDIS does not appear to be installed as of the last time the services status was refreshed. Did you recently install it?', 'wpcd' );
		}

		return $services_status;

	}

	/**
	 * Set the MEMCACHED status in the passed array.
	 *
	 * This is a helper function for refresh_services_status() above
	 * to help reduce the size of that function.
	 *
	 * @param int|string $id Post id of server record.
	 * @param string     $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 * @param array      $services_status array to be updated.
	 */
	public function set_memcached_status_in_status_array( $id, $action, $services_status ) {

		// Get Memcached status.
		if ( $this->is_memcached_installed( $id ) ) {
			$command    = 'sudo service memcached status';
			$raw_status = $this->submit_generic_server_command( $id, $action, $command, true );
			if ( is_wp_error( $raw_status ) ) {
				$services_status['memcached'] = __( 'still unknown - last status request errored', 'wpcd' );
			} else {
				if ( strpos( $raw_status, 'Active: active (running)' ) !== false ) {
					$services_status['memcached'] = 'running';
				} else {
					$services_status['memcached'] = 'errored';
				}
			}
		} else {
			$services_status['memcached'] = __( 'MemCached does not appear to be installed as of the last time the services status was refreshed. Did you recently install it?', 'wpcd' );
		}

		return $services_status;

	}

	/**
	 * Send a series of commands to the server to get the status of various PHP components
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function refresh_services_status_php( $id, $action ) {

		// What is the webserver type?
		$webserver_type = $this->get_web_server_type( $id );
		if ( 'nginx' !== $webserver_type ) {
			// PHP FPM status can only be acquired for NGINX server types.
			$result = array(
				'msg'     => __( 'This request cannot be completed because the webserver is not an NGINX webserver - this page will now refresh', 'wpcd' ),
				'refresh' => 'yes',
			);
			return $result;
		}

		// Array to hold status of services.
		$services_status = array();

		// Array of PHP service commands.
		$php_services = array(
			'php74' => 'sudo service php7.4-fpm status',
			'php80' => 'sudo service php8.0-fpm status',
			'php81' => 'sudo service php8.1-fpm status',
			'php82' => 'sudo service php8.2-fpm status',
		);

		if ( $this->is_old_php_version_installed( $id, '56' ) ) {
			$php_services['php56'] = 'sudo service php5.6-fpm status';
		}

		if ( $this->is_old_php_version_installed( $id, '71' ) ) {
			$php_services['php71'] = 'sudo service php7.1-fpm status';
		}

		if ( $this->is_old_php_version_installed( $id, '72' ) ) {
			$php_services['php72'] = 'sudo service php7.2-fpm status';
		}

		if ( $this->is_old_php_version_installed( $id, '73' ) ) {
			$php_services['php73'] = 'sudo service php7.3-fpm status';
		}

		// Loop through the array and get the status of each php service.
		foreach ( $php_services as $service_key => $service_command ) {
			$command    = $service_command;
			$raw_status = $this->submit_generic_server_command( $id, $action, $command, true );
			if ( is_wp_error( $raw_status ) ) {
				$services_status[ $service_key ] = __( 'still unknown - last status request errored', 'wpcd' );
			} else {
				if ( ( strpos( $raw_status, 'Active: active' ) !== false ) || ( strpos( $raw_status, 'active (running)' ) !== false ) ) {
					$services_status[ $service_key ] = 'running';
				} elseif ( strpos( $raw_status, 'Active: inactive' ) !== false ) {
					$services_status[ $service_key ] = 'off';
				} else {
					$services_status[ $service_key ] = 'errored';
				}
			}
		}

		foreach ( $php_services as $service_key => $nothing ) {
			$service_state = $this->is_php_version_active( $id, $service_key );
			if ( false === $service_state ) {
				$services_status[ $service_key ] = $services_status[ $service_key ] . ' / ' . __( 'deactivated', 'wpcd' );
			}
		}

		// write the entire status array to the server cpt.
		update_post_meta( $id, 'wpcd_wpapp_services_php_status', $services_status );
		update_post_meta( $id, 'wpcd_wpapp_services_php_status_date', 'Last checked on ' . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );

		// Let user know command is complete and force a page refresh.
		$result = array(
			'msg'     => __( 'Request completed - this page will now refresh', 'wpcd' ),
			'refresh' => 'yes',
		);

		return $result;

	}

	/**
	 * Restart a PHP service
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function do_php_restart( $id, $action ) {

		// Array of PHP service commands.
		$php_services = array(
			'php-server-restart-php56' => 'sudo service php5.6-fpm restart',
			'php-server-restart-php71' => 'sudo service php7.1-fpm restart',
			'php-server-restart-php72' => 'sudo service php7.2-fpm restart',
			'php-server-restart-php73' => 'sudo service php7.3-fpm restart',
			'php-server-restart-php74' => 'sudo service php7.4-fpm restart',
			'php-server-restart-php80' => 'sudo service php8.0-fpm restart',
			'php-server-restart-php81' => 'sudo service php8.1-fpm restart',
			'php-server-restart-php82' => 'sudo service php8.2-fpm restart',
		);

		if ( isset( $php_services[ $action ] ) ) {
			$result = $this->submit_generic_server_command( $id, $action, $php_services[ $action ] . ' && echo "' . __( 'The requested PHP service has been restarted.', 'wpcd' ) . '" ', true );  // notice the last parm is true to force the function to return the raw results to us for evaluation instead of a wp-error object.
			if ( ( ! is_wp_error( $result ) ) && $result ) {
				$this->set_php_service_state( $id, $action, 'running' );
			}

			// Make sure we handle errors.
			if ( is_wp_error( $result ) ) {
				/* translators: %s is replaced with an error message. */
				return new \WP_Error( sprintf( __( 'Unable to execute this request because an error occurred: %s', 'wpcd' ), $result->get_error_message() ) );
			} else {
				// Construct an appropriate return message.
				// Right now '$result' is just a string.
				// Need to turn it into an array for consumption by the JS AJAX beast.
				$result = array(
					'msg'     => $result,
					'refresh' => 'yes',
				);
			}
		} else {
			$result = array(
				'msg'     => __( 'Unknown command...', 'wpcd' ),
				'refresh' => 'no',
			);
		}

		return $result;

	}

	/**
	 * Activate or deactivate a PHP service.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed.
	 *
	 * @return boolean success/failure/other
	 */
	private function do_php_activation_toggle( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Figure out the action for the bash scripts...
		switch ( $action ) {
			case 'php-server-activate-php56':
				$action      = 'php_version_enable';
				$php_version = 5.6;
				$php_key     = 'php56';
				break;
			case 'php-server-activate-php70':
				$action      = 'php_version_enable';
				$php_version = 7.0;
				$php_key     = 'php70';
				break;
			case 'php-server-activate-php71':
				$action      = 'php_version_enable';
				$php_version = 7.1;
				$php_key     = 'php71';
				break;
			case 'php-server-activate-php72':
				$action      = 'php_version_enable';
				$php_version = 7.2;
				$php_key     = 'php72';
				break;
			case 'php-server-activate-php73':
				$action      = 'php_version_enable';
				$php_version = 7.3;
				$php_key     = 'php73';
				break;
			case 'php-server-activate-php74':
				$action      = 'php_version_enable';
				$php_version = 7.4;
				$php_key     = 'php74';
				break;
			case 'php-server-activate-php80':
				$action      = 'php_version_enable';
				$php_version = 8.0;
				$php_key     = 'php80';
				break;
			case 'php-server-activate-php81':
				$action      = 'php_version_enable';
				$php_version = 8.1;
				$php_key     = 'php81';
				break;
			case 'php-server-activate-php82':
				$action      = 'php_version_enable';
				$php_version = 8.2;
				$php_key     = 'php82';
				break;

			case 'php-server-deactivate-php56':
				$action      = 'php_version_disable';
				$php_version = 5.6;
				$php_key     = 'php56';
				break;
			case 'php-server-deactivate-php70':
				$action      = 'php_version_disable';
				$php_version = 7.0;
				$php_key     = 'php70';
				break;
			case 'php-server-deactivate-php71':
				$action      = 'php_version_disable';
				$php_version = 7.1;
				$php_key     = 'php71';
				break;
			case 'php-server-deactivate-php72':
				$action      = 'php_version_disable';
				$php_version = 7.2;
				$php_key     = 'php72';
				break;
			case 'php-server-deactivate-php73':
				$action      = 'php_version_disable';
				$php_version = 7.3;
				$php_key     = 'php73';
				break;
			case 'php-server-deactivate-php74':
				$action      = 'php_version_disable';
				$php_version = 7.4;
				$php_key     = 'php74';
				break;
			case 'php-server-deactivate-php80':
				$action      = 'php_version_disable';
				$php_version = 8.0;
				$php_key     = 'php80';
				break;
			case 'php-server-deactivate-php81':
				$action      = 'php_version_disable';
				$php_version = 8.1;
				$php_key     = 'php81';
				break;
			case 'php-server-deactivate-php82':
				$action      = 'php_version_disable';
				$php_version = 8.2;
				$php_key     = 'php82';
				break;
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'toggle_php_active_misc.txt',
			array(
				'action'      => $action,
				'php_version' => (string) $php_version,
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'toggle_php_active_misc.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// Enable or disable based on action.
			$return_msg = __( 'Action failed', 'wpcd' );  // default message - we should never really be in a state where this is shown and retured to the end user because one of the actions below should overwrite it!
			switch ( $action ) {
				case 'php_version_enable':
					$this->set_php_activation_state( $id, $php_key, 'enabled' );
					$return_msg = __( 'PHP Service Enabled!', 'wpcd' );
					break;
				case 'php_version_disable':
					$this->set_php_activation_state( $id, $php_key, 'disabled' );
					$return_msg = __( 'PHP Service Disabled!', 'wpcd' );
					break;
			}

			// Force update of php services meta...
			$this->refresh_services_status_php( $id, 'services-status-update-php' );

			return new \WP_Error( $return_msg );
		}

		return $result;

	}

	/**
	 * Restart memcached or clear the cache
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function manage_memcached( $id, $action ) {

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
			'manage_memcached.txt',
			array(
				'command' => $command,
				'action'  => $action,
				'domain'  => $domain,
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_memcached.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			if ( 'memcached_clear' === $action ) {
				return new \WP_Error( __( 'The cache has been cleared!', 'wpcd' ) );
			} elseif ( 'memcached_restart' === $action ) {
				return new \WP_Error( __( 'The MemCached server has been restarted!', 'wpcd' ) );
			} elseif ( 'remove_memcached' === $action ) {
				// Remove the meta indicating memcached is installed.
				delete_post_meta( $id, 'wpcd_wpapp_memcached_installed' );
				return new \WP_Error( __( 'The MemCached server has been removed!', 'wpcd' ) );
			}
		}

		return $result;

	}

	/**
	 * Toggle the ufw firewall state
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function do_ufw_toggle( $id, $action ) {

		$ufw_toggle_state = $this->get_ufw_state( $id );

		if ( 'on' === $ufw_toggle_state ) {
			$success_msg = __( 'The UFW service should be stopped now.', 'wpcd' );
			$result      = $this->submit_generic_server_command( $id, $action, 'sudo service ufw stop && echo "' . $success_msg . '"', true );  // notice the last parm is true to force the function to return the raw results to us for evaluation instead of a wp-error object.
			if ( ( ! is_wp_error( $result ) ) && $result ) {
				$this->set_service_state( $id, 'ufw', 'running' );
			}
		} elseif ( 'off' === $ufw_toggle_state ) {
			$success_msg = __( 'The UFW service should be started now.', 'wpcd' );
			$result      = $this->submit_generic_server_command( $id, $action, 'sudo service ufw start && echo "' . $success_msg . '"', true );   // notice the last parm is true to force the function to return the raw results to us for evaluation instead of a wp-error object.
			if ( ( ! is_wp_error( $result ) ) && $result ) {
				$this->set_service_state( $id, 'ufw', 'off' );
			}
		}

		// Make sure we handle errors.
		if ( is_wp_error( $result ) ) {
			/* translators: %s is replaced with an error message. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because an error occurred: %s', 'wpcd' ), $result->get_error_message() ) );
		} else {
			// Force refresh of services so that the UFW meta can be updated (and its not a bad thing to get the other services status as well.)
			// We're just not going to examine the status being returned.  If it works, great.  If it doesn't, deal with it as a separate issue when the user clicks the refresh services button explicitly.
			$this->refresh_services_status( $id, 'services_status_update' );

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

	/**
	 * Figure out the state of the firewall
	 *
	 * @param int $id The ID of the server post that we're working with.
	 *
	 * @return string|boolean 'on', 'off' or 'false'
	 */
	public function get_ufw_state( $id ) {

		$ufw_toggle_state = false;

		$services_status = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_services_status', true ) );

		if ( isset( $services_status['ufw'] ) ) {
			$ufw_status = $services_status['ufw'];
			if ( 'off' === $ufw_status ) {
				$ufw_toggle_state = 'off';
			} elseif ( 'running' !== $ufw_status ) {
				$ufw_toggle_state = 'off';
			} else {
				$ufw_toggle_state = 'on';  // should never really get here.
			}
		} else {
			$ufw_toggle_state = 'on';
		}

		return $ufw_toggle_state;

	}

	/**
	 * Set whether ubuntu pro is enabled or disabled on this server.
	 *
	 * @param int     $id The ID of the server post that we're working with.
	 * @param boolean $status true/false.
	 *
	 * @return boolean.
	 */
	public function set_ubuntu_pro_status( $id, $status ) {

		return update_post_meta( $id, 'wpcd_wpapp_ubuntu_pro_status', $status );

	}

	/**
	 * Figure out whether ubuntu pro is enabled or disabled on this server.
	 *
	 * @param int $id The ID of the server post that we're working with.
	 *
	 * @return boolean.
	 */
	public function get_ubuntu_pro_status( $id ) {

		return (bool) get_post_meta( $id, 'wpcd_wpapp_ubuntu_pro_status', true );

	}

	/**
	 * Set the metvalue on the server record indicating the state of a service
	 *
	 * @param int    $id The ID of the server.
	 * @param string $service $service name of service.
	 * @param string $state 'off', 'running'.
	 *
	 * @return boolean always true.
	 */
	public function set_service_state( $id, $service, $state ) {

		$services_status             = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_services_status', true ) );
		$services_status[ $service ] = $state;
		update_post_meta( $id, 'wpcd_wpapp_services_status', $services_status );

		return true;

	}

	/**
	 * Set the metvalue on the server record indicating the state of a PHP service
	 *
	 * @param int    $id The ID of the server.
	 * @param string $service $service name of service.
	 * @param string $state 'off', 'running'.
	 *
	 * @return boolean always true.
	 */
	public function set_php_service_state( $id, $service, $state ) {

		// Array of PHP services mapped to the array key stored in a metavalue in the database.
		$php_services = array(
			'php-server-restart-php56' => 'php56',
			'php-server-restart-php71' => 'php71',
			'php-server-restart-php72' => 'php72',
			'php-server-restart-php73' => 'php73',
			'php-server-restart-php74' => 'php74',
			'php-server-restart-php80' => 'php80',
			'php-server-restart-php81' => 'php81',
			'php-server-restart-php82' => 'php82',
		);

		if ( isset( $php_services[ $service ] ) ) {

			// Get existing meta..
			$services_status = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_services_php_status', true ) );

			// If meta is empty or not an array, create an empty array instead.
			if ( empty( $services_status ) ) {
				$services_status = array();
			}
			if ( ! is_array( $services_status ) ) {
				$services_status = array();
			}

			// update array.
			$services_status[ $php_services[ $service ] ] = $state;

			// write it back to database.
			update_post_meta( $id, 'wpcd_wpapp_services_php_status', $services_status );

		}

		return true;

	}


	/**
	 * Install the email gateway
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function email_gateway_setup( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Check to make sure each field has a value.
		if ( empty( $args['smtp_server'] ) ) {
			return new \WP_Error( __( 'Unable to setup email gateway - no smtp server was provided.', 'wpcd' ) );
		}
		if ( empty( $args['smtp_user'] ) ) {
			return new \WP_Error( __( 'Unable to setup email gateway - no user name was provided.', 'wpcd' ) );
		}
		if ( empty( $args['smtp_pass'] ) ) {
			return new \WP_Error( __( 'Unable to setup email gateway - no password for the user was provided.', 'wpcd' ) );
		}
		if ( empty( $args['domain'] ) ) {
			return new \WP_Error( __( 'Unable to setup email gateway - no sending domain was provided.', 'wpcd' ) );
		}

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Put some items into the instance array.
		$domain                = '';  // No domain for server level actions - note that this is NOT the sending domain.
		$command               = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command']   = $command;
		$instance['app_id']    = $id;   // @todo - this is not really the app id - need to test to see if the process will work without this array element.
		$instance['server_id'] = $id;

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'email_gateway.txt',
			array_merge(
				$args,
				array(
					'command' => $instance['command'],
					'action'  => $action,
					'domain'  => $args['domain'],
				)
			)
		);

		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'email_gateway.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
		} else {
			$success = array(
				'msg'     => __( 'The email gateway has been installed. You should send a test email now.', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		// if we're here, we were successful so write the data out to a meta...
		$original_args['smtp_pass'] = self::encrypt( $original_args['smtp_pass'] );
		update_post_meta( $id, 'wpcd_wpapp_email_gateway', $original_args );

		return $success;

	}

	/**
	 * Load defaults the email gateway
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function email_gateway_load_defaults( $id, $action ) {

		// Check for admin user.
		if ( ! wpcd_is_admin() ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
		}

		$smtp_server      = wpcd_get_early_option( 'wpcd_email_gateway_smtp_server' );
		$smtp_user        = wpcd_get_early_option( 'wpcd_email_gateway_smtp_user' );
		$smtp_password    = wpcd_get_early_option( 'wpcd_email_gateway_smtp_password' );
		$smtp_domain      = wpcd_get_early_option( 'wpcd_email_gateway_smtp_domain' );
		$smtp_hostname    = wpcd_get_early_option( 'wpcd_email_gateway_smtp_hostname' );
		$smtp_usetls      = wpcd_get_early_option( 'wpcd_email_gateway_smtp_usetls' );
		$smtp_usestarttls = wpcd_get_early_option( 'wpcd_email_gateway_smtp_usestarttls' );
		$smtp_note        = wpcd_get_early_option( 'wpcd_email_gateway_smtp_note' );

		$args                = array();
		$args['smtp_server'] = (string) $smtp_server;
		$args['smtp_user']   = (string) $smtp_user;
		$args['smtp_pass']   = (string) $smtp_password;
		$args['domain']      = (string) $smtp_domain;
		$args['hostname1']   = (string) $smtp_hostname;
		$args['usetls']      = (string) $smtp_usetls;
		$args['usestarttls'] = (string) $smtp_usestarttls;
		$args['note']        = (string) $smtp_note;

		$success = array(
			'msg'          => __( 'Defaults have been loaded successfully.', 'wpcd' ),
			'tab_prefix'   => 'wpcd_app_action_email-gateway',  // Used by the JS code so it knows which tab we're on.  It needs to know because we are using the same code to load defaults for the MONIT as well.
			'email_fields' => $args,
			'refresh'      => 'no',
		);

		return $success;

	}

	/**
	 * Send a test email
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function email_gateway_test( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Check to make sure each field has a value.
		if ( empty( $args['to'] ) ) {
			return new \WP_Error( __( 'Unable to send a test email - no target email address was entered.', 'wpcd' ) );
		}
		if ( empty( $args['from'] ) ) {
			return new \WP_Error( __( 'Unable to send a test email - no origin email address was entered.', 'wpcd' ) );
		}

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);
		$domain        = 'nodomain';

		// Put some items into the instance array.
		$command               = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command']   = $command;
		$instance['app_id']    = $id;   // @todo - this is not really the app id - need to test to see if the process will work without this array element.
		$instance['server_id'] = $id;

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'email_gateway.txt',
			array_merge(
				$args,
				array(
					'command' => $instance['command'],
					'action'  => $action,
					'domain'  => 'nodomain',
				)
			)
		);

		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'email_gateway.txt' );
		if ( ! $success ) {
			return new \WP_Error( __( 'Unfortunately we were unable to successfully send the test email message. You can check the SSH logs for more information as to the reason for the failure.', 'wpcd' ) );
		} else {
			$success = array(
				'msg'     => __( 'Test email has been sent.', 'wpcd' ),
				'refresh' => 'yes',
			);
		}

		return $success;

	}

	/**
	 * Remove the EMAIL gateway
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function email_gateway_remove( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Now lets make sure we escape all the arguments so it's safe for the command line, though there should be NO args.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);
		$domain        = 'nodomain';

		// Put some items into the instance array.
		$command               = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command']   = $command;
		$instance['app_id']    = $id;   // @todo - this is not really the app id - need to test to see if the process will work without this array element.
		$instance['server_id'] = $id;

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'email_gateway.txt',
			array_merge(
				$args,
				array(
					'command' => $instance['command'],
					'action'  => $action,
					'domain'  => 'nodomain',
				)
			)
		);

		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'email_gateway.txt' );
		if ( ! $success ) {
			return new \WP_Error( __( 'Unfortunately we were unable to remove the email gateway. You can check the SSH logs for more information as to the reason for the failure.', 'wpcd' ) );
		} else {
			$success = array(
				'msg'     => __( 'The email gatewway has been removed.', 'wpcd' ),
				'refresh' => 'yes',
			);
			delete_post_meta( $id, 'wpcd_wpapp_email_gateway' );
		}

		return $success;

	}

	/**
	 * Install / manage Maldet
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_maldet( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'antivirus_install':
				// Make sure required fields have been provided and return error if not.
				if ( empty( $args['emailid'] ) ) {
					return new \WP_Error( __( 'Unable to complete setup - no notification email address was provided.', 'wpcd' ) );
				} else {
					// Data has been provided so sanitize it.
					$args['emailid'] = wp_kses( $args['emailid'], array() );
				}

				// get the callback url.
				$callback_name                = 'maldet_scan';
				$args['callback_server_scan'] = $this->get_command_url( $id, $callback_name, 'completed' );

				break;

			case 'maldet-clear-history':
				delete_post_meta( $id, 'wpcd_maldet_scan_push_history' );
				$success = array(
					'msg'     => __( 'History has been cleared.', 'wpcd' ),
					'refresh' => 'yes',
				);
				return $success;
				break;

			case 'maldet-clear-all-metas':
				delete_post_meta( $id, 'wpcd_maldet_scan_push_history' );
				delete_post_meta( $id, 'wpcd_maldet_scan_push' );
				delete_post_meta( $id, 'wpcd_maldet_installed' );
				delete_post_meta( $id, 'wpcd_maldet_config' );
				$success = array(
					'msg'     => __( 'All metas have been cleared.', 'wpcd' ),
					'refresh' => 'yes',
				);
				return $success;
				break;

			default:
				// nothing.
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
		$run_cmd = $this->turn_script_into_command( $instance, 'maldet.txt', array_merge( $args, array( 'action' => $action ) ) );

		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'maldet.txt' );
		if ( ! $success ) {

			// Different handling for different actions.
			switch ( $action ) {
				case 'antivirus_install':
					if ( strpos( $result, '1 G+ RAM' ) !== false ) {
						// custom error message for 1 GB requirement.
						return new \WP_Error( __( 'Unable to install Malware & Antivirus scanning software - 1 minimum of GB of RAM is needed on the server. Unfortunately, this server seems to have less than that.', 'wpcd' ) );
					}
					// no special errors detected for this action so just send the default error.
					/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
					return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
					break;
				default:
					// send default error message.
					/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
					return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'wpcd' ), $action, $result ) );
					break;
			}
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {
				case 'antivirus_install':
					$save_data             = array();
					$save_data['email_id'] = $original_args['emailid'];
					update_post_meta( $id, 'wpcd_maldet_installed', 'yes' );
					update_post_meta( $id, 'wpcd_maldet_config', $save_data );
					$success = array(
						'msg'     => __( 'Malware & Antivirus scanning has been installed. This screen will refresh and you can navigate back to this tab to see new options.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'antivirus_restart':
					$success = array(
						'msg'     => __( 'Malware & Antivirus software daemons have been restarted.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'antivirus_remove':
					update_post_meta( $id, 'wpcd_maldet_installed', 'no' );
					delete_post_meta( $id, 'wpcd_maldet_config' );
					$success = array(
						'msg'     => __( 'Malware & Antivirus scanning software have been removed from the server.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'antivirus_update':
					$success = array(
						'msg'     => __( 'Malware & Antivirus software has been updated to the latest versions.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'antivirus_disable_cron':
					$success = array(
						'msg'     => __( 'Cron scheduler has been disabled - scanning will no longer be run automatically.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'antivirus_enable_cron':
					$success = array(
						'msg'     => __( 'Cron scheduler has been enabled - scanning process will be executed once per day.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;

				case 'antivirus_purge':
					$success = array(
						'msg'     => __( 'Antivirus data has been purged from the server.', 'wpcd' ),
						'refresh' => 'yes',
					);
					break;
			}
		}

		return $success;

	}


	/**
	 * Activate Ubuntu Pro.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used).
	 *
	 * @return boolean  success/failure/other
	 */
	private function activate_ubuntu_pro( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get args.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Make sure required fields have been provided and return error if not.
		if ( empty( $args['ubuntu_pro_token'] ) ) {
			return new \WP_Error( __( 'Unable to complete setup - no Ubuntu Pro Token was provided.', 'wpcd' ) );
		} else {
			// Data has been provided so sanitize it.
			$args['ubuntu_pro_token']          = wp_kses( $args['ubuntu_pro_token'], array() );
			$args['ubuntu_pro_token_original'] = $args['ubuntu_pro_token'];
			$args['ubuntu_pro_token']          = escapeshellarg( $args['ubuntu_pro_token'] );
		}

		// Setup unique command name.
		$action                = 'ubuntu_pro_apply_token';
		$domain                = 'no-domain';
		$command               = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command']   = $command;
		$instance['app_id']    = $id;   // @todo - this is not really the app id - need to test to see if the process will work without this array element.
		$instance['server_id'] = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'ubuntu_pro_activate.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action ) );
		}

		/**
		 * Run the constructed commmand
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;
	}

	/**
	 * Deactivate Ubuntu Pro.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed.
	 *
	 * @return boolean success/failure/other
	 */
	private function deactivate_ubuntu_pro( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$action  = 'ubuntu_pro_remove_token';
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'ubuntu_pro_actions.txt',
			array(
				'action'      => $action,
				'php_version' => (string) $php_version,
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'ubuntu_pro_actions.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// Tag the server record with the new ubuntu pro status.
			$this->set_ubuntu_pro_status( $id, false );
			$return_msg = __( 'Ubuntu Pro has been deactivated and detached from its account.', 'wpcd' );
			return new \WP_Error( $return_msg );
		}

		return $result;

	}

	/**
	 * Take the most current data about malware scanning and format it for a nice display
	 *
	 * @param int $id id.
	 */
	public function get_formatted_maldet_scan_callback_data_for_display( $id ) {

		// setup return variable.
		$return = '';

		// get data from server record.
		$maldet_scan_items = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_maldet_scan_push', true ) );

		// if no data return nothing.
		if ( empty( $maldet_scan_items ) ) {
			$return = '<div class="wpcd_no_data wpcd_maldet_scan_push_no_data">' . __( 'No data has been received yet.', 'wpcd' ) . '</div>';
			return $return;
		}

		// ok, we've got data - format it out into variables.
		if ( isset( $maldet_scan_items['reporting_time'] ) ) {
			$reporting_time = $maldet_scan_items['reporting_time'];
		} else {
			$reporting_time = 0;
		}

		if ( isset( $maldet_scan_items['total_files'] ) ) {
			$total_files = $maldet_scan_items['total_files'];
		} else {
			$total_files = __( 'unknown', 'wpcd' );
		}

		if ( isset( $maldet_scan_items['total_hits'] ) ) {
			$total_hits = $maldet_scan_items['total_hits'];
		} else {
			$total_hits = 0;
		}

		if ( isset( $maldet_scan_items['total_cleaned'] ) ) {
			$total_cleaned = $maldet_scan_items['total_cleaned'];
		} else {
			$total_cleaned = 0;
		}

		// Format the data.
		$return = '<div class="wpcd_push_data wpcd_maldet_scan_push_data">';
			/* translators: %s is a date string. */
			$return .= '<p class="wpcd_push_data_reporting_time">' . sprintf( __( 'Data current as of: %s ', 'wpcd' ), date( 'Y-m-d H:i:s', (int) $reporting_time ) ) . '</p>';
			$return .= '<div class="wpcd_push_data_inner_wrap wpcd_maldet_scan_push_data_inner_wrap">';

				/* Total files scanned */
				$return     .= '<div class="wpcd_push_data_label_item wpcd_maldet_scan_push_data_label_item">';
					$return .= __( 'Total Files Scanned:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_maldet_scan_push_data_value_item">';
					$return .= esc_html( $total_files );
				$return     .= '</div>';

				/* Total Hits */
				$return     .= '<div class="wpcd_push_data_label_item wpcd_maldet_scan_push_data_label_item">';
					$return .= __( 'Total Malware Items Found:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_maldet_scan_push_data_value_item">';
					$return .= esc_html( $total_hits );
				$return     .= '</div>';

				/* Total files cleaned */
				$return     .= '<div class="wpcd_push_data_label_item wpcd_maldet_scan_push_data_label_item">';
					$return .= __( 'Total Malware Items Cleaned:', 'wpcd' );
				$return     .= '</div>';

				$return     .= '<div class="wpcd_push_data_value_item wpcd_maldet_scan_push_data_value_item">';
					$return .= esc_html( $total_cleaned );
				$return     .= '</div>';

			$return .= '</div>';
		$return     .= '</div>';

		return $return;
	}

	/**
	 * Restart the appropriate webserver
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function restart_webserver( $id, $action ) {
		$result = __( 'Action Failed.', 'wpcd' );

		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );

		switch ( $webserver_type ) {
			case 'ols':
			case 'ols-enterprise':
				$webserver_service = 'lsws';
				break;

			case 'nginx':
			default:
				$webserver_service = 'nginx';
				break;

		}

		/* Translators: %s is the full name of the webserver eg: OpenLiteSpeed. */
		$success_message = sprintf( __( 'The %s webserver service has restarted.', 'wpcd' ), $webserver_type_name );

		switch ( $webserver_type ) {
			case 'ols':
			case 'ols-enterprise':
				$result = $this->submit_generic_server_command( $id, $action, 'sudo killall -9 lsphp' ); // Kill all litespeed php services.
				$result = $this->submit_generic_server_command( $id, $action, 'systemctl stop lsws' ); // Stop the litespeed service.
				$result = $this->submit_generic_server_command( $id, $action, "sudo systemctl start lsws && echo {$success_message}" );
				break;

			case 'nginx':
				$result = $this->submit_generic_server_command( $id, $action, "sudo service {$webserver_service} restart && echo {$success_message}" );
				break;
		}

		// Refresh services.
		do_action( 'wpcd_wordpress-app_server_refresh_services', '', $id, array() );

		return $result;

	}

	/**
	 * Restart the mariadb server.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function restart_database_server( $id, $action ) {

		$result = $this->submit_generic_server_command( $id, $action, 'sudo service mariadb restart && echo "' . __( 'The MariaDB database Service has restarted', 'wpcd' ) . '"' );

		// Refresh services.
		do_action( 'wpcd_wordpress-app_server_refresh_services', '', $id, array() );

		return $result;

	}

	/**
	 * Restart the uncomplicated firewall.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function restart_ufw( $id, $action ) {

		$result = $this->submit_generic_server_command( $id, $action, 'sudo service ufw restart && echo "' . __( 'The UFW firewall Service has restarted', 'wpcd' ) . '"' );

		// Refresh services.
		do_action( 'wpcd_wordpress-app_server_refresh_services', '', $id, array() );

		return $result;

	}

	/**
	 * Refresh Services - triggered via pending logs background process.
	 *
	 * Can also be triggered from other places directly by passing in blanks for the
	 * $task_id and $args parameters.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: "wpcd_{$this->get_app_name()}_server_refresh_services | wpcd_wordpress-app_server_refresh_services.
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function pending_log_refresh_services_status( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		if ( $task_id ) {
			$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );
		}

		/* Refresh Services */
		$action = 'services_status_update';
		$result = $this->refresh_services_status( $server_id, $action );
		$result = $this->refresh_services_status_php( $server_id, $action );

		/* Mark pending log record as complete - there's no real return status so can do it here right away. */
		if ( $task_id ) {
			WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );
		}

	}

	/**
	 * Returns a label that will show up on any button that is used to restart a service.
	 * It includes a fontawesome icon.
	 */
	public function get_restart_button_label() {

		/* Translators: %s is a fontawesome or similar icon. */
		return sprintf( __( '%s Restart', 'wpcd' ), '<i class="fa-sharp fa-solid fa-clock-rotate-left"></i> ' );

	}

}

new WPCD_WORDPRESS_TABS_SERVER_SERVICES();
