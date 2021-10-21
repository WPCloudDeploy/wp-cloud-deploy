<?php
/**
 * PHP Tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_PHP_OPTIONS
 */
class WPCD_WORDPRESS_TABS_PHP_OPTIONS extends WPCD_WORDPRESS_TABS {

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
		$tabs['php-options'] = array(
			'label' => __( 'PHP', 'wpcd' ),
			'icon'  => 'fad fa-cog',
		);
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the PHP tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {

		return $this->get_fields_for_tab( $fields, $id, 'php-options' );

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
			case 'restart-php-service':
				$result = $this->restart_php_service( $id, 'restart_php' );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
			case 'change-php-version':
				// change the php version.
				$php_version = get_post_meta( $id, 'wpapp_php_version', true );
				if ( empty( $php_version ) ) {
					$php_version = '7.4';
				}
				$result = $this->change_php_version( $id, 'change_php_version' );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
			case 'change-php-common-options':
				// Verify that the user is allowed to update php site options.
				if ( ! $this->wpcd_wpapp_user_can_change_php_options( $id ) ) {
					$msg    = __( 'You don\'t have permission to update PHP options for this site.', 'wpcd' );
					$result = array(
						'refresh' => 'yes',
						'msg'     => $msg,
					);
					break;
				}
				// User is allowed to update php options so proceed.
				$result = $this->change_php_options( $id, 'add_php_param' );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
			case 'change-php-advanced-options':
				// Verify that the user is allowed to update php site options.
				if ( ! $this->wpcd_wpapp_user_can_change_php_advanced_options( $id ) ) {
					$msg    = __( 'You don\'t have permission to update PHP options for this site.', 'wpcd' );
					$result = array(
						'refresh' => 'yes',
						'msg'     => $msg,
					);
					break;
				}
				// User is allowed to update php options so proceed.
				$result = $this->change_php_options( $id, 'add_php_param' );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
			case 'change-php-workers-pm':
				// Verify that the user is allowed to update php site options/php workers.
				if ( ! $this->wpcd_wpapp_user_can_change_php_advanced_options( $id ) ) {
					$msg    = __( 'You don\'t have permission to update PHP workers for this site.', 'wpcd' );
					$result = array(
						'refresh' => 'yes',
						'msg'     => $msg,
					);
					break;
				}
				// User is allowed to update php workers so proceed.
				$result = $this->change_php_workers( $id, 'change_php_workers' );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the PHP tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Basic checks passed, ok to proceed.
		return array_merge(
			$this->get_php_restart_fields( $id ),
			$this->get_php_version_fields( $id ),
			$this->get_common_php_options_fields( $id ),
			$this->get_advanced_php_options_fields( $id ),
			$this->get_php_workers_fields( $id ),
		);

	}

	/**
	 * Gets the fields required to allow the user to restart the PHP service for the current site.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_php_restart_fields( $id ) {

		$actions = array();

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = '';
		$confirmation_prompt = __( 'Are you sure you would like to restart the PHP service for this site??', 'wpcd' );

		$actions['restart-php-header'] = array(
			'label'          => __( 'Restart PHP Service', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Restart the PHP FPM service for this site', 'wpcd' ),
			),
		);

		$actions['restart-php-service'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Restart', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the fields for the PHP version options to be shown in the PHP tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_php_version_fields( $id ) {

		$actions = array();

		/* What is the current php version site? */
		$current_version = get_post_meta( $id, 'wpapp_php_version', true );
		if ( empty( $current_version ) ) {
			$current_version = '7.4';
		}

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = '';
		$confirmation_prompt = __( 'Are you sure you would like to switch PHP versions?', 'wpcd' );

		$actions['change-php-version-header'] = array(
			'label'          => __( 'Change PHP Version', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Use this section to change the PHP version for this site. If you have installed any custom PHP options, you will need to reinstall them after switching versions. <br /> We STRONGLY recommend that you use version 7.2 or greater since security updates have ceased for all prior PHP versions.', 'wpcd' ),
			),
		);

		// Create single element array if php 8.0 is installed.
		if ( $this->is_php_80_installed( $id ) ) {
			$php80 = array( '8.0' => '8.0' );
		} else {
			$php80 = array();
		}

		// Array of php version options.
		$php_select_options = array_merge(
			array(
				'7.4' => '7.4',
				'7.3' => '7.3',
				'7.2' => '7.2',
				'7.1' => '7.1',
				'5.6' => '5.6',
			),
			$php80
		);

