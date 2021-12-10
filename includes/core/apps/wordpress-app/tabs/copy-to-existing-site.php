<?php
/**
 * Copy a site to/over an existing site.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_COPY_TO_EXISTING_SITE
 */
class WPCD_WORDPRESS_TABS_COPY_TO_EXISTING_SITE extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the server cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed( $id, $name ) {

		if ( get_post_type( $id ) !== 'wpcd_app' ) {
			return;
		}

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'copy-to-existing-site';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_copy_to_existing_tab';
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
				'label' => __( 'Copy To Existing Site', 'wpcd' ),
				'icon'  => 'fad fa-copy',
			);
		}
		return $tabs;
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
			/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'copy-site-full', 'copy-site-partial', 'copy-site-files-only', 'copy-site-db-only', 'copy-site-partial-db-only', 'copy-site-save-site-settings' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'copy-site-full':
					$action = 'copy_to_existing_site_copy_full';
					$result = $this->copy_to_existing_site( $action, $id );
					break;
				case 'copy-site-partial':
					$action = 'copy_to_existing_site_copy_partial';
					$result = $this->copy_to_existing_site( $action, $id );
					break;
				case 'copy-site-files-only':
					$action = 'copy_to_existing_site_copy_files_only';
					$result = $this->copy_to_existing_site( $action, $id );
					break;
				case 'copy-site-db-only':
					$action = 'copy_to_existing_site_copy_db';
					$result = $this->copy_to_existing_site( $action, $id );
					break;
				case 'copy-site-partial-db-only':
					$action = 'copy_to_existing_site_copy_partial_db';
					$result = $this->copy_to_existing_site( $action, $id );
					break;
				case 'copy-site-save-site-settings':
					$result = $this->save_site_settings( $action, $id );

			}
		}
		return $result;

	}

	/**
	 * Copy a site over an existing site on the same server.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function copy_to_existing_site( $action, $id ) {

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Bail if certain things are empty...
		if ( empty( $args['target_domain'] ) ) {
			return new \WP_Error( __( 'The target domain must be provided', 'wpcd' ) );
		} else {
			$target_domain         = strtolower( sanitize_text_field( $args['target_domain'] ) );
			$target_domain         = wpcd_clean_domain( $target_domain );
			$args['target_domain'] = $target_domain;
		}

		// Bail if both wpincludedbtable and wpexcludedbtable args are set.
		if ( ! empty( $args['wpexcludedbtable'] ) && ! empty( $args['wpincludedbtable'] ) ) {
			return new \WP_Error( __( 'You must NOT set values for both include and exclude tables!', 'wpcd' ) );
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Add some items to the args array.
		$args['source_domain'] = $domain;
		$args['wpconfig']      = 'N';

		// sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// we want to make sure this command runs only once in a "swatch beat" for a domain.
		// e.g. 2 manual backups cannot run for the same domain at the same time (time = swatch beat).
		// although technically only one command can run per domain (e.g. backup and restore cannot run at the same time).
		// we are appending the Swatch beat to the command name because this command can be run multiple times.
		// over the app's lifetime.
		// but within a swatch beat, it can only be run once.
		$command             = sprintf( '%s---%s---%d', $action, $domain, date( 'B' ) );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'copy_site_to_existing_site.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action ) );
		}

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;
	}

	/**
	 * Gets the fields to be shown.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields( array $fields, $id ) {

		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'copy-to-existing-site' ) );
		}

		// Get saved site settings.
		$saved_settings = $this->get_site_settings( $id );

		// We got here so ok to show fields related to copying the site.
		$desc  = __( 'Push this site to an existing site on this server.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'Everything on this tab will overwrite data on your destination site. In other words, everything on this tab is a destructive operation - use with care!', 'wpcd' );

		$fields[] = array(
			'name' => __( 'Copy To Existing Site', 'wpcd' ),
			'tab'  => 'copy-to-existing-site',
			'type' => 'heading',
			'desc' => $desc,
		);
		$fields[] = array(
			'name'        => __( 'Target Domain', 'wpcd' ),
			'id'          => 'wpcd_app_copy_to_site_target_domain',
			'tab'         => 'copy-to-existing-site',
			'type'        => 'text',
			'desc'        => __( 'The target domain MUST exist on the same server as the source site. An error will be thrown if we cannot find the domain on the server.', 'wpcd' ),
			'std'         => ! empty( $saved_settings[0]['target_domain'] ) ? $saved_settings[0]['target_domain'] : '',
			'save_field'  => false,
			'attributes'  => array(
				'maxlength'      => '32',
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'target_domain',
			),
			'size'        => 90,
			'placeholder' => __( 'Domain without www or http - eg: mydomain.com', 'wpcd' ),
		);

		/**
		 * Copy everything.
		 */
		$fields[] = array(
			'name' => __( 'Copy Everything', 'wpcd' ),
			'tab'  => 'copy-to-existing-site',
			'type' => 'heading',
			'desc' => __( 'Copy all files and all database tables. We will not copy wp-config.php though. Existing files on the destination that do not exist on the this domain will NOT be deleted.  However, ALL tables on the destination will be dropped and recreated.', 'wpcd' ),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_copy_to_site_full_sync',
			'name'       => __( 'Full Sync', 'wpcd' ),
			'tab'        => 'copy-to-existing-site',
			'type'       => 'select',
			'options'    => array(
				'0' => 'Full Sync Disabled',
				'1' => 'Full Sync Enabled',
			),
			'std'        => '0',
			'desc'       => __( 'A full sync will remove all files and tables in the destination that does not exist in this site.', 'wpcd' ),
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'full_sync',
			),
			'save_field' => false,
		);
		$fields[] = array(
			'id'         => 'wpcd_app_copy_to_site_full',
			'name'       => '',
			'tab'        => 'copy-to-existing-site',
			'type'       => 'button',
			'std'        => __( 'Push Everything', 'wpcd' ),
			'desc'       => '',
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'copy-site-full',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_copy_to_site_target_domain', '#wpcd_app_copy_to_site_full_sync' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to copy this site and overwrite an existing domain/site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to push this site and overwrite the specified domain...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Copy the full database.
		 */
		$fields[] = array(
			'name' => __( 'Copy Database', 'wpcd' ),
			'tab'  => 'copy-to-existing-site',
			'type' => 'heading',
			'desc' => __( 'Copy just the database to the destination site.', 'wpcd' ),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_copy_to_site_db_only',
			'name'       => '',
			'tab'        => 'copy-to-existing-site',
			'type'       => 'button',
			'std'        => __( 'Push Database', 'wpcd' ),
			'desc'       => '',
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'copy-site-db-only',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_copy_to_site_target_domain' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to copy this database to the destination site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to push the database and overwrite the specified domain...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Copy Just The Files.
		 */
		$fields[] = array(
			'name' => __( 'Copy Files Only', 'wpcd' ),
			'tab'  => 'copy-to-existing-site',
			'type' => 'heading',
			'desc' => __( 'Copy all files except those folders and files that you specifically exclude.', 'wpcd' ),
		);
		$fields[] = array(
			'name'        => __( 'Exclude These Folders', 'wpcd' ),
			'id'          => 'wpcd_app_copy_to_site_excluded_folders',
			'tab'         => 'copy-to-existing-site',
			'type'        => 'text',
			'desc'        => '',
			'std'         => ! empty( $saved_settings[0]['wpexcludefolder'] ) ? $saved_settings[0]['wpexcludefolder'] : '',
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'wpexcludefolder',
			),
			'size'        => 90,
			'placeholder' => __( 'Enter excluded folders separated by commas.', 'wpcd' ),
		);
		$fields[] = array(
			'name'        => __( 'Exclude These Files', 'wpcd' ),
			'id'          => 'wpcd_app_copy_to_site_excluded_files',
			'tab'         => 'copy-to-existing-site',
			'type'        => 'text',
			'desc'        => '',
			'std'         => ! empty( $saved_settings[0]['wpexcludefile'] ) ? $saved_settings[0]['wpexcludefile'] : '',
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'wpexcludefile',
			),
			'size'        => 90,
			'placeholder' => __( 'Enter excluded files separated by commas.', 'wpcd' ),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_copy_to_site_files_only',
			'name'       => '',
			'tab'        => 'copy-to-existing-site',
			'type'       => 'button',
			'std'        => __( 'Push Files', 'wpcd' ),
			'desc'       => '',
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'copy-site-files-only',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_copy_to_site_target_domain', '#wpcd_app_copy_to_site_excluded_folders', '#wpcd_app_copy_to_site_excluded_files' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to copy these files to the destination site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to push the database and overwrite the specified domain...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Copy only certain tables.
		 */
		$fields[] = array(
			'name' => __( 'Copy Partial Database: For the PROs only!', 'wpcd' ),
			'tab'  => 'copy-to-existing-site',
			'type' => 'heading',
			'desc' => __( 'Copy all tables except those that you specifically exclude or include.  Do NOT specify both include and exclude table sets!', 'wpcd' ),
		);
		$fields[] = array(
			'name'        => __( 'Exclude These Tables', 'wpcd' ),
			'id'          => 'wpcd_app_copy_to_site_excluded_tables',
			'tab'         => 'copy-to-existing-site',
			'type'        => 'text',
			'desc'        => '',
			'std'         => ! empty( $saved_settings[0]['wpexcludedbtable'] ) ? $saved_settings[0]['wpexcludedbtable'] : '',
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'wpexcludedbtable',
			),
			'size'        => 90,
			'placeholder' => __( 'Enter excluded tables separated by commas.', 'wpcd' ),
		);
		$fields[] = array(
			'name'        => __( 'Include These Tables', 'wpcd' ),
			'id'          => 'wpcd_app_copy_to_site_included_tables',
			'tab'         => 'copy-to-existing-site',
			'type'        => 'text',
			'desc'        => '',
			'std'         => ! empty( $saved_settings[0]['wpincludedbtable'] ) ? $saved_settings[0]['wpincludedbtable'] : '',
			'save_field'  => false,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'wpincludedbtable',
			),
			'size'        => 90,
			'placeholder' => __( 'Enter tables to copy separated by commas - only these will be copied.', 'wpcd' ),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_copy_to_site_partial_db_only',
			'name'       => '',
			'tab'        => 'copy-to-existing-site',
			'type'       => 'button',
			'std'        => __( 'Push Tables', 'wpcd' ),
			'desc'       => '',
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'copy-site-partial-db-only',
				// the id.
				'data-wpcd-id'                  => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_copy_to_site_target_domain', '#wpcd_app_copy_to_site_excluded_tables', '#wpcd_app_copy_to_site_included_tables' ) ),
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to copy these tables to the destination site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to push the database and overwrite the specified domain...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Save settings
		 */
		$fields[] = array(
			'name' => __( 'Save Settings', 'wpcd' ),
			'tab'  => 'copy-to-existing-site',
			'type' => 'heading',
			'desc' => __( 'Save these settings as the default for this site.', 'wpcd' ),
		);
		$fields[] = array(
			'id'         => 'wpcd_app_copy_to_site_save_site_settings',
			'name'       => '',
			'tab'        => 'copy-to-existing-site',
			'type'       => 'button',
			'std'        => __( 'Save For This Site', 'wpcd' ),
			'desc'       => '',
			'columns'    => 3,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action' => 'copy-site-save-site-settings',
				// the id.
				'data-wpcd-id'     => $id,
				// fields that contribute data for this action.
				'data-wpcd-fields' => wp_json_encode( array( '#wpcd_app_copy_to_site_target_domain', '#wpcd_app_copy_to_site_excluded_folders', '#wpcd_app_copy_to_site_excluded_files', '#wpcd_app_copy_to_site_excluded_tables', '#wpcd_app_copy_to_site_included_tables' ) ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Save Site Settings.
	 *
	 * Saved array will look something like this:
	 *      array (
	 *      0 =>
	 *      array (
	 *          'target_domain' => 'copytositetest01.vnxv.com',
	 *          'wpexcludefolder' => '',
	 *          'wpexcludefile' => '',
	 *          'wpexcludedbtable' => '',
	 *      ),
	 *      )
	 *
	 * @param string $action Not Used.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function save_site_settings( $action, $id ) {

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		update_post_meta( $id, 'wpcd_wpapp_copy_to_site_settings', $args );

		return new \WP_Error( __( 'Settings have been saved for this site.', 'wpcd' ) );

	}

	/**
	 * Get Saved Site Settings
	 *
	 * See the function above (save_site_settings) for the format of the array being returned.
	 *
	 * @param int $id the id of the app post being handled.
	 *
	 * @return array|object array of settings retrieved from the post/site.
	 */
	public function get_site_settings( $id ) {

		$args = maybe_unserialize( get_post_meta( $id, 'wpcd_wpapp_copy_to_site_settings' ) );
		return $args;

	}

}

new WPCD_WORDPRESS_TABS_COPY_TO_EXISTING_SITE();
