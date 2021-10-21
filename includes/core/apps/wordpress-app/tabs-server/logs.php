<?php
/**
 * Logs tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_LOGS
 */
class WPCD_WORDPRESS_TABS_SERVER_LOGS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['server-logs'] = array(
			'label' => __( 'Logs', 'wpcd' ),
			'icon'  => 'fad fa-th-list',
		);
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the SERVER LOGS tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {
		return $this->get_fields_for_tab( $fields, $id, 'server-logs' );

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
			case 'server-logs-download':
				$result = array();
				$result = $this->do_server_log_actions( $id, $action );
				// most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
				if ( ! in_array( $action, array(), true ) && ! is_wp_error( $result ) ) {
					$result['refresh'] = 'yes';
				}
				break;
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the SERVER LOGS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_server_logs_fields( $id );

	}

	/**
	 * Gets the fields to shown in the SERVER LOGS tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_server_logs_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		/* Array variable to hold our field definitions */
		$actions = array();

		// manage server logs heading.
		$desc = __( 'Download various log files for this site.', 'wpcd' );

		$actions['server-logs-header'] = array(
			'label'          => __( 'Download Logs', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		// List of logs for download.
		$actions['server-logs-log-name-select'] = array(
			'label'          => __( 'Select Log', 'wpcd' ),
			// 'id'   => 'wpcd_app_server_log_name',
			'type'           => 'select',
			// 'save_field' => false,
			'raw_attributes' => array(
				'options'        => $this->get_log_list(),
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'server_log_name',
			),
		);

		$actions['server-logs-download'] = array(
			// 'id'   => 'wpcd_app_action_server_log_download_button',
			// 'tab'  => 'server-logs',
			'label'          => '',
			'type'           => 'button',
			'raw_attributes' => array(
				'std'              => __( 'Download', 'wpcd' ),
				// the _action that will be called in ajax.
				// 'data-wpcd-action' => 'server-log-download'.
				// the id.
				// 'data-wpcd-id' => $id.
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_action_server-logs-log-name-select' ) ),
			),
		);

		$actions['server-logs-warning-header'] = array(
			'label'          => __( 'Warning', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Attempting to download very large log files can cause your server memory to be exhausted which will likely cause your server to kill this process or, worse, crash. Use this download tool only if you are sure your logs are of a reasonable size. Otherwise connect via sFTP or ssh to download logs.', 'wpcd' ),
			),

		);

		return $actions;

	}

	/**
	 * Performs the SERVER LOG action.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean  success/failure/other
	 */
	private function do_server_log_actions( $id, $action ) {

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Grab the arguments sent from the front-end JS */
		$args = wp_parse_args( sanitize_text_field( $_POST['params'] ) );

		/* Make sure the log name has not been tampered with. We will not be escaping the log file name since we can validate it against our own known good list. */
		if ( ! isset( $this->get_log_list()[ $args['server_log_name'] ] ) ) {
			return new \WP_Error( __( 'We were unable to validate the log file name - this might be a security concern!.', 'wpcd' ) );
		}

		// Do the download...
		$result = $this->ssh()->do_file_download( $instance, $args['server_log_name'] );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s. It is possible that the file does not exist or that it is empty. Error message: %2$s. Error code: %3$s', 'wpcd' ), $action, $result->get_error_message(), $result->get_error_code() ) );
		}

		// create log file and store it in temp folder.
		$log_file = wpcd_get_log_file_without_extension( $args['server_log_name'] ) . '_' . time() . '.txt';
		$temppath = trailingslashit( $this->get_script_temp_path() );

		/* Put the log file into the temp folder... */
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$filepath    = $temppath . $log_file;
		$file_result = $wp_filesystem->put_contents(
			$filepath,
			$result,
			false
		);

		/* Send the file name to the browser which will handle the download via JS */
		if ( $file_result ) {
			$file_url = trailingslashit( $this->get_script_temp_path_uri() ) . $log_file;
			$result   = array(
				'file_url'  => $file_url,
				'file_name' => $log_file,
				'file_data' => $result,
			);
		}

		return $result;

	}

	/**
	 * Return a key-value array of logs that we can retrieve for the server.
	 */
	public function get_log_list() {
		return array(
			'/var/log/nginx/access.log'       => __( 'NGINX Access Log', 'wpcd' ),
			'/var/log/nginx/error.log'        => __( 'NGINX Error Log', 'wpcd' ),
			'/var/log/mysql/error.log'        => __( 'MYSQL Error Log', 'wpcd' ),
			'/var/log/wp-backup.log'          => __( 'Site WP Backup Logs', 'wpcd' ),
			'/var/log/wp-full-backup.log'     => __( 'Full Server WP Backup Logs', 'wpcd' ),
			'/var/log/syslog'                 => __( 'SYSLog', 'wpcd' ),
			'/var/log/unattended-upgrades/unattended-upgrades.log' => __( 'Unattended Upgrades Summary Log', 'wpcd' ),
			'/var/log/unattended-upgrades/unattended-upgrades-dpkg.log' => __( 'Unattended Upgrades Detail Log', 'wpcd' ),
			'/var/log/dpkg.log'               => __( 'Recently installed or removed packages, AKA dpkg.log', 'wpcd' ),
			'/var/log/redis/redis-server.log' => __( 'REDIS Log', 'wpcd' ),
			'/var/log/php7.1-fpm.log'         => __( 'PHP 7.1 FPM Log', 'wpcd' ),
			'/var/log/php7.2-fpm.log'         => __( 'PHP 7.2 FPM Log', 'wpcd' ),
			'/var/log/php7.3-fpm.log'         => __( 'PHP 7.3 FPM Log', 'wpcd' ),
			'/var/log/php7.4-fpm.log'         => __( 'PHP 7.4 FPM Log', 'wpcd' ),
			'/var/log/php8.0-fpm.log'         => __( 'PHP 8.0 FPM Log', 'wpcd' ),
			'/var/log/auth.log'               => __( 'Authorization Log', 'wpcd' ),
			'/var/log/ufw.log'                => __( 'UFW Firewall Log', 'wpcd' ),
			'/var/log/wp-server-status.log'   => __( 'WPCD Server Status Callback Log', 'wpcd' ),
		);
	}

}

new WPCD_WORDPRESS_TABS_SERVER_LOGS();
