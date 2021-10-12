<?php
/**
 * Plugin Name: Meta Box Columns
 * Plugin URI:  https://metabox.io/plugins/meta-box-columns/
 * Description: Display fields more beautiful by putting them into 12-columns grid.
 * Version:     1.2.7
 * Author:      MetaBox.io
 * Author URI:  https://metabox.io
 * License:     GPL2+
 *
 * @package    Meta Box
 * @subpackage Meta Box Columns
 */

// Prevent loading this file directly.
defined( 'ABSPATH' ) || die;

if ( ! function_exists( 'mb_columns_add_markup' ) ) {
	require __DIR__ . '/row.php';
	require __DIR__ . '/processor.php';

	add_filter( 'rwmb_meta_box_settings', 'mb_columns_add_markup' );

	/**
	 * Modify meta box settings to add column markup.
	 *
	 * @param array $meta_box Meta Box settings.
	 *
	 * @return array
	 */
	function mb_columns_add_markup( $meta_box ) {
		$processor = new MB_Columns_Processor( $meta_box );
		$processor->process();
		return $processor->get_meta_box();
	}

	add_action( 'rwmb_enqueue_scripts', 'mb_columns_enqueue' );

	/**
	 * Enqueue styles for columns
	 */
	function mb_columns_enqueue() {
		list( , $url ) = RWMB_Loader::get_path( __DIR__ );
		wp_enqueue_style( 'rwmb-columns', $url . 'columns.css', '', '1.2.6' );
	}
}
