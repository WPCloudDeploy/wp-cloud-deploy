<?php

use WPTest\Test\TestCase;

class ControllerTasksTest extends TestCase
{
    protected WP_REST_Server $rest;
    protected WPCD_REST_API_Controller_Base $controller;
    protected string $resource_route;
    protected string $resource_id_route;
    protected string $resource_name_route;

    /**
     * Sets tasks controller and other convenience variables
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->controller = WPCD_WORDPRESS_APP()->get_rest_controller('tasks');
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
        $this->assertInstanceOf(WPCD_REST_API_Controller_Tasks::class, $this->controller);
        $this->assertArrayHasKey($this->resource_route, $routes);
        $this->assertCount(1, $routes[$this->resource_route]);
        $this->assertArrayHasKey($this->resource_id_route, $routes);
        $this->assertCount(1, $routes[$this->resource_id_route]);
    }

    /**
     * Verifies GET /tasks response data.
     */
    function test_it_lists_tasks()
    {
        $request = new WP_REST_Request('GET', $this->resource_route);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([], $data);
        $site_id = WPCD_POSTS_APP()->add_app( WPCD_WORDPRESS_APP()->get_app_name(), 1, 1, 'site1.com' );
        $task_id_1 = WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry($site_id, 'temp_task', 'temp_command1', [], 'not-ready' );
        $task_id_2 = WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry($site_id, 'temp_task', 'temp_command2', [], 'not-ready' );
        update_post_meta($task_id_1, 'pending_task_start_date', 0);
        update_post_meta($task_id_1, 'pending_task_complete_date', 0);
        update_post_meta($task_id_2, 'pending_task_start_date', 0);
        update_post_meta($task_id_2, 'pending_task_complete_date', 0);
        $data = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            [
                'id' => $task_id_2, 'name' => 'Pending Task For: site1.com', 'user_id' => 1,
                'type' => 'temp_task', 'key' => 'temp_command2', 'state' => 'not-ready',
                'related_server_id' => '', 'reference' => '', 'comment' => '', 'parent_id' => $site_id,
                'parent_type' => 'wpcd_app', 'display_start_date' => '1970-01-01 @ 00:00',
                'display_complete_date' => '1970-01-01 @ 00:00', 'unix_start_date' => '0', 'unix_complete_date' => '0'

            ],
            [
                'id' => $task_id_1, 'name' => 'Pending Task For: site1.com', 'user_id' => 1,
                'type' => 'temp_task', 'key' => 'temp_command1', 'state' => 'not-ready',
                'related_server_id' => '', 'reference' => '', 'comment' => '', 'parent_id' => $site_id,
                'parent_type' => 'wpcd_app', 'display_start_date' => '1970-01-01 @ 00:00',
                'display_complete_date' => '1970-01-01 @ 00:00', 'unix_start_date' => '0', 'unix_complete_date' => '0'
            ]
        ], $data);
    }

    /**
     * Verifies GET /tasks/{id} response data
     */
    function test_it_gets_a_task()
    {
        $site_id = WPCD_POSTS_APP()->add_app( WPCD_WORDPRESS_APP()->get_app_name(), 1, 1, 'site1.com' );
        $task_id = WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry($site_id, 'temp_task', 'temp_command1', [], 'not-ready' );
        update_post_meta($task_id, 'pending_task_start_date', 0);
        update_post_meta($task_id, 'pending_task_complete_date', 0);
        $request = new WP_REST_Request('GET', $this->resource_route . '/9999999');
        $this->assertWPError($this->rest->dispatch($request)->as_error());
        $request = new WP_REST_Request('GET', $this->resource_route . '/' . $task_id);
        $task = $this->rest->dispatch($request)->get_data();
        $this->assertEquals([
            'id' => $task_id, 'name' => 'Pending Task For: site1.com', 'user_id' => 1,
            'type' => 'temp_task', 'key' => 'temp_command1', 'state' => 'not-ready',
            'related_server_id' => '', 'reference' => '', 'comment' => '', 'parent_id' => $site_id,
            'parent_type' => 'wpcd_app', 'display_start_date' => '1970-01-01 @ 00:00',
            'display_complete_date' => '1970-01-01 @ 00:00', 'unix_start_date' => '0', 'unix_complete_date' => '0'
        ], $task);
    }
}