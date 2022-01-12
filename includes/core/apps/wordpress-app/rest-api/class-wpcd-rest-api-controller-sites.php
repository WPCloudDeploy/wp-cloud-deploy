<?php
/**
 * WordPress App WPCD_REST_API_Controller_Sites.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_REST_API_Controller_Sites
 *
 * Endpoints for interacting with sites (WordPress wpcd_app)
 */
class WPCD_REST_API_Controller_Sites extends WPCD_REST_API_Controller_Base {

	/**
	 * Controller base path
	 *
	 * @var string
	 */
	protected $name = 'sites';

	/**
	 * WPCD_REST_API_Controller_Sites constructor.
	 *
	 * Registers action hooks.
	 */
	public function __construct() {
		parent::__construct();

		/* Trigger installation of a new WP site */
		add_action( 'wpcd_wordpress-app_rest_api_install_wp', array( $this, 'install_wp' ), 10, 3 );

		/* When WP install is complete, send email and possibly auto-issue ssl. */
		add_action( 'wpcd_command_wordpress-app_completed_after_cleanup', array( $this, 'wpcd_wpapp_install_complete' ), 10, 4 );

		/* Add to the list of fields that will be automatically stamped on the WordPress App Post for a new site */
		add_filter( 'wpcd_wordpress-app_add_wp_app_post_fields', array( &$this, 'add_wp_app_post_fields' ), 10, 1 );

	}

	/**
	 * Implements base method
	 */
	public function register_routes() {
		$this->register_get_route( $this->name, 'list_sites' );
		$this->register_post_route( $this->name, 'create_site' );
		$this->register_get_route( $this->name . static::RESOURCE_ID_PATH, 'get_site' );
		$this->register_put_route( $this->name . static::RESOURCE_ID_PATH, 'update_site' );  // not used.
		$this->register_put_route( $this->name . static::RESOURCE_ID_PATH . '/toggle-ssl', 'toggle_ssl' );
		$this->register_delete_route( $this->name . static::RESOURCE_ID_PATH, 'delete_site' );
		$this->register_post_route( $this->name . static::RESOURCE_ID_PATH . '/execute-action', 'execute_site_action' );
	}

