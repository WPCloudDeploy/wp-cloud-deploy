<?php
/**
 * SSH Keys Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_KEYS
 */
class WPCD_WORDPRESS_TABS_KEYS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'server-ssh-keys';
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs   The default value.
	 * @param int   $id     The post ID of the server.
	 *
	 * @return array    $tabs   New array of tabs
	 */
	public function get_tab( $tabs, $id ) {
		if ( true === $this->wpcd_wpapp_server_user_can( 'view_wpapp_server_ssh_keys_tab', $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Keys', 'wpcd' ),
				'icon'  => 'fad fa-key',
			);
		}
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the SSH KEYS (KEYS) tab.
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
	 * @param int    $id The post ID of the server.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the server before proceeding to do anything else */
		if ( ! $this->wpcd_user_can_view_wp_server( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'server-ssh-keys-save', 'server-ssh-keys-copy', 'server-ssh-keys-remove' );
		if ( in_array( $action, $valid_actions ) ) {
			if ( false === $this->wpcd_wpapp_server_user_can( 'view_wpapp_server_ssh_keys_tab', $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		// Perform actions if allowed to do so.
		if ( $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'server-ssh-keys-save':
				case 'server-ssh-keys-copy':
				case 'server-ssh-keys-remove':
					$result = $this->do_server_keys_actions( $id, $action );
					// most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
					if ( ! in_array( $action, array(), true ) && ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the SSH KEYS (KEYS) tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_server_ssh_keys_fields( $id );

	}

	/**
	 * Gets the fields to show in the SSH KEYS tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_server_ssh_keys_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		/* Array variable to hold our field definitions */
		$actions = array();

		/* Pull existing data from the database */
		$ssh_root_user            = get_post_meta( $id, 'wpcd_server_ssh_root_user', true );
		$ssh_private_key_password = self::decrypt( get_post_meta( $id, 'wpcd_server_ssh_private_key_password', true ) );
		$ssh_private_key          = self::decrypt( get_post_meta( $id, 'wpcd_server_ssh_private_key', true ) );
		$ssh_public_key           = get_post_meta( $id, 'wpcd_server_ssh_public_key', true );
		$ssh_key_notes            = get_post_meta( $id, 'wpcd_server_ssh_key_notes', true );

		/* Set some defaults if certain things are blank */
		$ssh_root_user = empty( $ssh_root_user ) ? 'root' : $ssh_root_user;

		// manage server SSH KEYS heading.
		$desc  = __( 'Under normal circumstances we log into your server using the SSH keypair data on the SETTINGS screen associated with the provider for this server.', 'wpcd' );
		$desc .= '<br />' . __( 'However, there are situations where you might want this server to have its own separate and unique SSH keypair.', 'wpcd' );

		$actions['server-ssh-keys-header'] = array(
			'label'          => __( 'SSH Keys', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		// Key data.
		$actions['server-ssh-keys-root-user-name'] = array(
			'label'          => __( 'Root User', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $ssh_root_user,
				'desc'           => __( 'The root user id - usually "root" or "ubuntu" for AWS SERVERS.', 'wpcd' ),
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'ssh_root_user',
			),
		);

		$actions['server-ssh-keys-private-key'] = array(
			'label'          => __( 'Private Key', 'wpcd' ),
			'type'           => 'textarea',
			'raw_attributes' => array(
				'std'            => $ssh_private_key,
				'desc'           => __( 'The private key corresponding to your root user public key.', 'wpcd' ),
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'ssh_private_key',
				'class'          => 'wpcd_app_pass_toggle',
			),
		);

		$actions['server-ssh-keys-private-key-password'] = array(
			'label'          => __( 'Private Key Password', 'wpcd' ),
			'type'           => 'password',
			'raw_attributes' => array(
				'std'            => $ssh_private_key_password,
				'desc'           => __( 'The password for your private key - this is optional and can be left blank if your private key does not have a password.', 'wpcd' ),
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'ssh_private_key_password',
				'class'          => 'wpcd_app_pass_toggle',
			),
		);

		$actions['server-ssh-keys-public-key'] = array(
			'label'          => __( 'Public Key', 'wpcd' ),
			'type'           => 'textarea',
			'raw_attributes' => array(
				'std'            => $ssh_public_key,
				'desc'           => __( 'The public key data for the private key entered above.  This is complete optional - we do not use it to connect to the server since the server should already have this information for your root user. However, sometimes it\'s good to see this data.', 'wpcd' ),
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'ssh_public_key',
			),
		);

		$actions['server-ssh-keys-notes'] = array(
			'label'          => __( 'Notes', 'wpcd' ),
			'type'           => 'textarea',
			'raw_attributes' => array(
				'std'            => $ssh_key_notes,
				'desc'           => __( 'Optional - your notes about this key.', 'wpcd' ),
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'ssh_key_notes',
			),
		);

		$actions['server-ssh-keys-save'] = array(
			'label'          => '',
			'type'           => 'button',
			'raw_attributes' => array(
				'std'              => __( 'Save', 'wcpcd' ),
				// fields that contribute data for this action.
				'data-wpcd-fields' => json_encode( array( '#wpcd_app_action_server-ssh-keys-root-user-name', '#wpcd_app_action_server-ssh-keys-private-key', '#wpcd_app_action_server-ssh-keys-private-key-password', '#wpcd_app_action_server-ssh-keys-public-key', '#wpcd_app_action_server-ssh-keys-notes' ) ),
				'columns'          => 2,
			),
		);

		$actions['server-ssh-keys-copy'] = array(
			'label'          => '',
			'type'           => 'button',
			'raw_attributes' => array(
				'std'     => __( 'Copy From Settings', 'wcpcd' ),
				'columns' => 2,
			),
		);

		$actions['server-ssh-keys-remove'] = array(
			'label'          => '',
			'type'           => 'button',
			'raw_attributes' => array(
				'std'     => __( 'Remove', 'wcpcd' ),
				'tooltip' => __( 'Remove this key information from this server - we will revert to using the data from the settings screen to login.', 'wcpcd' ),
				'columns' => 2,
			),
		);

		return $actions;

	}

	/**
	 * Performs the SERVER SSH KEYS action.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean  success/failure/other
	 */
	private function do_server_keys_actions( $id, $action ) {

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Grab the arguments sent from the front-end JS */
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		switch ( $action ) {
			case 'server-ssh-keys-save':
				$sanitized_private_key          = wp_kses( $_POST['ssh-private-key'], array(), array() ); // We have to grab the individual special item from $_POST (added by wpcd-wpapp-admin-common.js).
				$sanitized_public_key           = wp_kses( $_POST['ssh-public-key'], array(), array() );  // We have to grab the individual special item from $_POST (added by wpcd-wpapp-admin-common.js).
				$sanitized_private_key_password = wp_kses( sanitize_text_field( $_POST['ssh-private-key-password'] ), array(), array() );// We have to grab the individual special item from $_POST (added by wpcd-wpapp-admin-common.js).

				update_post_meta( $id, 'wpcd_server_ssh_root_user', $args['ssh_root_user'] );
				update_post_meta( $id, 'wpcd_server_ssh_private_key_password', self::encrypt( $sanitized_private_key_password ) );
				update_post_meta( $id, 'wpcd_server_ssh_private_key', self::encrypt( $sanitized_private_key ) );
				update_post_meta( $id, 'wpcd_server_ssh_public_key', $sanitized_public_key );
				update_post_meta( $id, 'wpcd_server_ssh_key_notes', $args['ssh_key_notes'] );

				$result = new \WP_error( __( 'Key information saved.', 'wpcd' ) );
				break;

			case 'server-ssh-keys-copy':
				// Copy ssh key settings from provider to this server record.
				// Start by getting the data from options.
				$root_user   = WPCD()->get_provider_api( $instance['provider'] )->get_root_user();
				$passwd      = wpcd_get_option( 'vpn_' . $instance['provider'] . '_sshkey_passwd' ); // this will be encrypted but no need to decrypt since we're just copying as-is.
				$private_key = wpcd_get_option( 'vpn_' . $instance['provider'] . '_sshkey' );  // this will be encrypted but no need to decrypt since we're just copying as-is.
				$key_notes   = wpcd_get_option( 'vpn_' . $instance['provider'] . '_sshkeynotes' );

				// Add to the server record...
				update_post_meta( $id, 'wpcd_server_ssh_root_user', $root_user );
				update_post_meta( $id, 'wpcd_server_ssh_private_key_password', $passwd );
				update_post_meta( $id, 'wpcd_server_ssh_private_key', $private_key );
				update_post_meta( $id, 'wpcd_server_ssh_key_notes', $key_notes );

				$result = new \WP_error( __( 'Key information copied from settings.', 'wpcd' ) );
				break;

			case 'server-ssh-keys-remove':
				delete_post_meta( $id, 'wpcd_server_ssh_root_user' );
				delete_post_meta( $id, 'wpcd_server_ssh_private_key_password' );
				delete_post_meta( $id, 'wpcd_server_ssh_private_key' );
				delete_post_meta( $id, 'wpcd_server_ssh_public_key' );
				delete_post_meta( $id, 'wpcd_server_ssh_key_notes' );

				$result = new \WP_error( __( 'Key information deleted.', 'wpcd' ) );
				break;

		}

		return $result;

	}

}

new WPCD_WORDPRESS_TABS_KEYS();
