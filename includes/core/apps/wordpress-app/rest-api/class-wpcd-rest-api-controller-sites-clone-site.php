<?php
/**
 * WordPress App WPCD_REST_API_Controller_Sites_Clone_Site.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_REST_API_Controller_Sites_Clone_Site
 *
 * Endpoints for interacting with sites (WordPress wpcd_app)
 */
class WPCD_REST_API_Controller_Sites_Clone_Site extends WPCD_REST_API_Controller_Sites {

	/**
	 * WPCD_REST_API_Controller_Sites_Clone_Site constructor.
	 *
	 * Registers action hooks.
	 */
	public function __construct() {
		parent::__construct();

		/* Hooks related to changing domain. */
		add_action( 'wpcd_wordpress-app_rest_api_pending_log_clone_site', array( $this, 'pending_log_clone_site_start' ), 10, 3 );  // Trigger the action hook that starts the clone site operation.
		add_action( 'wpcd_wordpress-app_clone_site_failed', array( $this, 'handle_clone_site_failed' ), 10, 4 );  // If the clone failed, mark the pending log record as failed.
		add_action( 'wpcd_wordpress-app_site_clone_new_post_completed', array( $this, 'handle_clone_site_successful' ), 10, 3 );  // If the clone site is successful, mark the pending log record as complete.

	}

	/**
	 * Implements base method
	 */
	public function register_routes() {
		$this->register_post_route( $this->name . static::RESOURCE_ID_PATH . '/clone-site', 'clone_site' );
	}

	/**
	 * Clones a site.
	 *
	 * It does this by adding a record to our pending tasks table with an action hook 'wpcd_wordpress-app_rest_api_pending_log_clone_site'.
	 * When that action hook is triggered it will then collect the parameters from the pending log table and call the real action hook.
	 *
	 * POST /sites/{id}/clone-site { new_domain: xxx }
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Unable to insert a new task.
	 * @throws Exception - Site Not Found.
	 */
	public function clone_site( WP_REST_Request $request ): array {
		// Get the site id we'll be working with.
		$site_id = (int) $request->get_param( 'id' );

		// Make sure site id is valid.
		$site = $this->get_site_post( $site_id ); // Will throw an error if the post doesn't exist.

		// validate and organize body parameters.
		$parameters = $request->get_body_params();
		$this->validate_required_parameters( $parameters, array( 'new_domain' ) );

		// Create an args array from the parameters to insert into the pending tasks table.
		$args['new_domain']  = sanitize_text_field( $parameters['new_domain'] );
		$args['action_hook'] = 'wpcd_wordpress-app_rest_api_pending_log_clone_site';

		// Create new install task.
		$task_id = WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $site_id, 'rest-api-clone-site', $site_id, $args, 'ready', $site_id, __( 'RESTAPI: Clone Site', 'wpcd' ) );

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
	 * Clone Site - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_wordpress-app_rest_api_pending_log_clone_site
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $app_id     Id of site on which this action will apply.
	 * @param array $args       All the data needed for this action.
	 */
	public function pending_log_clone_site_start( $task_id, $app_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		// Trigger the domain change action by calling the action hook set in the tabs/change-domain.php file.
		do_action( 'wpcd_wordpress-app_do_clone_site', $app_id, $args );

	}

	/**
	 * Handle things when the Clone Site operation has failed.
	 *
	 * Primarily, we'll be updating the pending log record as failed.
	 *
	 * Action Hook: wpcd_{$this->get_app_name()}_clone_site_failed | wpcd_wordpress-app_clone_site_failed
	 *
	 * @param int    $id       Post ID of the site.
	 * @param int    $action   String indicating the action name.
	 * @param string $message  Failure message if any.
	 * @param array  $args     All args that were passed in to the clone-site action.  Sometimes this can be an empty array.
	 *
	 * @return void
	 */
	public function handle_clone_site_failed( $id, $action, $message, $args ) {

		$site_post = get_post( $id );

		// Bail if not a post object.
		if ( ! $site_post || is_wp_error( $site_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_app_type( $id ) ) {
			return;
		}

		// This only matters if we cloning a site.  If not, then bail.
		if ( 'clone-site' !== $action ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_app_instance_details( $id );

		if ( 'wpcd_app' === get_post_type( $id ) ) {

			// Now check the pending tasks table for a record where the key=$id and type='rest-api-clone-site' and state='in-process'
			// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
			$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $id, 'in-process', 'rest-api-clone-site' );

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
	 * When a clone site operation is complete mark the pending log record as complete.
	 *
	 * Filter Hook: wpcd_{$this->get_app_name()}_site_clone_new_post_completed | wpcd_wordpress-app_site_clone_new_post_completed
	 *
	 * @param int    $new_site_id The id of the post app.
	 * @param int    $old_site_id The id of the post app.
	 * @param string $name The name of the command that was executed - it contains parts that we might need later.
	 */
	public function handle_clone_site_successful( $new_site_id, $old_site_id, $name ) {

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run
		// [1] => cf1110.wpvix.com
		// [2] => 911
		$command_array = explode( '---', $name );

		// Check to see if the command is to clone a site otherwise exit.
		if ( 'clone-site' == $command_array[0] ) {

			// Lets pull the logs.
			$logs = WPCD_WORDPRESS_APP()->get_app_command_logs( $old_site_id, $name );

			// Was the command successful?
			$success = WPCD_WORDPRESS_APP()->is_ssh_successful( $logs, 'clone_site.txt' );

			// Now check the pending tasks table for a record where the key=$id and type='rest-api-change-domain' and state='in-process'
			// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
			$posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $old_site_id, 'in-process', 'rest-api-clone-site' );

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


