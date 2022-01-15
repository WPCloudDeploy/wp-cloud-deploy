<?php
/**
 * WordPress App WPCD_REST_API_Controller_Servers.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_REST_API_Controller_Servers
 *
 * Endpoints for interacting with sites (WordPress wpcd_app)
 */
class WPCD_REST_API_Controller_Servers extends WPCD_REST_API_Controller_Base {

	/**
	 * Controller base path
	 *
	 * @var string
	 */
	protected $name = 'servers';

	/**
	 * WPCD_REST_API_Controller_Servers constructor.
	 *
	 * Registers action hooks.
	 */
	public function __construct() {
		parent::__construct();

		/* Update pending task entry after server is completed. */
		add_action( 'wpcd_command_wordpress-app_prepare_server_completed', array( $this, 'wpcd_wpapp_prepare_server_completed' ), 10, 2 );

	}

	/**
	 * Implements base method
	 */
	public function register_routes() {
		$this->register_get_route( $this->name, 'list_servers' );
		$this->register_post_route( $this->name, 'create_server' );
		$this->register_get_route( $this->name . static::RESOURCE_ID_PATH, 'get_server' );
		$this->register_delete_route( $this->name . static::RESOURCE_ID_PATH, 'delete_server' );

	}

	/**
	 * Lists all servers, filterable by user_id
	 *
	 * GET /servers
	 * GET /servers?user_id=xxx
	 * GET /servers?user_email=xxx
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 */
	public function list_servers( WP_REST_Request $request ): array {
		// base query.
		$args = array(
			'post_type'      => 'wpcd_app_server',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'wpcd_server_initial_app_name',
					'value' => WPCD_WORDPRESS_APP()->get_app_name(),
				),
			),
		);
		// build query from parameters.
		$user_id = (int) $request->get_param( 'user_id' );
		if ( $user_id ) {
			$args['author'] = $user_id;
		}

		$user_email = filter_var( $request->get_param( 'user_email' ), FILTER_SANITIZE_EMAIL );
		if ( $user_email ) {
			// get user id from user email address.
			$user = get_user_by( 'email', $user_email );
			if ( $user && ! is_wp_error( $user ) ) {
				$args['author'] = $user->ID;
			}
		}

		$servers = get_posts( $args );
		return array_map( array( $this, 'get_server_data' ), $servers );
	}

	/**
	 * Creates a new server
	 *
	 * POST /servers { name: xxx, provider: xxx, region: xxx, size: xxx, (optional) author_email: xxx@yyy }
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Unable to create new server.
	 */
	public function create_server( WP_REST_Request $request ): array {
		// validate and organize parameters.
		$parameters = $request->get_body_params();
		$this->validate_required_parameters( $parameters, array( 'name', 'provider', 'region', 'size' ) );

		// handle optional owner email.
		$author_email = filter_var( $request->get_param( 'author_email' ), FILTER_SANITIZE_EMAIL );
		$this->validate_author_email( $author_email, true );

		// setup server attributes array needed to create the server.
		$attributes = array(
			'initial_os'       => wpcd_get_option( 'wordpress_app_default_os' ),
			'initial_app_name' => WPCD_WORDPRESS_APP()->get_app_name(),
			'server-type'      => 'wordpress-app',
			'scripts_version'  => wpcd_get_option( 'wordpress_app_script_version' ),
			'region'           => sanitize_text_field( $parameters['region'] ),
			'size_raw'         => sanitize_text_field( $parameters['size'] ),
			'name'             => sanitize_text_field( $parameters['name'] ),
			'provider'         => sanitize_text_field( $parameters['provider'] ),
			'author_email'     => ! empty( $author_email ) ? $author_email : '',
			'init'             => true,
			'wp_restapi_flag'  => 'yes',
		);

		/* Create server */
		$instance = WPCD_SERVER()->create_server( 'create', $attributes );

		/* Check for errors */
		if ( empty( $instance ) || is_wp_error( $instance ) ) {
			throw new Exception( 'Unable to create new server', 400 );
		}

		/* Make sure the other fields in the server post type entry gets created/updated. */
		$instance = WPCD_WORDPRESS_APP()->add_app( $instance );

		/* Check for errors */
		if ( empty( $instance ) || is_wp_error( $instance ) ) {
			throw new Exception( 'Unable to create new server', 400 );
		}

		/**
		 * Create pending log entry - we'll update this entry in an action hook after the server is complete.
		 * Note that unlike other pending log entries, this one will go directly to 'in-process' because
		 * the create_server function above is already creating the server.
		 * We're providing a pending logs entry for consistency with creating sites.
		*/
		$server_id = $instance['post_id'];
		$task_id   = WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'rest_api_install_server', $server_id, $instance, 'in-process', $server_id, __( 'RESTAPI: Server is being created', 'wpcd' ) );

		/* Check for errors */
		if ( empty( $task_id ) || empty( $server_id ) ) {
			throw new Exception( 'Unable to create new server - task id or server is is still blank.', 400 );
		}

		return compact( 'server_id', 'task_id' );
	}

	/**
	 * Returns a single server with the given ID
	 *
	 * GET /servers/{id}
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Server not found.
	 */
	public function get_server( WP_REST_Request $request ): array {
		$id     = (int) $request->get_param( 'id' );
		$server = $this->get_server_post( $id );
		return $this->get_server_data( $server );
	}

	/**
	 * Deletes a server with the given ID
	 *
	 * DELETE /servers/{id}
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Failed to delete site.
	 */
	public function delete_server( WP_REST_Request $request ): array {
		// verify correct post.
		$id = (int) $request->get_param( 'id' );
		$this->get_server_post( $id );   // Will throw an exception if not a valid site.

		// Execute action.
		do_action( 'wpcd_server_wordpress-app_action', $id, 'delete' );

		// Attempt to retrieve the server post and check to see if its null or if its status is 'trash'.
		$server = get_post( $id );
		if ( is_object( $server ) ) {
			if ( 'trash' !== $server->post_status ) {
				throw new Exception( 'Unable to delete server - the server post object still exists and is not in the Trash.', 400 );
			}
		} elseif ( ! is_null( $server ) ) {
			throw new Exception( 'Unable to delete server.', 400 );
		}

		// Action hooks return nothing so just return an array of stuff.
		return array(
			'server_id' => $id,
			'deleted'   => true,
		);
	}

	/**
	 * Fetches post and verifies the correct post type
	 *
	 * @param int $id - requested post id.
	 *
	 * @return WP_Post
	 * @throws Exception - Server not found.
	 */
	protected function get_server_post( int $id ): WP_Post {
		$server = get_post( $id );
		if ( ! ( $server && 'wpcd_app_server' === $server->post_type ) ) {
			throw new Exception( 'Server not found', 400 );
		}
		return $server;
	}

	/**
	 * Builds response data for a server's general data
	 *
	 * @param WP_Post $server - fetched server object.
	 *
	 * @return array
	 */
	protected function get_server_data( WP_Post $server ): array {

		$server_id = $server->ID;
		$data      = array(
			'id'                     => $server->ID,
			'name'                   => $server->post_title,
			'author'                 => (int) $server->post_author,
			'os'                     => WPCD_SERVER()->get_server_os( $server_id ),
			'ipv4'                   => WPCD_SERVER()->get_ipv4_address( $server_id ),
			'provider'               => WPCD_SERVER()->get_server_provider( $server_id ),
			'instance_id'            => WPCD_SERVER()->get_server_provider_instance_id( $server_id ),
			'app_count'              => WPCD_SERVER()->get_app_count( $server_id ),
			'region'                 => WPCD_SERVER()->get_server_region( $server_id ),
			'available_for_commands' => WPCD_SERVER()->is_server_available_for_commands( $server_id ),
		);

		return $data;

	}

	/**
	 * Mark the pending log entry as completed.
	 * Perhaps do other things such as marking the server as 'delete protected'.
	 *
	 * Action hook: wpcd_command_{$this->get_app_name()}_{$base_command}_{$status} || wpcd_wordpress-app_prepare_server_completed
	 *
	 * @param int    $server_id      The post id of the server record.
	 * @param string $command_name   The full name of the command that triggered this action.
	 */
	public function wpcd_wpapp_prepare_server_completed( int $server_id, $command_name ) {

		$server_post = get_post( $server_id );

		// Bail if not a post object.
		if ( ! $server_post || is_wp_error( $server_post ) ) {
			return;
		}

		// Bail if not a WordPress app.
		if ( 'wordpress-app' <> WPCD_WORDPRESS_APP()->get_server_type( $server_id ) ) {
			return;
		}

		// If the server wasn't the result of a REST API command, then bail.
		if ( empty( get_post_meta( $server_id, 'wpcd_server_wp_restapi_flag' ) ) ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_instance_details( $server_id );

		if ( 'wpcd_app_server' === get_post_type( $server_id ) ) {

			// If the app install was done via a background pending tasks process then get that pending task post data here.
			// We do that by checking the pending tasks table for a record where the key=domain and type='rest_api_install_wp' and state='in-process'.
			$pending_task_posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'rest_api_install_server' );
			if ( $pending_task_posts ) {
				/* Now update the log entry to market is as complete. */
				$data_to_save = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $pending_task_posts[0]->ID );
				WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $pending_task_posts[0]->ID, $data_to_save, 'complete' );
			}
		}

	}

}


