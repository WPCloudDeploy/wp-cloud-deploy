<?php
/**
 * Site system users.
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
 * Class WPCD_WORDPRESS_TABS_SITE_SYSTEM_USERS
 */
class WPCD_WORDPRESS_TABS_SITE_SYSTEM_USERS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_SITE_SYSTEM_USERS constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
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
		$tabs['site-system-users'] = array(
			'label' => __( 'System Users', 'wpcd' ),
			'icon'  => 'fad fa-users-friends',
		);
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

		/* Verify that the list of actions is valid for this tab. */
		$valid_actions = array( 'site-user-change-password', 'site-user-remove-password', 'site-user-remove-key', 'site-user-set-key' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return $result;
		}

		/* Verify that the user is even allowed to view the server before proceeding to do anything else */
		$server_post = $this->get_server_by_app_id( $id );
		if ( ! $this->wpcd_user_can_view_wp_server( $server_post->ID ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->wpcd_user_can_view_wp_app( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Verify that the tab is enabled */
		if ( ( ! defined( 'WPCD_SHOW_SITE_USERS_TAB' ) ) || ( defined( 'WPCD_SHOW_SITE_USERS_TAB' ) && ! WPCD_SHOW_SITE_USERS_TAB ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		switch ( $action ) {
			case 'site-user-change-password':
			case 'site-user-remove-password':
			case 'site-user-remove-key':
			case 'site-user-set-key':
				$result = $this->do_site_user_actions( $action, $id );
				// most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
				if ( ! in_array( $action, array(), true ) && ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
		}
		return $result;
	}

	/**
	 * Gets the fields to be shown in the SITE USERS tab.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 */
	public function get_fields( array $fields, $id ) {

		return array_merge(
			$fields,
			$this->get_site_users_fields( $fields, $id )
		);

	}

	/**
	 * Gets the fields to be shown in the SITE USERS area of the tab.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 */
	public function get_site_users_fields( array $fields, $id ) {

		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'site-system-users' ) );
		}

		/* Array variable to hold our field definitions */
		$fields = array();

		/* Only show fields to users who can view servers */
		$server_post = $this->get_server_by_app_id( $id );
		if ( ! $this->wpcd_user_can_view_wp_server( $server_post->ID ) ) {

			$fields[] = array(
				'name' => __( 'Manage The Site User', 'wpcd' ),
				'desc' => __( 'You are not allowed to manage site users - only admins with full access to the server hosting this site can manage these users', 'wpcd' ),
				'tab'  => 'site-system-users',
				'type' => 'heading',
			);

			return $fields;

		}

		// Get password from database...
		$encrypted_pass = get_post_meta( $id, 'wpapp_site_user_pass', true );
		if ( empty( $encrypted_pass ) ) {
			$pass = '';
		} else {
			$pass = self::decrypt( $encrypted_pass );
		}

		// manage site user heading.
		$desc  = __( 'Site users are special users under which the website is run.  They normally have no password, are run in a chroot jail and cannot be used to log into the server.  The options in this section allow you to override this default behavior. Once a password or publickey is assigned, these users can log in with both sftp and ssh.', 'wpcd' );
		$desc .= '<br />' . sprintf( __( 'Your site user username is %s', 'wpcd' ), '<em><b>' . get_post_meta( $id, 'wpapp_domain', true ) . '</b></em>' );
		$desc .= '<br />' . __( 'Warning: Site users with passwords or public keys will have been released from their jails and have privileged access to your server resources so please be VERY careful if you assign a password or publickey to this user!', 'wpcd' );

		/*
		$fields[] = array(
				'name' => __( '', 'wpcd' ),
				'tab'	=> 'site-system-users',
				'type' => 'divider',
		);
		*/

		$fields[] = array(
			'name' => __( 'Manage The Site User', 'wpcd' ),
			'desc' => $desc,
			'tab'  => 'site-system-users',
			'type' => 'heading',
		);

		// change site user password.
		$fields[] = array(
			'name'       => __( 'Password', 'wpcd' ),
			'id'         => 'wpcd_app_site_user_pass',
			'tab'        => 'site-system-users',
			'type'       => 'password',
			'save_field' => false,
			'std'        => $pass,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'site_user_pass',
			),
			'class'      => 'wpcd_app_pass_toggle',
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_site_user_changepass_button',
			'tab'        => 'site-system-users',
			'type'       => 'button',
			'std'        => __( 'Change Password', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'site-user-change-password',
				// the id.
				'data-wpcd-id'     => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_site_user_pass' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// remove site user password.
		if ( ! empty( $pass ) ) {
			$fields[] = array(
				'id'         => 'wpcd_app_action_site_user_removepass_button',
				'tab'        => 'site-system-users',
				'type'       => 'button',
				'std'        => __( 'Remove Password', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'site-user-remove-password',
					// the id.
					'data-wpcd-id'     => $id,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);
		}

		// set public key for site user.
		$fields[] = array(
			'name'             => __( 'Public Key File', 'wpcd' ),
			'id'               => 'wpcd_app_site_user_public_key_file',
			'tab'              => 'site-system-users',
			'type'             => 'file',
			'max_file_uploads' => 1,
			'placeholder'      => __( 'Select a key', 'wpcd' ),
			'save_field'       => false,
			'class'            => 'wpcd_app_setkey wpcd_app_site_user_setkey',
			'attributes'       => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'site_user_public_key_file',
			),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_site_user_setkey_button',
			'tab'        => 'site-system-users',
			'type'       => 'button',
			'std'        => __( 'Set Public Key', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'site-user-set-key',
				// the id.
				'data-wpcd-id'     => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_site_user_public_key_file' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// Remove public key for site user...
		if ( ! empty( get_post_meta( $id, 'wpapp_site_user_public_key_flag', true ) ) ) {
			$fields[] = array(
				'id'         => 'wpcd_app_action_site_user_removekey_button',
				'tab'        => 'site-system-users',
				'type'       => 'button',
				'std'        => __( 'Remove Public Key', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'site-user-remove-key',
					// the id.
					'data-wpcd-id'     => $id,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);
		}

		return $fields;

	}

	/**
	 * Performs the SITE USER action.
	 *
	 * @param string $action action.
	 * @param int    $id id.
	 */
	private function do_site_user_actions( $action, $id ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return $result;
		}

		/* Grab the arguments sent from the front-end JS */
		$args = wp_parse_args( sanitize_text_field( $_POST['params'] ) );

		// The user is the application domain.
		$user         = get_post_meta( $id, 'wpapp_domain', true );
		$args['user'] = $user;

		// Do some manipulation of the data depending on the action.
		switch ( $action ) {
			case 'site-user-change-password':
				// Grab the original password.
				if ( isset( $args['site_user_pass'] ) ) {
					$original_pass = $args['site_user_pass'];
				} else {
					$original_pass = '';
				}

				// add it back to the array with the proper key...
				$args['pass'] = $original_pass;

				break;
		}

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$esc_args = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		$run_cmd = '';

		switch ( $action ) {
			case 'site-user-change-password':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_site_users.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'site_user_change_password',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'site-user-remove-password':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_site_users.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'site_user_remove_password',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'site-user-remove-key':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_site_users.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'site_user_remove_key',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'site-user-set-key':
				// first upload the file to /tmp/ and then specify its path in the script.
				$tmp_file = sprintf( '/tmp/%s%s', time(), $_FILES['file']['name'] );
				$run_cmd  = $this->execute_ssh(
					'upload',
					$instance,
					array(
						'remote' => $tmp_file,
						'local'  => $_FILES['file']['tmp_name'],
					)
				);
				if ( ! is_wp_error( $run_cmd ) ) {
					$run_cmd = $this->turn_script_into_command(
						$instance,
						'manage_site_users.txt',
						array_merge(
							$esc_args,
							array(
								'action'     => 'site_user_set_key',
								'domain'     => get_post_meta( $id, 'wpapp_domain', true ),
								'public_key' => $tmp_file,
							)
						)
					);
				}
				break;
		}

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'manage_site_users.txt', $action );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		switch ( $action ) {
			case 'site-user-change-password':
				update_post_meta( $id, 'wpapp_site_user_pass', self::encrypt( $original_pass ) );
				break;
			case 'site-user-remove-password':
				update_post_meta( $id, 'wpapp_site_user_pass', '' );
				break;
			case 'site-user-remove-key':
				update_post_meta( $id, 'wpapp_site_user_public_key_flag', '' );
				break;
			case 'site-user-set-key':
				update_post_meta( $id, 'wpapp_site_user_public_key_flag', '1' );
				break;
		}

		return $success;
	}


}

new WPCD_WORDPRESS_TABS_SITE_SYSTEM_USERS();
