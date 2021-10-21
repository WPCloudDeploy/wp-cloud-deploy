<?php
/**
 * WordPress App WPCD_REST_API_Controller_Test.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_REST_API_Controller_Test
 *
 * Controller subclass for testing base class behaviors
 */
class WPCD_REST_API_Controller_Test extends WPCD_REST_API_Controller_Base {

    protected $name = 'test';

    public function register_routes()
    {
        $this->register_get_route($this->name, fn() => ['success' => true]);
        $this->register_post_route($this->name, fn() => ['success' => true]);
        $this->register_put_route($this->name, fn() => ['success' => true]);
        $this->register_delete_route($this->name, fn() => ['success' => true]);
        $this->register_get_route($this->name . '/error', 'get_error');
    }

    /**
     * A test endpoint that purposefully throws an exception
     *
     * @throws Exception
     */
    public function get_error(WP_REST_Request $request)
    {
        throw new Exception('Test Error', 400);
    }

}
