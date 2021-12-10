<?php
/**
 * Logs tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This tab is a bit different from others because only users
 * who can manage a server is allowed to operate this tab.
 * System users have the whole run of the server so not good
 * to have non-server users be able to operate here.
 */

/**
 * Class WPCD_WORDPRESS_TABS_SITE_LOGS
 */
class WPCD_WORDPRESS_TABS_SITE_LOGS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_SITE_SYSTEM_USERS constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'site-logs';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_logs_tab';
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
		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Logs', 'wpcd' ),
				'icon'  => 'fad fa-th-list',
			);
		}
		return $tabs;
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
		$valid_actions = array( 'site-log-download' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'site-log-download':
					$result = array();
					$result = $this->do_site_log_actions( $action, $id );
					// most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
					if ( ! in_array( $action, array(), true ) && ! is_wp_error( $result ) ) {
						$result['refresh'] = 'yes';
					}
					break;
			}
		}
		return $result;
	}

	/**
	 * Gets the fields to be shown in the LOGS tab.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 */
	public function get_fields( array $fields, $id ) {

		return array_merge(
			$fields,
			$this->get_site_logs_fields( $id )
		);

	}

	/**
	 * Gets the fields to be shown in the LOGS area of the tab.
	 *
	 * @param int $id id.
	 */
	public function get_site_logs_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field( 'site-logs' );
		}

		/* Array variable to hold our field definitions */
		$fields = array();

		// manage site user heading.
		$desc = __( 'Download various log files for this site.', 'wpcd' );

		$fields[] = array(
			'name' => __( 'Download Logs', 'wpcd' ),
			'desc' => $desc,
			'tab'  => 'site-logs',
			'type' => 'heading',
		);

		// Get the domain name for this app - we'll need it later.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// List of logs for download.
		$fields[] = array(
			'name'       => __( 'Select Log', 'wpcd' ),
			'id'         => 'wpcd_app_site_log_name',
			'tab'        => 'site-logs',
			'type'       => 'select',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'site_log_name',
			),
			'options'    => $this->get_log_list( $id ),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_site_log_download_button',
			'tab'        => 'site-logs',
			'type'       => 'button',
			'std'        => __( 'Download', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'site-log-download',
				// the id.
				'data-wpcd-id'     => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_site_log_name' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		$fields[] = array(
			'name' => __( 'Warning', 'wpcd' ),
			'desc' => __( 'Attempting to download very large log files can cause your server memory to be exhausted which will likely cause your server to kill this process or, worse, crash. Use this download tool only if you are sure your logs are of a reasonable size. Otherwise connect via sFTP or ssh to download logs.', 'wpcd' ),
			'tab'  => 'site-logs',
			'type' => 'heading',
		);

		return $fields;

	}

	/**
	 * Performs the SITE LOG action.
	 *
	 * @param array $action action.
	 * @param int   $id id.
	 */
	private function do_site_log_actions( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Grab the arguments sent from the front-end JS */
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Get the domain...
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		/* Make sure the log name has not been tampered with. We will not be escaping the log file name since we can validate it against our own known good list. */
		if ( ! isset( $this->get_log_list( $id )[ $args['site_log_name'] ] ) ) {
			return new \WP_Error( __( 'We were unable to validate the log file name - this might be a security concern!.', 'wpcd' ) );
		}

		// Make sure we actually have a domain name.
		if ( empty( $domain ) ) {
			return new \WP_Error( __( 'We were unable to get the domain needed for this action.', 'wpcd' ) );
		}

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Do the download...
		$result = $this->ssh()->do_file_download( $instance, $args['site_log_name'] );  // We have to send the unescaped file name.
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s. It is possible that the file does not exist or that it is empty. Error message: %2$s. Error code: %3$s', 'wpcd' ), $action, $result->get_error_message(), $result->get_error_code() ) );
		}

		// create log file and store it in temp folder.
		$log_file = wpcd_get_log_file_without_extension( $args['site_log_name'] ) . '_' . time() . '.txt';
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
	 * Return a key-value array of logs that we can retrieve for the site.
	 *
	 * @param int $id id.
	 */
	public function get_log_list( $id ) {
		$domain = get_post_meta( $id, 'wpapp_domain', true );
		return array(
			"/var/www/$domain/html/wp-content/debug.log" => __( 'debug.log', 'wpcd' ),
			'other'                                      => __( 'For Future Use', 'wpcd' ),
		);
	}

}

new WPCD_WORDPRESS_TABS_SITE_LOGS();
