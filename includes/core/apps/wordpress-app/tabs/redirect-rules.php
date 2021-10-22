<?php
/**
 * Redirect Rules Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_REDIRECT_RULES
 */
class WPCD_WORDPRESS_TABS_REDIRECT_RULES extends WPCD_WORDPRESS_TABS {

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
		$tabs['redirect-rules'] = array(
			'label' => __( 'Redirect Rules', 'wpcd' ),
			'icon'  => 'fad fa-directions',
		);
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the REDIRECT RULES tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {

		return $this->get_fields_for_tab( $fields, $id, 'redirect-rules' );

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
			case 'redirect-rules-add':
				$result = $this->manage_redirect_rules( $id, 'redirect_add_rule_to_site' );
				break;
			case 'redirect-rules-remove':
				$result = $this->manage_redirect_rules( $id, 'redirect_remove_rule_from_site_by_key_code' );
				break;
			case 'redirect-rules-remove-all':
				$result = $this->manage_redirect_rules( $id, 'redirect_remove_all_rules_from_site' );
				break;

		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the REDIRECT RULES tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_redirect_fields( $id );

	}

	/**
	 * Gets the fields required to setup redirect rules.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_redirect_fields( $id ) {

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		$actions = array();

		/* Add redirect rules */
		$actions['redirect-rules-header-add'] = array(
			'label'          => __( 'Add A Redirect Rule', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Add a simple server-level redirection rule for this site.', 'wpcd' ),
			),
		);

		$actions['redirect-rules-from-url'] = array(
			'label'          => __( 'From Which URL do you need to redirect?', 'wpcd' ),
			'raw_attributes' => array(
				'placeholder'    => __( 'Enter a FULL url here including the http:// or https://', 'wpcd' ),
				'desc'           => __( 'Do not use regex or special characters!', 'wpcd' ),
				'size'           => 90,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'source_url',
			),
			'type'           => 'text',
		);

		$actions['redirect-rules-to-url'] = array(
			'label'          => __( 'What is the destination URL of this redirect rule?', 'wpcd' ),
			'raw_attributes' => array(
				'placeholder'    => __( 'Enter a FULL url here including the http:// or https://', 'wpcd' ),
				'desc'           => __( 'Do not use regex or special characters!', 'wpcd' ),
				'size'           => 90,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'destination_url',
			),
			'type'           => 'text',
		);

		// Array of redirection types.
		$redirect_select_options = array(
			'1' => 'Temporary Redirect',
			'2' => 'Permanent Redirect',
		);

		$actions['redirect-rules-type'] = array(
			'label'          => __( 'Redirection Type', 'wpcd' ),
			'raw_attributes' => array(
				'options'        => $redirect_select_options,
				'std'            => '1',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'redirection_number',
			),
			'type'           => 'select',
		);

