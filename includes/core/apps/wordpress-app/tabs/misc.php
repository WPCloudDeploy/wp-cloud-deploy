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
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// Allow the disable site action to be triggered via an action hook.  Will primarily be used by the woocommerce add-on and REST API.
		add_action( 'wpcd_wordpress-app_do_toggle_site_status', array( $this, 'toggle_site_status_action' ), 10, 3 );

		// Add bulk action option to the site list to bulk delete sites.
		add_filter( 'bulk_actions-edit-wpcd_app', array( $this, 'wpcd_add_new_bulk_actions_site' ) );

		// Action hook to handle bulk actions for site.
		add_filter( 'handle_bulk_actions-edit-wpcd_app', array( $this, 'wpcd_bulk_action_handler_sites' ), 10, 3 );

		// This action hook is only used by the WooCommerce sell wp sites functionality to trigger deletion of a site.
		add_action( 'wpcd_app_delete_wp_site', array( $this, 'remove_site_via_action_hook' ), 10, 2 );

		/* Pending Logs Background Task: Delete site */
		add_action( 'wpcd_pending_log_delete_wp_site', array( $this, 'pending_log_remove_site_and_leave_backups' ), 10, 3 );
		add_action( 'wpcd_pending_log_delete_wp_site_and_backups', array( $this, 'pending_log_remove_site_and_backups' ), 10, 3 );
	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'misc';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_misc_tab';
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
				'label' => __( 'Misc', 'wpcd' ),
				'icon'  => 'fad fa-random',
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
			/* Translators: %1: String representing action; %2: Filename where code is being executed; %3: Post id for site or server; %4: WordPress User id */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'remove', 'remove_full', 'site-status', 'admin-lock-status', 'basic-auth-status', 'wplogin-basic-auth-status', 'https-redirect-misc' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				/* Translators: %1: String representing action; %2: Filename where code is being executed; %3: Post id for site or server; %4: WordPress User id */
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'remove':
				case 'remove_full':
					// remove site - check if user has permission - note that there are TWO checks here - a general one for an app and one for the site itself.
					if ( ! wpcd_can_current_user_delete_app( $id ) ) {
						$result = new \WP_Error( __( 'You don\'t have permission To remove an App.', 'wpcd' ) );
						break;
					}
					if ( ! $this->wpcd_user_can_remove_wp_site( $id ) ) {
						$result = new \WP_Error( __( 'You don\'t have permission To remove a WordPress Site. If you are seeing this message, it probably means you have the ability to remove an app but not delete a site. Plese check with your admin to enable the permissions needed to delete a WordPress site.', 'wpcd' ) );
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
					$this->toggle_site_status_action( $id, $action );
					break;
				case 'admin-lock-status':
					// toggle admin lock.
					$current_status = $this->get_admin_lock_status( $id );
					// we don't have to perform any server operations, just update a meta.
					$new_status = true === $current_status ? 'off' : 'on';
					$this->set_admin_lock_status( $id, $new_status );
					$result = array( 'refresh' => 'yes' );
					break;
				case 'basic-auth-status':
					// enable/disable basic authentication.
					$current_status = $this->get_site_http_auth_status( $id );
					$result = $this->toggle_basic_auth( $id, 'on' === $current_status ? 'disable_auth' : 'enable_auth' );
					if ( ! is_wp_error( $result ) ) {
						$new_status = 'on' === $current_status ? 'off' : 'on';
						$this->set_site_http_auth_status( $id, $new_status );
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'wplogin-basic-auth-status':
					// enable/disable basic authentication on wp-login page.
					$current_status = $this->get_wplogin_http_auth_status( $id );
					if ( empty( $current_status ) ) {
						$current_status = 'off';
					}
					$result = $this->toggle_wplogin_basic_auth( $id, 'on' === $current_status ? 'wpadmin_disable_auth' : 'wpadmin_enable_auth' );
					if ( ! is_wp_error( $result ) ) {
						$new_status = 'on' === $current_status ? 'off' : 'on';
						$this->set_wplogin_http_auth_status( $id, $new_status );
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'https-redirect-misc':
					// enable/disable https redirection.
					$current_status = get_post_meta( $id, 'wpapp_misc_https_redirect', true );
					if ( empty( $current_status ) ) {
						$current_status = 'off';
					}
					$result = $this->toggle_https( $id, 'on' === $current_status ? 'disable_https_redir' : 'enable_https_redir' );
					if ( ! is_wp_error( $result ) ) {
						update_post_meta( $id, 'wpapp_misc_https_redirect', 'on' === $current_status ? 'off' : 'on' );
						$result = array( 'refresh' => 'yes' );
					}
					break;

			}
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

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $this->get_disabled_header_field( '' ), $this->get_site_status_action_fields( $id ) );
		}

		// Site is not disabled so show all fields.
		return array_merge(
			$this->get_initial_credentials( $id ),
			$this->get_basic_auth_action_fields( $id ),
			$this->get_wp_login_basic_auth_action_fields( $id ),
			$this->get_site_status_action_fields( $id ),
			$this->get_admin_lock_action_fields( $id ),
			$this->get_delete_site_action_fields( $id ),
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

		// Option to activate or deactivate site.
		switch ( $status ) {
			case 'on':
			case 'off':
				$actions['site-status'] = array(
					'label'          => '',
					'std'            => 'on' === $status,
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => 'on' === $status,
						'desc'                => 'on' === $status ? __( 'Click to deactivate the site without removing data', 'wpcd' ) : __( 'Click to reactivate the site', 'wpcd' ),
						'confirmation_prompt' => $confirmation_prompt,
					),
					'type'           => 'switch',
				);
				break;
		}

		return $actions;

	}

	/**
	 * Gets the fields for the admin lock section to be shown in the MISC tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_admin_lock_action_fields( $id ) {

		// This section can only be see by an admin.
		if ( ! wpcd_is_admin() ) {
			return array();
		}

		$actions = array();

		$actions['site-admin-lock-header'] = array(
			'label'          => __( 'Admin Lock', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Lock all tabs on this site so that only admins can perform actions on them.', 'wpcd' ),
			),
		);

		/* What is the current status of the site? */
		$status = $this->get_admin_lock_status( $id );

		/* Set the confirmation prompt based on the the current status of the site */
		$confirmation_prompt = '';
		if ( true === $status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable the admin lock for this site?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable the admin lock for this site?', 'wpcd' );
		}

		// Option to activate or deactivate site.
		switch ( $status ) {
			case true:
			case false:
				$actions['admin-lock-status'] = array(
					'label'          => '',
					'std'            => 'on' === $status,
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => true === $status,
						'desc'                => true === $status ? __( 'Click to enable tabs for non-admins', 'wpcd' ) : __( 'Click to disable tabs for non-admins', 'wpcd' ),
						'confirmation_prompt' => $confirmation_prompt,
					),
					'type'           => 'switch',
				);
				break;
		}

		return $actions;

	}

	/**
	 * Gets the fields for the site deletion section to be shown in the MISC tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_delete_site_action_fields( $id ) {

		$actions = array();

		// Option to delete site.
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
				'label'          => '',
				'type'           => 'button',
				'raw_attributes' => array(
					'std'                 => __( 'Remove', 'wpcd' ),
					'desc'                => __( 'Delete site and data - this action is not reversible!', 'wpcd' ),
					'confirmation_prompt' => __( 'Are you sure you would like to delete this site and data? This action is NOT reversible!', 'wpcd' ),
				),
			);

			$actions['remove_full'] = array(
				'label'          => '',
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
		$basic_auth_status = $this->get_site_http_auth_status( $id ); 

		/* What is the current basic authentication status of the wplogin page? */	
		$wplogin_basic_auth_status = $this->get_wplogin_http_auth_status( $id ); 

		/* If basic authentication is enabled for the login page, we can't enable it for the whole site. */
		if ( 'on' === $wplogin_basic_auth_status ) {
			$actions['pw-auth-header'] = array(
				'label'          => __( 'Password Protect All Pages With HTTP Basic Authentication', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Basic authentication is already turned on for the wp-login page.  You will need to turn that off before you are able to use this option to protect the entire site.', 'wpcd' ),
				),
			);
			return $actions;
		}

		/* Set the text of the confirmation prompt based on the current basic authentication status of the site */
		$confirmation_prompt = '';
		if ( 'on' === $basic_auth_status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable password protection for this site?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable password protection for this site?', 'wpcd' );
		}

		$actions['pw-auth-header'] = array(
			'label'          => __( 'Password Protect All Pages With HTTP Basic Authentication', 'wpcd' ),
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
				'disabled'       => 'off' === $basic_auth_status ? false : true,
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
				'disabled'       => 'off' === $basic_auth_status ? false : true,
				'size'           => 60,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'basic_auth_pass',
			),
		);

		switch ( $basic_auth_status ) {
			case 'on':
			case 'off':
				$actions['basic-auth-status'] = array(
					'label'          => '',
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => 'on' === $basic_auth_status,
						'desc'                => __( 'Add or remove password protection on your site', 'wpcd' ),
						'confirmation_prompt' => $confirmation_prompt,                      // fields that contribute data for this action.
						'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_basic-auth-user', '#wpcd_app_action_basic-auth-pw' ) ),
					),
					'type'           => 'switch',
				);
				break;
		}

		return $actions;

	}

	/**
	 * Gets the fields for the WPLOGIN page basic authentication section to be shown in the MISC tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_wp_login_basic_auth_action_fields( $id ) {

		$actions = array();

		/* What is the current basic authentication status of the wplogin page? */
		$wplogin_basic_auth_status = $this->get_wplogin_http_auth_status( $id ); 

		/* What is the current basic authentication status of the site? */
		$basic_auth_status = $this->get_site_http_auth_status( $id ); 

		/* If basic authentication is turned on for the whole site, disable this option and return right away. */
		if ( 'on' === $basic_auth_status ) {
			$actions['wplogin-pw-auth-header'] = array(
				'label'          => __( 'Password Protect WPLOGIN With HTTP Basic Authentication', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'All pages on this site are already fully password protected with a site-wide HTTP Authentication directive.', 'wpcd' ),
				),
			);
			return $actions;
		}

		/* Set the text of the confirmation prompt based on the current basic authentication status of the site */
		$confirmation_prompt = '';
		if ( 'on' === $wplogin_basic_auth_status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable password protection for the wp-login page for this site?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable password protection for the wp-login page for this site?', 'wpcd' );
		}

		$desc  = __( 'This action places an http password popup in front of your WPLOGIN page.  This is useful because it stops bad login attempts at the webserver, before it hits WordPress.', 'wpcd' );
		$desc .= '<br />' . __( 'If this is already turned on and you have forgotten your password, turn it off, fill in the user and password fields with data you know and turn it back on.', 'wpcd' );
		$desc .= '<br />' . __( 'Important Note: Turn this off before COPYING, CLONING, STAGING or MOVING the site!', 'wpcd' );

		$actions['wplogin-pw-auth-header'] = array(
			'label'          => __( 'Password Protect WPLOGIN With HTTP Basic Authentication', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['wplogin-basic-auth-user'] = array(
			'label'          => __( 'User', 'wpcd' ),
			'desc'           => __( 'User name to use when basic authentication is turned on', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'disabled'       => 'off' === $wplogin_basic_auth_status ? false : true,
				'size'           => 60,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'wplogin_basic_auth_user',
			),

		);

		$actions['wplogin-basic-auth-pw'] = array(
			'label'          => __( 'Password', 'wpcd' ),
			'desc'           => __( 'Password to use when basic authentication is turned on', 'wpcd' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'disabled'       => 'off' === $wplogin_basic_auth_status ? false : true,
				'size'           => 60,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'wplogin_basic_auth_pass',
			),
		);

		switch ( $wplogin_basic_auth_status ) {
			case 'on':
			case 'off':
				$actions['wplogin-basic-auth-status'] = array(
					'label'          => '',
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => 'on' === $wplogin_basic_auth_status,
						'desc'                => __( 'Add or remove password protection for the wp-login page', 'wpcd' ),
						'confirmation_prompt' => $confirmation_prompt,                      // fields that contribute data for this action.
						'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_wplogin-basic-auth-user', '#wpcd_app_action_wplogin-basic-auth-pw' ) ),
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
					'std'            => 'off' === $status,
					'raw_attributes' => array(
						'on_label'            => __( 'Enabled', 'wpcd' ),
						'off_label'           => __( 'Disabled', 'wpcd' ),
						'std'                 => 'on' === $status,
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
	 * Helper function to set some meta values before and after toggling site status.
	 *
	 * Can be called directly or by an action hook.
	 *
	 * Action hook: wpcd_wordpress-app_do_toggle_site_status (Optional).
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed  - always 'site-status'.
	 * @param string $new_status The new status to force the site into.  If empty it will toggle the existing status.
	 *
	 * @return string|WP_Error
	 */
	public function toggle_site_status_action( $id, $action, $new_status = '' ) {

		$result = '';

		if ( empty( $new_status)) {
			// The parameter $new_status is empty so we'll just toggle the existings status.
			$current_status = $this->site_status( $id );
			if ( empty( $current_status ) ) {
				$current_status = 'on';
			}
		} else {
			// The parameter $new_status is not empty so we'll force the $current_status var to the opposite of it and then toggle.
			switch ( $new_status ) {
				case 'on':
					$current_status = 'off';
					break;
				case 'off':
					$current_status = 'on';
					break;
				default:
					$current_status = 'on';
					break;
			}
		}

		$result = $this->toggle_site_status( $id, 'on' === $current_status ? 'disable' : 'enable' );
		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $id, 'wpapp_site_status', 'on' === $current_status ? 'off' : 'on' );
			$result = array( 'refresh' => 'yes' );
		}

		return $result;  // Will not matter in an action hook.

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
			/* Translators: %s is a string representing the action we're trying to perform. */
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

		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'disable_remove_site.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
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
	public function remove_site_via_action_hook( $id, $action ) {
		$this->remove_site( $id, $action );
	}

	/**
	 * Remove site and associated backups on disk via an action hook call from pending logs.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_pending_log_delete_wp_site
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site involved in this action.
	 * @param array $args       All the data needed to handle this action.
	 */
	public function pending_log_remove_site_and_leave_backups( $task_id, $site_id, $args ) {
		$this->pending_log_remove_site( $task_id, $site_id, $args, 'remove' );
	}

	/**
	 * Remove site via an action hook call from pending logs.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_pending_log_delete_wp_site_and_backups
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site involved in this action.
	 * @param array $args       All the data needed to handle this action.
	 */
	public function pending_log_remove_site_and_backups( $task_id, $site_id, $args ) {
		$this->pending_log_remove_site( $task_id, $site_id, $args, 'remove_full' );
	}

	/**
	 * Helper function to remove site when certain other delete site functions are triggered
	 * via an action hook call from pending logs.
	 *
	 * @param int    $task_id    Id of pending task that is firing this thing...
	 * @param int    $site_id    Id of site involved in this action.
	 * @param array  $args       All the data needed to handle this action.
	 * @param string $action    What kind of site delete are we doing?  Options are 'remove' or 'remove_full'.
	 */
	public function pending_log_remove_site( $task_id, $site_id, $args, $action ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Attempt to remove the site */
		$result = $this->remove_site( $site_id, $action );

		$task_status = 'complete';  // Assume success.

		if ( is_array( $result ) ) {
			// Some processes return an array array with a success message. This one does not so there's nothing to do here.
			// We'll just reset the $task_status to complete (which is the value it was initialized with) to avoid complaints by PHPcs about an empty if statement.
			$task_status = 'complete';
		} else {
			if ( false === (bool) $result || is_wp_error( $result ) ) {
				$task_status = 'failed';
			}
		}

		WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, $task_status );

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
			/* Translators: %s is a string representing the action we're trying to perform. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Fire yet another action hook so that other tasks can be completed before site is removed. Eg: prepare the list of sFTP users to be removed by adding it to pending logs. */
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

		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'disable_remove_site.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// Attempt to delete DNS for the domain...
			WPCD_DNS()->delete_dns_for_domain( get_post_meta( $id, 'wpapp_original_domain', true ) );
			WPCD_DNS()->delete_dns_for_domain( get_post_meta( $id, 'wpapp_domain', true ) );

			// If it's a staging site then lets remove the companion metas from the original site.
			if ( ! empty( $this->get_live_id_for_staging_site( $id ) ) ) {
				$live_id = $this->get_live_id_for_staging_site( $id );
				update_post_meta( $live_id, 'wpapp_staging_domain', '' );
				update_post_meta( $live_id, 'wpapp_staging_domain_id', '' );
			}
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
			/* Translators: %s is a string representing the action we're trying to perform. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

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

		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'basic_auth_misc.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		return $success;

	}

	/**
	 * Enable/disable basic authentication for the wplogin url.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'wpadmin_enable_auth' or 'wpadmin_disable_auth' (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function toggle_wplogin_basic_auth( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is a string representing the action we're trying to perform. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Check to make sure that both a user id and password is offered if the action is to turn on authentication.
		if ( 'wpadmin_enable_auth' === $action ) {
			if ( ! $args['wplogin_basic_auth_user'] ) {
				return new \WP_Error( __( 'The user cannot be blank if you would like to turn on basic authentication for this site.', 'wpcd' ) );
			}
			if ( ! $args['wplogin_basic_auth_pass'] ) {
				return new \WP_Error( __( 'The password cannot be blank if you would like to turn on basic authentication for this site.', 'wpcd' ) );
			}
		}

		// Special sanitization for user id and passwords which are going to be passed to the shell scripts.
		if ( isset( $args['wplogin_basic_auth_user'] ) ) {
			$args['wplogin_basic_auth_user'] = escapeshellarg( $args['wplogin_basic_auth_user'] );
		}
		if ( isset( $args['wplogin_basic_auth_pass'] ) ) {
			$args['wplogin_basic_auth_pass'] = escapeshellarg( $args['wplogin_basic_auth_pass'] );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'basic_auth_wplogin_misc.txt',
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

		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'basic_auth_wplogin_misc.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
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
			/* Translators: %s is a string representing the action we're trying to perform. */
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

		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'toggle_https_misc.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		return $success;

	}

	/**
	 * Add new bulk options in site list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function wpcd_add_new_bulk_actions_site( $bulk_array ) {

		// Bail if option isn't enabled.
		if ( ! ( (bool) wpcd_get_option( 'wordpress_app_enable_bulk_site_delete_on_full_app_list' ) ) ) {
			return $bulk_array;
		}

		if ( wpcd_is_admin() ) {
			$bulk_array['wpcd_sites_bulk_delete_sites']             = __( 'Delete Sites From Server', 'wpcd' );
			$bulk_array['wpcd_sites_bulk_delete_sites_and_backups'] = __( 'Delete Sites and Backups From Server', 'wpcd' );
			return $bulk_array;
		}

	}

	/**
	 * Returns an array of actions that is valid for the bulk actions menu.
	 * It's basically the same as the get_valid_actions() function above but
	 * with the actions prefixed by "wpcd_sites_".
	 */
	public function get_valid_bulk_actions() {
		return array( 'wpcd_sites_bulk_delete_sites', 'wpcd_sites_bulk_delete_sites_and_backups' );
	}

	/**
	 * Handle bulk actions for sites.
	 *
	 * @param string $redirect_url  redirect url.
	 * @param string $action        bulk action slug/id - this is not the WPCD action key.
	 * @param array  $post_ids      all post ids.
	 */
	public function wpcd_bulk_action_handler_sites( $redirect_url, $action, $post_ids ) {
		// Let's remove query args first for redirect url.
		$redirect_url = remove_query_arg( array( 'wpcd_update_themes_and_plugins' ), $redirect_url );

		// Lets make sure we're an admin otherwise return an error.
		if ( ! wpcd_is_admin() ) {
			do_action( 'wpcd_log_error', 'Someone attempted to run a function that required admin privileges.', 'security', __FILE__, __LINE__ );

			// Show error message to user at the top of the admin list as a dismissible notice.
			wpcd_global_add_admin_notice( __( 'You attempted to run a function that requires admin privileges.', 'wpcd' ), 'error' );

			return $redirect_url;
		}

		// Verify that the bulk action is one we want to handle here.
		if ( in_array( $action, $this->get_valid_bulk_actions(), true ) ) {

			// If we have sites to be deleted then add them to the pending log table.
			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $app_id ) {
					$task_name = '';
					$task_desc = '';
					switch ( $action ) {
						case 'wpcd_sites_bulk_delete_sites':
							$args['action_hook'] = 'wpcd_pending_log_delete_wp_site';
							$args['action']      = 'remove';
							$task_name           = 'delete-site';
							$task_desc           = __( 'Bulk Action: Waiting to delete site.', 'wpcd' );
							break;
						case 'wpcd_sites_bulk_delete_sites_and_backups':
							$args['action_hook'] = 'wpcd_pending_log_delete_wp_site_and_backups';
							$args['action']      = 'remove_full';
							$task_name           = 'delete-site-and-backups';
							$task_desc           = __( 'Bulk Action: Waiting to delete site and associated backups.', 'wpcd' );
							break;

					}
					WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, $task_name, $app_id, $args, 'ready', $app_id, $task_desc );
				}

				// Add message to be displayed in admin header.
				if ( 1 === count( $post_ids ) ) {
					/* Translators: %s is the number of sites that have been scheduled for deletion. */
					wpcd_global_add_admin_notice( sprintf( __( '%s site has been scheduled for deletion. You can view the progress in the PENDING TASKS screen.', 'wpcd' ), count( $post_ids ) ), 'success' );
				} else {
					/* Translators: %s is the number of sites that have been scheduled for deletion. */
					wpcd_global_add_admin_notice( sprintf( __( '%s sites have been scheduled for deletion. You can view the progress in the PENDING TASKS screen.', 'wpcd' ), count( $post_ids ) ), 'success' );
				}
			}
		}

		return $redirect_url;
	}
}

new WPCD_WORDPRESS_TABS_MISC();
