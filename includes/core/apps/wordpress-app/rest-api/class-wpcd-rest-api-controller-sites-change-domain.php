<?php
/**
 * WordPress App WPCD_REST_API_Controller_Sites_Change_Domain.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_REST_API_Controller_Sites_Change_Domain
 *
 * Endpoints for interacting with sites (WordPress wpcd_app)
 */
class WPCD_REST_API_Controller_Sites_Change_Domain extends WPCD_REST_API_Controller_Sites {

	/**
	 * WPCD_REST_API_Controller_Sites_Change_Domain constructor.
	 *
	 * Registers action hooks.
	 */
	public function __construct() {
		parent::__construct();

		/* Hooks related to changing domain. */
		add_action( 'wpcd_wordpress-app_rest_api_pending_log_change_domain_full_live', array( $this, 'pending_log_change_domain_start' ), 10, 3 );  // Trigger the action hook that starts the domain change operation.
		add_action( 'wpcd_wordpress-app_site_change_domain_failed', array( $this, 'handle_domain_change_failed' ), 10, 4 );  // If the domain change failed, mark the pending log record as failed.
		add_action( 'wpcd_wordpress-app_site_change_domain_completed', array( $this, 'handle_domain_change_successful' ), 10, 4 );  // If the domain change is successful, mark the pending log record as complete.

	}

	/**
	 * Implements base method
	 */
	public function register_routes() {
		$this->register_post_route( $this->name . static::RESOURCE_ID_PATH . '/change-domain', 'change_domain' );
	}

	/**
	 * Changes the domain for a site.
	 *
	 * It does this by adding a record to our pending tasks table with an action hook 'wpcd_wordpress-app_rest_api_change_domain_full_live'.
	 * When that action hook is triggered it will then collect the parameters from the pending log table and call the real action hook.
	 *
	 * POST /sites/{id}/change_domain { new_domain: xxx }
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Unable to insert a new task.
	 * @throws Exception - Site Not Found.
	 */
	public function change_domain( WP_REST_Request $request ): array {
		// Get the site id we'll be working with.
		$site_id = (int) $request->get_param( 'id' );

		// Make sure site id is valid.
		$site = $this->get_site_post( $site_id ); // Will throw an error if the post doesn't exist.

		// validate and organize body parameters.
		$parameters = $request->get_body_params();
		$this->validate_required_parameters( $parameters, array( 'new_domain' ) );

		// Create an args array from the parameters to insert into the pending tasks table.
		$args['new_domain']  = sanitize_text_field( $parameters['new_domain'] );
		$args['action_hook'] = 'wpcd_wordpress-app_rest_api_pending_log_change_domain_full_live';

		// Create new install task.
		$task_id = WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $site_id, 'rest-api-change-domain', $site_id, $args, 'ready', $site_id, __( 'RESTAPI: Change Domain', 'wpcd' ) );

		// Throw some exceptions if for some reason there's no $task_id.
		if ( empty( $task_id ) ) {
			throw new Exception( 'Unable to insert a new task', 400 );
		}
		if ( is_wp_error( $task_id ) ) {
			throw new Exception( $task_id->get_error_message(), 400 );
		}

		return compact( 'task_id' );
	}

	/**
	 * Change Domain - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: pending_log_update_themes_and_plugins
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $app_id     Id of site on which this action will apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function pending_log_change_domain_start( $task_id, $app_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		// Trigger the domain change action by calling the action hook set in the tabs/change-domain.php file.
		do_action( 'wpcd_wordpress-app_do_change_domain_full_live', $app_id, $args );

	}

	/**
	 * Handle things when the Change Domain operation has failed.
	 *
	 * Primarily, we'll be updating the pending log record as failed.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_site_change_domain_failed | wpcd_wordpress-app_site_change_domain_failed
	 *
	 * @param int    $id     Post ID of the site.
	 * @param int    $action String indicating the action name.
	 * @param string $message Failure message if any.
	 * @param array  $args       All args that were passed in to the change_domain action.  Sometimes this can be an empty array.
	 *
	 * @return void
	 */
	public function handle_domain_change_failed( $id, $action, $message, $args ) {

		$site_post = get_post( $id );

		// Bail if not a post object.
		if ( ! $site_post || is_wp_error( $site_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_app_type( $id ) ) {
			return;
		}

		// This only matters if we doing a full domain replace.  If not, then bail.
		if ( 'replace_domain' !== $action ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_app_instance_details( $id );

		if ( 'wpcd_app' === get_post_type( $id ) ) {

			// Now check the pending tasks table for a record where the key=$id and type='rest-api-change-domain' and state='in-process'
			// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
			$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $id, 'in-process', 'rest-api-change-domain' );

			if ( $posts ) {

				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				// Mark post as failed.
				WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );

			}
		}
	}

	/**
	 * When a domain change is complete mark the pending log record as complete.
	 *
	 * Filter Hook: wpcd_{$this->get_app_name()}_site_change_domain_completed | wpcd_wordpress-app_site_change_domain_completed
	 *
	 * @param int    $id The id of the post app.
	 * @param string $old_domain The domain we're changing from.
	 * @param string $new_domain The domain we're changing to.
	 * @param string $name The name of the command that was executed - it contains parts that we might need later.
	 */
	public function handle_domain_change_successful( $id, $old_domain, $new_domain, $name ) {

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

			// Now check the pending tasks table for a record where the key=$id and type='rest-api-change-domain' and state='in-process'
			// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
			$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $id, 'in-process', 'rest-api-change-domain' );

			if ( $posts ) {

				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				if ( true == $success ) {
					// Mark post as complete.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );
				} else {
					// Mark post as failed.
					WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );
				}
			}
		}

	}

}