	/**
	 * Lists all sites, filterable by user_id and/or server_id
	 *
	 * GET /sites
	 * GET /sites?user_id=xxx
	 * GET /sites?server_id=xxx
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 */
	public function list_sites( WP_REST_Request $request ): array {
		// base query.
		$args = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'app_type',
					'value' => WPCD_WORDPRESS_APP()->get_app_name(),
				),
			),
		);

		// build query from parameters.
		$user_id    = (int) $request->get_param( 'user_id' );
		$server_id  = (int) $request->get_param( 'server_id' );
		$user_email = filter_var( $request->get_param( 'user_email' ), FILTER_SANITIZE_EMAIL );
		if ( $user_id ) {
			$args['author'] = $user_id;
		}
		if ( $server_id ) {
			$args['meta_query'][] = array(
				'key'   => 'parent_post_id',
				'value' => $server_id,
			);
		}

		if ( $user_email ) {
			// get user id from user email address.
			$user = get_user_by( 'email', $user_email );
			if ( $user && ! is_wp_error( $user ) ) {
				$args['author'] = $user->ID;
			}
		}

		$sites = get_posts( $args );
		return array_map( array( $this, 'get_site_data' ), $sites );
	}

	/**
	 * Creates a new site
	 *
	 * POST /sites { server_id: xxx, wp_domain: xxx, wp_user: xxx, wp_password: xxx, wp_email: xxx@yyy }
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Insert post failed.
	 */
	public function create_site( WP_REST_Request $request ): array {
		// validate and organize parameters.
		$parameters = $request->get_body_params();
		$this->validate_required_parameters( $parameters, array( 'server_id', 'wp_domain', 'wp_user', 'wp_password', 'wp_email' ) );

		// handle optional owner email.
		$author_email = filter_var( $request->get_param( 'author_email' ), FILTER_SANITIZE_EMAIL );
		$this->validate_author_email( $author_email, true );

		// Create an args array from the parameters to insert into the pending tasks table.
		$server_id = (int) $parameters['server_id'];
		// @codingStandardsIgnoreLine - added to ignore the misspelling in 'wordpress' below when linting with PHPcs. Otherwise linting will automatically uppercase the first letter.
		$args['wpcd_app_type']         = 'wordpress';
		$args['wp_domain']       = wp_strip_all_tags( $parameters['wp_domain'] );
		$args['wp_user']         = wp_strip_all_tags( $parameters['wp_user'] );
		$args['wp_password']     = wp_strip_all_tags( $parameters['wp_password'] );
		$args['wp_email']        = filter_var( $parameters['wp_email'], FILTER_SANITIZE_EMAIL );
		$args['wp_version']      = 'latest';
		$args['wp_locale']       = 'en_US';
		$args['id']              = $server_id;
		$args['wp_restapi_flag'] = 'yes';
		$args['action_hook']     = 'wpcd_wordpress-app_rest_api_install_wp';
		$args['author_email']    = $author_email;

		// Create new install task.
		$task_id = WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'rest_api_install_wp', $args['wp_domain'], $args, 'ready', $server_id, __( 'RESTAPI: Waiting To Install New WP Site', 'wpcd' ) );

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
	 * Returns a single site with the given ID
	 *
	 * GET /sites/{id}
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Site not found.
	 */
	public function get_site( WP_REST_Request $request ): array {
		$id   = (int) $request->get_param( 'id' );
		$site = $this->get_site_post( $id );
		return $this->get_site_data( $site );
	}

	/**
	 * Updates a site with the given ID
	 *
	 * PUT /sites/{id}
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Failed to update site.
	 */
	public function update_site( WP_REST_Request $request ): array {
		// This is not used so just throw exception right away.
		throw new Exception( 'Call to update_site rest api endpoint is not allowed.', 405 );
	}

	/**
	 * Enables or Disables SSL for a site with the given ID
	 *
	 * PUT /sites/{id}/toggle-ssl
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Site not found.
	 */
	public function toggle_ssl( WP_REST_Request $request ): array {
		// verify correct post.
		$id = (int) $request->get_param( 'id' );
		$this->get_site_post( $id );   // Will throw an exception if not a valid site.

		// Execute action.
		do_action( 'wpcd_wordpress-app_do_toggle_ssl_status', $id, 'ssl-status' );

		// Get ssl status from database.
		$ssl_enabled = false;
		if ( 'on' === get_post_meta( $id, 'wpapp_ssl_status', true ) ) {
			$ssl_enabled = true;
		}

		// Action hooks return nothing so just return an array of stuff.
		return array(
			'site_id' => $id,
			'enabled' => $ssl_enabled,
		);
	}

	/**
	 * Deletes a site with the given ID
	 *
	 * DELETE /sites/{id}
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Failed to delete site.
	 */
	public function delete_site( WP_REST_Request $request ): array {
		// verify correct post.
		$id = (int) $request->get_param( 'id' );
		$this->get_site_post( $id );   // Will throw an exception if not a valid site.

		// Execute action.
		do_action( 'wpcd_app_delete_wp_site', $id, 'remove_full' );

		// Action hooks return nothing so just return an array of stuff.
		return array(
			'site_id' => $id,
			'deleted' => true,
		);
	}

	/**
	 * Executes a pre-defined action for a site
	 *
	 * POST /sites/{id}/execute_site_action { action: xxx }
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Site not found.
	 */
	public function execute_site_action( WP_REST_Request $request ) {
		// verify correct post.
		$site_id = (int) $request->get_param( 'id' );
		$this->get_site_post( $site_id );

		$parameters = $request->get_body_params();
		$this->validate_required_parameters( $parameters, array( 'action' ) );

		$app_name = WPCD_WORDPRESS_APP()->get_app_name();
		$action   = $parameters['action'];

		// execute hook associated with the action.
		$result = apply_filters( "wpcd_app_{$app_name}_tab_action", '', $action, $site_id );
		return compact( 'site_id', 'action', 'result' );
	}

	/**
	 * Fetches post and verifies the correct post type
	 *
	 * @param int $id - requested post id.
	 *
	 * @return WP_Post
	 * @throws Exception - Site not found.
	 */
	protected function get_site_post( int $id ): WP_Post {
		$site = get_post( $id );
		if ( ! ( $site && 'wpcd_app' === $site->post_type ) ) {
			throw new Exception( 'Site not found', 400 );
		}
		return $site;
	}

	/**
	 * Builds response data for a site's general data
	 *
	 * @param WP_Post $site - fetched site object.
	 *
	 * @return array
	 */
	protected function get_site_data( WP_Post $site ): array {

		$data = array(
			'id'           => $site->ID,
			'name'         => $site->post_title,
			'author'       => (int) $site->post_author,
			'server_id'    => (int) get_post_meta( $site->ID, 'parent_post_id', true ),
			'domain'       => get_post_meta( $site->ID, 'wpapp_domain', true ),
			'ssl_status'   => get_post_meta( $site->ID, 'wpapp_ssl_status', true ),
			'http2_status' => get_post_meta( $site->ID, 'wpapp_ssl_http2_status', true ),
		);

		$push_data = wpcd_maybe_unserialize( get_post_meta( $site->ID, 'wpcd_site_status_push', true ) );

		if ( is_array( $push_data ) ) {
			$data = array_merge( $data, $push_data );
		}

		return $data;

	}

	/**
	 * Install WordPress on a server.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_wordpress-app_rest_api_install_wp-wp
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing.
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function install_wp( $task_id, $server_id, $args ) {

		/* Install standard wp app on the designated server */
		$additional = WPCD_WORDPRESS_APP()->install_wp_validate( $args );

	}

	/**
	 * Add to the list of fields that will be automatically stamped on the WordPress App Post.
	 *
	 * Filter Hook: wpcd_{get_app_name()}_add_wp_app_post_fields | wpcd_wordpress-app_add_wp_app_post_fields
	 * The filter hook is located in the wordpress-app class.
	 *
	 * @param array $flds string array of existing field names.
	 */
	public function add_wp_app_post_fields( $flds ) {

		return array_merge( $flds, array( 'restapi_flag' ) );

	}

	/**
	 * Send an email and possibly auto-issue SSL after a site has been installed.
	 *
	 * Action Hook: wpcd_command_wordpress-app_completed_after_cleanup
	 *
	 * @param int    $id                 post id of server.
	 * @param int    $app_id             post id of wp app.
	 * @param string $name               command name executed for new site.
	 * @param string $base_command       basename of command.
	 * @param string $pending_task_type  Task type to update when we're done. This is not part of the action hook definition - it's only passed in explicitly when this is called as a function.
	 */
	public function wpcd_wpapp_install_complete( $id, $app_id, $name, $base_command, $pending_task_type = 'rest_api_install_wp' ) {

		// If not installing an app, return.
		if ( 'install_wp' !== $base_command ) {
			return;
		}

		$app_post = get_post( $app_id );

		// Bail if not a post object.
		if ( ! $app_post || is_wp_error( $app_post ) ) {
			return;
		}

		// Bail if not a WordPress app.
		if ( 'wordpress-app' !== WPCD_WORDPRESS_APP()->get_app_type( $app_id ) ) {
			return;
		}

		// If the site wasn't the result of a restapi command, then bail.
		if ( empty( get_post_meta( $app_id, 'wpapp_restapi_flag' ) ) ) {
			return;
		}

		// Get app instance array.
		$instance = WPCD_WORDPRESS_APP()->get_app_instance_details( $app_id );

		// If the app install was done via a background pending tasks process then get that pending task post data here.
		// We do that by checking the pending tasks table for a record where the key=domain and type='rest_api_install_wp' and state='in-process'.
		$pending_task_posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( WPCD_WORDPRESS_APP()->get_domain_name( $app_id ), 'in-process', $pending_task_type );

		/**
		 * This is the spot where we would send emails and enable ssl and such if necessary.
		 * We're not doing that now but might later.
		 * Check the sell sites with woocommerce add-on for examples of what can go in this spot.
		 */

		// Finally update pending tasks table if applicable...
		if ( $pending_task_posts ) {
			$data_to_save                = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $pending_task_posts[0]->ID );
			$data_to_save['wp_password'] = '***removed***';  // remove the password data from the pending log table.
			WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $pending_task_posts[0]->ID, $data_to_save, 'complete' );
		}

	}

}


