<?php
/**
 * Plugin Name: MB Custom Table
 * Plugin URI:  https://metabox.io/plugins/mb-custom-table/
 * Description: Save custom fields data to custom table instead of the default meta tables.
 * Version:     2.1.1
 * Author:      MetaBox.io
 * Author URI:  https://metabox.io
 * License:     GPL2+
 * Text Domain: mb-custom-table
 * Domain Path: /languages/
 *
 * @package    Meta Box
 * @subpackage MB Custom Table
 */

// Prevent loading this file directly.
defined( 'ABSPATH' ) || die;

if ( ! function_exists( 'mb_custom_table_load' ) ) {
	if ( file_exists( __DIR__ . '/vendor' ) ) {
		require __DIR__ . '/vendor/autoload.php';
	}

	/**
	 * Hook to 'init' with priority 5 to make sure all actions are registered before Meta Box 4.9.0 runs
	 */
	add_action( 'init', 'mb_custom_table_load', 5 );

	/**
	 * Load plugin files after Meta Box is loaded
	 */
	function mb_custom_table_load() {
		if ( ! defined( 'RWMB_VER' ) ) {
			return;
		}

		define( 'MBCT_DIR', __DIR__ );
		list( , $url ) = RWMB_Loader::get_path( __DIR__ );
		define( 'MBCT_URL', $url );

		new MetaBox\CustomTable\Loader;
		new MetaBox\CustomTable\Model\Ajax;
	}
}