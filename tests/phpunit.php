<?php

use WPTest\Test\PHPUnitBootstrap;

$bootstrap = new PHPUnitBootstrap();
$bootstrap->beforePluginsLoaded(function() {
    /* Add project-specific bootstrapping code to run on the 'muplugins_loaded' hook */
});
$bootstrap->afterPluginsLoaded(function() {
    /* Add project-specific bootstrapping code to run on the 'plugins_loaded' hook */
    $wpcd_settings = get_option( 'wpcd_settings' );
    $wpcd_settings['wordpress_app_rest_api_enable'] = true;
    update_option( 'wpcd_settings', $wpcd_settings );
});
$bootstrap->load();

