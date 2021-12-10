<?php
/**
 * Update plugins and themes tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_THEME_AND_PLUGIN_UPDATES
 */
class WPCD_WORDPRESS_TABS_THEME_AND_PLUGIN_UPDATES extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

		// Add bulk action option to the site list to update themes and plugins.
		add_filter( 'bulk_actions-edit-wpcd_app', array( $this, 'wpcd_add_new_bulk_actions_site' ) );

		// Action hook to handle bulk actions for site.
		add_filter( 'handle_bulk_actions-edit-wpcd_app', array( $this, 'wpcd_bulk_action_handler_sites' ), 10, 3 );

		// Allow the update themes/plugins action to be triggered via an action hook.  Will primarily be used by Bulk Actions.
		add_action( 'wpcd_wordpress-update-themes-and-plugins', array( $this, 'update_site' ), 10, 2 );

		/* Pending Logs Background Task: Trigger plugin and theme updates */
		add_action( 'pending_log_update_themes_and_plugins', array( $this, 'pending_log_update_themes_and_plugins' ), 10, 3 );

		/* Handle callback success and tag the pending log record as successful */
		add_action( 'wpcd_app_wordpress-app_update_themes_and_plugins_successful', array( $this, 'handle_update_themes_and_plugins_success' ), 10, 3 );

		/* Handle callback failure and tag the pending log record as failed */
		add_action( 'wpcd_app_wordpress-app_update_themes_and_plugins_failed', array( $this, 'handle_update_themes_and_plugins_failed' ), 10, 3 );

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

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905.
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run.
		// [1] => cf1110.wpvix.com.
		// [2] => 911.
		$command_array = explode( '---', $name );

		// If the command is to update themes and plugins then we just need to trigger an action for pending logs.
		$action = $command_array[0];
		if ( in_array( $action, $this->get_valid_actions(), true ) ) {
			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = (bool) $this->is_ssh_successful( $logs, 'reliable_updates.txt' );

			if ( true === $success ) {
				do_action( 'wpcd_app_wordpress-app_update_themes_and_plugins_successful', $id, $action, $success );
			} else {
				do_action( 'wpcd_app_wordpress-app_update_themes_and_plugins_failed', $id, $action, $success );
			}
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
		return 'theme-and-plugin-updates';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_updates_tab';
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
				'label' => __( 'Site Updates', 'wpcd' ),
				'icon'  => 'fad fa-snowplow',
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
		$valid_actions = array( 'update-everything', 'update-themes-and-plugins', 'update-themes', 'update-plugins', 'update-wordpress' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( false === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && false === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) ) {
			switch ( $action ) {
				case 'update-everything':
					$action = 'update_everything';
					$result = $this->update_site( $action, $id );
					break;
				case 'update-themes-and-plugins':
					$action = 'update_themes_and_plugins';
					$result = $this->update_site( $action, $id );
					break;
				case 'update-themes':
					$action = 'update_allthemes';
					$result = $this->update_site( $action, $id );
					break;
				case 'update-plugins':
					$action = 'update_allplugins';
					$result = $this->update_site( $action, $id );
					break;
				case 'update-wordpress':
					$action = 'update_wordpress';
					$result = $this->update_site( $action, $id );
					break;

			}
		}
		return $result;

	}


	/**
	 * Returns an array of actions that is valid for this tab.
	 */
	public function get_valid_actions() {
		return array( 'update_everything', 'update_themes_and_plugins', 'update_allthemes', 'update_allplugins', 'update_wordpress' );
	}

	/**
	 * Returns an array of actions that is valid for the bulk actions menu.
	 * It's basically the same as the get_valid_actions() function above but
	 * with the actions prefixed by "wpcd_sites_".
	 */
	public function get_valid_bulk_actions() {
		return array( 'wpcd_sites_update_everything', 'wpcd_sites_update_themes_and_plugins', 'wpcd_sites_update_allthemes', 'wpcd_sites_update_allplugins', 'wpcd_sites_update_wordpress' );
	}

	/**
	 * Update themes and plugins.
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function update_site( $action, $id ) {

		// Data is passed from front-end via a 'params' element in $_POST.  So extract it and sanitize it here.
		if ( ! empty( $_POST['params'] ) ) {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = array();
		}

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// If no domain then return error.
		if ( empty( $domain ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the domain name for action %s', 'wpcd' ), $action ) );
		}

		// Get an array of credentials and buckets.
		$creds  = $this->get_s3_credentials_for_backup( $id );  // Function get_s3_credentials_for_backup is located in a trait file.
		$key    = $creds['aws_access_key_id'];
		$secret = $creds['aws_secret_access_key'];
		$bucket = $creds['aws_bucket_name'];

		// Now, fill in all the other items that the bash script needs to run properly.
		$args['update_type']      = '1';
		$args['api_userid']       = escapeshellarg( wpcd_get_option( 'wordpress_app_hcti_api_user_id' ) );
		$args['api_key']          = escapeshellarg( wpcd_get_option( 'wordpress_app_hcti_api_key' ) );
		$args['excluded_plugins'] = escapeshellarg( wpcd_get_option( 'wordpress_app_plugin_updates_excluded_list' ) );
		$args['excluded_themes']  = escapeshellarg( wpcd_get_option( 'wordpress_app_theme_updates_excluded_list' ) );
		$threshold                = wpcd_get_option( 'wordpress_app_tandc_updates_pixel_threshold' );
		if ( ! empty( $threshold ) ) {
			$args['threshold'] = escapeshellarg( $threshold );
		} else {
			$args['threshold'] = '1000';
		}

		// If we don't have an api user id or key for the htci service, then make the update_type 2 (which allows for optional rollbacks).
		if ( empty( wpcd_get_option( 'wordpress_app_hcti_api_user_id' ) ) && empty( wpcd_get_option( 'wordpress_app_hcti_api_key' ) ) ) {
			$args['update_type'] = '2';
		}

		// callback url to let us know the status of the updates...
		$callback_command_name       = 'theme_plugin_updates';
		$args['status_callback_url'] = $this->get_command_url( $id, $callback_command_name, 'completed' );

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
			'reliable_updates.txt',
			array_merge(
				$args,
				$creds,
				array(
					'command' => $command,
					'action'  => escapeshellarg( $action ),
					'domain'  => escapeshellarg( $domain ),
				)
			)
		);

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			/* translators: %s is replaced with the internal action name. */
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
			return array_merge( $fields, $this->get_disabled_header_field( 'update-site' ) );
		}

		// We got here so ok to show fields related to updating the site.
		$desc  = __( 'Update Themes & Plugins is an experimental feature. Please make sure you read our documentation before using it.', 'wpcd' );
		$desc .= '<br />';
		$desc .= '<br />';
		$desc .= sprintf( '<a href="%s">%s</a>', apply_filters( 'wpcd_documentation_links', 'https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/theme-plugin-updates/' ), __( 'View Documentation', 'wpcd' ) );

		$fields[] = array(
			'name' => __( 'About Updating Themes & Plugins', 'wpcd' ),
			'tab'  => 'theme-and-plugin-updates',
			'type' => 'heading',
			'desc' => $desc,
		);

		/**
		 * Update Everything
		 */

		$fields[] = array(
			'name' => __( 'Update Everything', 'wpcd' ),
			'tab'  => 'theme-and-plugin-updates',
			'type' => 'heading',
			'desc' => __( 'Update WordPress, all themes and all plugins - except those specifically excluded in the settings screen.', 'wpcd' ),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_update_everything',
			'name'       => '',
			'tab'        => 'theme-and-plugin-updates',
			'type'       => 'button',
			'std'        => __( 'Update Everything', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'update-everything',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to run all updates for this site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to update your site...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Update All Themes and All Plugins
		 */

		$fields[] = array(
			'name' => __( 'Update Themes and Plugins', 'wpcd' ),
			'tab'  => 'theme-and-plugin-updates',
			'type' => 'heading',
			'desc' => __( 'Update all themes and all plugins only - except those specifically excluded in the settings screen.', 'wpcd' ),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_update_themes_and_plugins',
			'name'       => '',
			'tab'        => 'theme-and-plugin-updates',
			'type'       => 'button',
			'std'        => __( 'Update', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'update-themes-and-plugins',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to run all theme & plugin updates for this site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to update your site...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Update Themes Only
		 */

		$fields[] = array(
			'name' => __( 'Update Themes', 'wpcd' ),
			'tab'  => 'theme-and-plugin-updates',
			'type' => 'heading',
			'desc' => __( 'Update all themes only - except those specifically excluded in the settings screen.', 'wpcd' ),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_update_themes',
			'name'       => '',
			'tab'        => 'theme-and-plugin-updates',
			'type'       => 'button',
			'std'        => __( 'Update Themes', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'update-themes',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to update all themes for this site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to update your site...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Update Plugins Only
		 */

		$fields[] = array(
			'name' => __( 'Update Plugins', 'wpcd' ),
			'tab'  => 'theme-and-plugin-updates',
			'type' => 'heading',
			'desc' => __( 'Update all plugins only - except those specifically excluded in the settings screen.', 'wpcd' ),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_update_plugins',
			'name'       => '',
			'tab'        => 'theme-and-plugin-updates',
			'type'       => 'button',
			'std'        => __( 'Update Plugins', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'update-plugins',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to update all plugins for this site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to update your site...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Update WordPress Only
		 */

		$fields[] = array(
			'name' => __( 'Update WordPress', 'wpcd' ),
			'tab'  => 'theme-and-plugin-updates',
			'type' => 'heading',
			'desc' => __( 'Update WordPress Only.', 'wpcd' ),
		);

		$fields[] = array(
			'id'         => 'wpcd_app_update_wordpress',
			'name'       => '',
			'tab'        => 'theme-and-plugin-updates',
			'type'       => 'button',
			'std'        => __( 'Update WordPress', 'wpcd' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'update-wordpress',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to update WordPress on this site?', 'wpcd' ),
				// show log console?
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to update your site...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Add new bulk options in site list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function wpcd_add_new_bulk_actions_site( $bulk_array ) {

		if ( wpcd_is_admin() ) {
			$bulk_array['wpcd_sites_update_themes_and_plugins'] = __( 'Update All Themes and Plugins', 'wpcd' );
			$bulk_array['wpcd_sites_update_allthemes']          = __( 'Update All Themes', 'wpcd' );
			$bulk_array['wpcd_sites_update_allplugins']         = __( 'Update All Plugins', 'wpcd' );
			$bulk_array['wpcd_sites_update_wordpress']          = __( 'Update WordPress', 'wpcd' );
			$bulk_array['wpcd_sites_update_everything']         = __( 'Update All Themes, Plugins & WordPress', 'wpcd' );
			return $bulk_array;
		}

	}

	/**
	 * Handle bulk actions for sites.
	 *
	 * @param string $redirect_url  redirect url.
	 * @param string $action        bulk action slug/id - this is not the WPCD action key.
	 * @param array  $post_ids      all post ids.
	 */
	public function wpcd_bulk_action_handler_sites( $redirect_url, $action, $post_ids ) {
		// Let's remove query args first for redirect url.
		$redirect_url = remove_query_arg( array( 'wpcd_update_themes_and_plugins' ), $redirect_url );

		// Lets make sure we're an admin otherwise return an error.
		if ( ! wpcd_is_admin() ) {
			do_action( 'wpcd_log_error', 'Someone attempted to run a function that required admin privileges.', 'security', __FILE__, __LINE__ );

			// Show error message to user at the top of the admin list as a dismissible notice.
			wpcd_global_add_admin_notice( __( 'You attempted to run a function that requires admin privileges.', 'wpcd' ), 'error' );

			return $redirect_url;
		}

		// Update themes and plugins.
		if ( in_array( $action, $this->get_valid_bulk_actions(), true ) ) {

			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $app_id ) {
					$args['action_hook'] = 'pending_log_update_themes_and_plugins';
					$args['action']      = $action;

					// Remove the 'wpcd_sites_' from the action string - we'll use it in the message we print in the pending logs table.
					$printed_action = str_replace( 'wpcd_sites_', '', $action );
					/* Translators: %s is an internal action name. */
					WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'update-themes-and-plugins', $app_id, $args, 'ready', $app_id, sprintf( __( 'Update themes and plugins From Bulk Operation. Scope of Updates: %s', 'wpcd' ), $printed_action ) );
				}

				// Add message to be displayed in admin header.
				wpcd_global_add_admin_notice( __( 'Updates have been scheduled for the selected sites. You can view the progress in the PENDING LOG screen.', 'wpcd' ), 'success' );

			}
		}

		return $redirect_url;
	}

	/**
	 * Update themes and plugins - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: pending_log_update_themes_and_plugins
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $app_id     Id of site on which this action will apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function pending_log_update_themes_and_plugins( $task_id, $app_id, $args ) {

		// Grab our data array from pending tasks record...
		$data   = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );
		$action = $data['action']; // This element is set by the wpcd_bulk_action_handler_sites() function above.

		if ( ! empty( $action ) ) {
			// Remove the 'wpcd' prefix from the action in the $data array - this element is set by the wpcd_bulk_action_handler_sites() function above.
			$action = str_replace( 'wpcd_sites_', '', $action );
			if ( ! empty( $action ) ) {
				/* Update themes and plugins */
				do_action( 'wpcd_wordpress-update-themes-and-plugins', $action, $app_id );
			} else {
				$this->handle_update_themes_and_plugins_failed( $app_id, $action, array() );
			}
		} else {
			$this->handle_update_themes_and_plugins_failed( $app_id, $action, array() );
		}

	}

	/**
	 * Handle theme and plugin updates successful
	 *
	 * Action Hook: wpcd_app_wordpress-app_update_themes_and_plugins_failed || wpcd_app_wordpress-app_update_themes_and_plugins_failed
	 *
	 * @param int     $app_id               Post id of app.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the update function (actually the action hook that is called after a command is complete - function command_completed() above.).
	 */
	public function handle_update_themes_and_plugins_success( $app_id, $action, $success_msg_array ) {
		$this->handle_update_themes_and_plugins_install_success_or_failure( $app_id, $action, $success_msg_array, 'success' );
	}

	/**
	 * Handle theme and plugin updates failed
	 *
	 * Action Hook: wpcd_app_wordpress-app_update_themes_and_plugins_failed || wpcd_app_wordpress-app_update_themes_and_plugins_failed
	 *
	 * @param int     $app_id               Post id of app.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (actually the action hook that is called after a command is complete - function command_completed() above.).
	 */
	public function handle_update_themes_and_plugins_failed( $app_id, $action, $success_msg_array ) {
		$this->handle_update_themes_and_plugins_install_success_or_failure( $app_id, $action, $success_msg_array, 'failed' );
	}

	/**
	 * Handle server status callback install successful or failed when being processed from pending logs / via action hooks.
	 *
	 * @param int     $app_id               Post id of app.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (actually the action hook that is called after a command is complete - function command_completed() above.).
	 * @param boolean $success              Was the callback installation a sucesss or failure.
	 */
	public function handle_update_themes_and_plugins_install_success_or_failure( $app_id, $action, $success_msg_array, $success ) {

		$app_post = get_post( $app_id );

		// Bail if not a post object.
		if ( ! $app_id || is_wp_error( $app_id ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_app_type( $app_id ) ) {
			return;
		}

		// This only matters if we were updating themes and plugins.  If not, then bail.
		if ( ! in_array( $action, $this->get_valid_actions(), true ) ) {
			return;
		}

		// Get server instance array.
		$instance = $this->get_app_instance_details( $app_id );

		if ( 'wpcd_app' === get_post_type( $app_id ) ) {

				// Now check the pending tasks table for a record where the key=$app_id and type='update-themes-and-plugins' and state='in-process'
				// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
				$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $app_id, 'in-process', 'update-themes-and-plugins' );

			if ( $posts ) {
				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				// And mark it as successful or failed.
				if ( 'failed' === $success ) {
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );
				} else {
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );
				}
			}
		}

	}

}

new WPCD_WORDPRESS_TABS_THEME_AND_PLUGIN_UPDATES();
