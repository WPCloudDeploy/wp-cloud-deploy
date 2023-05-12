<?php
/**
 * Plugin Name: Meta Box Columns
 * Plugin URI:  https://metabox.io/plugins/meta-box-columns/
 * Description: Display fields more beautiful by putting them into 12-columns grid.
 * Version:     1.2.15
 * Author:      MetaBox.io
 * Author URI:  https://metabox.io
 * License:     GPL2+
 */

// Prevent loading this file directly.
defined( 'ABSPATH' ) || die;

if ( ! function_exists( 'mb_columns_add_markup' ) ) {
	if ( file_exists( __DIR__ . '/vendor' ) ) {
		require __DIR__ . '/vendor/autoload.php';
	}

	add_filter( 'rwmb_meta_box_settings', 'mb_columns_add_markup' );

	/**
	 * Modify meta box settings to add column markup.
	 *
	 * @param array $meta_box Meta Box settings.
	 *
	 * @return array
	 */
	function mb_columns_add_markup( $meta_box ) {
		$processor = new MetaBox\Columns\Processor( $meta_box );
		$processor->process();
		return $processor->get_meta_box();
	}

	add_action( 'rwmb_enqueue_scripts', 'mb_columns_enqueue' );

	function mb_columns_enqueue() {
		list( , $url ) = RWMB_Loader::get_path( __DIR__ );
		wp_enqueue_style( 'rwmb-columns', $url . 'columns.css', '', '1.2.15' );
	}
}
