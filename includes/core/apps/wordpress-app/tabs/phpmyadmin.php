<?php
/**
 * PHPMYADMIN Database tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_PHPMYADMIN.
 */
class WPCD_WORDPRESS_TABS_PHPMYADMIN extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
		/* add_filter( 'wpcd_is_ssh_successful', array( $this, 'was_ssh_successful' ), 10, 5 ); */

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

		if ( get_post_type( $id ) !== 'wpcd_app' ) {
			return;
		}

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905.
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run.
		// [1] => cf1110.wpvix.com.
		// [2] => 911.
		$command_array = explode( '---', $name );

		// if the command is to install phpmyadmin then we need to update some postmeta items in the app with the database user id, password and phpmyadmin status.
		if ( 'install_phpmyadmin' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'manage_phpmyadmin.txt' );

			if ( true == $success ) {

				// We need to parse the log files to find.
				// 1. user name.
				// 2. password.
				// 3. url string.

				// split the logs into an array.
				$logs_array = explode( "\n", $logs );

				// 1. user name.
				$searchword   = 'User:';
				$matches_user = array_filter(
					$logs_array,
					function( $var ) use ( $searchword ) {
						return strpos( $var, $searchword ) !== false;
					}
				);
				// 2. password.
				$searchword       = 'Password:';
				$matches_password = array_filter(
					$logs_array,
					function( $var ) use ( $searchword ) {
						return strpos( $var, $searchword ) !== false;
					}
				);

				if ( ! empty( $matches_user ) && count( $matches_user ) == 1 ) {

					update_post_meta( $id, 'wpapp_phpmyadmin_status', 'on' );
					update_post_meta( $id, 'wpapp_phpmyadmin_user_id', array_values( $matches_user )[0] );
					update_post_meta( $id, 'wpapp_phpmyadmin_user_password', $this::encrypt( array_values( $matches_password )[0] ) );

				}
			}
		}

		// if the command is to remove phpmyadmin then we need to remove some postmeta items and update phpmyadmin status.
		if ( 'remove_phpmyadmin' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'manage_phpmyadmin.txt' );

			if ( true == $success ) {

					update_post_meta( $id, 'wpapp_phpmyadmin_status', 'off' );
					delete_post_meta( $id, 'wpapp_phpmyadmin_user_id' );
					delete_post_meta( $id, 'wpapp_phpmyadmin_user_password' );

			}
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
		return 'database';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_phpmyadmin_tab';
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
				'label' => __( 'Database', 'wpcd' ),
				'icon'  => 'fad fa-database',
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
		$valid_actions = array( 'install-phpmyadmin', 'update-phpmyadmin', 'remove-phpmyadmin' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'install-phpmyadmin':
					$result = $this->manage_phpmyadmin( 'install_phpmyadmin', $id );
					break;
				case 'update-phpmyadmin':
					$result = $this->manage_phpmyadmin( 'update_phpmyadmin', $id );
					break;
				case 'remove-phpmyadmin':
					$result = $this->manage_phpmyadmin( 'remove_phpmyadmin', $id );
					break;
			}
		}

		return $result;

	}

	/**
	 * Manage phpmyadmin - add, remove, update/upgrade
	 *
	 * @param string $action The action key to send to the bash script.  This is actually the key of the drop-down select.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function manage_phpmyadmin( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// we want to make sure this command runs only once in a "swatch beat" for a domain.
		// e.g. 2 manual backups cannot run for the same domain at the same time (time = swatch beat).
		// although technically only one command can run per domain (e.g. backup and restore cannot run at the same time).
		// we are appending the Swatch beat to the command name because this command can be run multiple times.
		// over the app's lifetime.
		// but within a swatch beat, it can only be run once.
		$command             = sprintf( '%s---%s---%d', $action, $domain, date( 'B' ) );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_phpmyadmin.txt',
			array(
				'command' => $command,
				'action'  => $action,
				'domain'  => $domain,
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
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
	 * Toggle local status for phpmyadmin
	 *
	 * @param int $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function toggle_local_status_phpmyadmin( $id ) {

		// get current local memcached status.
		$pa_status = get_post_meta( $id, 'wpapp_phpmyadmin_status', true );
		if ( empty( $pa_status ) ) {
			$pa_status = 'off';
		}

		// whats the new status going to be?
		if ( 'on' === $pa_status ) {
			$new_pa_status = 'off';
		} else {
			$new_pa_status = 'on';
		}

		// update it.
		update_post_meta( $id, 'wpapp_phpmyadmin_status', $new_pa_status );

		// Force refresh?
		if ( ! is_wp_error( $result ) ) {
			$result = array(
				'msg'     => __( 'The local PHPMyAdmin status has been toggled.', 'wpcd' ),
				'refresh' => 'yes',
			);
		} else {
			$result = false;
		}

		return $result;

	}

	/**
	 * Gets the fields to be shown.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields( array $fields, $id ) {

		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'database' ) );
		}

		$desc  = __( 'Use PHPMyAdmin to access and manage the data in your WordPress database.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'This is a very powerful tool with which you can easily corrupt your database beyond repair.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'Before performing any actions with it, we urge you to backup your site!', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'Finally, because this tool is accessible from the internet we suggest that you remove it when you are done using it.  Or, at least, restrict access to it by your IP address.', 'wpcd' );

		$fields[] = array(
			'name' => __( 'Database', 'wpcd' ),
			'tab'  => 'database',
			'type' => 'heading',
			'desc' => $desc,
		);

		// What is the status of PHPMyAdmin?
		$pa_status = get_post_meta( $id, 'wpapp_phpmyadmin_status', true );
		if ( empty( $pa_status ) ) {
			$pa_status = 'off';
		}

		if ( 'off' == $pa_status ) {
			// PHPMyAdmin is not installed on this site, so show button to install it.
			$fields[] = array(
				'id'         => 'install-phpmyadmin',
				'name'       => __( 'Install PHPMyAdmin', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Install PHPMyAdmin', 'wpcd' ),
				'desc'       => __( 'The tool for managing your database is not enabled. Click this button to enable it now.', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'install-phpmyadmin',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to install the PHPMyAdmin tool?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to install PHPMyAdmin.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);
		} else {

			// use custom html to show a launch link.
			$phpmyadmin_user_id  = get_post_meta( $id, 'wpapp_phpmyadmin_user_id', true );
			$phpmyadmin_password = $this::decrypt( get_post_meta( $id, 'wpapp_phpmyadmin_user_password', true ) );

			// Remove any "user:" and "Password:" phrases that might be embedded inside the user id and password strings.
			$phpmyadmin_user_id  = str_replace( 'User:', '', $phpmyadmin_user_id );
			$phpmyadmin_password = str_replace( 'Password:', '', $phpmyadmin_password );

			if ( 'on' == get_post_meta( $id, 'wpapp_ssl_status', true ) ) {
				$phpmyadmin_url = 'https://' . $this->get_domain_name( $id ) . '/' . 'phpMyAdmin';
			} else {
				$phpmyadmin_url = 'http://' . $this->get_domain_name( $id ) . '/' . 'phpMyAdmin';
			}

			$launch = sprintf( '<a href="%s" target="_blank">', $phpmyadmin_url ) . __( 'Launch PHPMyAdmin', 'wpcd' ) . '</a>';

			$fields[] = array(
				'tab'   => 'database',
				'type'  => 'custom_html',
				'std'   => $launch,
				'class' => 'button',
			);
			$fields[] = array(
				'tab'  => 'database',
				'type' => 'custom_html',
				'std'  => __( 'User Id: ', 'wpcd' ) . $phpmyadmin_user_id . '<br />' . __( 'Password: ', 'wpcd' ) . $phpmyadmin_password,
			);

			// new fields section for update and remove of phpmyadmin.
			$fields[] = array(
				'name' => __( 'Database Tools - Update and Remove', 'wpcd' ),
				'tab'  => 'database',
				'type' => 'heading',
				'desc' => __( 'Update and/or remove PHPMyAdmin', 'wpcd' ),
			);

			// update php my admin.
			$fields[] = array(
				'id'         => 'update-phpmyadmin',
				'name'       => __( 'Update PHPMyAdmin', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Update PHPMyAdmin', 'wpcd' ),
				'desc'       => __( 'Update the PHPMyAdmin tool to the latest version.', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'update-phpmyadmin',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to update the PHPMyAdmin tool? This is a risky operation and should only be done if there are security issues that need to be addressed!', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to update PHPMyAdmin.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			// remove phpmyadmin.
			$fields[] = array(
				'id'         => 'remove-phpmyadmin',
				'name'       => __( 'Remove PHPMyAdmin', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Remove PHPMyAdmin', 'wpcd' ),
				'desc'       => __( 'Remove the PHPMyAdmin tool from this site.', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'remove-phpmyadmin',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to remove the PHPAdmin tool from this site?', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to remove PHPMyAdmin.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

		}

		return $fields;

	}

}

new WPCD_WORDPRESS_TABS_PHPMYADMIN();