		$actions['redirect-rules-add'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Add This Rule', 'wpcd' ),
				'confirmation_prompt' => __( 'Are you sure you would like to add this rule?', 'wpcd' ),                 // fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_redirect-rules-from-url', '#wpcd_app_action_redirect-rules-to-url', '#wpcd_app_action_redirect-rules-type' ) ),
			),
			'type'           => 'button',
		);

		/* Remove redirect rules */

		// Get list of existing rules.
		// $rules = [$args['keycode']] = array( 'from' => $args['original_source_url'], 'to' =>     $args['original_destination_url'], 'type' => $args['original_redirection_number'] );.
		$rules = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_redirect_rules', true ) );

		// Only show this section if we have some rules that can be removed...
		if ( ! empty( $rules ) ) {

			$actions['redirect-rules-header-remove'] = array(
				'label'          => __( 'Remove A Redirect Rule', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Remove an existing redirection rule for this site.', 'wpcd' ),
				),
			);

			// Now build out drop-down list of rules that can be removed...
			$option_rules = array();
			foreach ( $rules as $rule_id => $rule ) {
				$option_rules[ $rule_id ] = $rule['from'] . ' -> ' . $rule['to'];
			}

			$actions['redirect-rules-to-remove'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'options'        => $option_rules,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'key_code',
				),
				'type'           => 'select',
			);

			$actions['redirect-rules-remove'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Remove This Rule', 'wpcd' ),
					'confirmation_prompt' => __( 'Are you sure you would like to REMOVE this rule?', 'wpcd' ),                  // fields that contribute data for this action.
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_redirect-rules-to-remove' ) ),
				),
				'type'           => 'button',
			);

		}

		/* Remove all redirect rules */
		$actions['redirect-rules-header-remove-all'] = array(
			'label'          => __( 'Remove ALL Redirect Rules', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Remove all existing redirection rules from this site.', 'wpcd' ),
			),
		);

		$actions['redirect-rules-remove-all'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Remove All Rules', 'wpcd' ),
				'confirmation_prompt' => __( 'Are you sure you would like to REMOVE ALL rules from this site?', 'wpcd' ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Add/Remove Redirect rules .
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function manage_redirect_rules( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the field values from the front-end.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Get the domain...
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		if ( empty( $domain ) ) {
			return new \WP_Error( __( 'Oops - looks like we are unable to figure out the domain name for this site.', 'wpcd' ) );
		}

		// Validate based on the type of action.
		switch ( $action ) {
			case 'redirect_add_rule_to_site':
				// Do some case conversion...
				$args['source_url']      = strtolower( $args['source_url'] );
				$args['destination_url'] = strtolower( $args['destination_url'] );

				// Validate required fields...
				if ( ! $args['source_url'] ) {
					return new \WP_Error( __( 'Sorry but we need a source URL to perform this action.', 'wpcd' ) );
				}
				if ( ! $args['destination_url'] ) {
					return new \WP_Error( __( 'Sorry but we need a destination URL to perform this action.', 'wpcd' ) );
				}
				if ( ! $args['redirection_number'] ) {
					return new \WP_Error( __( 'Sorry but we need to know what type of REDIRECT you need.', 'wpcd' ) );
				}

				// If we got here make sure that there is an "http://" or "https://" at the start of the "from" url.
				if ( ( 'http://' <> substr( $args['source_url'], 0, 7 ) ) && ( 'https://' <> substr( $args['source_url'], 0, 8 ) ) ) {
					return new \WP_Error( __( 'Your source URL needs to start with http:// or https://!', 'wpcd' ) );
				}

				// If we got here make sure that there is an "http://" or "https://" at the start of the "to" url.
				if ( ( 'http://' <> substr( $args['destination_url'], 0, 7 ) ) && ( 'https://' <> substr( $args['destination_url'], 0, 8 ) ) ) {
					return new \WP_Error( __( 'Your destination URL needs to start with http:// or https://!', 'wpcd' ) );
				}

				// Make sure that both URLS are different!
				if ( $args['destination_url'] === $args['source_url'] ) {
					return new \WP_Error( __( 'Your source & destination URLs cannot be the same!', 'wpcd' ) );
				}

				// Make sure that the domain is in both urls...
				if ( strpos( $args['source_url'], $domain ) === false || strpos( $args['destination_url'], $domain ) === false ) {
					return new \WP_Error( __( 'Your source & destination URLs must be for this domain!', 'wpcd' ) );
				}

				// Escape values in the $args array for use on the linux command line!
				$args['original_source_url']         = $args['source_url'];
				$args['original_destination_url']    = $args['destination_url'];
				$args['original_redirection_number'] = $args['redirection_number'];

				$args['source_url']         = escapeshellarg( $args['source_url'] );
				$args['destination_url']    = escapeshellarg( $args['destination_url'] );
				$args['redirection_number'] = escapeshellarg( $args['redirection_number'] );

				// Add into the $args array a unique random string to identify the line in the conf files when we're ready to remove it.
				$args['key_code'] = wpcd_random_str( 12, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' );

				break;
			case 'redirect_remove_rule_from_site_by_key_code':
				// Make sure we get a keycode.
				if ( ! $args['key_code'] ) {
					return new \WP_Error( __( 'Sorry but we need an internal keycode to perform this action and one was not provided - probably a system error - contact tech support.', 'wpcd' ) );
				}
				// Escape values we'll be using...
				$args['key_code'] = escapeshellarg( $args['key_code'] );
				break;
			case 'redirect_remove_all_rules_from_site':
				// nothing to do here.
				break;
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'server_redirect.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => $domain,
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'server_redirect.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// If we got here, command was a success...
		switch ( $action ) {
			case 'redirect_add_rule_to_site':
				// Add new redirect into a meta...
				$saved_values = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_redirect_rules', true ) );
				if ( empty( $saved_values ) || ( ! is_array( $saved_values ) ) ) {
					$saved_values = array();
				}
				$saved_values[ $args['key_code'] ] = array(
					'from' => $args['original_source_url'],
					'to'   => $args['original_destination_url'],
					'type' => $args['original_redirection_number'],
				);
				update_post_meta( $id, 'wpapp_redirect_rules', $saved_values );

				// Set up return with success message.
				// Return the message as an error so it can be shown in a dialog box.
				$success = new \WP_Error( __( 'Successfully added redirect rule.', 'wpcd' ) );

				break;
			case 'redirect_remove_rule_from_site_by_key_code':
				$saved_values = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_redirect_rules', true ) );
				unset( $saved_values[ $args['key_code'] ] );
				update_post_meta( $id, 'wpapp_redirect_rules', $saved_values );

				// Set up return with success message.
				// Return the message as an error so it can be shown in a dialog box.
				$success = new \WP_Error( __( 'Successfully removed redirect rule.', 'wpcd' ) );

				break;
			case 'redirect_remove_all_rules_from_site':
				delete_post_meta( $id, 'wpapp_redirect_rules' );

				// Set up return with success message.
				// Return the message as an error so it can be shown in a dialog box.
				$success = new \WP_Error( __( 'Successfully removed all redirect rules.', 'wpcd' ) );

				break;
		}

		return $success;

	}

}

new WPCD_WORDPRESS_TABS_REDIRECT_RULES();
