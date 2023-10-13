<?php
/**
 * This file contains functions that spin up classes that manages post types for the wordpress app.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'wpcd_init_site_package', -10, 1 );
/**
 * Create a class var for WPCD_POSTS_Site_Package and
 * add it to the WPCD array of classes for management
 */
function wpcd_init_site_package() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['WPCD_SITE_PACKAGE'] ) ) {
			WPCD()->classes['WPCD_SITE_PACKAGE'] = new WPCD_POSTS_Site_Package();
		}
	}
}

function WPCD_SITE_PACKAGE() {
	return WPCD()->classes['WPCD_SITE_PACKAGE'];
}