		$actions['change-php-version-new-version'] = array(
			'label'          => __( 'PHP Version', 'wpcd' ),
			'desc'           => __( 'Set your PHP version', 'wpcd' ),
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => $php_select_options,
				'std'            => $current_version,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'new_php_version',
			),
		);

		$actions['change-php-version'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Change PHP Version', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,              // fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_change-php-version-new-version' ) ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the fields for the common PHP options to be shown in the PHP tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_common_php_options_fields( $id ) {

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to set this option?', 'wpcd' );

		$actions['change-php-common-options-header'] = array(
			'label'          => __( 'Add Or Update Some Common PHP Options', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'These are the most commonly changed PHP options.  Make sure you set a VALID value for the option - we do not validate your input before sending it on to the server! ', 'wpcd' ),
			),
		);

		/* Check permissions and fill out fields array if appropriate */
		if ( $this->wpcd_wpapp_user_can_change_php_options( $id ) ) {

			$actions['change-php-common-options-select'] = array(
				'label'          => __( 'Select a common PHP option to add or change', 'wpcd' ),
				'type'           => 'select',
				'raw_attributes' => array(
					'options'        => $this->get_common_php_options_list(),
					'desc'           => __( 'Select a common PHP option to add or change', 'wpcd' ),
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'php_common_option_to_set',
				),
			);

			$actions['change-php-common-options-value'] = array(
				'label'          => __( 'Enter a value for the option', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Make sure you set a VALID value for the option - we do not validate your input before sending it on to the server!', 'wpcd' ),
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'php_common_option_value',
				),
			);

			$actions['change-php-common-options'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Set the selected option', 'wpcd' ),
					'confirmation_prompt' => $confirmation_prompt,                  // fields that contribute data for this action.
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_change-php-common-options-select', '#wpcd_app_action_change-php-common-options-value' ) ),
				),
				'type'           => 'button',
			);
		} else {
			// Show message indicating that user cannot update php options.
			$actions['change-php-common-options-no-permit'] = array(
				'label'          => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => '<h4>' . __( '***You do not have permissions to add or update common php options.***', 'wpcd' ) . '</h4>',
				),
			);
		}

		/* Fields to show existing options that the user has set */
		$actions['change-php-common-existing-header'] = array(
			'label'          => __( 'Existing Options', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Here are the custom PHP options currently set for this site.', 'wpcd' ),
			),
		);

		/* Get the existing php options that were set from the database and construct a custom HTML string*/
		$customhtml   = '';
		$saved_values = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_php_custom_options', true ) );
		if ( empty( $saved_values ) || ( ! is_array( $saved_values ) ) ) {
			$customhtml = '<p>' . __( 'No common or custom php values have been set for this site.', 'wpcd' ) . '</p>';
		} else {
			// At least one value was set in the past so lets loop through the array and construct the html.
			foreach ( $saved_values as $key => $value ) {
				$customhtml .= '<p>' . "$key: <b>$value</b>" . '</p>';
			}
		}

		$actions['change-php-common-existing-values'] = array(
			'label'          => __( 'Existing Options', 'wpcd' ),
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std' => $customhtml,
			),
		);

		return $actions;

	}

	/**
	 * Get a list of "common" php functions.
	 *
	 * Will be used to show list to user.
	 * If you change something here, change the function below it as well!
	 */
	private function get_common_php_options_list() {
		return array(
			'upload_max_filesize' => 'upload_max_filesize: requires a numeric value in megabytes, eg 10M would be 10 megabtyes',
			'post_max_size'       => 'post_max_size: requires a numeric value in megabytes, eg 10M would be 10 megabtyes',
			'memory_limit'        => 'memory_limit: requires a numeric value in megabytes, eg 10M would be 10 megabtyes',
			'max_execution_time'  => 'max_execution_time: requires a numeric value in seconds, eg 30 would be 30 seconds',
			'max_input_time'      => 'max_input_time: requires a numeric value in seconds, eg 60 would be 60 seconds',
		);
	}

	/**
	 * Get a list of "common" php function keys.
	 *
	 * Will be used to validate when user chooses action.
	 */
	private function get_common_php_options_keys() {
		return array( 'upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time', 'max_input_time' );
	}

	/**
	 * Gets the fields for the Advanced PHP options to be shown in the PHP tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_advanced_php_options_fields( $id ) {

		if ( ! $this->wpcd_wpapp_user_can_change_php_advanced_options( $id ) ) {
			return array();
		}

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to set this option?', 'wpcd' );

		$actions['change-php-advanced-options-header'] = array(
			'label'          => __( '[Danger Zone] Add Or Update a PHP Option', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Use this to set a custom value for a PHP.ini value. Custom values are placed in the NGINX configuration files and apply only to this site. Please make sure that you set a VALID value for the option - we do not validate your input before sending it on to the server! ', 'wpcd' ),
			),
		);

		if ( $this->wpcd_wpapp_user_can_change_php_options( $id ) ) {

			$actions['change-php-advanced-options-item'] = array(
				'label'          => __( 'php.ini item', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Enter a text string corresponding to an item in the php.ini file. Make sure you set a valid string for the option - we do not validate your input before sending it on to the server!', 'wpcd' ),
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'php_advanced_option_to_set',
				),
			);

			$actions['change-php-advanced-options-value'] = array(
				'label'          => __( 'Enter a value for the option', 'wpcd' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Make sure you set a VALID value for the option - we do not validate your input before sending it on to the server!', 'wpcd' ),
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'php_advanced_option_value',
				),
			);

			$actions['change-php-advanced-options'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Set the selected option', 'wpcd' ),
					'confirmation_prompt' => $confirmation_prompt,                  // fields that contribute data for this action.
					'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_change-php-advanced-options-item', '#wpcd_app_action_change-php-advanced-options-value' ) ),
				),
				'type'           => 'button',
			);
		} else {
			// Show message indicating that user cannot update php options.
			$actions['change-php-advanced-options-header-no-permit'] = array(
				'label'          => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => '<h4>' . __( '***You do not have permissions to add or update advanced php options.***', 'wpcd' ) . '</h4>',
				),
			);
		}

		return $actions;

	}

	/**
	 * Gets the fields for the PHP WORKERS options to be shown in the PHP tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_php_workers_fields( $id ) {

		if ( ! $this->wpcd_wpapp_user_can_change_php_advanced_options( $id ) ) {
			return array();
		}

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to update your PHP Workers? If you set these values incorrectly, your server will NOT restart!', 'wpcd' );

		$actions['change-php-workers-fields-header'] = array(
			'label'          => __( '[Danger Zone] PHP Workers', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Use this section to update the PHP Workers values used for this site. Note: Incorrect values in this section can break your site by preventing the PHP service from restarting.', 'wpcd' ),
			),
		);

		/* Get current values */
		$pm = get_post_meta( $id, 'wpapp_php_workers', true );  // should be an array.
		if ( empty( $pm ) ) {
			$pm                         = array();
			$pm['pm']                   = 'dynamic';
			$pm['pm_max_children']      = '5';
			$pm['pm_start_servers']     = '2';
			$pm['pm_min_spare_servers'] = '1';
			$pm['pm_max_spare_servers'] = '2';
		}

		$actions['change-php-workers-pm-value'] = array(
			'label'          => __( 'PM', 'wpcd' ),
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => array(
					'dynamic'  => __( 'Dynamic', 'wpcd' ),
					'static'   => __( 'Static', 'wpcd' ),
					'ondemand' => __( 'On Demand', 'wpcd' ),
				),
				'std'            => $pm['pm'],
				'columns'        => 3,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'pm',
			),
		);

		$actions['change-php-workers-pm-max-children'] = array(
			'label'          => __( 'PM - Max Children', 'wpcd' ),
			'type'           => 'number',
			'raw_attributes' => array(
				'std'            => $pm['pm_max_children'],
				'columns'        => 3,              // the key of the field (the key goes in the request).
				'data-wpcd-name' => 'pm_max_children',
			),
		);

		$actions['change-php-workers-pm-max-start-servers'] = array(
			'label'          => __( 'PM - Start Servers', 'wpcd' ),
			'type'           => 'number',
			'raw_attributes' => array(
				'std'            => $pm['pm_start_servers'],
				'columns'        => 3,              // the key of the field (the key goes in the request).
				'data-wpcd-name' => 'pm_start_servers',
			),
		);

		$actions['change-php-workers-pm-min-spare-servers'] = array(
			'label'          => __( 'PM - Min Spare Servers', 'wpcd' ),
			'type'           => 'number',
			'raw_attributes' => array(
				'std'            => $pm['pm_min_spare_servers'],
				'columns'        => 3,              // the key of the field (the key goes in the request).
				'data-wpcd-name' => 'pm_min_spare_servers',
			),
		);

		$actions['change-php-workers-pm-max-spare-servers'] = array(
			'label'          => __( 'PM - Max Spare Servers', 'wpcd' ),
			'type'           => 'number',
			'raw_attributes' => array(
				'std'            => $pm['pm_max_spare_servers'],
				'columns'        => 3,              // the key of the field (the key goes in the request).
				'data-wpcd-name' => 'pm_max_spare_servers',
			),
		);

		$actions['change-php-workers-pm'] = array(
			'label'          => __( 'Apply Changes', 'wpcd' ),
			'raw_attributes' => array(
				'std'                 => __( 'Update', 'wpcd' ),
				'confirmation_prompt' => $confirmation_prompt,
				'columns'             => 5,                 // fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode(
					array(
						'#wpcd_app_action_change-php-workers-pm-value',
						'#wpcd_app_action_change-php-workers-pm-max-children',
						'#wpcd_app_action_change-php-workers-pm-max-start-servers',
						'#wpcd_app_action_change-php-workers-pm-min-spare-servers',
						'#wpcd_app_action_change-php-workers-pm-max-spare-servers',
					)
				),
			),
			'type'           => 'button',
		);

		return $actions;

	}


	/**
	 * Switch PHP Version.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function change_php_version( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Special sanitization for the new version...
		$new_php_version = '';
		if ( isset( $args['new_php_version'] ) ) {
			$new_php_version         = $args['new_php_version'];  // Get the version before we escape it for the linux command line - we'll need this if the action is successful.
			$args['new_php_version'] = escapeshellarg( $args['new_php_version'] );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'change_php_version_misc.txt',
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
		$success = $this->is_ssh_successful( $result, 'change_php_version_misc.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Record the new version on the app record.
		update_post_meta( $id, 'wpapp_php_version', $new_php_version );

		return $success;
	}

	/**
	 * Change PHP Options.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function change_php_options( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the field values from the front-end.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Are we changing a 'common' option or an 'advanced' option?
		if ( ! empty( $args['php_common_option_value'] ) ) {
			// common options.
			$php_option       = sanitize_text_field( $args['php_common_option_to_set'] );
			$php_option_value = sanitize_text_field( $args['php_common_option_value'] );

			// Make sure the "common" option is in a known good list.
			if ( ! in_array( $php_option, $this->get_common_php_options_keys() ) ) {
				return new \WP_Error( __( 'Whoa...looks like you are trying to hack the system - this is most definitely not allowed!', 'wpcd' ) );
			}

			// Now make sure we sanitize the fields for use on the linux command line and put them back into the array - in this case under a different KEY!...
			$args['php_option_to_set'] = escapeshellarg( $args['php_common_option_to_set'] );
			$args['php_option_value']  = escapeshellarg( $args['php_common_option_value'] );
		} else {
			if ( ! empty( $args['php_advanced_option_value'] ) ) {
				// advanced option.
				$php_option       = sanitize_text_field( $args['php_advanced_option_to_set'] );
				$php_option_value = sanitize_text_field( $args['php_advanced_option_value'] );

				// Now make sure we sanitize the fields for use on the linux command line and put them back into the array - in this case under a different KEY!...
				$args['php_option_to_set'] = escapeshellarg( $args['php_advanced_option_to_set'] );
				$args['php_option_value']  = escapeshellarg( $args['php_advanced_option_value'] );
			} else {
				// whoops, neither field has a value so error...
				return new \WP_Error( sprintf( __( 'Looks like no valid data was entered so we are unable to execute this request for action %s. Please check to make sure you have entered both an option and a value for the option!', 'wpcd' ), $action ) );
			}
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'change_php_option_misc.txt',
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

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'change_php_option_misc.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Record the new data onto the app record.
		$saved_values = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_php_custom_options', true ) );
		if ( empty( $saved_values ) || ( ! is_array( $saved_values ) ) ) {
			$saved_values = array();
		}
		$saved_values[ $php_option ] = $php_option_value;
		update_post_meta( $id, 'wpapp_php_custom_options', $saved_values );

		return $success;
	}

	/**
	 * Restart the PHP FPM Service.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function restart_php_service( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'restart_php_service.txt',
			array(
				'action' => $action,
				'domain' => get_post_meta(
					$id,
					'wpapp_domain',
					true
				),
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'restart_php_service.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		return $success;

	}

	/**
	 * Change PHP Workers.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function change_php_workers( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the field values from the front-end.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Create a new array with the unescaped fields for storage in the database.
		$pm                         = array();
		$pm['pm']                   = $args['pm'];
		$pm['pm_max_children']      = $args['pm_max_children'];
		$pm['pm_start_servers']     = $args['pm_start_servers'];
		$pm['pm_min_spare_servers'] = $args['pm_min_spare_servers'];
		$pm['pm_max_spare_servers'] = $args['pm_max_spare_servers'];

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$args = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'php_workers.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'php_workers.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Record the new data onto the app record.
		update_post_meta( $id, 'wpapp_php_workers', $pm );

		return $success;
	}

}

new WPCD_WORDPRESS_TABS_PHP_OPTIONS();
