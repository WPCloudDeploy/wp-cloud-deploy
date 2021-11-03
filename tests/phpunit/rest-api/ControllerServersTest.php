<?php

use WPTest\Test\TestCase;

class ControllerServersTest extends TestCase
{
    protected WP_REST_Server $rest;
    protected WPCD_REST_API_Controller_Base $controller;
    protected string $resource_route;
    protected string $resource_id_route;
    protected string $resource_name_route;
    protected int $server_post_id;

    /**
     * Sets sites controller and other convenience variables
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->controller = WPCD_WORDPRESS_APP()->get_rest_controller('servers');
        $this->rest = rest_get_server();
        $this->resource_route = '/' . $this->controller->get_namespace();
        $this->resource_id_route = $this->resource_route . $this->controller::RESOURCE_ID_PATH;
        wp_set_current_user(1);
    }

    /**
     * Verifies all endpoints have been properly registered.
     */
    function test_it_registers_rest_routes()
    {
        $routes = $this->rest->get_routes();
        $this->assertInstanceOf(WPCD_REST_API_Controller_Servers::class, $this->controller);
        $this->assertArrayHasKey($this->resource_route, $routes);
        $this->assertCount(2, $routes[$this->resource_route]);
        $this->assertArrayHasKey($this->resource_id_route, $routes);
        $this->assertCount(2, $routes[$this->resource_id_route]);
    }

    /**
     * Verifies GET /servers response data.
     */
    function test_it_lists_servers()
    {
        $request = new WP_REST_Request('GET', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([], $data);
        $server_post_id_1 = $this->create_server(1);
        $server_post_id_2 = $this->create_server(2);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            [
                'id' => $server_post_id_1, 'name' => 'server 1', 'author' => 1, 'os' => 'ubuntu1804lts',
                'ipv4' => '', 'provider' => 'test', 'instance_id' => '12345', 'app_count' => 0,
                'region' => '', 'available_for_commands' => true
            ],
            [
                'id' => $server_post_id_2, 'name' => 'server 2', 'author' => 1, 'os' => 'ubuntu1804lts',
                'ipv4' => '', 'provider' => 'test', 'instance_id' => '12345', 'app_count' => 0,
                'region' => '', 'available_for_commands' => true
            ]
        ], $data);
    }

    /**
     * Verifies GET /servers response data with user_id parameter.
     */
    function test_it_lists_sites_filtered_by_user()
    {
        $request = new WP_REST_Request('GET', $this->resource_route);
        $user_id = $this->factory()->user->create();
        $request->set_param('user_id', $user_id);
        $this->create_server(1);
        $server_post_id_2 = $this->create_server(2, $user_id);

        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            [
                'id' => $server_post_id_2, 'name' => 'server 2', 'author' => $user_id, 'os' => 'ubuntu1804lts',
                'ipv4' => '', 'provider' => 'test', 'instance_id' => '12345', 'app_count' => 0,
                'region' => '', 'available_for_commands' => true
            ]
        ], $data);
    }

    /**
     * Verifies POST /servers correctly creates a new server entry and pending log entry
     */
    function test_it_creates_a_site()
    {
        $this->create_test_provider();
        $list_request = new WP_REST_Request('GET', $this->resource_route);
        $servers = $this->rest->dispatch($list_request)->get_data();
        $tasks = get_posts(['post_type' => 'wpcd_pending_log', 'post_status' => 'private']);
        $this->assertEmpty($servers);
        $this->assertEmpty($tasks);
        $request = new WP_REST_Request('POST', $this->resource_route);
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('name', 'server 1');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('provider', 'test');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('region', 'us-east-1');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('size', 'c5.xlarge');
        $data = $this->rest->dispatch($request)->get_data();
        $servers = get_posts(['post_type' => 'wpcd_app_server', 'post_status' => 'private', 'post_author' => 1]);
        $tasks = get_posts(['post_type' => 'wpcd_pending_log', 'post_status' => 'private', 'post_author' => 1]);
        $this->assertCount(1, $tasks);
        $this->assertEquals(['server_id' => $servers[0]->ID, 'task_id' => $tasks[0]->ID], $data);
    }

    /**
     * Verifies GET /servers/{id} response data
     */
    function test_it_gets_a_server()
    {
        $request = new WP_REST_Request('GET', $this->resource_route . '/9999999');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $server_post_id = $this->create_server(1);
        $request = new WP_REST_Request('GET', $this->resource_route . '/' . $server_post_id);
        $server = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            'id' => $server['id'], 'name' => 'server 1', 'author' => 1, 'os' => 'ubuntu1804lts',
            'ipv4' => '', 'provider' => 'test', 'instance_id' => '12345', 'app_count' => 0,
            'region' => '', 'available_for_commands' => true
        ], $server);
    }

    /**
     * Verifies DELETE /servers/{id} correctly deletes the specified server
     */
    function test_it_deletes_a_site()
    {
        //$this->mock_ssh('Site has been deleted');
        $this->create_test_provider();
        $request = new WP_REST_Request('DELETE', $this->resource_route . '/9999999');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $server_post_id = $this->create_server(1);
        $this->assertEquals('private', get_post_status(get_post($server_post_id)));
        $request = new WP_REST_Request('DELETE', $this->resource_route . '/' . $server_post_id);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            'server_id' => $server_post_id,
            'deleted' => true,
        ], $data);
        $this->assertEquals('trash', get_post_status(get_post($server_post_id)));
    }

    /**
     * Convenience method to create a new site via the REST API and return its response
     *
     * @return int
     */
    protected function create_server($count, $user_id = 1, $provider_key = 'test')
    {
        $server_post_id = WPCD_SERVER()->create_server_post(['name' => 'server ' . $count, 'user_id' => $user_id]);
        update_post_meta($server_post_id, 'wpcd_server_initial_app_name', WPCD_WORDPRESS_APP()->get_app_name());
        update_post_meta($server_post_id, 'wpcd_server_provider', $provider_key);
        update_post_meta($server_post_id, 'wpcd_server_provider_instance_id', '12345');
        return $server_post_id;
    }

    /**
     * Loads in test provider to use instead of a real provider
     */
    protected function create_test_provider($key = 'test')
    {
        require_once dirname(dirname(__DIR__)) . '/data/class-wpcd-cloud-provider-test.php';
        WPCD()->classes['wpcd_vpn_api_provider_' . $key] = new WPCD_Cloud_Provider_Test();
    }
}