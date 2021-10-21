<?php

use WPTest\Test\TestCase;

/**
 * Class ControllerBaseTest
 *
 * Test common behavior of all rest api controllers
 */
class ControllerBaseTest extends TestCase
{
    protected WP_REST_Server $rest;
    protected WPCD_REST_API_Controller_Base $controller;
    protected string $resource_route;
    protected string $error_route;

    /**
     * Sets test controller and other convenience variables
     */
    public function setUp(): void
    {
        parent::setUp();
        require_once dirname(dirname(__DIR__)) . '/data/class-wpcd-rest-api-controller-test.php';
        $this->controller = new WPCD_REST_API_Controller_Test();
        $this->rest = rest_get_server();
        $this->resource_route = '/' . $this->controller->get_namespace();
        $this->error_route = $this->resource_route . '/error';
        wp_set_current_user(1);
    }

    /**
     * Verifies all endpoints have been properly registered.
     */
    function test_it_registers_rest_routes()
    {
        $routes = $this->rest->get_routes();
        $this->assertArrayHasKey($this->resource_route, $routes);
        $this->assertArrayHasKey($this->error_route, $routes);
        $this->assertCount(4, $routes[$this->resource_route]);
    }

    /**
     * Verify basic GET route execution
     */
    function test_it_executes_a_get_route()
    {
        $request = new WP_REST_Request('GET', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals(['success' => true], $data);
    }

    /**
     * Verify basic POST route execution
     */
    function test_it_executes_a_post_route()
    {
        $request = new WP_REST_Request('POST', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals(['success' => true], $data);
    }

    /**
     * Verify basic PUT route execution
     */
    function test_it_executes_a_put_route()
    {
        $request = new WP_REST_Request('PUT', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals(['success' => true], $data);
    }

    /**
     * Verify basic DELETE route execution
     */
    function test_it_executes_a_delete_route()
    {
        $request = new WP_REST_Request('DELETE', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals(['success' => true], $data);
    }

    /**
     * Verifies that the user must be logged in and has admin permissions to make requests to the REST API.
     */
    function test_it_fails_with_incorrect_permissions()
    {
        wp_set_current_user(0);
        $request = new WP_REST_Request('GET', $this->resource_route);
        $error = $this->rest->dispatch($request)->as_error();
        $this->assertWPError($error);
        $this->assertEquals(['status' => 401], $error->get_error_data());
        wp_set_current_user($this->factory()->user->create());
        $error = $this->rest->dispatch($request)->as_error();
        $this->assertEquals(['status' => 403], $error->get_error_data());
    }

    /**
     * Verifies that exceptions and errors in endpoints get handled with a standard response.
     */
    function test_handles_exceptions_as_error_responses()
    {
        $request = new WP_REST_Request('GET', $this->error_route);
        $error = $this->rest->dispatch($request)->as_error();
        $this->assertWPError($error);
        $this->assertEquals(['status' => 400], $error->get_error_data());
        $request->set_param('disable_status_code', 1);
        $response = $this->rest->dispatch($request);
        $this->assertFalse($response->is_error());
        $this->assertEquals([
            'code' => 'wpcd_rest_test_error',
            'message' => 'Test Error',
            'data' => ['status' => 200]
        ], $response->get_data());

    }
}