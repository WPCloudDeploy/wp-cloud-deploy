<?php
/**
 * WordPress App WPCD_REST_API_Controller_Base.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_REST_API_Controller_Base
 *
 * Provides common behavior and convenience methods for all controller sub-classes
 */
abstract class WPCD_REST_API_Controller_Base {

	/**
	 * Root endpoint path
	 */
	const NAMESPACE = 'wpcd/v1';

	/**
	 * Regex used when resource ID is present in endpoint URL
	 */
	const RESOURCE_ID_PATH = '/(?P<id>\d+)';

	/**
	 * Controller base path, MUST be set in sub-classes
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * WPCD_REST_API_Controller_Base constructor.
	 *
	 * Registers routes
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Get full namespace of controller
	 *
	 * @return string
	 */
	public function get_namespace() {
		return static::NAMESPACE . '/' . $this->name;
	}

	/**
	 * Get controller name / base path
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Sub-classes register routes inside this method, calling methods like register_get_route()
	 */
	abstract public function register_routes();

	/**
	 * Registers a route for the controller
	 * Use one of the convenience methods unless of calling this directly
	 *
	 * @param string          $route - unique ending path for this route.
	 * @param string          $method - HTTP verb used for this endpoint.
	 * @param callable|string $action - name of controller method to execute, or a callable.
	 *
	 * @return bool
	 */
	protected function register_route( string $route, string $method, $action ): bool {
		return register_rest_route(
			static::NAMESPACE,
			$route,
			array(
				'methods'             => $method,
				'callback'            => $this->build_callback( $action ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					return wpcd_is_admin() ?: $this->handle_exception(
						new Exception( 'Incorrect permissions', rest_authorization_required_code() ),
						$request
					);
				},
			)
		);
	}

	/**
	 * Register a GET route
	 *
	 * @param string          $route  - unique ending path for this route.
	 * @param callable|string $action - name of controller method to execute, or a callable.
	 * @return bool
	 */
	protected function register_get_route( string $route, $action ): bool {
		return $this->register_route( $route, 'GET', $action );
	}

	/**
	 * Register a POST route
	 *
	 * @param string          $route  - unique ending path for this route.
	 * @param callable|string $action - name of controller method to execute, or a callable.
	 * @return bool
	 */
	protected function register_post_route( string $route, $action ): bool {
		return $this->register_route( $route, 'POST', $action );
	}

	/**
	 * Register a PUT route
	 *
	 * @param string          $route  - unique ending path for this route.
	 * @param callable|string $action - name of controller method to execute, or a callable.
	 * @return bool
	 */
	protected function register_put_route( string $route, $action ): bool {
		return $this->register_route( $route, 'PUT', $action );
	}

	/**
	 * Register a DELETE route
	 *
	 * @param string          $route  - unique ending path for this route.
	 * @param callable|string $action - name of controller method to execute, or a callable.
	 * @return bool
	 */
	protected function register_delete_route( string $route, $action ): bool {
		return $this->register_route( $route, 'DELETE', $action );
	}

	/**
	 * Wraps controller action method to format response and handle exceptions
	 *
	 * @param callable|string $callback - name of controller method to execute, or a callable.
	 * @return callable
	 */
	protected function build_callback( $callback ): callable {
		// if not already a callable, assume it's a controller method name.
		if ( ! is_callable( $callback ) ) {
			$callback = array( $this, $callback );
		}
		// wrapper function actually used by rest api server.
		return function( WP_REST_Request $request ) use ( $callback ) {
			try {
				$response = call_user_func( $callback, $request );
			} catch ( \Throwable $e ) {
				$response = $this->handle_exception( $e, $request );
			}
			return rest_ensure_response( $response );
		};
	}

	/**
	 * The WP Rest API requires a WP_Error instance to be returned for error responses
	 * This converts exceptions or errors into a WP_Error instance with either the given HTTP status code
	 * or a 200 status code if the "disable_status_code" request parameter is set
	 *
	 * @param Throwable       $e - incoming exception or error.
	 * @param WP_REST_Request $request - request object.
	 * @return WP_Error
	 */
	protected function handle_exception( Throwable $e, WP_REST_Request $request ): WP_Error {
		$status = $request->get_param( 'disable_status_code' ) ? 200 : ( $e->getCode() >= 400 ? $e->getCode() : 500 );
		return new WP_Error( 'wpcd_rest_' . $this->get_name() . '_error', $e->getMessage(), compact( 'status' ) );
	}

	/**
	 * Used within an action method to check required parameters and throw an appropriate exception if one is missing
	 *
	 * @param array $parameters - request parameters.
	 * @param array $required_parameters - list of required parameter names.
	 *
	 * @throws Exception - if a required parameter is missing.
	 */
	protected function validate_parameters( array $parameters, array $required_parameters ) {
		foreach ( $required_parameters as $parameter ) {
			if ( empty( $parameters[ $parameter ] ) ) {
				throw new Exception( "The '$parameter' parameter is required", 400 );
			}
		}
	}

}
