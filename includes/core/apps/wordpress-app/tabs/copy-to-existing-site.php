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

		/* Execute update plan:  Push template site to server */
		add_action( 'execute_update_plan_push_template_to_server', array( $this, 'execute_update_plan_push_template_to_server' ), 10, 3 );

		/* Execute update plan: When a site sync (push template site to server) is complete it's time to change the domain and do some other stuff. */
		add_action( 'wpcd_wordpress-app_site_sync_new_post_completed', array( $this, 'site_sync_complete' ), 100, 3 ); // Priority set to run after almost everything else.

		/* Execute update plan: When a domain change is complete from a template site, update the site records to contain all the other data it need. */
		add_action( 'wpcd_wordpress-app_site_change_domain_completed', array( $this, 'execute_update_plan_site_change_domain_complete' ), 10, 4 );

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
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Copy To Existing Site', 'wpcd' ),
				'icon'  => 'fad fa-copy',
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
		$valid_actions = array( 'copy-site-full', 'copy-site-partial', 'copy-site-files-only', 'copy-site-db-only', 'copy-site-partial-db-only', 'copy-site-save-site-settings', 'copy-site-execute-update-plan', 'copy-site-execute-update-plan-dry-run' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
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
					break;
				case 'copy-site-execute-update-plan':
					$result = $this->execute_update_plan( $action, $id );
					break;
				case 'copy-site-execute-update-plan-dry-run':
					$result = $this->execute_update_plan_dry_run( $action, $id );
					break;
			}
		}
		return $result;

	}

	/**
	 * Copy a site over an existing site on the same server.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	private function copy_to_existing_site( $action, $id, $in_args = array() ) {

		// Get data from POST request or from incoming args array.
		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

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

		/**
		 * Let developers hook to run custom code and change the args if necessary before running the command.
		 * Hook Name: wpcd_app_wordpress-app_before_action_copy_to_existing_site
		 */
		$args = apply_filters( "wpcd_app_{$this->get_app_name()}_before_action_copy_to_existing_site", $args, $action, $id, $instance, $domain, $original_args );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
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
	 * Do a dry-run of an update plan to show the servers and sites that
	 * will be affected.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function execute_update_plan_dry_run( $action, $id ) {

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Bail if certain things are empty...
		if ( empty( $args['site_update_plan'] ) ) {
			return new \WP_Error( __( 'You must provide a site update plan.', 'wpcd' ) );
		} else {
			$update_plan_id = $args['site_update_plan'];
		}

		$servers_and_sites = WPCD_APP_UPDATE_PLAN()->get_server_and_sites( $update_plan_id );

		set_transient( 'wpcd_execute_update_plan_dry_run', $servers_and_sites, 60 );  // We'll rad this transient when the screen refreshs.

		$result = array( 'refresh' => 'yes' );

		return $result;

	}

	/**
	 * Execute an update plan.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function execute_update_plan( $action, $id ) {

		// Get data from the POST request.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Bail if certain things are empty...
		if ( empty( $args['site_update_plan'] ) ) {
			return new \WP_Error( __( 'You must provide a site update plan.', 'wpcd' ) );
		} else {
			$update_plan_id = $args['site_update_plan'];
		}

		// What's this domain?
		$template_domain = WPCD_WORDPRESS_APP()->get_domain_name( $id );
		if ( empty( $template_domain ) ) {
			return new \WP_Error( __( 'We were unable to determine the domain for this template site - which is unsual.', 'wpcd' ) );
		}

		// What server is this application on?
		$server_id = WPCD_WORDPRESS_APP()->get_server_id_by_app_id( $id );

		/**
		 * Array format returned from this function call will be as follows:
		 * array(
		 *      servers => array ( 'server_title' => server_id, 'server_title' => server_id ),
		 *      sites   => array ( 'domain' => site_id, 'domain' => site_id ),
		 *      mapped_server_to_sites = array( server_id[site_id] => domain ),
		 * )
		 */
		$servers_and_sites = WPCD_APP_UPDATE_PLAN()->get_server_and_sites( $update_plan_id );
		$maps              = $servers_and_sites['mapped_server_to_sites'];

		foreach ( $maps as $target_server_id => $sites ) {
				// Add some stuff to the $args array.
				$args['wp_template_app_id']             = $id;                      // Add the source of the template to the array.
				$args['author']                         = get_current_user_id();    // Who is going to own this site?  We'll need this later after the template is copied and domain changed.
				$args['site_sync_destination']          = $target_server_id;        // Which server will we be copying the template site to?
				$args['sec_source_dest_check_override'] = 1;                        // Disable some server level security checks in the site-sync program.
				$args['update_plan_id']                 = $update_plan_id;
				$args['update_plan_sites']              = $sites;

				// Setup task that will store data to pass to the next task in sequence.
				// The site-sync core function will see this and create a task in the pending tasks log for us to be able to link back to this even later.
				$args['pending_tasks_type'] = 'execute-update-plan-get-data-after-push-template-to-server'; 

				/* Setup pending task to push the template to the target server. */
				$args['action_hook']     = 'execute_update_plan_push_template_to_server';
				$new_pending_task_status = 'ready';
				WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'execute-update-plan-push-template-to-server', $template_domain, $args, $new_pending_task_status, $target_server_id, sprintf( __( 'Executing update plan - push template to server: %s', 'wpcd' ), WPCD_SERVER()->get_server_name( $target_server_id ) ) );

		}

		$result = array( 'refresh' => 'yes' );

		return $result;

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

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
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

		/**
		 * Bulk Push Themes & Plugins using an app/site update plan.
		 */
		if ( class_exists( 'WPCD_WooCommerce_Init' ) ) {
			$fields[] = array(
				'name' => '',
				'tab'  => 'copy-to-existing-site',
				'type' => 'custom_html',
				'std'  => '<hr/>',
			);

			$fields[] = array(
				'name' => __( 'Execute Update Plan', 'wpcd' ),
				'tab'  => 'copy-to-existing-site',
				'type' => 'heading',
				'desc' => __( 'Execute an update plan on multiple sites. You generally use this in a SaaS project where you have individual sites deployed and you need to update plugins and themes on those sites to match your template.', 'wpcd' ),
			);

			$fields[] = array(
				'name'       => __( 'Select and update plan', 'wpcd' ),
				'id'         => 'wpcd_app_copy_to_site_update_plan',
				'tab'        => 'copy-to-existing-site',
				'type'       => 'post',
				'post_type'  => 'wpcd_app_update_plan',
				'query_args' => array(
					'posts_per_page' => - 1,
				),
				'field_type' => 'select_advanced',
				'desc'       => '',
				'save_field' => false,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-wpcd-name' => 'site_update_plan',
				),
				'columns'    => 4,
			);

			$fields[] = array(
				'id'         => 'wpcd_app_copy_to_site_execute_update_plan',
				'name'       => __( 'Execute Plan', 'wpcd' ),
				'tab'        => 'copy-to-existing-site',
				'type'       => 'button',
				'std'        => __( 'Execute', 'wpcd' ),
				'desc'       => '',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'copy-site-execute-update-plan',
					// the id.
					'data-wpcd-id'                  => $id,
					// fields that contribute data for this action.
					'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_copy_to_site_update_plan' ) ),
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to execute this update plan?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => false,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
				'columns'    => 4,
			);

			$fields[] = array(
				'id'         => 'wpcd_app_copy_to_site_execute_update_plan_dry_run',
				'name'       => __( 'Dry Run', 'wpcd' ),
				'tab'        => 'copy-to-existing-site',
				'type'       => 'button',
				'std'        => __( 'Execute Plan - Dry Run', 'wpcd' ),
				'desc'       => '',
				'tooltip'    => __( 'This will list out all the servers and sites that will be affected by this action.', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'copy-site-execute-update-plan-dry-run',
					// the id.
					'data-wpcd-id'                  => $id,
					// fields that contribute data for this action.
					'data-wpcd-fields'              => wp_json_encode( array( '#wpcd_app_copy_to_site_update_plan' ) ),
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to execute a dry-run this update plan?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => false,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
				'columns'    => 4,
			);

			// Maybe show results of dry run that is held in a transient.
			$last_dry_run_results = get_transient( 'wpcd_execute_update_plan_dry_run' );
			if ( ! empty( $last_dry_run_results ) ) {
				// Get dry run results for sites and servers.
				$sites                    = $this->get_update_plan_dry_run_sites_as_string( $last_dry_run_results );
				$servers                  = $this->get_update_plan_dry_run_servers_as_string( $last_dry_run_results );
				$mapped_servers_and_sites = $this->get_update_plan_dry_run_mapped_servers_and_sites_as_string( $last_dry_run_results );

				// Setup fields to show data from transient.
				$fields[] = array(
					'name' => __( 'Dry Run Results', 'wpcd' ),
					'tab'  => 'copy-to-existing-site',
					'type' => 'heading',
					'desc' => __( 'Sites and servers that would be affected based on last dry run request.', 'wpcd' ),
				);

				$fields[] = array(
					'name'    => __( 'Affected Servers', 'wpcd' ),
					'tab'     => 'copy-to-existing-site',
					'type'    => 'custom_html',
					'std'     => $servers,
					'columns' => 6,
				);

				$fields[] = array(
					'name'    => __( 'Affected Sites', 'wpcd' ),
					'tab'     => 'copy-to-existing-site',
					'type'    => 'custom_html',
					'std'     => $sites,
					'columns' => 6,
				);

				$fields[] = array(
					'name' => __( 'Affected Servers & Sites ', 'wpcd' ),
					'tab'  => 'copy-to-existing-site',
					'type' => 'custom_html',
					'std'  => $mapped_servers_and_sites,
				);

			}
		}

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

	/**
	 * Take the results of an update plan and return the list of sites as a string
	 * delimted by html breaks.
	 *
	 * Incomming array format will be as follows:
	 * array(
	 *      servers => array ( 'server_title' => server_id, 'server_title' => server_id ),
	 *      sites   => array ( 'domain' => site_id, 'domain' => site_id ),
	 *      mapped_server_to_sites = array( server_id[site_id] => domain ),
	 * )
	 *
	 * @param array $dry_run Array with elements formatted as described above.
	 */
	public function get_update_plan_dry_run_sites_as_string( $dry_run ) {

		// Extract the sites array.
		$sites = $dry_run['sites'];

		// Setup blank return string.
		$return = '';

		// Loop through and create string.
		foreach ( $sites as $domain => $site_id ) {
			$return .= empty( $return ) ? $domain : '<br />' . $domain;
		}

		return $return;

	}

	/**
	 * Take the results of an update plan and return the list of servers as a string
	 * delimted by html breaks.
	 *
	 * Incomming array format will be as follows:
	 * array(
	 *      servers => array ( 'server_title' => server_id, 'server_title' => server_id ),
	 *      sites   => array ( 'domain' => site_id, 'domain' => site_id ),
	 *      mapped_server_to_sites = array( server_id[site_id] => domain ),
	 * )
	 *
	 * @param array $dry_run Array with elements formatted as described above.
	 */
	public function get_update_plan_dry_run_servers_as_string( $dry_run ) {

		// Extract the sites array.
		$sites = $dry_run['servers'];

		// Setup blank return string.
		$return = '';

		// Loop through and create string.
		foreach ( $sites as $server_name => $server_id ) {
			$return .= empty( $return ) ? $server_name : '<br />' . $server_name;
		}

		return $return;

	}

	/**
	 * Take the results of an update plan and return the list of mapped servers
	 * and sites as a string delimted by html breaks.
	 *
	 * Incomming array format will be as follows:
	 * array(
	 *      servers => array ( 'server_title' => server_id, 'server_title' => server_id ),
	 *      sites   => array ( 'domain' => site_id, 'domain' => site_id ),
	 *      mapped_server_to_sites = array( server_id[site_id] => domain ),
	 * )
	 *
	 * @param array $dry_run Array with elements formatted as described above.
	 */
	public function get_update_plan_dry_run_mapped_servers_and_sites_as_string( $dry_run ) {

		// Extract the sites array.
		$maps = $dry_run['mapped_server_to_sites'];

		// Setup blank return string.
		$return = '';

		// Loop through and create string.
		foreach ( $maps as $server_id => $sites ) {
			$server_name = WPCD_WORDPRESS_APP()->get_server_name( $server_id );
			$return     .= empty( $return ) ? '<b>' . $server_name . '</b>' : '<br />' . '<b>' . $server_name . '</b>';
			foreach ( $sites as $site_id => $domain ) {
				$return .= '<br />' . $domain;
			}
			$return .= '<br />';
		}

		return $return;

	}

	/**
	 * Push a template site to a target server..
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: execute_update_plan_push_template_to_server
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function execute_update_plan_push_template_to_server( $task_id, $server_id, $args ) {
		/* Now fire the action located in the includes/core/apps/wordpress-app/tabs/site-sync.php file to copy the template site. */
		do_action( 'wpcd_wordpress-app_do_site_sync', $args['wp_template_app_id'], $args );
	}

	/**
	 * When a site sync is complete we need to change the domain if:
	 * 1. It was because of a update plan execution.
	 * 2. n/a
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_site_sync_new_post_completed || wpcd_wordpress-app_site_sync_new_post_completed
	 *
	 * @param int    $new_app_post_id    The post id of the new app record.
	 * @param int    $id                 ID of the template site (source site being synced to a destination server).
	 * @param string $name               The command name.
	 */
	public function site_sync_complete( $new_app_post_id, $id, $name ) {

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run
		// [1] => cf1110.wpvix.com
		// [2] => 911
		$command_array = explode( '---', $name );

		// if the command is to copy a site to a new server then we need to do some things.
		if ( 'site-sync' == $command_array[0] ) {

			// Lets pull the logs.
			$logs = WPCD_WORDPRESS_APP()->get_app_command_logs( $id, $name );

			// Was the command successful?
			$success = WPCD_WORDPRESS_APP()->is_ssh_successful( $logs, 'site_sync.txt' );

			if ( $success === true ) {

				// What server is this application on?
				$server_id = WPCD_WORDPRESS_APP()->get_server_id_by_app_id( $id );

				// Get domain name of the original site.
				$original_domain = WPCD_WORDPRESS_APP()->get_domain_name( $id );

				// Now check the pending tasks table for a record where the key=$name and type='execute-update-plan-after-push-template-to-server' and state='not-ready'.
				// This allows us to pull data saved by the site-sync process for use here.
				$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $name, 'not-ready', 'execute-update-plan-get-data-after-push-template-to-server' );

				/**
				 * Start process of changing domain on the copied template site. We're assuming $posts has one and only one item in it!
				 * If we got a record in here we have satisfied the criteria we outlined at the top of this function in order to proceed.
				 */
				if ( $posts ) {

					// Grab our data array from pending tasks record.
					$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $posts[0]->ID );

					// Set the new domain.
					$new_domain = WPCD_DNS()->get_full_temp_domain( 6 );
					if ( empty( $new_domain ) ) {
						// We'll just make something up here since we have no domain string.
						$new_domain = WPCD_DNS()->get_subdomain() . 'dev';
					} else {
						// We'll addd a prefix to the temporary domain name to make it easier that this is associated with an update plan.
						// Maybe later we'll be able to add in a category/tag/group label instead.
						$new_domain = 'updateplan-' . $new_domain;
					}
					$data['new_domain'] = $new_domain;

					// The domain change core function will see that 'pending_tasks_type' element in our data array and create a new pending record.
					// This pending record is used to pass data to the next task in the sequence (see function execute_update_plan_site_change_domain_complete() below).
					$args['pending_tasks_type'] = 'execute-update-plan-get-data-after-template-domain-change';

					// Mark our get-data pending record as complete.  Later, when the domain change is complete it will set a new pending record.
					$data_to_save = $data;
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $posts[0]->ID, $data_to_save, 'complete' );

					// Locate the original 'execute-update-plan-push-template-to-server' task record and mark it complete.
					// This task was added in function execute_update_plan() above (or somewhere in this file/class).
					$original_task = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $original_domain, 'in-process', 'execute-update-plan-push-template-to-server' );
					if ( $original_task ) {
						WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $original_task[0]->ID, array(), 'complete' );
					}

					// Action hook to fire: wpcd_wordpress-app_do_change_domain_full_live - need $id and $args ($data).
					do_action( 'wpcd_wordpress-app_do_change_domain_full_live', $new_app_post_id, $data );
				}
			}
		}

	}


	/**
	 * When a domain change is complete from a template site, update the site records to contain all the other data it needs.
	 *
	 * We should only do this if we're executing an update plan.
	 *
	 * *** Note that changes to this function might also need to be done to the the following functions in the
	 * *** woocommerce addon:
	 * - site_change_domain_complete()
	 * - clone_site_complete()
	 *
	 * Filter Hook: wpcd_{$this->get_app_name()}_site_change_domain_completed | wpcd_wordpress-app_site_change_domain_completed
	 *
	 * @param int    $id The id of the post app.
	 * @param string $old_domain The domain we're changing from.
	 * @param string $new_domain The domain we're changing to.
	 * @param string $name The name of the command that was executed - it contains parts that we might need later.
	 */
	public function execute_update_plan_site_change_domain_complete( $id, $old_domain, $new_domain, $name ) {

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run
		// [1] => cf1110.wpvix.com
		// [2] => 911
		$command_array = explode( '---', $name );

		// Check to see if the command is to replace a domain otherwise exit.
		if ( 'replace_domain' == $command_array[0] ) {

			// Lets pull the logs.
			$logs = WPCD_WORDPRESS_APP()->get_app_command_logs( $id, $name );

			// Was the command successful?
			$success = WPCD_WORDPRESS_APP()->is_ssh_successful( $logs, 'change_domain_full.txt' );

			if ( true == $success ) {
				// now check the pending tasks table for a record where the key=$name and type='execute-update-plan-get-data-after-template-domain-change' and state='not-ready'.
				// This allows us to pull data saved by the prior task.
				$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $name, 'not-ready', 'execute-update-plan-get-data-after-template-domain-change' );

				/**
				 * Start process of updating the app cpt record. We're assuming $posts has one and only one item in it!
				 * If we got a record in here we have satisfied the criteria we outlined at the top of this function in order to proceed.
				 */
				if ( $posts ) {

					// Grab our data array from pending tasks record...
					$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $posts[0]->ID );

					/* Add cross-reference data to order lines and subscription lines */
					$this->add_wp_data_to_wc_orders( $id, $data );

					/**
					 * Now update the app record with new data about the user, passwords etc.
					 *
					 * Changes in this code block might need to be done in the
					 * clone_site_complete() function just above.
					 */
					// Start by getting the app post to make sure it's valid.
					$app_post = get_post( $id );

					if ( $app_post ) {
						// reset the author since it probably has data from the template site.
						$author    = get_user_by( 'email', $data['wp_email'] )->ID;
						$post_data = array(
							'ID'          => $id,
							'post_author' => $author,
						);
						wp_update_post( $post_data );

						// Handle Post Template Copy Actions including adding a new admin user.
						$this->do_after_copy_template_actions( $id, $data );

						// @TODO - do we need to copy teams from the template site?  Probably not.

						// Update domain, user id, password, email etc...
						$update_items = array(
							'wpapp_domain'          => $data['wp_domain'],
							'wpapp_original_domain' => $data['wp_domain'],
							'wpapp_email'           => $data['wp_email'],
						);
						foreach ( $update_items as $metakey => $value ) {
							update_post_meta( $id, $metakey, $value );
						}
					}
					/* End update the app record with new data */

					/**
					 * Maybe convert site to an mt tenant.
					 * If this proves to take a long time, causing timeouts,
					 * we might have to restructure to use a background process instead.
					 * Changes here might need to be made to the clone_site_complete()
					 * function above.
					 *
					 * Note: this is supposed to be a template site so doubt that it
					 * needs to be converted.  But keeping this code in here just in case.
					 */
					$this->maybe_convert_to_tenant( $id );

					/**
					 * Need to add the individual site records that need their plugins/themes updated.
					 */

					// Mark our get-data pending record as complete.
					$data_to_save = $data;
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $posts[0]->ID, $data_to_save, 'complete' );

				}
			}
		}

	}

	/**
	 * Perhaps convert a site to a tenant in an MT tenant situation.
	 *
	 * *** Changes to this function should probably be done to the same
	 * *** function [maybe_convert_to_tenant()] in the wpcd woocommerce add-on.
	 *
	 * @param int $id Postid of site that we might convert to a tenant.
	 */
	public function maybe_convert_to_tenant( $id ) {

		if ( false === wpcd_is_mt_enabled() ) {
			return;
		}

		/**
		 * Is there a parent id meta?
		 * The presence of a mt parent meta value is what tells us that
		 * The site should be an MT site.
		 */
		$parent_id = WPCD_WORDPRESS_APP()->get_mt_parent( $id );

		if ( ! empty( $parent_id ) ) {
			$args['mt_product_template'] = $parent_id;
			$args['mt_version']          = WPCD_WORDPRESS_APP()->get_mt_version( $id );
			/* Now fire the action located in the includes/core/apps/wordpress-app/tabs/multitenant-site.php file to convert the site. */
			do_action( 'wpcd_wordpress-app_do_mt_apply_version', $id, $args );
		}

	}
}

new WPCD_WORDPRESS_TABS_COPY_TO_EXISTING_SITE();
