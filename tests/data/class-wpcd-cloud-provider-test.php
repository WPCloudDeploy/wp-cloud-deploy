<?php
/**
 * WordPress App WPCD_Cloud_Provider_Test.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPCD_Cloud_Provider_Test
 *
 * Test double to avoid calling a real provider
 */
class WPCD_Cloud_Provider_Test extends CLOUD_PROVIDER_API {

    public function call()
    {
        return [];
    }
}