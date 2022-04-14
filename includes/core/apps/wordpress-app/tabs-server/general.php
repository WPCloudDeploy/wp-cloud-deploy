<?php
/**
 * General
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_GENERAL
 */
class WPCD_WORDPRESS_TABS_SERVER_GENERAL extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		/* add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 ); */
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_nontab_fields' ), 9, 2 ); // these fields will go at the top of the metabox, before the tabs start.
		// add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		// add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		// add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.
	}

	/**
	 * Populates the tab name.   - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['general'] = array(
			'label' => __( 'General', 'wpcd' ),
		);
		return $tabs;
	}

	/**
	 * Checks whether or not the user can view the current tab.
	 *
	 * @param int $id The post ID of the server.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		return ( true === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Gets the fields to be shown in the GENERAL tab.   - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs.
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

		return $this->get_fields_for_tab( $fields, $id, 'general' );

	}

	/**
	 * Called when an action needs to be performed on the tab.  - NOT USED HERE / PLACEHOLDER ONLY
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

		switch ( $action ) {
			case 'test':
				// Remove all metas related to background processes that might be "hung" or in-processs.
				$result = new \WP_Error( __( 'Test successful.', 'wpcd' ) );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the GENERAL tab.   - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_general_fields( $id );

	}

	/**
	 * Gets the fields for the GENERAL tab on the server details page.   - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * @param int $id the post id of the server cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_general_fields( $id ) {
		$actions = array();

		$actions['general-welcome-header'] = array(
			'label'          => __( 'Welcome', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Awesome - Another WordPress Server!', 'wpcd' ),
			),
		);

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you want to run this test?', 'wpcd' );

		$actions['test'] = array(
			'label'          => __( 'Test', 'wpcd' ),
			'raw_attributes' => array(
				'std'                 => __( 'Test Button For AJAX', 'wpcd' ),
				'desc'                => __( 'This is a test for AJAX functions inside the Server Details scree.', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the fields that go at the top of the metabox on the server details page.
	 *
	 * @param string $actions actions.
	 * @param int    $id the post id of the server cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_nontab_fields( $actions, $id ) {

		$actions['general-welcome-top-col_1'] = array(
			'name'    => __( 'Server Name', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'wpcd_server_name', true ),
			'columns' => 3,
			'class'   => 'wpcd_server_details_top_row',
		);

		$actions['general-welcome-top-col_2'] = array(
			'name'    => __( 'IPv4', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => WPCD_SERVER()->get_all_ip_addresses_for_display( $id ),
			'columns' => 3,
			'class'   => 'wpcd_server_details_top_row',
		);

		$actions['general-welcome-top-col_3'] = array(
			'name'    => __( 'Provider', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => WPCD()->wpcd_get_cloud_provider_desc( get_post_meta( $id, 'wpcd_server_provider', true ) ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row',
		);

		$actions['general-welcome-top-col_4'] = array(
			'name'    => __( 'Region', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'wpcd_server_region', true ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row',
		);

		$actions['general-welcome-top-col_5'] = array(
			'name'    => __( 'Apps', 'wpcd' ),
			'type'    => 'custom_html',
			'std'     => sprintf( '<a href="%s" target="_blank">%d</a>', esc_url( admin_url( 'edit.php?post_type=wpcd_app&server_id=' . $id ) ), WPCD_SERVER()->get_app_count( $id ) ),
			'columns' => 2,
			'class'   => 'wpcd_server_details_top_row',
		);

		$actions['general-welcome-top-divider-01'] = array(
			'name'  => '',
			'type'  => 'custom_html',
			'std'   => '<hr />',
			'class' => 'wpcd_server_details_top_row-divider',
		);

		return $actions;

	}

}

/* new WPCD_WORDPRESS_TABS_SERVER_GENERAL(); */
