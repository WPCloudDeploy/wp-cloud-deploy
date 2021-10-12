<?php
/**
 * Misc tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_MISC
 */
class WPCD_WORDPRESS_TABS_MISC extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_MISC constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// This action hook is only used by the WooCommerce sell wp sites functionality to trigger deletion of a site.
		add_action( 'wpcd_app_delete_wp_site', array( $this, 'remove_site_wc' ), 10, 2 );
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['misc'] = array(
			'label' => __( 'Misc', 'wpcd' ),
			'icon'  => 'fad fa-random',
		);
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the MISC tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {
		return $this->get_fields_for_tab( $fields, $id, 'misc' );
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
			case 'remove':
			case 'remove_full':
				// remove site - check if user has permission - note that there are TWO checks here - a general one for an app and one for the site itself.
				if ( ! wpcd_can_current_user_delete_app( $id ) ) {
					$result = new \WP_Error( __( 'You don\'t have permission To Remove an App.', 'wpcd' ) );
					break;
				}
				if ( ! $this->wpcd_user_can_remove_wp_site( $id ) ) {
					$result = new \WP_Error( __( 'You don\'t have permission To Remove a WordPress Site. If you are seeing this message, it probably means you have the ability to remove an app but not delete a site. Plese check with your admin to enable the permissions needed to delete a WordPress site.', 'wpcd' ) );
					break;
				}
				// remove site.
				$result = $this->remove_site( $id, $action );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'redirect' => 'yes' );
				}
				break;
			case 'site-status':
				// enable/disable site.
				$current_status = $this->site_status( $id );
				if ( empty( $current_status ) ) {
					$current_status = 'on';
				}
				$result = $this->toggle_site_status( $id, $current_status === 'on' ? 'disable' : 'enable' );
				if ( ! is_wp_error( $result ) ) {
					update_post_meta( $id, 'wpapp_site_status', $current_status === 'on' ? 'off' : 'on' );
					$result = array( 'refresh' => 'yes' );
				}
				break;
			case 'basic-auth-status':
				// enable/disable basic authentication.
				$current_status = get_post_meta( $id, 'wpapp_basic_auth_status', true );
				if ( empty( $current_status ) ) {
					$current_status = 'off';
				}
				$result = $this->toggle_basic_auth( $id, $current_status === 'on' ? 'disable_auth' : 'enable_auth' );
				if ( ! is_wp_error( $result ) ) {
					update_post_meta( $id, 'wpapp_basic_auth_status', $current_status === 'on' ? 'off' : 'on' );
					$result = array( 'refresh' => 'yes' );
				}
				break;
			case 'https-redirect-misc':
				// enable/disable https redirection.
				$current_status = get_post_meta( $id, 'wpapp_misc_https_redirect', true );
				if ( empty( $current_status ) ) {
					$current_status = 'off';
				}
				$result = $this->toggle_https( $id, $current_status === 'on' ? 'disable_https_redir' : 'enable_https_redir' );
				if ( ! is_wp_error( $result ) ) {
					update_post_meta( $id, 'wpapp_misc_https_redirect', $current_status === 'on' ? 'off' : 'on' );
					$result = array( 'refresh' => 'yes' );
				}
				break;

		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the MISC tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return array_merge(
			$this->get_initial_credentials( $id ),
			$this->get_basic_auth_action_fields( $id ),
			$this->get_site_status_action_fields( $id ),
			$this->get_https_action_fields( $id )
		);

	}

	/**
	 * Gets the fields for the site status section to be shown in the MISC tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_site_status_action_fields( $id ) {

		$actions = array();

		$actions['site-status-header'] = array(
			'label'          => __( 'Enable/Disable Site', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Temporarily enable or disable your site.  All files and data remain when the site is disabled.', 'wpcd' ),
			),
		);

		/* What is the current status of the site? */
		$status = $this->site_status( $id );

		/* Set the confirmation prompt based on the the current status of the site */
		$confirmation_prompt = '';
		if ( 'on' === $status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable this site?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable this site?', 'wpcd' );
		}

		switch ( $status ) {
			case 'on':
			case 'off':
				$actions['site-status'] = array(
					'label'          => __( 'Site Status', 'wpcd' ),
					'std'            => $status === 'on',
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => $status === 'on',
						'desc'                => __( 'Deactivate the site without removing data', 'wpcd' ),
						'confirmation_prompt' => $confirmation_prompt,
					),
					'type'           => 'switch',
				);
				break;
		}

		$actions['remove-site-header'] = array(
			'label'          => __( 'DANGER ZONE: Remove Site', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Permanently remove your site from the server - all data on the server is deleted.  Offsite backups that you might have made to AWS S3 are not deleted.', 'wpcd' ),
			),
		);

		if ( wpcd_is_app_delete_protected( $id ) ) {

			// Show message indicating that user cannot delete site.
			$actions['remove'] = array(
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => '<h4>' . __( '***You cannot remove this site because deletion protection is turned on. If you would really like to delete this site you can turn off deletion protection in the APP DELETE PROTECTION metabox on the right. ***', 'wpcd' ) . '</h4>',
				),
			);
		} elseif ( ! wpcd_can_current_user_delete_app( $id ) ) {

			// Show message indicating that user cannot delete site.
			$actions['remove'] = array(
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => '<h4>' . __( '***You do not have permissions to delete a site***', 'wpcd' ) . '</h4>',
				),
			);

		} else {

			$actions['remove'] = array(
				'label'          => __( 'Remove Site', 'wpcd' ),
				'type'           => 'button',
				'raw_attributes' => array(
					'std'                 => __( 'Remove', 'wpcd' ),
					'desc'                => __( 'Delete site and data - this action is not reversible!', 'wpcd' ),
					'confirmation_prompt' => __( 'Are you sure you would like to delete this site and data? This action is NOT reversible!', 'wpcd' ),
				),
			);

			$actions['remove_full'] = array(
				'label'          => __( 'Remove Site & Backups', 'wpcd' ),
				'type'           => 'button',
				'raw_attributes' => array(
					'std'                 => __( 'Remove Site & Backups', 'wpcd' ),
					'desc'                => __( 'Delete site,  data & local backups - remote backups will not be removed.  This action is not reversible!', 'wpcd' ),
					'confirmation_prompt' => __( 'Are you sure you would like to delete this site, data and local backups? This action is NOT reversible!', 'wpcd' ),
				),
			);

		}

		return $actions;

	}

	/**
	 * Gets the fields for the basic authentication section to be shown in the MISC tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_basic_auth_action_fields( $id ) {

		$actions = array();

		/* What is the current basic authentication status of the site? */
		$basic_auth_status = get_post_meta( $id, 'wpapp_basic_auth_status', true );
		if ( empty( $basic_auth_status ) ) {
			$basic_auth_status = 'off';
		}

		/* Set the text of the confirmation prompt based on the current basic authentication status of the site */
		$confirmation_prompt = '';
		if ( 'on' === $basic_auth_status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable password protection for this site?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable password protection for this site?', 'wpcd' );
		}

		$actions['pw-auth-header'] = array(
			'label'          => __( 'Password Protection With HTTP Basic Authentication', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Basic authentication places an http password popup in front of your site.  This is useful for staging sites and sites you are not ready to make public yet.<br /> If this is already turned on and you have forgotten your password, turn it off, fill in the user and password fields with data you know and turn it back on.', 'wpcd' ),
			),
		);

		$actions['basic-auth-user'] = array(
			'label'          => __( 'User', 'wpcd' ),
			'desc'           => __( 'User name to use when basic authentication is turned on', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'size'           => 60,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'basic_auth_user',
			),

		);

		$actions['basic-auth-pw'] = array(
			'label'          => __( 'Password', 'wpcd' ),
			'desc'           => __( 'Password to use when basic authentication is turned on', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'size'           => 60,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'basic_auth_pass',
			),
		);

		switch ( $basic_auth_status ) {
			case 'on':
			case 'off':
				$actions['basic-auth-status'] = array(
					'label'          => __( 'Basic Authentication', 'wpcd' ),
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => $basic_auth_status === 'on',
						'desc'                => __( 'Add or remove password protection on your site', 'wpcd' ),
						'confirmation_prompt' => $confirmation_prompt,                      // fields that contribute data for this action.
						'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_basic-auth-user', '#wpcd_app_action_basic-auth-pw' ) ),
					),
					'type'           => 'switch',
				);
				break;
		}

		return $actions;

	}

	/**
	 * Gets the initial wp user id and password when the site was initially set up..
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_initial_credentials( $id ) {

		$actions = array();

		$uid = get_post_meta( $id, 'wpapp_user', true );
		$pw  = $this->decrypt( get_post_meta( $id, 'wpapp_password', true ) );

		$actions['initial-credentials-header'] = array(
			'label'          => __( 'Initial WordPress Credentials', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Here are the credentials that were initially set up for this site. You can use them to log into the site for the first time.  We strongly recommend that you change them after you login.', 'wpcd' ),
			),
		);

		$actions['initial-credentials-uid'] = array(
			'label'          => __( 'User Id', 'wpcd' ),
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std' => $uid,
			),
		);

		$actions['initial-credentials-pw'] = array(
			'label'          => __( 'Password', 'wpcd' ),
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std' => $pw,
			),
		);

		return $actions;

	}

	/**
	 * Gets the fields for redirection http to https to be shown in the MISC tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_https_action_fields( $id ) {

		$actions = array();

		$actions['https-redirection-header'] = array(
			'label'          => __( 'Enable/Disable https', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Under normal circumstances you should not need to use this option. Instead, use the actions on the SSL tab. <br /> But, if for some reason you need to manually force https or disable https you can use this option.', 'wpcd' ),
			),
		);

		/* What is the current status of https redirect on the site? */
		$status = get_post_meta( $id, 'wpapp_misc_https_redirect', true );
		if ( empty( $status ) ) {
			$status = 'off';
		}

		/* Set the confirmation prompt based on the the current status of this flag */
		$confirmation_prompt = '';
		if ( 'on' === $status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable https redirect?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable https?', 'wpcd' );
		}

		switch ( $status ) {
			case 'on':
			case 'off':
				$actions['https-redirect-misc'] = array(
					'label'          => __( 'Redirect Site to HTTPS', 'wpcd' ),
					'std'            => $status === 'off',
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => $status === 'on',
						'desc'                => __( 'Enable or disable https redirect. This option does NOT automatically issue or revoke SSL certificates! <br />It will, however, reinstall an existing LetsEncrypt certificate if one exists. <br />If you want to automatically create SSL certificates use the actions available on the SSL tab instead.', 'wpcd' ),
						'confirmation_prompt' => $confirmation_prompt,
					),
					'type'           => 'switch',
				);
				break;
		}

		return $actions;

	}


	/**
	 * Enable/disable site.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'enable' or 'disable'  (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function toggle_site_status( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'disable_remove_site.txt',
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

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'disable_remove_site.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		return $success;

	}


	/**
	 * Remove site via an action hook.
	 *
	 * Action Hook: wpcd_app_delete_wp_site
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'remove' or 'remove_full' (this matches the string required in the bash scripts).
	 */
	public function remove_site_wc( $id, $action ) {
		$this->remove_site( $id, $action );
	}

	/**
	 * Remove site.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'remove' or 'remove_full' (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function remove_site( $id, $action ) {

		/* Fire action hook so that other tasks can be completed before site is removed. */
		do_action( 'wpcd_before_remove_site_action_precheck', $id, $action );

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Fire yet another action hook so that other tasks can be completed before site is removed. */
		do_action( 'wpcd_before_remove_site_action', $id, $action );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'disable_remove_site.txt',
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

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'disable_remove_site.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// Attempt to delete DNS for the domain...
			WPCD_DNS()->delete_dns_for_domain( get_post_meta( $id, 'wpapp_original_domain', true ) );
			WPCD_DNS()->delete_dns_for_domain( get_post_meta( $id, 'wpapp_domain', true ) );
		}

		// now force delete the post.
		wp_delete_post( $id, true );

		return $success;

	}

	/**
	 * Enable/disable basic authentication.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'enable_auth' or 'disable_auth' (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function toggle_basic_auth( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = wp_parse_args( sanitize_text_field( $_POST['params'] ) );

		// Check to make sure that both a user id and password is offered if the action is to turn on authentication.
		if ( 'enable_auth' === $action ) {
			if ( ! $args['basic_auth_user'] ) {
				return new \WP_Error( __( 'The user cannot be blank if you would like to turn on basic authentication for this site.', 'wpcd' ) );
			}
			if ( ! $args['basic_auth_pass'] ) {
				return new \WP_Error( __( 'The password cannot be blank if you would like to turn on basic authentication for this site.', 'wpcd' ) );
			}
		}

		// Special sanitization for user id and passwords which are going to be passed to the shell scripts.
		if ( isset( $args['basic_auth_user'] ) ) {
			$args['basic_auth_user'] = escapeshellarg( $args['basic_auth_user'] );
		}
		if ( isset( $args['basic_auth_pass'] ) ) {
			$args['basic_auth_pass'] = escapeshellarg( $args['basic_auth_pass'] );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'basic_auth_misc.txt',
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
		$success = $this->is_ssh_successful( $result, 'basic_auth_misc.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		return $success;

	}

	/**
	 * Enable/disable https redirection
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'enable_auth' or 'disable_auth' (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function toggle_https( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'toggle_https_misc.txt',
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

		$success = $this->is_ssh_successful( $result, 'toggle_https_misc.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		return $success;

	}
}

new WPCD_WORDPRESS_TABS_MISC();
