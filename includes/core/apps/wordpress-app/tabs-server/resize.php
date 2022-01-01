<?php
/**
 * Resize Server Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_RESIZE.
 */
class WPCD_WORDPRESS_TABS_SERVER_RESIZE extends WPCD_WORDPRESS_TABS {

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
		return 'resize';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_resize_tab';
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
		if ( true === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Resize', 'wpcd' ),
				'icon'  => 'fad fa-expand-alt',
			);
		}
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the tab.
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
			/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'server-resize' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'server-resize':
					$result = $this->server_resize( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_resize_fields( $id );

	}

	/**
	 * Gets the fields to shown in the RESIZE tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_resize_fields( $id ) {

		$actions = array();

		/* Get a list of server sizes */
		$provider = WPCD_SERVER()->get_server_provider( $id );
		$sizes    = WPCD()->get_provider_api( $provider )->call( 'sizes' );

		/* Bail out if resize isn't supported. */
		if ( ! (bool) WPCD()->get_provider_api( $provider )->get_feature_flag( 'resize' ) ) {
			$actions['server-resize-provider-header'] = array(
				'label'          => __( 'Resize', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'This provider does not support resize operations at this time.  If you would like this provider to support resize operations please contact our support team for a customized quote.', 'wpcd' ),
				),
			);
			return $actions;
		}

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to resize this server?', 'wpcd' );

		/**
		 * Provider Resize.
		 */
		$actions['server-resize-provider-header'] = array(
			'label'          => __( 'Resize', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Initiate a resize operation.', 'wpcd' ),
			),
		);

		$actions['server-resize-new-size'] = array(
			'label'          => __( 'New Size', 'wpcd' ),
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => $sizes,
				'std'            => WPCD_SERVER()->get_server_size( $id ),
				'desc'           => __( 'Not all resize operations are supported - make sure you select a new size that is supported by your provider by checking their website!', 'wpcd' ),
				'size'           => 120,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'new_size',
			),
		);

		$actions['server-resize'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Resize', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
				'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_server-resize-new-size' ) ),
			),
			'type'           => 'button',
		);

		/**
		 * After Resize instructions.
		 */
		$instructions  = __( 'If CALLBACKS are installed, the server status should update automatically when the server is restarted.', 'wpcd' );
		$instructions .= '<br />' . __( 'However, you will need to monitor the status of the resize operation and restart the server when the operation is complete.', 'wpcd' );
		$instructions .= '<br />' . __( 'If callbacks are not installed you can check the status of the reboot by going to the ALL CLOUD SERVERS list and clicking on the UPDATE REMOTE STATE link for the server. In this case the server will be unavailable for further operations until you click that link to update the server status.', 'wpcd' );

		$actions['server-resize-instructions'] = array(
			'label'          => __( 'After-resize Instructions', 'wpcd' ),
			'raw_attributes' => array(
				'std' => $instructions,
			),
			'type'           => 'custom_html',
		);

		return $actions;

	}


	/**
	 * Use the server provider's api to initiate the resize operation!
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function server_resize( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Sanitize arguments array.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Extract the new size out of the array.
		$new_size = $args['new_size'];

		// Bail if $new_size is empty.
		if ( empty( $new_size ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because the new size of the server is empty. Action: %s', 'wpcd' ), $action ) );
		}

		// Make sure the new size isn't the same as the old size.
		$old_size = WPCD_SERVER()->get_server_size( $id );
		if ( $old_size === $new_size ) {
			return new \WP_Error( __( 'Cannot resize server because the current size is the same as the new size being requested.', 'wpcd' ) );
		}

		// Make sure the new size is in the $instance array since that is getting passed to the provider.
		$instance['new_size'] = $new_size;

		/* Get the provider object */
		$provider     = WPCD_SERVER()->get_server_provider( $id );
		$provider_api = WPCD()->get_provider_api( $provider );

		/* Call the resize function on the api */
		if ( $provider_api && is_object( $provider_api ) ) {
			$provider_api->call( 'resize', $instance );
		} else {
			return new \WP_Error( __( 'Unable to execute this request because we were unable to get a handle to the API class.', 'wpcd' ) );
		}

		/* Update server meta to show operation in progress */
		update_post_meta( $id, 'wpcd_server_current_state', 'in-progress' );  // @TODO: 'in-progress' should be a constant from the main PROVIDER ancestor class.

		/* Update server to show the new size is pending. */
		WPCD_SERVER()->set_pending_server_size( $id, $new_size );

		/**
		 * Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS.
		 * Note that we are assuming success always because we have no way of knowing if the thing worked or failed.
		 * So there is no corresponding action hook for failure.
		*/
		$success = true;
		do_action( "wpcd_server_{$this->get_app_name()}_server_resize_action_successful", $id, $action, $success );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'We have requested a server resize operation via the server provider API. You will need to log into your server provider dashboard to monitor the resize operation and restart the server when it is complete.', 'wpcd' ) );

	}

}

new WPCD_WORDPRESS_TABS_SERVER_RESIZE();
