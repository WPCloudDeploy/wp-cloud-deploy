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

		// Allow add new wp-admin action to be triggered via an action hook.
		add_action( "wpcd_{$this->get_app_name()}_add_new_wp_admin", array( $this, 'do_add_admin_user_action' ), 10, 2 ); // Hook:wpcd_wordpress-app_add_new_wp_admin.

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
		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'WP Site Users', 'wpcd' ),
				'icon'  => 'fad fa-users-crown',
			);
		}
		return $tabs;
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
		$valid_actions = array( 'tools-add-admin-user' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'tools-add-admin-user':
					$action = 'add_admin';
					$result = $this->add_admin_user( $id, $action );
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

		return $this->get_tools_fields( $id );

	}

	/**
	 * Gets the fields for the wp linux cron options to be shown in the TOOLS tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_tools_fields( $id ) {

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Basic checks passed, ok to proceed.
		$actions = array();

		/* ADD ADMIN USER */
		$actions['tools-add-admin-user-header'] = array(
			'label'          => __( 'Add An Administrator', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Add an emergency administrator to this site. You should only need to use this tool if you do not remember your administrator credentials.', 'wpcd' ),
			),
		);

		$actions['tools-add-admin-user-name'] = array(
			'label'          => __( 'User Name', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the user name of the new administrator', 'wpcd' ),
				'data-wpcd-name' => 'add_admin_user_name',
			),
			'type'           => 'text',
		);

		$actions['tools-add-admin-user-pw'] = array(
			'label'          => __( 'Password', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the password for the new administrator', 'wpcd' ),
				'data-wpcd-name' => 'add_admin_pw',
			),
			'type'           => 'text',
		);

		$actions['tools-add-admin-user-email'] = array(
			'label'          => __( 'Email', 'wpcd' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the email address for the new administrator', 'wpcd' ),
				'data-wpcd-name' => 'add_admin_email',
				'size'           => 90,
			),
			'type'           => 'text',
		);

		$actions['tools-add-admin-user'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Add New Admin User', 'wpcd' ),
				'confirmation_prompt' => __( 'Are you sure you would like to add this user as a new admin to this site?', 'wpcd' ),
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_tools-add-admin-user-name', '#wpcd_app_action_tools-add-admin-user-pw', '#wpcd_app_action_tools-add-admin-user-email' ) ),
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
			do_action( "wpcd_{$this->get_app_name()}_add_wp_admin_succeeded", $id, $action, $args );
		}

		return $success;

	}

	/**
	 * Trigger the site clone from an action hook.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_add_new_wp_admin | wpcd_wordpress-app_add_new_wp_admin
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the add admin function needs.
	 */
	public function do_add_admin_user_action( $id, $args ) {
		$this->add_admin_user( $id, 'add_admin', $args );
	}

}

new WPCD_WORDPRESS_TABS_WP_SITE_USERS();
