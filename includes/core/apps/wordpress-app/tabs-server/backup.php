<?php
/**
 * Backup Tab
 *
 * @TODO: Some metavalues in this file are set to "wpapp_" prefixes instead of "wpcd_wpapp" or "wpcd_wpapp_server" or "wpcd_server_wordpress-app_server".
 * While this does not hurt anything right now, it needs to be fixed and an upgrade routine written to maintain our metavalue naming standards
 * on the server record.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SERVER_BACKUP
 */
class WPCD_WORDPRESS_TABS_SERVER_BACKUP extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'wpcd_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		// Allow the auto_backup_action_all_sites action to be triggered via an action hook.
		add_action( 'wpcd_wordpress-manage_server_backup_action_all_sites', array( $this, 'auto_backup_action_all_sites' ), 10, 1 ); // Unlike others, this one only needs the server id.
		add_action( 'wpcd_{$this->get_app_name()}_manage_server_backup_action_all_sites', array( $this, 'auto_backup_action_all_sites' ), 10, 1 ); // Duplicate of the one above because the one above has the incorrect action hook name - the one above is for backwards compatibiilty.

		// Allow the server configuration backups to be triggered via an action hook. Hook: wpcd_wordpress-app-toggle_server_configuration_backups.
		add_action( "wpcd_{$this->get_app_name()}_toggle_server_configuration_backups", array( $this, 'toggle_server_configuration_backups' ), 10, 1 ); // Unlike others, this one only needs the server id.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'server_backup';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_backup_tab';
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
				'label' => __( 'Backup', 'wpcd' ),
				'icon'  => 'fad fa-server',
			);
		}
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
		$valid_actions = array( 'backup-change-cred', 'backup-run-schedule-all-sites', 'delete-all-server-backups', 'prune-all-server-backups', 'toggle-server-configuration-backups', 'delete-server-configuration-backups', 'take-a-snapshot' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'backup-change-cred':
					$result = $this->save_s3_credentials( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
				case 'backup-run-schedule-all-sites':
					$result = $this->auto_backup_action_all_sites( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
				case 'delete-all-server-backups':
					$result = $this->delete_all_server_backups( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
				case 'prune-all-server-backups':
					$result = $this->prune_all_server_backups( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
				case 'toggle-server-configuration-backups':
					$result = $this->toggle_server_configuration_backups( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
				case 'delete-server-configuration-backups':
					$result = $this->delete_server_configuration_backups( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
				case 'take-a-snapshot':
					$result = $this->take_a_snapshot( $id );  // no action being passed in - don't need it - it'll get figured out in the function.
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the fields to show in the BACKUP tab in the server details screen.
	 *
	 * @param array $fields list of existing fields.
	 * @param int   $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields( array $fields, $id ) {

		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		// credentials.
		$fields[] = array(
			'name' => __( 'AWS S3 Credentials', 'wpcd' ),
			'id'   => 'wpcd_app_action_aws-s3-credentials-header',
			'tab'  => 'server_backup',
			'type' => 'heading',
			'desc' => __( 'You can use a unique set of S3 credentials for each of your servers.  Set the credentials for this server below.  If these are left empty, the values in your SETTINGS screen will be used for any backup operations. <br /> Warning: You must save credentials on this screen at least once. To use the values in your global SETTINGS just save ALL the fields with empty values. We will then copy the values from your global settings to the server.<br />Note: As you might expect, credentials entered here are used to backup all sites on the server!', 'wpcd' ),
		);
		$fields[] = array(
			'name'       => __( 'AWS Access Key ID', 'wpcd' ),
			'id'         => 'wpcd_app_aws_key',
			'tab'        => 'server_backup',
			'type'       => 'text',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'aws_key',
			),
			'std'        => get_post_meta( $id, 'wpcd_wpapp_backup_aws_key', true ),
			'size'       => 60,
		);

		$pass = get_post_meta( $id, 'wpcd_wpapp_backup_aws_secret', true );
		if ( ! empty( $pass ) ) {
			$pass = self::decrypt( $pass );
		}
		$fields[] = array(
			'name'       => __( 'AWS Access Secret', 'wpcd' ),
			'id'         => 'wpcd_app_aws_secret',
			'tab'        => 'server_backup',
			'type'       => 'password',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'aws_secret',
			),
			'class'      => 'wpcd_app_pass_toggle',
			'std'        => $pass,
			'size'       => 60,
		);
		$fields[] = array(
			'name'       => __( 'AWS Bucket Name', 'wpcd' ),
			'id'         => 'wpcd_app_aws_bucket',
			'tab'        => 'server_backup',
			'type'       => 'text',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'aws_bucket',
			),
			'std'        => get_post_meta( $id, 'wpcd_wpapp_backup_aws_bucket', true ),
			'size'       => 90,
		);
		$fields[] = array(
			'name'       => __( 'AWS Region', 'wpcd' ),
			'id'         => 'wpcd_app_aws_region',
			'tab'        => 'server_backup',
			'type'       => 'text',
			'tooltip'    => sprintf( __( 'You can find a list of valid regions here: <a href="%s" target="_blank" >Valid Regions</a>', 'wpcd'), 'https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using-regions-availability-zones.html#concepts-available-regions' ),
			'desc'       => sprintf( __( '<a href="%s" target="_blank" >Valid Regions</a>', 'wpcd'), 'https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using-regions-availability-zones.html#concepts-available-regions' ),
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'aws_region',
			),
			'std'        => get_post_meta( $id, 'wpcd_wpapp_backup_aws_region', true ),
			'size'       => 10,
		);
		$fields[] = array(
			'name'       => __( 'S3 Endpoint URL', 'wpcd' ),
			'id'         => 'wpcd_app_s3_endpoint',
			'tab'        => 'server_backup',
			'type'       => 'text',
			'tooltip'    => __( 'Set this if you want to use an alternative S3-compatible service.', 'wpcd' ),
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 's3_endpoint',
			),
			'std'        => get_post_meta( $id, 'wpcd_wpapp_backup_s3_endpoint', true ),
			'size'       => 90,
		);
		$fields[] = array(
			'id'         => 'wpcd_app_action_change_cred',
			'name'       => '',
			'tab'        => 'server_backup',
			'type'       => 'button',
			'std'        => __( 'Save Credentials', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'backup-change-cred',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_aws_key', '#wpcd_app_aws_secret', '#wpcd_app_aws_bucket', '#wpcd_app_aws_region' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to save these credentials?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Auto backups for the entire server
		 */

		/* all sites on the server            */
		$auto_backup_status_all_sites                = get_post_meta( $id, 'wpapp_auto_backups_status_all_sites', true );
		$auto_backup_bucket_all_sites                = get_post_meta( $id, 'wpapp_auto_backup_bucket_all_sites', true );
		$auto_backup_retention_days_all_sites        = get_post_meta( $id, 'wpapp_auto_backup_retention_days_all_sites', true );
		$auto_backup_manual_retention_days_all_sites = get_post_meta( $id, 'wpapp_auto_backup_manual_retention_days_all_sites', true );
		$auto_backup_delete_remotes_all_sites        = get_post_meta( $id, 'wpapp_auto_backup_delete_remotes_all_sites', true );

		// Default retention days.
		if ( empty( $auto_backup_retention_days_all_sites ) ) {
			$auto_backup_retention_days_all_sites = 7;
		}
		if ( empty( $auto_backup_manual_retention_days_all_sites ) ) {
			$auto_backup_manual_retention_days_all_sites = 7;
		}

		// Set the confirmation prompt text.
		if ( 'on' === $auto_backup_status_all_sites ) {
			$auto_backups_confirmation_prompt_all_sites = __( 'Are you sure you would like to disable daily automatic backups for all sites?', 'wpcd' );
		} else {
			$auto_backups_confirmation_prompt_all_sites = __( 'Are you sure you would like to enable daily automatic backups for all sites?', 'wpcd' );
		}

		if ( 'on' === $auto_backup_status_all_sites ) {
			// Backups have been enabled.  Show message about disabling it first before making changes.
			$fields[] = array(
				'name' => __( 'Automatic Backups - All Current and Future Sites On This Server', 'wpcd' ),
				'desc' => __( 'Backups are enabled for this server. If you would like to make changes, please disable it first using the switch below.', 'wpcd' ),
				'tab'  => 'server_backup',
				'type' => 'heading',
			);
		} else {
			$fields[] = array(
				'name' => __( 'Automatic Backups - All Current and Future Sites On This Server', 'wpcd' ),
				'desc' => __( 'Enable automatic backups to run once per day for all current and future sites on this server.<br />With this option you do not have to schedule individual backups for each site.<br /> Just configure this once and it will backup all your sites on **this server** once each night.<br />You should set up your S3 credentials and create a bucket for these backups before turning this option on!', 'wpcd' ),
				'tab'  => 'server_backup',
				'type' => 'heading',
			);
		}

		if ( 'on' !== $auto_backup_status_all_sites ) {
			// Backups are not currently enabled so we can show all fields.
			$fields[] = array(
				'id'         => 'wpcd_app_action_auto_backup_all_sites_bucket_name',
				'desc'       => __( 'If this is left blank then the server bucket name shown above or the global bucket name from the SETTINGS screen will be used', 'wpcd' ),
				'tab'        => 'server_backup',
				'type'       => 'text',
				'name'       => __( 'AWS Bucket Name', 'wpcd' ),
				'std'        => $auto_backup_bucket_all_sites,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'auto_backup_bucket_name_all_sites',
				),
				'size'       => 90,
				'save_field' => false,
			);
			$fields[] = array(
				'id'         => 'wpcd_app_action_auto_backup_all_sites_retention_days',
				'desc'       => __( 'If this is left blank or zero, we will default to 7 days. We recommend that you keep this number low and rely on S3 to store your older backups. If this is set to -1, local backups will NEVER be kept (NOT RECOMMENDED)', 'wpcd' ),
				'tab'        => 'server_backup',
				'type'       => 'number',
				'min'        => -1,
				'name'       => __( 'Retention Days', 'wpcd' ),
				'std'        => $auto_backup_retention_days_all_sites,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'auto_backup_retention_days_all_sites',
				),
				'size'       => 90,
				'save_field' => false,
			);
			$fields[] = array(
				'id'         => 'wpcd_app_action_auto_backup_all_sites_delete_remotes',
				'name'       => __( 'Delete Remote Backups', 'wpcd' ),
				'tab'        => 'server_backup',
				'type'       => 'select',
				'options'    => array(
					'off' => __( 'Disabled', 'wpcd' ),
					'on'  => __( 'Enabled', 'wpcd' ),
				),
				'std'        => $auto_backup_delete_remotes_all_sites,
				'desc'       => __( 'Delete remote backups when deleting local backups that exceed the retention days. We recommend that you keep this disabled and set a low number for the retention days above.', 'wpcd' ),
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'auto_backup_delete_remotes_all_sites',
				),
				'save_field' => false,
			);
		}
		$fields[] = array(
			'id'         => 'wpcd_app_action_auto_backup_all_sites',
			'name'       => 'on' === $auto_backup_status_all_sites ? '' : __( 'Schedule It', 'wpcd' ),
			'tab'        => 'server_backup',
			'type'       => 'switch',
			'on_label'   => __( 'Enabled', 'wpcd' ),
			'off_label'  => __( 'Disabled', 'wpcd' ),
			'std'        => 'on' === $auto_backup_status_all_sites,
			'desc'       => 'on' === $auto_backup_status_all_sites ? '' : __( 'Enable daily automatic backups', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'backup-run-schedule-all-sites',
				// fields that contribute data for this action.
				'data-wpcd-fields'              => 'on' === $auto_backup_status_all_sites ? '' : wp_json_encode( array( '#wpcd_app_action_auto_backup_all_sites_bucket_name', '#wpcd_app_action_auto_backup_all_sites_retention_days', '#wpcd_app_action_auto_backup_all_sites_delete_remotes' ) ),              // the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => $auto_backups_confirmation_prompt_all_sites,
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);
		/* End auto backups for the entire server */

		/* Delete All Backups */
		$fields[] = array(
			'name' => __( 'Delete Backups', 'wpcd' ),
			'desc' => __( 'Manually delete LOCAL backups. Backups stored at AWS will not be deleted. Before you can use this option you must have configured backups and run the backup process at least once.', 'wpcd' ),
			'tab'  => 'server_backup',
			'type' => 'heading',
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_delete_all_server_backups',
			'name'       => '',
			'tab'        => 'server_backup',
			'type'       => 'button',
			'std'        => __( 'Delete All Backups For All Sites', 'wpcd' ),
			'desc'       => '',
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'delete-all-server-backups',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you really really SURE you want to delete all backups for this site? This action cannot be reversed!', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);
		/* End Delete All Backups */

		/* Manually Prune Backups */
		$fields[] = array(
			'name' => __( 'Prune Backups', 'wpcd' ),
			'desc' => __( 'Delete old local backups for all sites on this server. Before you can use these options you must have configured backups and run the backup process at least once.', 'wpcd' ),
			'tab'  => 'server_backup',
			'type' => 'heading',
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_manual_prune_server_backup_retention_days',
			'desc'       => __( 'If left blank or zero, the backups will never be deleted.', 'wpcd' ),
			'tab'        => 'server_backup',
			'type'       => 'number',
			'std'        => (int) $auto_backup_manual_retention_days_all_sites,
			'name'       => __( 'Retention Days', 'wpcd' ),
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'manual_prune_server_backup_retention_days',
			),
			'size'       => 90,
			'save_field' => false,
		);

		$fields[] = array(
			'type' => 'custom_html',
			'tab'  => 'server_backup',
			'std'  => '<br />',
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_delete_server_backups',
			'name'       => '',
			'tab'        => 'server_backup',
			'type'       => 'button',
			'std'        => __( 'Prune Backups For All Sites On This Server', 'wpcd' ),
			'desc'       => __( 'Prune backups for all sites on this server.  You must have set a retention interval above before this is used!', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'prune-all-server-backups',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_action_manual_prune_server_backup_retention_days' ) ),                // make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you really really SURE you want to prune backups for all sites on this server? This action cannot be reversed!', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);
		/* End Manually Prune Backups */

		// Add a note about how the S3 credentials work.
		$s3note  = '';
		$s3note .= __( 'When you change credentials on the global SETTINGS screen, they are not saved to any server.  Only when you click the save credentials on this screen and leave the credentials on this screen blank will the new global credentials be saved to your server and used for future backups.', 'wpcd' );
		$s3note .= '<br />';
		$s3note .= __( 'Yes, we know, its confusing. So feel free to check in with our support team with any questions!', 'wpcd' );
		$s3note .= '<br />';
		$s3note .= __( 'To avoid confusion, our recommendation is that you use a set of credentials on each site/server and not leave the fields on this screen blank.', 'wpcd' );

		$fields[] = array(
			'name' => __( 'IMPORTANT notes about how the S3 credentials work', 'wpcd' ),
			'tab'  => 'server_backup',
			'type' => 'heading',
			'desc' => $s3note,
		);

		/* Configuration Backups */
		$fields[] = array(
			'name' => __( 'Local Server Configuration Backups', 'wpcd' ),
			'desc' => __( 'Backup your server level configuration files to another folder every four hours. These include your nginx.conf, php.ini, ssl certificates, wp-config.php files and more. 90 days of history is retained.', 'wpcd' ),
			'tab'  => 'server_backup',
			'type' => 'heading',
		);

		$status = get_post_meta( $id, 'wpcd_wpapp_backup_config_status', true );
		if ( empty( $status ) ) {
			$status = 'off';
		}

		/* Set the confirmation prompt based on the the current status of this flag */
		$confirmation_prompt = '';
		if ( 'on' === $status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable backups for your server configurations??', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable backups of your server configurations?', 'wpcd' );
		}

		$fields[] = array(
			'id'         => 'wpcd_app_action_conf_backup_toggle',
			'name'       => __( 'Configuration Backups', 'wpcd' ),
			'tab'        => 'server_backup',
			'type'       => 'switch',
			'on_label'   => __( 'Enabled', 'wpcd' ),
			'off_label'  => __( 'Disabled', 'wpcd' ),
			'std'        => 'on' === $status,
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'toggle-server-configuration-backups',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => $confirmation_prompt,
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		$fields[] = array(
			'id'         => 'wpcd_app_action_conf_backup_delete',
			'name'       => __( 'Delete all configuration backups', 'wpcd' ),
			'tab'        => 'server_backup',
			'type'       => 'button',
			'std'        => __( 'Delete', 'wpcd' ),
			// fields that contribute data for this action.
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'delete-server-configuration-backups',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you really really SURE you want to delete all server configuration backups? This action cannot be reversed!', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/* Snapshots */
		$fields[] = array(
			'name' => __( 'Server Provider Snapshots', 'wpcd' ),
			'desc' => __( 'If this provider supports snapshots you can quickly submit a request to create a snapshot from here.', 'wpcd' ),
			'tab'  => 'server_backup',
			'type' => 'heading',
		);

		/* Get the provider object */
		$provider     = WPCD_SERVER()->get_server_provider( $id );
		$provider_api = WPCD()->get_provider_api( $provider );

		if ( $provider_api && $provider_api->get_feature_flag( 'snapshots' ) ) {
			// snapshots are supported - yay!

			$fields[] = array(
				'id'         => 'wpcd_app_action_take_snapshot',
				'name'       => '',
				'tab'        => 'server_backup',
				'type'       => 'button',
				'std'        => __( 'Take a Snapshot', 'wpcd' ),
				'desc'       => __( 'Server will NOT be shutdown before snapshot operation which could lead to data inconsistency in the snapshot.', 'wpcd' ),
				// fields that contribute data for this action.
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'take-a-snapshot',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to take a snapshot?', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

		} else {
			// snapshots are not supported for the current provider.
			$fields[] = array(
				'name' => __( 'No Snapshots Support', 'wpcd' ),
				'desc' => __( 'Unfortunately there is no support for snapshots in this provider.', 'wpcd' ),
				'tab'  => 'server_backup',
				'type' => 'custom_html',
			);
		}

		return $fields;

	}

	/**
	 * Save S3 credentials.
	 * Note that we're going to save these on the SERVER record.
	 * This is because the credentials apply to all the sites on a server
	 * regardless of which domain/app it was saved from!
	 *
	 * @param int $id id.
	 */
	public function save_s3_credentials( $id ) {

		$action = 'change_aws_credentials';  // The action being passed to the bash script.

		// Get data from the post.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// sanitize the fields to allow them to be used safely on the bash command line.
		if ( ! empty( $args['aws_key'] ) ) {
			$creds['aws_access_key_id'] = escapeshellarg( $args['aws_key'] );
		} else {
			// Get the one from the global settings screen...
			$creds['aws_access_key_id'] = escapeshellarg( wpcd_get_option( 'wordpress_app_aws_access_key' ) );
		}
		if ( ! empty( $args['aws_secret'] ) ) {
			$creds['aws_secret_access_key'] = escapeshellarg( $args['aws_secret'] );
		} else {
			// Get the one from the global settings screen...
			$creds['aws_secret_access_key'] = escapeshellarg( self::decrypt( wpcd_get_option( 'wordpress_app_aws_secret_key' ) ) );
		}
		if ( ! empty( $args['aws_region'] ) ) {
			$creds['aws_region'] = escapeshellarg( $args['aws_region'] );
		} else {
			// Get the one from the global settings screen...
			$creds['aws_region'] = escapeshellarg( wpcd_get_option( 'wordpress_app_aws_default_region' ) );
		}
		if ( ! empty( $args['s3_endpoint'] ) ) {
			$creds['s3_endpoint'] = escapeshellarg( $args['s3_endpoint'] );
		} else {
			// Get the one from the global settings screen...
			$creds['s3_endpoint'] = escapeshellarg( wpcd_get_option( 'wordpress_app_s3_endpoint' ) );
		}

		// If at this point both the credential fields are still blank, error out.
		if ( empty( $creds['aws_access_key_id'] ) || empty( $creds['aws_secret_access_key'] ) || empty( $creds['aws_region'] ) ) {
			return new \WP_Error( __( 'We are unable to execute this request because there are blank fields in the request and/or blank fields in the global credentials settings. Blanks on this screen are ok as long as there are some credentials configured in the global settings screen!', 'wpcd' ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup_restore_save_credentials.txt',
			array_merge(
				$args,
				$creds,
				array(
					'action' => $action,
					'domain' => 'nodomain',
				)
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'backup_restore_save_credentials.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// How that we know we're successful, lets save the credentials to the server post meta.

		$server_id = $id;

		if ( ! empty( $args['aws_key'] ) && ! empty( $args['aws_secret'] ) && ! empty( $args['aws_bucket'] ) && ! empty( $args['aws_region'] ) ) {

			// update the creds only if all fields are not empty.
			update_post_meta( $server_id, 'wpcd_wpapp_backup_aws_key', $args['aws_key'] );
			update_post_meta( $server_id, 'wpcd_wpapp_backup_aws_secret', self::encrypt( $args['aws_secret'] ) );
			update_post_meta( $server_id, 'wpcd_wpapp_backup_aws_bucket', $args['aws_bucket'] );
			update_post_meta( $server_id, 'wpcd_wpapp_backup_aws_region', $args['aws_region'] );
			update_post_meta( $server_id, 'wpcd_wpapp_backup_s3_endpoint', $args['s3_endpoint'] );

		} elseif ( empty( $args['aws_key'] ) && empty( $args['aws_secret'] ) && empty( $args['aws_bucket'] ) ) {

			// delete the creds if all fields are empty.
			delete_post_meta( $server_id, 'wpcd_wpapp_backup_aws_key' );
			delete_post_meta( $server_id, 'wpcd_wpapp_backup_aws_secret' );
			delete_post_meta( $server_id, 'wpcd_wpapp_backup_aws_bucket' );
			delete_post_meta( $server_id, 'wpcd_wpapp_backup_aws_region' );
			delete_post_meta( $server_id, 'wpcd_wpapp_backup_s3_endpoint' );

		} else {
			// something ambiguous - let user know...
			return new \WP_Error( __( 'Only some portions of this operation were successful because not all data fields were supplied. You should try this operation again and fill in all the fields or leave all the fields blank.', 'wpcd' ) );
		}

		$result = array(
			'msg'     => __( 'Your backup settings have been saved.', 'wpcd' ),
			'refresh' => 'yes',
		);  // If we got here, everything worked out so we'll return true.

		return $result;

	}

	/**
	 * Turn on/off auto backups - all sites on the server
	 *
	 * @param int $id     The postID of the app cpt.
	 *
	 * @return boolean|WP_Error success/failure
	 */
	public function auto_backup_action_all_sites( $id ) {

		// Get some data from the app record.
		$auto_backup_status = get_post_meta( $id, 'wpapp_auto_backups_status_all_sites', true );
		$auto_backup_bucket = get_post_meta( $id, 'wpapp_auto_backup_bucket_all_sites', true );

		// What action are we going to try to perform here?
		if ( 'on' === $auto_backup_status ) {
			// currently on, so assume we're going to turn it off.
			$action     = 'unschedule_full';
			$new_status = 'off';
		} else {
			// assume currently off and therefore need to turn it on.
			$action     = 'schedule_full';
			$new_status = 'on';
		}

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			$error_obj = new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
			do_action( "wpcd_server_{$this->get_app_name()}_server_auto_backup_action_all_sites_failed", $id, $action, $error_obj );
			return $error_obj;
		}

		// Get an array of credentials and buckets, with escapeshellarg already applied.
		// Function get_s3_credentials_for_backup is located in a trait file.
		$creds = $this->get_s3_credentials_for_backup( $id );

		// Get args passed in..
		if ( ! empty( $_POST['params'] ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			// You might get here if this function ends up being called by an action hook instead of a browser click.
			$args = array();
		}

		// If a bucket was passed in for this action, add it to the creds array.
		if ( ! empty( $args['auto_backup_bucket_name_all_sites'] ) ) {
			$creds['aws_bucket_name']       = escapeshellarg( sanitize_text_field( $args['auto_backup_bucket_name_all_sites'] ) );
			$creds['aws_bucket_name_noesc'] = sanitize_text_field( $args['auto_backup_bucket_name_all_sites'] );
		}

		// Set the s3_sync_parms element in the args array to match what the bash script expects.
		if ( ! empty( $args['auto_backup_delete_remotes_all_sites'] ) ) {
			$auto_backup_delete_remotes = $args['auto_backup_delete_remotes_all_sites'];
		} else {
			$auto_backup_delete_remotes = '';
		}
		if ( 'on' === $auto_backup_delete_remotes ) {
			$args['s3_sync_delete_parm'] = 'delete';  // note that we are not apply escapeshellarg to this. @todo - it might be safe to do it but needs testing.
		} else {
			$args['s3_sync_delete_parm'] = 'follow-symlinks';  // stick something in here so that the backup script doesn't error out.
		}

		/**
		 * @TODO: If some creds are empty we should consider bailing out here!
		 * BUT, the problem is that we also want to allow local-only backups
		 * on the server and not force aws configuration on the admin.
		 * So, for now we'll skip the empty creds handling logic.
		 */

		// Save bucket name into a variable without escapeshellarg applied because we'll be storing this in the db.
		$bucket = $creds['aws_bucket_name_noesc'];

		// Get retention days.
		if ( ! empty( $args['auto_backup_retention_days_all_sites'] ) ) {
			$retention_days = (int) sanitize_text_field( $args['auto_backup_retention_days_all_sites'] );
		} else {
			$retention_days = 7;
		}
		if ( empty( $retention_days ) ) {
			$retention_days = 7;
		}

		// Pass the URL to which backups will call to let us know its starting or completing.
		// Note that, unlike other callbacks, this one is hard-coded into the backup script.
		// So all it needs is the site url to callback into.
		$args['callback_domain'] = home_url();

		// apply escapshellarg on retention days.
		$args['auto_backup_retention_days_all_sites'] = escapeshellarg( $retention_days );

		// The script expects a different var for retention days so add that to the args array as well.
		$args['auto_backup_retention_days'] = escapeshellarg( $retention_days );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup_restore_schedule.txt',
			array_merge(
				$args,
				$creds,
				array(
					'action' => $action,
					'domain' => 'nodomain',
				)
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'backup_restore_schedule.txt' );
		if ( ! $success ) {
			do_action( "wpcd_server_{$this->get_app_name()}_server_auto_backup_action_all_sites_failed", $id, $action, $success );
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			$result = array(
				'msg'     => __( 'Your backup settings have been saved.', 'wpcd' ),
				'refresh' => 'yes',
			);  // If we got here, everything worked out so we'll return true.
		}

		// Now that we know we've been successful set the new status on the server record as well as any other data that needs to be stamped there.
		$server_id = $id;
		update_post_meta( $server_id, 'wpapp_auto_backups_status_all_sites', $new_status );
		update_post_meta( $server_id, 'wpapp_auto_backup_delete_remotes_all_sites', $auto_backup_delete_remotes );
		if ( ! empty( $bucket ) ) {
			update_post_meta( $server_id, 'wpapp_auto_backup_bucket_all_sites', $bucket );
		}
		if ( ! empty( $retention_days ) ) {
			update_post_meta( $server_id, 'wpapp_auto_backup_retention_days_all_sites', $retention_days );
		}

		do_action( "wpcd_server_{$this->get_app_name()}_server_auto_backup_action_all_sites_successful", $id, $action, $success );

		return $result;
	}

	/**
	 * Delete all backups for all sites on the server
	 *
	 * @param int $id     The postID of the app cpt.
	 *
	 * @return boolean|WP_Error success/failure
	 */
	public function delete_all_server_backups( $id ) {

		$action = 'delete_all_backups';

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get an array of credentials and buckets, with escapeshellarg already applied.
		// Function get_s3_credentials_for_backup is located in a trait file.
		$creds = $this->get_s3_credentials_for_backup( $id );

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// If a bucket was passed in for this action, add it to the creds array.
		if ( ! empty( $args['auto_backup_bucket_name_all_sites'] ) ) {
			$creds['aws_bucket_name']       = escapeshellarg( sanitize_text_field( $args['auto_backup_bucket_name_all_sites'] ) );
			$creds['aws_bucket_name_noesc'] = sanitize_text_field( $args['auto_backup_bucket_name_all_sites'] );
		}

		/**
		 * @TODO: If some creds are empty we should consider bailing out here!
		 * BUT, the problem is that we also want to allow local-only backups
		 * on the server and not force aws configuration on the admin.
		 * So, for now we'll skip the empty creds handling logic.
		 */

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup_restore_delete_and_prune_server.txt',
			array_merge(
				$args,
				$creds,
				array(
					'action'       => $action,
					'domain'       => 'nodomain',
					'confirmation' => 'yes',
				)
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'backup_restore_delete_and_prune_server.txt' );
		if ( ! $success || is_wp_error( $success ) ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			$result = array(
				'msg'     => __( 'All local backups for all sites on this server have been deleted.', 'wpcd' ),
				'refresh' => 'yes',
			);  // If we got here, everything worked out so we'll return true.
		}

		return $result;
	}

	/**
	 * Prune all backups for all sites on the server - Manual Action
	 *
	 * @param int $id     The postID of the app cpt.
	 *
	 * @return boolean|WP_Error success/failure
	 */
	public function prune_all_server_backups( $id ) {

		$action = 'prune_all_backups';

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get an array of credentials and buckets, with escapeshellarg already applied.
		// Function get_s3_credentials_for_backup is located in a trait file.
		$creds = $this->get_s3_credentials_for_backup( $id );

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Make sure we have a number for the retention days...

		if ( (int) $args['manual_prune_server_backup_retention_days'] <= 0 ) {
			return new \WP_Error( 'Unable to execute this request because the retention days need to be greater than zero.', 'wpcd' );
		}

		// If a bucket was passed in for this action, add it to the creds array.
		if ( ! empty( $args['auto_backup_bucket_name_all_sites'] ) ) {
			$creds['aws_bucket_name']       = escapeshellarg( sanitize_text_field( $args['auto_backup_bucket_name_all_sites'] ) );
			$creds['aws_bucket_name_noesc'] = sanitize_text_field( $args['auto_backup_bucket_name_all_sites'] );
		}

		// Get retention days.
		if ( ! empty( $args['manual_prune_server_backup_retention_days'] ) ) {
			$retention_days = (int) sanitize_text_field( $args['manual_prune_server_backup_retention_days'] );
		} else {
			$retention_days = 7;
		}
		if ( empty( $retention_days ) ) {
			$retention_days = 7;
		}

		/**
		 * @TODO: If some creds are empty we should consider bailing out here!
		 * BUT, the problem is that we also want to allow local-only backups
		 * on the server and not force aws configuration on the admin.
		 * So, for now we'll skip the empty creds handling logic.
		 */

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup_restore_delete_and_prune_server.txt',
			array_merge(
				$args,
				$creds,
				array(
					'action'       => $action,
					'domain'       => 'nodomain',
					'days'         => escapeshellarg( $retention_days ),
					'confirmation' => 'yes',
				)
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'backup_restore_delete_and_prune_server.txt' );
		if ( ! $success || is_wp_error( $success ) ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		} else {

			// Update the meta to store the retention days.
			if ( ! empty( $retention_days ) ) {
				update_post_meta( $id, 'wpapp_auto_backup_manual_retention_days_all_sites', $retention_days );
			}

			$result = array(
				'msg'     => __( 'All local backups for all sites on this server have been pruned.', 'wpcd' ),
				'refresh' => 'yes',
			);  // If we got here, everything worked out so we'll return true.
		}

		return $result;
	}

	/**
	 * Turn on or off server configuration backups.
	 *
	 * @param int $id     The postID of the app cpt.
	 *
	 * @return boolean|WP_Error success/failure
	 */
	public function toggle_server_configuration_backups( $id ) {

		// Figure out the current state and set action based on that.
		$status = get_post_meta( $id, 'wpcd_wpapp_backup_config_status', true );
		if ( empty( $status ) ) {
			$status = 'off';
		}

		if ( 'off' === $status ) {
			// we need to turn things on.
			$action = 'conf_backup_enable';
		} else {
			// we need to turn things off.
			$action = 'conf_backup_disable';
		}

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// construct the callback.
		$command_name    = 'server_config_backup';
		$callback_backup = $this->get_command_url( $id, $command_name, 'completed' );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup_config_files.txt',
			array(
				'action'          => $action,
				'callback_backup' => $callback_backup,
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'backup_config_files.txt' );
		if ( ! $success || is_wp_error( $success ) ) {
			do_action( "wpcd_server_{$this->get_app_name()}_toggle_server_configuration_backups_failed", $id, $action, $success );
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// Everything ok so set some metas.
			if ( 'off' === $status ) {
				// we just turned things on.
				update_post_meta( $id, 'wpcd_wpapp_backup_config_status', 'on' );
				$msg = __( 'Success - Configuration files will be backed up every four hours.', 'wpcd' );
			} else {
				// we just turned things off.
				update_post_meta( $id, 'wpcd_wpapp_backup_config_status', 'off' );
				$msg = __( 'Success - Backups of configuration files have been disabled.', 'wpcd' );
			}

			// Return success.
			$result = array(
				'msg'     => $msg,
				'refresh' => 'yes',
			);

			do_action( "wpcd_server_{$this->get_app_name()}_toggle_server_configuration_backups_success", $id, $action, $success );
		}

		return $result;

	}

	/**
	 * Delete server configuration backups.
	 *
	 * @param int $id     The postID of the app cpt.
	 *
	 * @return boolean|WP_Error success/failure
	 */
	public function delete_server_configuration_backups( $id ) {

		// Set action.
		$action = 'conf_backup_remove';

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command( $instance, 'backup_config_files.txt', array( 'action' => $action ) );

		// log.
		// phpcs:ignore
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'backup_config_files.txt' );
		if ( ! $success || is_wp_error( $success ) ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'wpcd' ), $action, $result ) );
		} else {
			// Return success.
			$msg    = __( 'Success - Server configuration files have been removed from the backup folders.', 'wpcd' );
			$result = array(
				'msg'     => $msg,
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Use the server provider's api to take a snapshot
	 *
	 * @param int $id         The postID of the server cpt.
	 *
	 * @return boolean success/failure/other
	 */
	public function take_a_snapshot( $id ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		/* Set action variable for consistency with other tabs */
		$action = 'take_a_snapshot';

		/* Get the provider object */
		$provider     = WPCD_SERVER()->get_server_provider( $id );
		$provider_api = WPCD()->get_provider_api( $provider );

		/* Call the snapshot function on the api */
		if ( $provider_api && is_object( $provider_api ) ) {
			$result = $provider_api->call( 'snapshot', $instance );
		}

		if ( 'success' === $result['status'] ) {
			$success = true;
			/**
			 * Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS.
			 */
			do_action( "wpcd_server_{$this->get_app_name()}_take_a_snapshot_action_successful", $id, $action, $success, $result );

			// Return the data as an error so it can be shown in a dialog box.
			return new \WP_Error( __( 'We have requested a snapshot via the server provider API. Please check your providers dashboard to verify completion.', 'wpcd' ) );

		} else {
			$success = false;
			/**          *
			 * Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS.
			 */
			do_action( "wpcd_server_{$this->get_app_name()}_take_a_snapshot_action_failed", $id, $action, $success );

			// Return the data as an error so it can be shown in a dialog box.
			return new \WP_Error( __( 'Unfortunately we encountered an issue while requesting the snapshot.', 'wpcd' ) );
		}

	}

}

new WPCD_WORDPRESS_TABS_SERVER_BACKUP();
