<?php
/**
 * This file contains functions that spin up classes that manages post types for the WordPress app.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a class var for WPCD_POSTS_Site_Package and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_site_package', -10, 1 );
function wpcd_init_site_package() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['WPCD_SITE_PACKAGE'] ) ) {
			WPCD()->classes['WPCD_SITE_PACKAGE'] = new WPCD_POSTS_Site_Package();
		}
	}
}

/**
 * Return instance of class WPCD_SITE_PACKAGE.
 */
function WPCD_SITE_PACKAGE() {
	if ( empty( WPCD()->classes['WPCD_SITE_PACKAGE'] ) ) {
		wpcd_init_site_package();
	}
	return WPCD()->classes['WPCD_SITE_PACKAGE'];
}

/**
 * Create a class var for WPCD_POSTS_App_Update_Plan and
 * add it to the WPCD array of classes for management
 *
 * Note that this is contingent on the WPCD_WooCommerce add-on being available.
 */
add_action( 'init', 'wpcd_init_app_update_plan', -10, 1 );
function wpcd_init_app_update_plan() {
	if ( function_exists( 'WPCD' ) && class_exists( 'WPCD_WooCommerce_Init' ) ) {
		if ( empty( WPCD()->classes['WPCD_APP_UPDATE_PLAN'] ) ) {
			WPCD()->classes['WPCD_APP_UPDATE_PLAN'] = new WPCD_POSTS_App_Update_Plan();
		}
	}

	if ( function_exists( 'WPCD' ) && class_exists( 'WPCD_WooCommerce_Init' ) ) {
		if ( empty( WPCD()->classes['WPCD_SITE_UPDATE_PLAN_LOG'] ) ) {
			WPCD()->classes['WPCD_SITE_UPDATE_PLAN_LOG'] = new WPCD_SITE_UPDATE_PLAN_LOG();
		}
	}
}

/**
 * Return instance of class WPCD_APP_UPDATE_PLAN.
 */
function WPCD_APP_UPDATE_PLAN() {
	if ( empty( WPCD()->classes['WPCD_APP_UPDATE_PLAN'] ) ) {
		wpcd_init_app_update_plan();
	}
	return WPCD()->classes['WPCD_APP_UPDATE_PLAN'];
}

/**
 * Return instance of class WPCD_SITE_UPDATE_PLAN_LOG.
 */
function WPCD_SITE_UPDATE_PLAN_LOG() {
	if ( empty( WPCD()->classes['WPCD_SITE_UPDATE_PLAN_LOG'] ) ) {
		wpcd_init_app_update_plan();
	}
	return WPCD()->classes['WPCD_SITE_UPDATE_PLAN_LOG'];
}


/**
 * Create a class var for WPCD_WORDPRESS_APP_LOGTIVITY and
 * add it to the WPCD array of classes for management
 */
add_action( 'init', 'wpcd_init_wordpress_app_logtivity', -10, 1 );
function wpcd_init_wordpress_app_logtivity() {
	if ( function_exists( 'WPCD' ) ) {
		if ( empty( WPCD()->classes['WPCD_WORDPRESS_APP_LOGTIVITY'] ) ) {
			WPCD()->classes['WPCD_WORDPRESS_APP_LOGTIVITY'] = new WPCD_WORDPRESS_APP_LOGTIVITY();
		}
	}
}

/**
 * Return instance of class WPCD_WORDPRESS_APP_LOGTIVITY.
 */
function WPCD_WORDPRESS_APP_LOGTIVITY() {
	if ( empty( WPCD()->classes['WPCD_WORDPRESS_APP_LOGTIVITY'] ) ) {
		wpcd_init_wordpress_app_logtivity();
	}
	return WPCD()->classes['WPCD_WORDPRESS_APP_LOGTIVITY'];
}
