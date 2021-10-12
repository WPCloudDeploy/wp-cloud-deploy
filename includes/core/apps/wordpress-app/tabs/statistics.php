<?php
/**
 * Statistics tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_STATISTICS
 */
class WPCD_WORDPRESS_TABS_STATISTICS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['statistics'] = array(
			'label' => __( 'Statistics', 'wpcd' ),
			'icon'  => 'fad fa-chart-pie',
		);
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the STATISTICS tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {

		return $this->get_fields_for_tab( $fields, $id, 'statistics' );

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

		switch ( $action ) {
			case 'show-diskspace-used':
				// Show the diskspace used by this app.
				$result = $this->show_diskspace_used( $id, 'show_disk_usage' );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;

			case 'show-vnstat':
				// Show vnstat data.
				$result = $this->show_vnstat_data( $id, 'show_vnstat_data' );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;

		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the STATISTICS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_statistics_fields( $id );

	}

	/**
	 * Gets the fields for the wp linux cron options to be shown in the STATISTICS tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_statistics_fields( $id ) {

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Basic checks passed, ok to proceed.
		$actions = array();

		/* Disk Space Used */
		$actions['show-diskspace-used-header'] = array(
			'label'          => __( 'Disk Space', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'View the disk space used by this app', 'wpcd' ),
			),
		);

		/* Get any disk space data we might have stored already */
		$disk_used = get_post_meta( $id, 'wpapp_diskspace_used', true );
		if ( ! empty( $disk_used ) ) {
			$desc = sprintf( __( '<b>%s.</b>', 'wpcd' ), $disk_used );
		} else {
			$desc = __( 'Re-calculate the amount of disk space used by this app', 'wpcd' );
		}

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to calculate the diskspace used for this site?', 'wpcd' );

		$actions['show-diskspace-used'] = array(
			'label'          => __( 'Calculate used disk space', 'wpcd' ),
			'raw_attributes' => array(
				'std'                 => __( 'Re-calculate', 'wpcd' ),
				'desc'                => $desc,
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'button',
		);
		/* End Disk Space Used */

		/* VNSTAT */
		if ( wpcd_get_early_option( 'wordpress_app_show_vnstat_in_app' ) ) {
			$actions['show-vnstat-header'] = array(
				'label'          => __( 'VNSTAT', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'VNSTAT is a lightweight traffic statistics program in Linux that provides a formatted text output. View those statistics here. <br /> Note that these statistics are for the entire server and not just for this site!', 'wpcd' ),
				),
			);

			/* Get any VNstat data we might have stored */
			$vnstat = get_post_meta( $id, 'wpapp_vnstat_data', true );

			/* Set the text of the confirmation prompt */
			$confirmation_prompt_vnstat = __( 'Are you sure you would like to vnstat data for this site?', 'wpcd' );

			$actions['show-vnstat'] = array(
				'label'          => __( 'Get VNSTAT Data', 'wpcd' ),
				'raw_attributes' => array(
					'std'                 => __( 'VNSTAT', 'wpcd' ),
					'desc'                => __( 'Collect VNSTAT Data', 'wpcd' ),
					'confirmation_prompt' => $confirmation_prompt_vnstat,
				),
				'type'           => 'button',
			);

			if ( ! empty( $vnstat ) ) {
				$actions['vnstat-data'] = array(
					'label'          => __( 'VNStat Data', 'wpcd' ),
					'raw_attributes' => array(
						'std' => $vnstat,
					),
					'type'           => 'custom_html',
				);
			}
		}
		/* End VNSTAT */

		/* Point back to server for additional data */
		$actions['stats-addl-data-location'] = array(
			'label'          => __( 'Additional Statistics', 'wpcd' ),
			'raw_attributes' => array(
				'std' => __( 'You can view additional statistics in the STATISTICS tab on the server that is holding this site.' ),
			),
			'type'           => 'custom_html',
		);

		return $actions;

	}


	/**
	 * Calculate the disk space used.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function show_diskspace_used( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'get_diskspace_used_misc.txt',
			array(
				'command' => "{$action}_site",
				'action'  => $action,
				'domain'  => get_post_meta(
					$id,
					'wpapp_domain',
					true
				),
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Get the last 3 lines of the result which should contain the data...
		$result = wpcd_get_last_lines_from_string( trim( $result ), 3, '<br />' );

		// update the data...
		update_post_meta( $id, 'wpapp_diskspace_used', $result . '<br />Last calculated on ' . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( sprintf( __( '%s', 'wpcd' ), $result ) );

	}

	/**
	 * Refresh and show VNSTAT data.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if used ).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function show_vnstat_data( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo vnstat' ) );
		$result2 = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo vnstat --oneline' ) );

		// update the data...
		update_post_meta( $id, 'wpapp_vnstat_data', '<pre>' . $result . '<pre/>' );
		update_post_meta( $id, 'wpapp_vnstat_data_oneline', '<pre>' . $result2 . '<pre/>' );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'VNSTAT data has been collected. This page will now refresh.  Navigate back to this tab to see the new data!', 'wpcd' ) );

	}

}

new WPCD_WORDPRESS_TABS_STATISTICS();
