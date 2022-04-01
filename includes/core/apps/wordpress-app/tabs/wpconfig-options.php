<?php
/**
 * WPCONFIG Tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_WPCONFIG
 */
class WPCD_WORDPRESS_TABS_WPCONFIG extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_WPCONFIG constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'wpconfig';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_wpconfig_tab';
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
		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'WPConfig', 'wpcd' ),
				'icon'  => 'fad fa-screwdriver ',
			);
		}
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
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = $this->get_valid_actions( $id );

		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
				/* Translators: %1: String representing action; %2: Filename where code is being executed; %3: Post id for site or server; %4: WordPress User id */
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			$result = $this->update_wp_config_option( $id, $action );
			return $result;
		}

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
		return $this->get_wpconfig_general_fields( $id );
		/*
		return array_merge(
			$this->get_wpconfig_general_fields( $id ),
			$this->get_php_version_fields( $id ),
			$this->get_common_php_options_fields( $id ),
			$this->get_advanced_php_options_fields( $id ),
			$this->get_php_workers_fields( $id ),
		);
		*/

	}

	/**
	 * Gets the fields for the WPCONFIG tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_wpconfig_general_fields( $id ) {

		$actions = array();

		/* What is the current php version site? */
		$current_version = get_post_meta( $id, 'wpapp_php_version', true );
		if ( empty( $current_version ) ) {
			$current_version = '7.4';
		}

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = '';
		$confirmation_prompt = __( 'Are you sure you would like to change or update this WPCONFIG.PHP value?', 'wpcd' );

		/* Header Descriptiont */
		$head_desc  = __( 'Use this section to update your WPCONFIG.PHP options.', 'wpcd' );
		$head_desc .= '<br />';
		$head_desc  = __( 'If you enter invalid or spurious values here, you will break your site!', 'wpcd' );
		$head_desc .= '<br />';
		$head_desc .= __( 'Note that WP-DEBUG options are under the TOOLs tab.', 'wpcd' );

		$actions['wpconfig-section-header'] = array(
			'label'          => __( 'Update WPCONFIG.PHP Options', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $head_desc,
			),
		);

		$actions['change-wpconfig-divider-top'] = array(
			'label' => '',
			'type'  => 'divider',
		);

		// Setup array of wpconfig options we're going to support.
		$config_options = $this->get_wp_config_options( $id );

		// Loop through the array and create metabox.io fields dynamically.
		foreach ( $config_options as $config_option_key => $config_option_array ) {
			$label          = $config_option_array['label'];
			$desc           = $config_option_array['desc'];
			$type           = $config_option_array['type'];
			$select_options = empty( $config_option_array['select_options'] ) ? '' : $config_option_array['select_options'];

			$actions[ "change-$config_option_key-label" ] = array(
				'label'          => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'columns' => 4,
					'std'     => '<b>' . __( "$label", 'wpcd' ) . '</b>',
					'desc'    => $desc,
				),
			);

			$actions[ "change-$config_option_key-value" ] = array(
				'label'          => '',
				'type'           => $type,
				'raw_attributes' => array(
					'std'            => '',
					'columns'        => 4,
					'options'        => $select_options,
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => $label,
				),
			);

			$actions[ "change-$config_option_key" ] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Change', 'wpcd' ),
					'columns'             => 4,
					'confirmation_prompt' => $confirmation_prompt,              // fields that contribute data for this action.
					'data-wpcd-fields'    => json_encode( array( "#wpcd_app_action_change-$config_option_key-value" ) ),
				),
				'type'           => 'button',
			);

			$actions[ "change-wpconfig-$config_option_key-divider" ] = array(
				'label' => '',
				'type'  => 'divider',
			);
		}

		return $actions;

	}

	/**
	 * Return the list of wp-config.php options that we're going to allow the user to change.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of wp-config.php options that we'll allow the user to handle.
	 */
	public function get_wp_config_options( $id ) {

		$config_options = array(
			'wp-memory-limit'        => array(
				'label' => 'WP_MEMORY_LIMIT',
				'desc'  => __( 'The maximum amount of memory in megabytes that your site can use when a user is viewing it on the front-end.', 'wpcd' ),
				'type'  => 'number',
			),
			'wp-max-memory-limit'    => array(
				'label' => 'WP_MAX_MEMORY_LIMIT',
				'desc'  => __( 'The maximum amount of memory in megabytes that your site can use when an admin is working in the wp-admin area.', 'wpcd' ),
				'type'  => 'number',
			),
			'wp-max-post-revisions'  => array(
				'label' => 'WP_POST_REVISIONS',
				'desc'  => __( 'The maximum number of post revisions allowed - if this is not set, WordPress keeps all revisions. WPCD default is to NOT set this value.', 'wpcd' ),
				'type'  => 'number',
			),
			'wp-empty-trash-days'    => array(
				'label' => 'EMPTY_TRASH_DAYS',
				'desc'  => __( 'controls the number of days before WordPress permanently deletes posts, pages, attachments, and comments, from the trash bin. The default is 30 days.', 'wpcd' ),
				'type'  => 'number',
			),
			'wp-environment-type'    => array(
				'label'          => 'WP_ENVIRONMENT_TYPE',
				'desc'           => '',
				'type'           => 'select',
				'select_options' => array(
					'production'  => __( 'Production', 'wpcd' ),
					'development' => __( 'Development', 'wpcd' ),
					'staging'     => __( 'Staging', 'wpcd' ),
				),
			),
			'wp-concatenate-scripts' => array(
				'label'          => 'CONCATENATE_SCRIPTS',
				'desc'           => __( 'Whether or not to smash all JS scripts into a single file.  As of WPCD 4.16 this value is automatically set to false and we strongly recommend that it remains that way.', 'wpcd' ),
				'type'           => 'select',
				'select_options' => array(
					'false' => __( 'False', 'wpcd' ),
					'true'  => __( 'True', 'wpcd' ),
				),
			),
			'wp-auto-save-interval'  => array(
				'label' => 'AUTOSAVE_INTERVAL',
				'desc'  => __( 'When editing a post, automatically save it every X seconds. WP Default is 160 seconds.', 'wpcd' ),
				'type'  => 'number',
			),
			'wp-lang'                => array(
				'label' => 'WPLANG',
				'desc'  => __( 'The default language to use for the site when no other setting is available.', 'wpcd' ),
				'type'  => 'text',
			),
			'wp-site-url'            => array(
				'label' => 'WP_SITEURL',
				'desc'  => __( 'The value defined is the address where your WordPress core files reside. It should include the https:// part as well. Do not put a slash “/” at the end. Setting this value in wp-config.php overrides the wp_options table value for siteurl.', 'wpcd' ),
				'type'  => 'text',
			),
			'wp-home'                => array(
				'label' => 'WP_HOME',
				'desc'  => __( 'Similar to WP_SITEURL, WP_HOME overrides the wp_options table value for home but does not change it in the database. home is the address you want people to type in their browser to reach your WordPress blog. It should include the http:// part and should not have a slash “/” at the end. Adding this in can sometimes reduce the number of database calls when loading your site.', 'wpcd' ),
				'type'  => 'text',
			),
		);

		return $config_options;

	}

	/**
	 * Return an array of of valid actions.  It's build dynamically from the array returned from $this->get_wp_config_options
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Simple Array of valid actions.
	 */
	public function get_valid_actions( $id ) {

		// Get an array of valid wp-config options we're handing.
		$config_options = $this->get_wp_config_options( $id );

		// Array var we'll be returning.
		$ret_array = array();

		// Loop through wp config options array and dynamically create an array of action values that match those we've setup in get_wpconfig_general_fields.
		foreach ( $config_options as $config_option_key => $config_option_value ) {

			$ret_array[] = "change-$config_option_key";

		}

		return $ret_array;

	}

	/**
	 * Update a wp-config.php option.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function update_wp_config_option( $id, $action, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Get app/server details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if no app/server details.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action );
			do_action( "wpcd_{$this->get_app_name()}_update_wp_site_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Make sure the option action is in the approved list.  We should have already validated this but it doesn't hurt to do it again!
		$valid_actions = $this->get_valid_actions( $id );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			$message = __( 'The option name is invalid - possible security issue.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_update_wp_site_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Get the full array of possible actions including the actual key that goes into wp-config.php.
		$wp_config_options = $this->get_wp_config_options( $id );

		// The $action variable contains a prefix of "change-" in it's name and so cannot be used to index into the $wp_config_options.var above.  So lets remove it.
		$action_index = str_replace( 'change-', '', $action );

		// Check to make sure that all required fields have values.
		if ( ! $args[ $wp_config_options[ $action_index ]['label'] ] ) {
			$message = __( 'This is not a valid option or the option value is empty.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_update_wp_config_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Most values we're setting should have the wp-cli raw option set to 'no'.
		$args['wps_wpconfig_option_is_raw'] = 'no';

		// Unique actions for each potential WP-CONFIG.PHP option.
		$option_key       = $wp_config_options[ $action_index ]['label'];
		$new_option_value = $args[ $option_key ];  // This is the option value that will be written to the wp-config.php file.

		switch ( $option_key ) {
			case 'WP_MEMORY_LIMIT':
			case 'WP_MAX_MEMORY_LIMIT':
				$args[ $option_key ] = intval( $args[ $option_key ] ); // Make sure we've got an integer value here.
				$args[ $option_key ] = $args[ $option_key ] . 'MB';  // Megabytes need to be added to the end of the numeric value.
				break;
			case 'WP_POST_REVISIONS':
			case 'EMPTY_TRASH_DAYS':
			case 'AUTOSAVE_INTERVAL':
				$args[ $option_key ]                = intval( $args[ $option_key ] ); // Make sure we've got an integer value here.
				$args['wps_wpconfig_option_is_raw'] = 'yes'; // Numeric options should be inserted as raw wp-cli values, not with quotes around them.
				break;
			case 'WPLANG':
			case 'WP_SITEURL':
			case 'WP_HOME':
				// No special actions here - strings should have already been sanitized.
				break;
			case 'WP_ENVIRONMENT_TYPE':
				// No special actions here - strings should have already been sanitized.
				// Maybe we should check to make sure that the value is one of the three valid ones that WP wants just in case it was forced in via a custom html script.
				// But, I suspect that in the future, wp will allow this value to be anything so will not validate for now.
				break;
			case 'CONCATENATE_SCRIPTS':
			case 'COMPRESS_SCRIPTS':
			case 'COMPRESS_CSS':
			case 'WP_DISABLE_FATAL_ERROR_HANDLER':
				// These values should only be true or false.  Need to make sure that the wp-cli option for raw is set to true.
				$args['wps_wpconfig_option_is_raw'] = 'yes';
				break;
			default:
				$message = __( 'Another check has revealed that this is not a valid option.', 'wpcd' );
				do_action( "wpcd_{$this->get_app_name()}_update_wp_config_option_failed", $id, $action, $message, $args );
				return new \WP_Error( $message );
		}

		// One more check to make sure that the revised values aren't empty.  It could happen if the user somehow sent in a text field instead of a numeric when we're expecting a numeric.
		// In that case, the intval statements above would render a zero value for the option.
		if ( empty( $args[ $option_key ] ) ) {
			$message = __( 'Yet another check has revealed that this is not a valid option.', 'wpcd' );
			do_action( "wpcd_{$this->get_app_name()}_update_wp_config_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Sanitize the option name for use on the linux command line but only if the option is a text option - this will put single-quotes around it which is what wp-config.php would expect.
		// By now integers would have been validated with intval in the above CASE block.
		if ( 'text' === $wp_config_options[ $action_index ]['type'] ) {
			$args[ $option_key ] = escapeshellarg( $args[ $option_key ] );
		}

		// Transfer the option name/value pair to array elements that the BASH script expects...
		$args['wps_wpconfig_option']           = $option_key;  // eg: WP_MEMORY_LIMIT.
		$args['wps_new_wpconfig_option_value'] = $args[ $option_key ]; // eg: 120MB.

		// Bash script expects a specific action value to handle these changes.
		$action_for_bash_script = 'wp_site_update_wpconfig_option';

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'update_wp_config_option.txt',
			array_merge(
				$args,
				array(
					'action' => $action_for_bash_script,
					'domain' => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'update_wp_config_option.txt' );

		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			$message = sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result );
			do_action( "wpcd_{$this->get_app_name()}_update_wp_config_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$success = array(
				'msg'     => __( 'The wp-config.php option value was updated.', 'wpcd' ),
				'refresh' => 'yes',
			);

			// Let others know we've been successful.
			do_action( "wpcd_{$this->get_app_name()}_update_wp_config_option_successful", $id, $action, $args );
		}

		return $success;

	}


}

new WPCD_WORDPRESS_TABS_WPCONFIG();
