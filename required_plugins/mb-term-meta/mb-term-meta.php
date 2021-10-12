<?php
/**
 * Plugin Name: MB Term Meta
 * Plugin URI:  https://metabox.io/plugins/mb-term-meta/
 * Description: Add custom fields (meta data) for terms.
 * Version:     1.2.10
 * Author:      MetaBox.io
 * Author URI:  https://metabox.io
 * License:     GPL2+
 * Text Domain: mb-term-meta
 * Domain Path: /languages/
 *
 * @package    Meta Box
 * @subpackage MB Term Meta
 */

// Prevent loading this file directly.
defined( 'ABSPATH' ) || die;

if ( ! function_exists( 'mb_term_meta_load' ) ) {
	if ( file_exists( __DIR__ . '/vendor' ) ) {
		require __DIR__ . '/vendor/autoload.php';
	}

	/**
	 * Hook to 'init' with priority 5 to make sure all actions are registered before Meta Box 4.9.0 runs
	 */
	add_action( 'init', 'mb_term_meta_load', 5 );

	/**
	 * Load plugin files after Meta Box is loaded
	 */
	function mb_term_meta_load() {
		if ( ! defined( 'RWMB_VER' ) ) {
			return;
		}

		new MBTM\Loader;
	}
}
