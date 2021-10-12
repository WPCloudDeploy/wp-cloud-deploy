<?php
/**
 * General Tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_GENERAL
 */
class WPCD_WORDPRESS_TABS_GENERAL extends WPCD_WORDPRESS_TABS {

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
		$tabs['general'] = array(
			'label' => __( 'General', 'wpcd' ),
			'icon'  => 'fad fa-raindrops',
		);
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the GENERAL tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {

		return $this->get_fields_for_tab( $fields, $id, 'general' );

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
			case 'sample-action':
				break;
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the GENERAL tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_general_fields( $id );

	}

	/**
	 * Gets the fields GENERAL tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_general_fields( $id ) {

		$actions = array();

		$actions['general-welcome-header'] = array(
			'label'          => __( 'Welcome', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Sweet - Another WordPress Site!', 'wpcd' ),
			),
		);

		/* set up some css stubs */
		$ok_span          = '<span class="dashicons dashicons-yes"></span>';
		$not_ok_span      = '<span class="dashicons dashicons-no-alt"></span>';
		$neutral_span     = '<span class="dashicons dashicons-arrow-right"></span>';
		$user_span        = '<span class="dashicons dashicons-businesswoman"></span>';
		$credentials_span = '<span class="dashicons dashicons-nametag"></span>';

		/* Set up an HTML string with the current domain configuration */
		$config_desc = '';
		$site_status = $this->site_status( $id );
		if ( 'on' === $site_status || empty( $site_status ) ) {

			// Basic status - we're here so site is enabled.
			$config_desc .= $ok_span . __( 'Your site seems to be ready!', 'wpcd' );

			// SSL Status.
			$config_desc .= '<br />';
			if ( 'on' === get_post_meta( $id, 'wpapp_ssl_status', true ) ) {
				$ssl          = true;
				$config_desc .= $ok_span . __( 'Your site seems to be secured with SSL.', 'wpcd' );
			} else {
				$ssl          = false;
				$config_desc .= $not_ok_span . __( 'Your site is not secured with SSL. You can turn this on under the SSL tab.', 'wpcd' );
			}

			// Backups.
			$config_desc .= '<br />';
			if ( ! empty( get_post_meta( $id, 'wpapp_backups_list', true ) ) ) {
				$config_desc .= $ok_span . __( 'At least one backup has been completed - good job.', 'wpcd' );
			} else {
				$config_desc .= $not_ok_span . __( 'Your site has not been backed up yet. Setup backups under the BACKUP AND RESTORE tab.', 'wpcd' );
			}

			// sftp users.
			$config_desc .= '<br />';
			if ( ! empty( wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_sftp_users', true ) ) ) ) {
				$config_desc .= $user_span . __( 'There is at least one sFTP user configured for this site.', 'wpcd' );
			} else {
				$config_desc .= $user_span . __( 'No sFTP users have been set up yet. If you need one you can do so under the sFTP tab.', 'wpcd' );
			}

			// Credentials.
			$config_desc .= '<br />';
			$config_desc .= $credentials_span . __( 'Looking for your credentials to log into wp-admin for the first time?  Click on the MISC tab.', 'wpcd' );

			// Create wp-admin and front-end site links.
			// *** Disabled in V 2.10.0.

			/*
			$config_desc .= '<br /><br />';
			$config_desc .= $this->get_formatted_wpadmin_link( $id );
			$config_desc .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' ;
			$config_desc .= $this->get_formatted_site_link( $id );
			*/

		} else {

			$config_desc .= $not_ok_span . __( 'Your site seems to be disabled!', 'wpcd' );
			$config_desc .= '<br />';
			$config_desc .= __( 'You can re-enable it in the MISC tab!', 'wpcd' );

		}

		$actions['general-what-you-have'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std' => $config_desc,
			),
			'type'           => 'custom_html',
		);

		return $actions;

	}

}

new WPCD_WORDPRESS_TABS_GENERAL();
