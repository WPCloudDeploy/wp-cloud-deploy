<?php
/**
 * Tabs
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Parent class for all tabs in the admin screen for the WordPress App */

/**
 * Class WPCD_WORDPRESS_TABS
 */
class WPCD_WORDPRESS_TABS extends WPCD_WORDPRESS_APP {

	/**
	 * WPCD_WORDPRESS_TABS constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Gets the fields to be shown in certain tabs - eg: the misc tab and the cron tab.
	 * This function isn't used for all tabs, just for ones that are less complex.
	 * Tabs such as the sFTP and Backup & Restore uses their own function.
	 *
	 * This uses a function called get_actions that is expected to be defined by the descendant class.
	 * That function should return an array in a specific format - something like this:
				['basic-auth-status'] = array(
					'label' => __( 'Basic Authentication', 'wpcd' ),
					'raw_attributes' => array(
						'on_label'  => __( 'Enabled', 'wpcd' ),
						'off_label' => __( 'Disabled', 'wpcd' ),
						'std'       => $basic_auth_status === 'on',
						'desc'      => __( 'Add or remove password protection on your site', 'wpcd' ),
						'confirmation_prompt' => $confirmation_prompt,
						// fields that contribute data for this action
						'data-wpcd-fields' => json_encode(array( '#wpcd_app_action_basic-auth-user', '#wpcd_app_action_basic-auth-pw' )),
					),
					'type'  => 'switch',
				);
	 *
	 * Notice that the raw_attributes array contains most of the metabox.io
	 * attributes along with some attributes for data-wpcd-* used in JS code.
	 *
	 * @param array  $fields list of existing fields.
	 * @param int    $id post id of the current app.
	 * @param string $tab name of tab to put fields on.
	 */
	public function get_fields_for_tab( array $fields, $id, $tab ) {
		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		$actions = $this->get_actions( $id );  // get_actions needs to be defined by the descendant class if this get_fields function ever gets called!
		foreach ( $actions as $slug => $attributes ) {
			$raw_attributes = isset( $attributes['raw_attributes'] ) ? $attributes['raw_attributes'] : array();
			$fields[]       = array_merge(
				$raw_attributes,
				array(
					'name'              => isset( $attributes['label'] ) ? $attributes['label'] : '',
					'id'                => "wpcd_app_action_{$slug}",
					'tab'               => $tab,
					'type'              => $attributes['type'],
					'attributes'        => array(
						// the _action that will be called in ajax.
						'data-wpcd-action'              => $slug,
						// the id, not of much use.
						'data-wpcd-id'                  => wpcd_get_current_page_server_id(),
						// fields that contribute data for this action. For example, if action is to set a password, this will contain the field ID references for the user id and password fields.
						'data-wpcd-fields'              => isset( $raw_attributes['data-wpcd-fields'] ) ? $raw_attributes['data-wpcd-fields'] : '',
						// the key of the field (the key goes in the request). For example, if action is to set a password, this will contain the field NAME references for the user id and password fields.
						'data-wpcd-name'                => isset( $raw_attributes['data-wpcd-name'] ) ? $raw_attributes['data-wpcd-name'] : "wpcd_app_action_{$slug}",
						// confirmation prompt.
						'data-wpcd-confirmation-prompt' => isset( $raw_attributes['confirmation_prompt'] ) ? $raw_attributes['confirmation_prompt'] : '',                       // show log console?
						'data-show-log-console'         => isset( $raw_attributes['log_console'] ) ? $raw_attributes['log_console'] : '',
						// Initial console message.
						'data-initial-console-message'  => isset( $raw_attributes['console_message'] ) ? $raw_attributes['console_message'] : '',
						// Spellcheck security issue.
						'spellcheck'                    => isset( $raw_attributes['spellcheck'] ) ? $raw_attributes['spellcheck'] : 'true',
					),
					'class'             => isset( $raw_attributes['class'] ) ? 'wpcd_app_action ' . $raw_attributes['class'] : 'wpcd_app_action',
					'save_field'        => false,
					'column_row_before' => isset( $attributes['column_row_before'] ) ? $attributes['column_row_before'] : '',
					'column_row_after'  => isset( $attributes['column_row_after'] ) ? $attributes['column_row_after'] : '',
				)
			);
		}
		return $fields;
	}

	/**
	 * Send a raw server command and return the output.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 * @param string $command    The raw command to sent to the server - eg: 'sudo service nginx restart'.
	 * @param bool   $raw_output raw_output.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	public function submit_generic_server_command( $id, $action, $command, $raw_output = false ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $command ) );

		// Return the data as an error so it can be shown in a dialog box.
		if ( is_wp_error( $result ) ) {
			return $result;
		} else {
			// everything good!
			if ( $raw_output ) {
				// send result for analysis by another program/function.
				return $result;
			} else {
				// send result for ajax.
				return new \WP_Error( __( 'The requested server operation has completed.  Here is the raw output: ' . $result, 'wpcd' ) );
			}
		}

	}

	/**
	 * If a site is disabled then tabs can this function
	 * to show a standard "site is disabled message.
	 *
	 * @param string $tab    The tab id - if this is empty, assume that the tab id will be automatically filled in.
	 *
	 * @return array
	 */
	public function get_disabled_header_field( $tab = '' ) {

		$actions = array();

		// Start new card.
		$actions[] = wpcd_start_full_card( $tab );

		$desc       = __( 'This site has been disabled. To view the options on this tab you must re-enable the site using the options under the MISC tab.', 'wpcd' );
		$random_str = wpcd_random_str( 20, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' );

		if ( empty( $tab ) ) {
			$actions[ 'site-is-disabled-status-header-' . $random_str ] = array(
				'label'          => __( 'Site Is Disabled', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc'    => $desc,
					'columns' => 12,
				),
			);
		} else {
			$actions[ 'site-is-disabled-status-header-' . $random_str ] = array(
				'name'    => __( 'Site Is Disabled', 'wpcd' ),
				'type'    => 'heading',
				'tab'     => $tab,
				'desc'    => $desc,
				'columns' => 12,
			);
		}

		// Close up prior card.
		$actions[] = wpcd_end_card( $tab );

		return $actions;

	}

	/**
	 * If a site is on a server where the maximum sites
	 * allowed have been exceeded, return his header.
	 *
	 * @param string $tab    The tab id - if this is empty, assume that the tab id will be automatically filled in.
	 *
	 * @return array
	 */
	public function get_max_sites_exceeded_header_field( $tab = '' ) {

		$actions = array();

		$desc       = __( 'This site is on a server where the maximum number of allowed sites have been met or exceeded. Please contact your admin or customer support for assistance.', 'wpcd' );
		$random_str = wpcd_random_str( 20, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' );

		if ( empty( $tab ) ) {
			$actions[ 'function-not-available-status-header-' . $random_str ] = array(
				'label'          => __( 'This Function is Not Available', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);
		} else {
			$actions[ 'function-not-available-status-header-' . $random_str ] = array(
				'name' => __( 'This Function is Not Available', 'wpcd' ),
				'type' => 'heading',
				'tab'  => $tab,
				'desc' => $desc,
			);
		}

		return $actions;

	}

}
