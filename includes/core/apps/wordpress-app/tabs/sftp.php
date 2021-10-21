<?php
/**
 * SFTP Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SFTP
 */
class WPCD_WORDPRESS_TABS_SFTP extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_SFTP constructor.
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
		$tabs['sftp'] = array(
			'label' => __( 'sFTP', 'wpcd' ),
			'icon'  => 'fad fa-exchange',
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

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->wpcd_user_can_view_wp_app( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		switch ( $action ) {
			case 'sftp-get-passwd':
				$result = $this->get_sftp_user_password( $id, sanitize_text_field( $_POST['user'] ) );
				break;
			case 'sftp-add-user':
			case 'sftp-remove-user':
			case 'sftp-change-password':
			case 'sftp-remove-password':
			case 'sftp-remove-key':
			case 'sftp-set-key':
				$result = $this->do_sftp_actions( $action, $id );
				// most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
				if ( ! in_array( $action, array(), true ) && ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
		}
		return $result;
	}

	/**
	 * Gets the fields to be shown in the sFTP USERS tab.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 */
	public function get_fields( array $fields, $id ) {

		return array_merge(
			$fields,
			$this->get_sftp_fields( $fields, $id )
		);

	}

	/**
	 * Gets the fields to be shown in the sFTP area of the tab.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 */
	public function get_sftp_fields( array $fields, $id ) {

		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		$fields = array();

		// add user.
		$fields[] = array(
			'name' => __( 'Add an sFTP User', 'wpcd' ),
			'tab'  => 'sftp',
			'type' => 'heading',
		);
		$fields[] = array(
			'name'       => __( 'User Name', 'wpcd' ),
			'id'         => 'wpcd_app_user1',
			'tab'        => 'sftp',
			'type'       => 'text',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'user',
			),
		);
		$fields[] = array(
			'name'       => __( 'Password', 'wpcd' ),
			'id'         => 'wpcd_app_pass1',
			'tab'        => 'sftp',
			'type'       => 'password',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'pass',
			),
			'class'      => 'wpcd_app_pass_toggle',
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_add_button',
			'tab'        => 'sftp',
			'type'       => 'button',
			'std'        => __( 'Add User', 'wcpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'sftp-add-user',
				// the id.
				'data-wpcd-id'     => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_user1', '#wpcd_app_pass1' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// remove user.
		$users = $this->get_sftp_users( $id );

		// do not show the other fields if there are no users.
		if ( ! $users ) {
			return $fields;
		}

		$fields[] = array(
			'name' => '',
			'tab'  => 'sftp',
			'type' => 'divider',
		);
		$fields[] = array(
			'name' => __( 'Remove an sFTP User', 'wpcd' ),
			'tab'  => 'sftp',
			'type' => 'heading',
		);
		$fields[] = array(
			'name'        => __( 'User Name', 'wpcd' ),
			'id'          => 'wpcd_app_user2',
			'tab'         => 'sftp',
			'type'        => 'select',
			'placeholder' => __( 'Select a User', 'wpcd' ),
			'options'     => $users['users'],
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'user',
			),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_remove_button',
			'tab'        => 'sftp',
			'type'       => 'button',
			'std'        => __( 'Remove User', 'wcpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'sftp-remove-user',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_user2' ) ),
				// confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you want to remove this user?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// change password - - show only those users who do not have a public key.
		$fields[] = array(
			'name' => '',
			'tab'  => 'sftp',
			'type' => 'divider',
		);
		$fields[] = array(
			'name' => __( 'Change Password for an sFTP User', 'wpcd' ),
			'tab'  => 'sftp',
			'type' => 'heading',
		);
		$fields[] = array(
			'name'        => __( 'User Name', 'wpcd' ),
			'id'          => 'wpcd_app_user3',
			'tab'         => 'sftp',
			'type'        => 'select',
			'placeholder' => __( 'Select a User', 'wpcd' ),
			'options'     => $users['pass'],
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'user',
				// the id, important for showing the password.
				'data-wpcd-id'   => $id,
			),
		);
		$fields[] = array(
			'name'       => __( 'Password', 'wpcd' ),
			'id'         => 'wpcd_app_pass3',
			'tab'        => 'sftp',
			'type'       => 'password',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'pass',
			),
			'class'      => 'wpcd_app_pass_toggle',
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_changepass_button',
			'tab'        => 'sftp',
			'type'       => 'button',
			'std'        => __( 'Change Password', 'wcpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'sftp-change-password',
				// the id.
				'data-wpcd-id'     => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_user3', '#wpcd_app_pass3' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// set key.
		$fields[] = array(
			'name' => '',
			'tab'  => 'sftp',
			'type' => 'divider',
		);
		$fields[] = array(
			'name' => __( 'Set public key for an sFTP User', 'wpcd' ),
			'tab'  => 'sftp',
			'type' => 'heading',
		);
		$fields[] = array(
			'name'        => __( 'User Name', 'wpcd' ),
			'id'          => 'wpcd_app_user6',
			'tab'         => 'sftp',
			'type'        => 'select',
			'placeholder' => __( 'Select a User', 'wpcd' ),
			'options'     => $users['users'],
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'user',
			),
		);
		$fields[] = array(
			'name'             => __( 'Public Key File', 'wpcd' ),
			'id'               => 'wpcd_app_file6',
			'tab'              => 'sftp',
			'type'             => 'file',
			'max_file_uploads' => 1,
			'placeholder'      => __( 'Select a key', 'wpcd' ),
			'save_field'       => false,
			'class'            => 'wpcd_app_setkey',
			'attributes'       => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'user',
			),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_setkey_button',
			'tab'        => 'sftp',
			'type'       => 'button',
			'std'        => __( 'Set Public Key', 'wcpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'sftp-set-key',
				// the id.
				'data-wpcd-id'     => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_user6' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// remove password - show only those users who have a public key.
		$fields[] = array(
			'name' => '',
			'tab'  => 'sftp',
			'type' => 'divider',
		);
		$fields[] = array(
			'name' => __( 'Remove password for an sFTP User', 'wpcd' ),
			'tab'  => 'sftp',
			'type' => 'heading',
		);
		$fields[] = array(
			'name'        => __( 'User Name', 'wpcd' ),
			'id'          => 'wpcd_app_user4',
			'tab'         => 'sftp',
			'type'        => 'select',
			'placeholder' => __( 'Select a User', 'wpcd' ),
			'options'     => $users['keys'],
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'user',
			),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_removepass_button',
			'tab'        => 'sftp',
			'type'       => 'button',
			'std'        => __( 'Remove Password', 'wcpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'sftp-remove-password',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_user4' ) ),
				// confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you want to remove this user\'s password?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		// remove key - show only those users who have a public key.
		$fields[] = array(
			'name' => '',
			'tab'  => 'sftp',
			'type' => 'divider',
		);
		$fields[] = array(
			'name' => __( 'Remove public key for an sFTP User', 'wpcd' ),
			'tab'  => 'sftp',
			'type' => 'heading',
		);
		$fields[] = array(
			'name'        => __( 'User Name', 'wpcd' ),
			'id'          => 'wpcd_app_user5',
			'tab'         => 'sftp',
			'type'        => 'select',
			'placeholder' => __( 'Select a User', 'wpcd' ),
			'options'     => $users['keys'],
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'user',
			),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_removekey_button',
			'tab'        => 'sftp',
			'type'       => 'button',
			'std'        => __( 'Remove Public Key', 'wcpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'sftp-remove-key',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => json_encode( array( '#wpcd_app_user5' ) ),
				// confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you want to remove this user\'s key?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;
	}

	/**
	 * Gets the sftp users, users that have keys set and users that have passwords set.
	 *
	 * @param int $id id.
	 * @return array Array of users with the following keys
	 * users: array of all SFTP users
	 * keys: array of all SFTP users that use public keys
	 * pass: array of all SFTP users that use passwords
	 *
	 * The value of each array above is in the form of 'username' => 'username'
	 * to enable them to be directly shown in the select box.
	 */
	private function get_sftp_users( $id ) {
		$users     = array();
		$user_list = array();
		$key_list  = array();
		$pass_list = array();

		$users = get_post_meta( $id, 'wpapp_sftp_users', true );
		if ( ! $users ) {
			return null;
		}

		// get the users that have keys.
		$keys = get_post_meta( $id, 'wpapp_sftp_keys', true );
		if ( ! $keys ) {
			$keys = array();
		}

		// collect them in an array.
		foreach ( $keys as $user ) {
			$key_list[ $user ] = $user;
		}

		// get all users.
		$users = get_post_meta( $id, 'wpapp_sftp_users', true );
		if ( ! $users ) {
			$users = array();
		}
		foreach ( $users as $user ) {
			$user_list[ $user ] = $user;
			// collect users that don't have keys in the array for passworded users.
			if ( ! array_key_exists( $user, $key_list ) ) {
				$pass_list[ $user ] = $user;
			}
		}

		return array(
			// all users.
			'users' => $user_list,
			// users with keys.
			'keys'  => $key_list,
			// users with passwords.
			'pass'  => $pass_list,
		);
	}

	/**
	 * Performs the SFTP action.
	 *
	 * @param string $action action.
	 * @param int    $id id.
	 */
	private function do_sftp_actions( $action, $id ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		/* Grab the arguments sent from the front-end JS */
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		/* For certain actions, need to verify that the user is part of the current site because anyone can modify the JS scripts on the front end to send in a random user name   */
		$ftp_users = $this->get_sftp_users( $id );
		$user      = $args['user']; // already sanitized above.

		if ( 'sftp-add-user' <> $action ) {

			// all actions beside adding a new user needs to be validated to make sure that the user already exists for this site.
			if ( in_array( $user, $ftp_users['users'] ) ) {
				// good to go.
			} else {
				return new \WP_Error( __( 'You are not authorized to perform an operation on this user!', 'wpcd' ) );
			}
		}
		/* End verify that user is a part of the site. */

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$esc_args = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		$run_cmd = '';

		switch ( $action ) {
			case 'sftp-add-user':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'add_remove_sftp.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'add',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'sftp-remove-user':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'add_remove_sftp.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'remove',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'sftp-change-password':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'add_remove_sftp.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'change_password',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'sftp-remove-password':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'add_remove_sftp.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'remove_password',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'sftp-remove-key':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'add_remove_sftp.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'remove_key',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'sftp-set-key':
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
						'add_remove_sftp.txt',
						array_merge(
							$esc_args,
							array(
								'action'     => 'set_key',
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

		// Certain actions need special checks in the $result var.
		if ( 'sftp-add-user' === $action ) {
			if ( strpos( $result, 'already exists. Use a different user name.' ) !== false ) {
				// User already exists so error out and return...
				return new \WP_Error( __( 'A user with this name already exists and is likely associated with another site.  Please use a different user name', 'wpcd' ) );
			}
		}

		$success = $this->is_ssh_successful( $result, 'add_remove_sftp.txt', $action );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		switch ( $action ) {
			case 'sftp-add-user':
				$users = get_post_meta( $id, 'wpapp_sftp_users', true );
				if ( ! $users ) {
					$users = array();
				}
				$users[] = $args['user'];
				update_post_meta( $id, 'wpapp_sftp_users', $users );

				$pass = get_post_meta( $id, 'wpapp_sftp_pass', true );
				if ( ! $pass ) {
					$pass = array();
				}
				$pass[ $args['user'] ] = self::encrypt( $args['pass'] );
				update_post_meta( $id, 'wpapp_sftp_pass', $pass );
				break;
			case 'sftp-remove-user':
				$users = get_post_meta( $id, 'wpapp_sftp_users', true );
				if ( $users ) {
					// Update basic users array.
					$new_users = array();
					foreach ( $users as $user ) {
						if ( $user !== $args['user'] ) {
							$new_users[] = $user;
						}
					}
					update_post_meta( $id, 'wpapp_sftp_users', $new_users );

					// Update passwords array.
					$pass     = get_post_meta( $id, 'wpapp_sftp_pass', true );
					$new_pass = array();
					foreach ( $pass as $u => $p ) {
						if ( $u !== $args['user'] ) {
							$new_pass[ $u ] = $p;
						}
					}
					update_post_meta( $id, 'wpapp_sftp_pass', $new_pass );

					// Update keys array.
					$keys     = get_post_meta( $id, 'wpapp_sftp_keys', true );
					$new_keys = array();
					foreach ( $keys as $key ) {
						if ( $key !== $args['user'] ) {
							$new_keys[] = $key;
						}
					}
					update_post_meta( $id, 'wpapp_sftp_keys', $new_keys );

				}
				break;
			case 'sftp-change-password':
				$pass     = get_post_meta( $id, 'wpapp_sftp_pass', true );
				$new_pass = array();
				foreach ( $pass as $u => $p ) {
					if ( $u !== $args['user'] ) {
						$new_pass[ $u ] = $p;
					} else {
						$new_pass[ $u ] = self::encrypt( $args['pass'] );
					}
				}
				update_post_meta( $id, 'wpapp_sftp_pass', $new_pass );
				break;
			case 'sftp-remove-password':
				$pass     = get_post_meta( $id, 'wpapp_sftp_pass', true );
				$new_pass = array();
				foreach ( $pass as $u => $p ) {
					if ( $u !== $args['user'] ) {
						$new_pass[ $u ] = $p;
					}
				}
				update_post_meta( $id, 'wpapp_sftp_pass', $new_pass );
				break;
			case 'sftp-remove-key':
				$users = get_post_meta( $id, 'wpapp_sftp_keys', true );
				if ( $users ) {
					$new_users = array();
					foreach ( $users as $user ) {
						if ( $user !== $args['user'] ) {
							$new_users[] = $user;
						}
					}
					update_post_meta( $id, 'wpapp_sftp_keys', $new_users );
				}
				break;
			case 'sftp-set-key':
				$users = get_post_meta( $id, 'wpapp_sftp_keys', true );
				if ( ! $users ) {
					$users = array();
				}
				$users[] = $args['user'];
				$users   = array_unique( $users );
				update_post_meta( $id, 'wpapp_sftp_keys', $users );
				break;
		}

		return $success;

	}

	/**
	 * Returns the password for an sftp user.
	 *
	 * @param int $id id.
	 * @param int $user user.
	 */
	private function get_sftp_user_password( $id, $user ) {
		$password = null;
		$users    = get_post_meta( $id, 'wpapp_sftp_users', true );
		if ( $users ) {
			$pass = get_post_meta( $id, 'wpapp_sftp_pass', true );
			if ( $pass && array_key_exists( $user, $pass ) ) {
				$password = self::decrypt( $pass[ $user ] );
			}
		}

		if ( $password ) {
			return $password;
		}

		// if there is no password, this is definitely an error.
		return new \WP_Error( sprintf( __( 'Password for user %s not found!', 'wpcd' ), $user ) );
	}

}

new WPCD_WORDPRESS_TABS_SFTP();
