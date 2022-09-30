<?php
/**
 * WP SITE USERS tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_WP_SITE_USERS
 */
class WPCD_WORDPRESS_TABS_WP_SITE_USERS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_WP_SITE_USERS constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// Allow add new admin action to be triggered via an action hook.
		add_action( "wpcd_{$this->get_app_name()}_add_new_wp_admin", array( $this, 'do_add_admin_user_action' ), 10, 2 ); // Hook:wpcd_wordpress-app_add_new_wp_admin.

		// Allow change credentials action to be triggered via an action hook.
		add_action( "wpcd_{$this->get_app_name()}_change_wp_credentials", array( $this, 'do_change_wp_credentials_action' ), 10, 2 ); // Hook:wpcd_wordpress-app_change_wp_credentials.

		// Allow add new user action to be triggered via an action hook.
		add_action( "wpcd_{$this->get_app_name()}_add_new_wp_user", array( $this, 'do_add_user_action' ), 10, 2 ); // Hook:wpcd_wordpress-app_add_new_wp_user.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'wp-site-users';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_wpsiteusers_tab';
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
				'label' => __( 'Users', 'wpcd' ),
				'icon'  => 'fad fa-users-crown',
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
	 * Gets the fields to be shown in the WP SITE USERS tab.
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
		$valid_actions = array( 'wpsiteusers-add-admin-user', 'wpsiteusers-change-credentials', 'wpsiteusers-add-user' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'wpsiteusers-add-admin-user':
					$action = 'add_admin';
					$result = $this->add_admin_user( $id, $action );
					break;
				case 'wpsiteusers-change-credentials':
					$action = 'wp_site_change_credentials';
					$result = $this->change_wp_credentials( $id, $action );
					break;
				case 'wpsiteusers-add-user':
					$action = 'wp_site_add_user';
					$result = $this->add_user( $id, $action );
					break;
			}
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the WP SITE USERS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_wp_site_users_fields( $id );

	}

	/**
	 * Gets the fields for the tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_wp_site_users_fields( $id ) {

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Basic checks passed, ok to proceed.
		$actions = array();

		/* ADD ADMIN USER */
		$actions['wpsiteusers-add-admin-user-header'] = array(
			'label'          => __( 'Add An Administrator', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Add an emergency administrator to this site. You should only need to use this tool if you do not remember your administrator credentials.', 'wpcd' ),
			),
		);

		$actions['wpsiteusers-add-admin-user-name'] = array(
			'label'          => __( 'User Name', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the user name of the new administrator', 'wpcd' ),
				'data-wpcd-name' => 'add_admin_user_name',
				'spellcheck'     => 'false',
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-add-admin-user-pw'] = array(
			'label'          => __( 'Password', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the password for the new administrator', 'wpcd' ),
				'data-wpcd-name' => 'add_admin_pw',
				'spellcheck'     => 'false',
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-add-admin-user-email'] = array(
			'label'          => __( 'Email', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the email address for the new administrator', 'wpcd' ),
				'data-wpcd-name' => 'add_admin_email',
				'size'           => 90,
				'spellcheck'     => 'false',
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-add-admin-user'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Add New Admin User', 'wpcd' ),
				'confirmation_prompt' => __( 'Are you sure you would like to add this user as a new admin to this site?', 'wpcd' ),
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_wpsiteusers-add-admin-user-name', '#wpcd_app_action_wpsiteusers-add-admin-user-pw', '#wpcd_app_action_wpsiteusers-add-admin-user-email' ) ),
			),
			'type'           => 'button',
		);

		/* CHANGE CREDENTIALS FOR EXISTING USER */
		$actions['wpsiteusers-change-credentials-header'] = array(
			'label'          => __( 'Change User Credentials', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Change the email address, password and username for an existing user on this site.', 'wpcd' ),
			),
		);

		$actions['wpsiteusers-change-credentials-user-id'] = array(
			'label'          => __( 'Existing User', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the user name, user id or email address for the user whose credentials will be changed.', 'wpcd' ),
				'data-wpcd-name' => 'wps_user',
				'spellcheck'     => 'false',
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-change-credentials-new-email'] = array(
			'label'          => __( 'New Email', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the new email address for this user', 'wpcd' ),
				'data-wpcd-name' => 'wps_new_email',
				'size'           => 90,
				'spellcheck'     => 'false',
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-change-credentials-new-pw'] = array(
			'label'          => __( 'New Password', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the new password for this user', 'wpcd' ),
				'data-wpcd-name' => 'wps_new_password',
				'size'           => 90,
				'spellcheck'     => 'false',
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-change-credentials'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Update Credentials', 'wpcd' ),
				'confirmation_prompt' => __( 'Are you sure you would like to update the credentials for this user?', 'wpcd' ),
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_wpsiteusers-change-credentials-user-id', '#wpcd_app_action_wpsiteusers-change-credentials-new-email', '#wpcd_app_action_wpsiteusers-change-credentials-new-pw' ) ),
			),
			'type'           => 'button',
		);

		/* ADD REGULAR USER */
		$actions['wpsiteusers-add-user-header'] = array(
			'label'          => __( 'Add A User', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Add a user to this site.', 'wpcd' ),
			),
		);

		$actions['wpsiteusers-add-user-name'] = array(
			'label'          => __( 'User Name', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the user name for the new user', 'wpcd' ),
				'data-wpcd-name' => 'wps_user',
				'spellcheck'     => 'false',
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-add-user-pw'] = array(
			'label'          => __( 'Password', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the password for the new', 'wpcd' ),
				'data-wpcd-name' => 'wps_password',
				'spellcheck'     => 'false',
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-add-user-email'] = array(
			'label'          => __( 'Email', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the email address for the new user', 'wpcd' ),
				'data-wpcd-name' => 'wps_email',
				'spellcheck'     => 'false',
				'size'           => 90,
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-add-user-role'] = array(
			'label'          => __( 'Role', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the new user\'s role', 'wpcd' ),
				'data-wpcd-name' => 'wps_role',
				'size'           => 90,
			),
			'type'           => 'text',
		);

		$actions['wpsiteusers-add-user'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Add New User', 'wpcd' ),
				'confirmation_prompt' => __( 'Are you sure you would like to add this user to this site?', 'wpcd' ),
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_wpsiteusers-add-user-name', '#wpcd_app_action_wpsiteusers-add-user-pw', '#wpcd_app_action_wpsiteusers-add-user-email', '#wpcd_app_action_wpsiteusers-add-user-role' ) ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Add a new admin user to the site.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function add_admin_user( $id, $action, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Get app/server details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if no app/server details.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_admin_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Check to make sure that all required fields have values.
		if ( ! $args['add_admin_user_name'] ) {
			$message = __( 'The username for the new admin cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_admin_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}
		if ( ! $args['add_admin_pw'] ) {
			$message = __( 'The password for the new admin cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_admin_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}
		if ( ! $args['add_admin_email'] ) {
			$message = __( 'The email address for the new admin cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_admin_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Special sanitization for data elements that are going to be passed to the shell scripts.
		if ( isset( $args['add_admin_user_name'] ) ) {
			$args['wp_user'] = escapeshellarg( $args['add_admin_user_name'] );
		}
		if ( isset( $args['add_admin_pw'] ) ) {
			$args['wp_password'] = escapeshellarg( $args['add_admin_pw'] );
		}
		if ( isset( $args['add_admin_email'] ) ) {
			$args['wp_email'] = escapeshellarg( $args['add_admin_email'] );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'add_wp_admin.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'add_wp_admin.txt' );

		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			$message = sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_admin_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$success = array(
				'msg'     => __( 'The new administrator has been added to the site.  We hope you remember your new password since no details about this new user will be kept in this console.', 'wpcd' ),
				'refresh' => 'yes',
			);

			// Let others know we've been successful.
			do_action( "wpcd_{$this->get_app_name()}_add_wp_admin_successful", $id, $action, $args );
		}

		return $success;

	}

	/**
	 * Add a new regular user to the site.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function add_user( $id, $action, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Get app/server details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if no app/server details.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_user_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Check to make sure that all required fields have values.
		if ( ! $args['wps_user'] ) {
			$message = __( 'The username for the new user cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_user_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}
		if ( ! $args['wps_password'] ) {
			$message = __( 'The password for the new user cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_user_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}
		if ( ! $args['wps_email'] ) {
			$message = __( 'The email address for the new user cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_user_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}
		if ( ! $args['wps_role'] ) {
			$message = __( 'The role for the new user cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_user_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Special sanitization for data elements that are going to be passed to the shell scripts.
		if ( isset( $args['wps_user'] ) ) {
			$args['wps_user'] = escapeshellarg( $args['wps_user'] );
		}
		if ( isset( $args['wps_password'] ) ) {
			$args['wps_password'] = escapeshellarg( $args['wps_password'] );
		}
		if ( isset( $args['wps_email'] ) ) {
			$args['wps_email'] = escapeshellarg( $args['wps_email'] );
		}
		if ( isset( $args['wps_role'] ) ) {
			$args['wps_role'] = escapeshellarg( $args['wps_role'] );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'add_wp_user.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'add_wp_user.txt' );

		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			$message = sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result );
			do_action( "wpcd_{$this->get_app_name()}_add_wp_user_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$success = array(
				'msg'     => __( 'The new user has been added to this site.', 'wpcd' ),
				'refresh' => 'yes',
			);

			// Let others know we've been successful.
			do_action( "wpcd_{$this->get_app_name()}_add_wp_user_successful", $id, $action, $args );
		}

		return $success;

	}

	/**
	 * Change credentials for existing user.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function change_wp_credentials( $id, $action, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Get app/server details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if no app/server details.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_change_wp_credentials_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Check to make sure that all required fields have values.
		if ( ! $args['wps_user'] ) {
			$message = __( 'The user cannot be blank - it must be the user id, user name or email address for the existing user.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_change_wp_credentials_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			// Sanitize the option name for use on the linux command line.
			$args['wps_user'] = escapeshellarg( $args['wps_user'] );
		}

		if ( ! $args['wps_new_email'] ) {
			$message = __( 'The new email address cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_change_wp_credentials_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			// Sanitize the option name for use on the linux command line.
			$args['wps_new_email'] = escapeshellarg( $args['wps_new_email'] );
		}

		if ( ! $args['wps_new_password'] ) {
			$message = __( 'The new password cannot be blank.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_change_wp_credentials_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			// Sanitize the option name for use on the linux command line.
			$args['wps_new_password'] = escapeshellarg( $args['wps_new_password'] );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'change_wp_credentials.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'change_wp_credentials.txt' );

		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			$message = sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result );
			do_action( "wpcd_{$this->get_app_name()}_change_wp_credentials_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$success = array(
				'msg'     => __( 'The credentials for the specified user have been changed.', 'wpcd' ),
				'refresh' => 'yes',
			);

			// Let others know we've been successful.
			do_action( "wpcd_{$this->get_app_name()}_change_wp_credentials_successful", $id, $action, $args );
		}

		return $success;

	}

	/**
	 * Trigger the add admin user action from an action hook.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_add_new_wp_admin | wpcd_wordpress-app_add_new_wp_admin
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the add admin function needs.
	 */
	public function do_add_admin_user_action( $id, $args ) {
		$this->add_admin_user( $id, 'add_admin', $args );
	}

	/**
	 * Trigger the change credentials action from an action hook.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_change_wp_credentails | wpcd_wordpress-app_change_wp_credentials
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the add admin function needs.
	 */
	public function do_change_wp_credentials_action( $id, $args ) {
		$this->change_wp_credentials( $id, 'wp_site_change_credentials', $args );
	}

	/**
	 * Trigger the add user action from an action hook.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_add_new_wp_user | wpcd_wordpress-app_add_new_wp_user
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the add admin function needs.
	 */
	public function do_add_user_action( $id, $args ) {
		$this->add_user( $id, 'wp_site_add_user', $args );
	}


}

new WPCD_WORDPRESS_TABS_WP_SITE_USERS();
