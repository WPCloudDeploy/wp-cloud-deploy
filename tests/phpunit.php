<?php

use WPTest\Test\PHPUnitBootstrap;

$bootstrap = new PHPUnitBootstrap();
$bootstrap->beforePluginsLoaded(function() {
    /* Add project-specific bootstrapping code to run on the 'muplugins_loaded' hook */
});
$bootstrap->afterPluginsLoaded(function() {
    /* Add project-specific bootstrapping code to run on the 'plugins_loaded' hook */
});
$bootstrap->load();

