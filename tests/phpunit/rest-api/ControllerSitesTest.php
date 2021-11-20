<?php

use WPTest\Test\TestCase;

class ControllerSitesTest extends TestCase
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
        $this->controller = WPCD_WORDPRESS_APP()->get_rest_controller('sites');
        $this->rest = rest_get_server();
        $this->resource_route = '/' . $this->controller->get_namespace();
        $this->resource_id_route = $this->resource_route . $this->controller::RESOURCE_ID_PATH;
        $this->server_post_id = WPCD_SERVER()->create_server_post(['name' => 'Test Server']);
        wp_set_current_user(1);
    }

    /**
     * Verifies all endpoints have been properly registered.
     */
    function test_it_registers_rest_routes()
    {
        $routes = $this->rest->get_routes();
        $this->assertInstanceOf(WPCD_REST_API_Controller_Sites::class, $this->controller);
        $this->assertArrayHasKey($this->resource_route, $routes);
        $this->assertCount(2, $routes[$this->resource_route]);
        $this->assertArrayHasKey($this->resource_id_route, $routes);
        $this->assertCount(3, $routes[$this->resource_id_route]);
        $this->assertArrayHasKey($this->resource_id_route . '/execute-action', $routes);
    }

    /**
     * Verifies GET /sites response data.
     */
    function test_it_lists_sites()
    {
        $request = new WP_REST_Request('GET', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([], $data);
        $app_post_id_1 = WPCD_POSTS_APP()->add_app( WPCD_WORDPRESS_APP()->get_app_name(), 1, 1, 'site1.com' );
        $app_post_id_2 = WPCD_POSTS_APP()->add_app( WPCD_WORDPRESS_APP()->get_app_name(), 2, 1, 'site2.com' );
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            [
                'id' => $app_post_id_1, 'name' => 'site1.com', 'server_id' => 1,
                'author' => 1, 'domain' => '', 'ssl_status' => '', 'http2_status' => ''
            ],
            [
                'id' => $app_post_id_2, 'name' => 'site2.com', 'server_id' => 2,
                'author' => 1, 'domain' => '', 'ssl_status' => '', 'http2_status' => ''
            ]
        ], $data);
    }

    /**
     * Verifies GET /sites response data with user_id parameter.
     */
    function test_it_lists_sites_filtered_by_user()
    {
        $request = new WP_REST_Request('GET', $this->resource_route);
        $user_id = $this->factory()->user->create();
        $request->set_param('user_id', $user_id);
        WPCD_POSTS_APP()->add_app( WPCD_WORDPRESS_APP()->get_app_name(), 1, 1, 'site1.com' );
        $app_post_id_2 = WPCD_POSTS_APP()->add_app( WPCD_WORDPRESS_APP()->get_app_name(), 1, $user_id, 'site2.com' );
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            [
                'id' => $app_post_id_2, 'name' => 'site2.com', 'server_id' => 1,
                'author' => $user_id,'domain' => '','ssl_status' => '', 'http2_status' => ''
            ]
        ], $data);
    }

    /**
     * Verifies GET /sites response data with server_id parameter
     */
    function test_it_lists_sites_filtered_by_server()
    {
        $request = new WP_REST_Request('GET', $this->resource_route);
        $request->set_param('server_id', 2);
        WPCD_POSTS_APP()->add_app( WPCD_WORDPRESS_APP()->get_app_name(), 1, 1, 'site1.com' );
        $app_post_id_2 = WPCD_POSTS_APP()->add_app( WPCD_WORDPRESS_APP()->get_app_name(), 2, 1, 'site2.com' );
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            [
                'id' => $app_post_id_2, 'name' => 'site2.com', 'server_id' => 2, 'author' => 1,
                'domain' => '', 'ssl_status' => '', 'http2_status' => ''
            ]
        ], $data);
    }

    /**
     * Verifies POST /sites correctly creates a new site entry and pending log entry
     */
    function test_it_creates_a_site()
    {
        $list_request = new WP_REST_Request('GET', $this->resource_route);
        $sites = $this->rest->dispatch($list_request)->get_data();
        $tasks = get_posts(['post_type' => 'wpcd_pending_log', 'post_status' => 'private']);
        $this->assertEmpty($sites);
        $this->assertEmpty($tasks);
        $request = new WP_REST_Request('POST', $this->resource_route);
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('server_id', $this->server_post_id);
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('wp_domain', 'site1.com');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('wp_user', 'admin');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('wp_email', 'test@example.org');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('wp_password', 'password');
        $data = $this->rest->dispatch($request)->get_data();
        $tasks = get_posts(['post_type' => 'wpcd_pending_log', 'post_status' => 'private', 'post_author' => 1]);
        $this->assertCount(1, $tasks);
        $this->assertEquals(['task_id' => $tasks[0]->ID], $data);
    }

    /**
     * Verifies GET /sites/{id} response data
     */
    function test_it_gets_a_site()
    {
        $request = new WP_REST_Request('GET', $this->resource_route . '/9999999');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $site = $this->create_site();
        $request = new WP_REST_Request('GET', $this->resource_route . '/' . $site->ID);
        $site = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            'id' => $site['id'], 'author' => 1, 'server_id' => $this->server_post_id,
            'ssl_status' => '', 'http2_status' => '', 'name' => 'site1.com', 'domain' => 'site1.com'
        ], $site);
    }

    /**
     * Verifies PUT /sites/{id} correctly update an existing site
     */
    function test_it_updates_a_site()
    {
        $request = new WP_REST_Request('PUT', $this->resource_route . '/9999999');
        $error = $this->rest->dispatch($request)->as_error();
        $this->assertWPError($error);
        $this->assertEquals(405, $error->get_error_data()['status']);

    }

    /**
     * Verifies DELETE /sites/{id} correctly deletes the specified site
     */
    function test_it_deletes_a_site()
    {
        $this->mock_ssh('Site has been deleted');
        $this->create_test_provider();
        $request = new WP_REST_Request('DELETE', $this->resource_route . '/9999999');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $site = $this->create_site();
        $this->assertEquals('private', get_post_status($site));
        $request = new WP_REST_Request('DELETE', $this->resource_route . '/' . $site->ID);
        $site = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            'site_id' => $site['site_id'],
            'deleted' => true,
        ], $site);
        $this->assertFalse(get_post_status($site['site_id']));
    }

    /**
     * Verifies POST /sites/{id}/execute-action correctly triggers a site action hook
     */
    function test_it_executes_a_site_action()
    {
        $this->mock_ssh('SSL has been enabled');
        $this->create_test_provider();
        $request = new WP_REST_Request('POST', $this->resource_route . '/9999999/execute-action');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $site = $this->create_site();
        $route = $this->resource_route . '/' . $site->ID . '/execute-action';
        $request = new WP_REST_Request('POST', $route);
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request->set_param('action', 'ssl-status');
        $action = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            'site_id' => $site->ID,
            'action' => 'ssl-status',
            'result' => ['refresh' => 'yes']
        ], $action);
    }

    /**
     * Convenience method to create a new site via the REST API and return its response
     *
     * @return WP_Post
     */
    protected function create_site()
    {
        $site_id = WPCD_WORDPRESS_APP()->add_wp_app_post(
            $this->server_post_id,
            ['server_id' => $this->server_post_id, 'wp_domain' => 'site1.com', 'wp_password' => 'password'],
            []
        );
        return get_post($site_id);
    }

    /**
     * Loads in test provider to use instead of a real provider
     */
    protected function create_test_provider($key = 'test')
    {
        require_once dirname(dirname(__DIR__)) . '/data/class-wpcd-cloud-provider-test.php';
        WPCD()->classes['wpcd_vpn_api_provider_' . $key] = new WPCD_Cloud_Provider_Test();
        update_post_meta($this->server_post_id, 'wpcd_server_provider', $key);
        update_post_meta($this->server_post_id, 'wpcd_server_provider_instance_id', '12345');
        return $this->server_post_id;
    }

    /**
     * Creates SSH mock object to avoid making a real SSH connection
     *
     * @param string $return_message
     */
    protected function mock_ssh(string $return_message)
    {
        $mock = $this->createMock(WORDPRESS_SSH::class);
        $mock->method('get_ssh_key_details')->willReturn([
            'root_user' => 'root',
            'key' => '12345',
            'passwd' => 'password',
            'ip' => '127.0.0.1'
        ]);
        $mock->method('exec')->willReturn($return_message);
        WPCD()->classes['wpcd_wordpress_ssh'] = $mock;
    }
}