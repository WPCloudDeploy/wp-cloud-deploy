<?php
/**
 * Crons tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_CRONS
 */
class WPCD_WORDPRESS_TABS_CRONS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_CRONS constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// Allow the cron action to be triggered via an action hook.  Will primarily be used by the woocommerce add-on and REST API.
		add_action( 'wpcd_wordpress-app_do_manage_cron_status', array( $this, 'manage_cron_status_action' ), 10, 3 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'crons';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_crons_tab';
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
				'label' => __( 'Crons', 'wpcd' ),
				'icon'  => 'fad fa-clock',
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
	 * Gets the fields to be shown in the CRONS tab.
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
		$valid_actions = array( 'wp-linux-cron-status' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'wp-linux-cron-status':
					// enable/disable the wp linux cron process.
					$current_status = get_post_meta( $id, 'wpapp_wp_linux_cron_status', true );
					if ( empty( $current_status ) ) {
						$current_status = 'off';
					}
					$result = $this->toggle_wp_linux_cron_status( $id, $current_status === 'on' ? 'disable_system_cron' : 'enable_system_cron' );
					if ( ! is_wp_error( $result ) ) {
						update_post_meta( $id, 'wpapp_wp_linux_cron_status', $current_status === 'on' ? 'off' : 'on' );
						$result = array( 'refresh' => 'yes' );
					}
					break;
			}
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the CRONS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_wp_linux_cron_fields( $id );

	}

	/**
	 * Gets the fields for the wp linux cron options to be shown in the CRONS tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_wp_linux_cron_fields( $id ) {

		$actions = array();

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		/* What is the current wp_cron status of the site? */
		$status = get_post_meta( $id, 'wpapp_wp_linux_cron_status', true );
		if ( empty( $status ) ) {
			$status = 'off';
		}

		/* Set the text of the confirmation prompt based on the current basic authentication status of the site */
		$confirmation_prompt = '';
		if ( 'on' === $status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable the Linux cron for this site? This will re-enable the native WordPress Cron process.', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable a native Linux cron process for this site?', 'wpcd' );
		}

		/* Get the cron interval that is currently set */
		$current_cron_interval = get_post_meta( $id, 'wpapp_wp_linux_cron_interval', true );
		if ( empty( $current_cron_interval ) ) {
			$current_cron_interval = '15m';
		}

		$desc = __( 'This function enables a Linux cron process and disables the native WordPress cron process.  This can help to address several limitations in the native WordPress cron process and ensure that future background processes kick off in a more timely manner. ', 'wpcd' );
		$desc = sprintf( '<details>%s %s</details>', wpcd_get_html5_detail_element_summary_text(), $desc );

		// Start new card.
		$actions[] = wpcd_start_half_card( $this->get_tab_slug() );

		$actions['wp-linux-cron-header'] = array(
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => wpcd_apply_clock_icon( __( '%s Linux Cron', 'wpcd' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		// Only show the interval field if the status is off.
		if ( 'off' === $status ) {
			$actions['wp-linux-cron-interval'] = array(
				'label'          => __( 'Interval', 'wpcd' ),
				'type'           => 'select',
				'raw_attributes' => array(
					'tooltip'        => __( 'How often should we run background processes in WordPress?  Remember, the more often it runs, the larger the load on your website.  Do not configure it to run more often than needed.  Most users do not need less than a 15 min interval and most WordPress sites can operate with a one hour interval without any issues.', 'wpcd' ),
					'options'        => array(
						'1m'  => '1 Minute',
						'2m'  => '2 Minutes',
						'3m'  => '3 Minutes',
						'5m'  => '5 Minutes',
						'15m' => '15 Minutes',
						'1h'  => '1 Hour / 60 Minutes',
					),
					'std'            => $current_cron_interval,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'wp_linux_cron_interval',
				),
			);
		}

		switch ( $status ) {
			case 'on':
			case 'off':
				$actions['wp-linux-cron-status'] = array(
					'label'          => __( 'Linux Cron', 'wpcd' ),
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => $status === 'on',
						/* Translators: %s is the current cron interval. */
						'desc'                => 'off' === $status ? __( 'Click to enable Cron', 'wpcd' ) : sprintf( __( 'Click to disable the Linux Cron currently configured to fire every %s', 'wpcd' ), $current_cron_interval ),
						'confirmation_prompt' => $confirmation_prompt,
						// fields that contribute data for this action.
						'data-wpcd-fields'    => 'off' === $status ? json_encode( array( '#wpcd_app_action_wp-linux-cron-interval' ) ) : '',
					),
					'type'           => 'switch',
				);
				break;
		}

		// Close up prior card.
		$actions[] = wpcd_end_card( $this->get_tab_slug() );

		return $actions;

	}

	/**
	 * Turn on/off the WP linux cron triggered via an action hook.
	 *
	 * Action Hook: wpcd_wordpress-app_do_manage_cron_status
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'disable_system_cron' or 'enable_system_cron'  (this matches the string required in the bash scripts).
	 * @param string $interval Interval of cron if we're enabling it.
	 */
	public function manage_cron_status_action( $id, $action, $interval = '15m' ) {

		$args['wp_linux_cron_interval'] = $interval;

		$result = $this->toggle_wp_linux_cron_status( $id, $action, $args );
		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $id, 'wpapp_wp_linux_cron_status', $action === 'enable_system_cron' ? 'on' : 'off' );
			$result = array( 'refresh' => 'yes' );
		}

	}


	/**
	 * Turn on/off the WP linux cron.
	 *
	 * @param int    $id      The postID of the app cpt.
	 * @param string $action  The action to be performed 'enable' or 'disable'  (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function toggle_wp_linux_cron_status( $id, $action, $in_args = array() ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Special sanitization for the interval...
		$new_cron_interval = '1h';
		if ( isset( $args['wp_linux_cron_interval'] ) ) {
			$new_cron_interval              = sanitize_text_field( $args['wp_linux_cron_interval'] );  // Get the interval before we escape it for the linux command line - we'll need this if the action is successful.
			$args['wp_linux_cron_interval'] = escapeshellarg( $args['wp_linux_cron_interval'] );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'toggle_wp_linux_cron_misc.txt',
			array_merge(
				$args,
				array(
					'command' => "{$action}_site",
					'action'  => $action,
					'domain'  => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'toggle_wp_linux_cron_misc.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Set the new cron interval on the app record.
		update_post_meta( $id, 'wpapp_wp_linux_cron_interval', $new_cron_interval );

		return $success;
	}

}

new WPCD_WORDPRESS_TABS_CRONS();
