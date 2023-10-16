<?php
/**
 * Plugin Name: MB Admin Columns
 * Plugin URI:  https://metabox.io/plugins/mb-admin-columns/
 * Description: Show custom fields in the post list table.
 * Version:     1.7.1
 * Author:      MetaBox.io
 * Author URI:  https://metabox.io
 * License:     GPL2+
 * Text Domain: mb-admin-columns
 * Domain Path: /languages/
 *
 * @package    Meta Box
 * @subpackage MB Admin Columns
 */

// Prevent loading this file directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mb_admin_columns_load' ) ) {

	if ( file_exists( __DIR__ . '/vendor' ) ) {
		require __DIR__ . '/vendor/autoload.php';
	}

	add_action( 'admin_init', 'mb_admin_columns_load' );

	function mb_admin_columns_load() {
		if ( ! defined( 'RWMB_VER' ) ) {
			return;
		}

		$loader = new MBAC\Loader();
		$loader->posts();
		$loader->taxonomies();
		$loader->users();
		$loader->models();
	}
}
